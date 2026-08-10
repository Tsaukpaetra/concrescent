<?php

namespace CM3_Lib\Middleware;

use CM3_Lib\util\CurrentUserInfo;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class AccessLogMiddleware implements MiddlewareInterface
{

    public function __construct(private LoggerInterface $logger,
        private CurrentUserInfo $CurrentUserInfo, private string $basePath)
    {
    }
    private const ACTION_MAP = [
        'GET'    => 'Load',
        'POST'   => 'Submit',
        'PUT'    => 'Update',
        'PATCH'  => 'Modify',
        'DELETE' => 'Delete',
    ];

    private const STATUS_MESSAGES = [
        200 => 'OK',
        201 => 'Created',
        204 => 'No Content',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        422 => 'Validation Failed',
        500 => 'Server Error',
    ];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $startTime = microtime(true);
        
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        // Strip the base path if present
        if ($this->basePath !== '' && str_starts_with($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath));
        }

        $data = $this->extractData($request);
        
        $this->redactSensitiveData($data, [
            'password',
            'token',
            'auth',
            'qr_data_uri',
            'cart_url',
            'retrieve_url',
            'email_address',
            'phone_number',
            'address_1',
            'address_2',
            'retrieve_url',
        ]);

        try {
            // Let the request flow through the application stack
            $response = $handler->handle($request);

            $context['contact_id'] = $this->CurrentUserInfo->GetContactId();
            $context['event_id'] = $this->CurrentUserInfo->GetEventId();
            $context['status_code'] = $response->getStatusCode();
            $context['path'] = $path;
            $context['data'] = $data;
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $context['duration'] = $duration;

            $responseMessage = null;
            $contentType = $response->getHeaderLine('Content-Type');
            
            if (str_contains($contentType, 'application/json')) {
                $bodyStream = $response->getBody();
                if ($bodyStream->getSize() > 0) {
                    $bodyStream->rewind();
                    $rawResponse = $bodyStream->getContents();
                    $bodyStream->rewind(); // Crucial: Reset stream pointer so the client still gets the data

                    $decodedResponse = json_decode($rawResponse, true);
                    if (is_array($decodedResponse) && isset($decodedResponse['error']['message'])) {
                        $responseMessage = $decodedResponse['error']['message'];
                    }
                }
            }

            // Log request result (Success/Normal flow)
            // Build human-friendly message using class constants
            $action = self::ACTION_MAP[$method] ?? 'Request';
            $statusDescription = self::STATUS_MESSAGES[$context['status_code']] ?? ($context['status_code'] >= 400 ? 'Failed' : 'OK');
            $logMessage = "{$action} {$statusDescription}";
            if(!empty($responseMessage)) {
                $logMessage .= ": {$responseMessage}";
            }
            $this->logger->info("{$logMessage}", $context);

            return $response->withHeader('X-Response-Time', "{$duration}ms");

        } catch (\Throwable $e) {
            //This should literally never ever happen unless the error middleware is severely broken
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Log request result (Failure/Exception caught during flow)
            // Build human-friendly message using class constants
            $action = self::ACTION_MAP[$method] ?? 'Request';
            $statusDescription = self::STATUS_MESSAGES[$context['status_code']] ?? ($context['status_code'] >= 400 ? 'Failed' : 'OK');
            $logMessage = "{$action} {$statusDescription}";
            
            $this->logger->error("!{$logMessage} - " . $e->getMessage(), [
                'method' => $method,
                'uri' => $path,
                'duration' => $duration,
                'exception' => $e,
            ]);

            // Re-throw so Slim's ErrorMiddleware can handle it downstream
            throw $e;
        }
    }
    /**
     * Recursively redacts specified keys from an array or object.
     *
     * @param array|mixed $data The data structure to sanitize.
     * @param array $keysToRedact List of case-insensitive keys to look for.
     * @param string $replacement The string to replace sensitive values with.
     */
    function redactSensitiveData(&$data, array $keysToRedact, string $replacement = '[REDACTED]'): void
    {
        if (!is_array($data)) {
            return;
        }

        // Convert target keys to lowercase for case-insensitive matching
        $keysToRedact = array_map('strtolower', $keysToRedact);

        foreach ($data as $key => &$value) {
            // Check if the current key matches any sensitive field name
            if (in_array(strtolower((string)$key), $keysToRedact, true)) {
                $value = $replacement;
            } elseif (is_array($value) || is_object($value)) {
                // Recurse deeper if it's a nested array or object
                $this->redactSensitiveData($value, $keysToRedact, $replacement);
            }
        }
    }

    function extractData(ServerRequestInterface $request)
    {
        
        $method = $request->getMethod();
        $queryParams = $request->getQueryParams();
        $postData = null;
        $isTruncated = false;

        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            // 2. Automatically handles JSON, Form-data, etc., and turns it into an array
            $parsedBody = $request->getParsedBody();
            
            if (!empty($parsedBody)) {
                // Cast objects (like deserialized JSON objects) or arrays cleanly to an array
                $bodyArray = (array) $parsedBody;
                
                $encoded = json_encode($bodyArray);
                $maxLength = 60000; // MySQL TEXT safety limit

                if (mb_strlen($encoded) > $maxLength) {
                    $postData = mb_substr($encoded, 0, $maxLength);
                    $isTruncated = true;
                } else {
                    $postData = $encoded; // Stored as a structured JSON string in your TEXT column
                }
            } else {
                // 2. Fallback for raw files, plain text, or binary payloads
                $contentType = $request->getHeaderLine('Content-Type');
                $bodyStream = $request->getBody();
                $bodySize = $bodyStream->getSize();

                if ($bodySize !== null && $bodySize > 0) {
                    // 1. If it's structured text/data, read and parse it once
                    if (str_contains($contentType, 'application/json') || str_contains($contentType, 'application/x-www-form-urlencoded')) {
                        $bodyStream->rewind();
                        $rawContent = $bodyStream->getContents();

                        $decoded = [];
                        if (str_contains($contentType, 'application/json')) {
                            $decoded = json_decode($rawContent, true) ?? [];
                        } else {
                            parse_str($rawContent, $decoded);
                        }

                        if (!empty($decoded)) {
                            $encoded = json_encode($decoded);
                            $maxLength = 60000;

                            if (mb_strlen($encoded) > $maxLength) {
                                $postData = mb_substr($encoded, 0, $maxLength);
                                $isTruncated = true;
                            } else {
                                $postData = $decoded;
                            }
                        }
                    } 
                    // 2. Otherwise, treat as a binary/raw file and only read a 200-byte snippet
                    else {
                        $bodyStream->rewind();
                        $snippet = $bodyStream->read(200);

                        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $snippet)) {
                            $postData = "[BINARY DATA OMITTED, content type submitted was {$contentType}]";
                        } else {
                            $postData = $snippet;
                        }
                        $isTruncated = ($bodySize > 200);
                    }
                }
            }
        }

        return 
        [
            'query_params' => $queryParams,
            'request_body' => $postData,
            'is_truncated' => $isTruncated
        ];
    }
}