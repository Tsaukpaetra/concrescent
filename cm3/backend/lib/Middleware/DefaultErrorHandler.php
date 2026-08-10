<?php

namespace CM3_Lib\Middleware;

use CM3_Lib\Factory\LoggerFactory;
use CM3_Lib\Responder\Responder;
use CM3_Lib\util\CurrentUserInfo;
use DomainException;
use Fig\Http\Message\StatusCodeInterface;
use InvalidArgumentException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;
use Slim\Interfaces\ErrorHandlerInterface;
use Throwable;

/**
 * Default Error Renderer.
 */
final class DefaultErrorHandler implements ErrorHandlerInterface
{
    private string $basePath;
    private Responder $responder;

    private ResponseFactoryInterface $responseFactory;

    private LoggerInterface $logger;

    private string $installpath;
    private CurrentUserInfo $CurrentUserInfo;
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param ResponseFactoryInterface $responseFactory The response factory
     * @param LoggerFactory $loggerFactory The logger factory
     */
    public function __construct(
        Responder $responder,
        ResponseFactoryInterface $responseFactory,
        LoggerFactory $loggerFactory,
        CurrentUserInfo $CurrentUserInfo,
        \Slim\App $app
    ) {
        $this->responder = $responder;
        $this->responseFactory = $responseFactory;
        $this->logger = $loggerFactory->createLogger('Main');
        $this->installpath = dirname(__DIR__, 2);
        $this->CurrentUserInfo = $CurrentUserInfo;
        $this->basePath = $app->getBasePath();
    }

    /**
     * Invoke.
     *
     * @param ServerRequestInterface $request The request
     * @param Throwable $exception The exception
     * @param bool $displayErrorDetails Show error details
     * @param bool $logErrors Log errors
     * @param bool $logErrorDetails Log error details
     *
     * @return ResponseInterface The response
     */
    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        // Log error
        if ($logErrors) {
            $path = $request->getUri()->getPath();
            // Strip the base path if present
            if ($this->basePath !== '' && str_starts_with($path, $this->basePath)) {
                $path = substr($path, strlen($this->basePath));
            }

            $data = [
                ... $this->extractData($request),
                ... $this->getErrorDetails($exception, $logErrorDetails)
            ];

            $context['contact_id'] = $this->CurrentUserInfo->GetContactId();
            $context['event_id'] = $this->CurrentUserInfo->GetEventId();
            $context['path'] = $path;
            $context['data'] = $data;

            try {
                //code...
                $this->logger->error($exception->getMessage(), $context);
            } catch (\Throwable $th) {
                //throw $th;
                

                $response = $this->responseFactory->createResponse();

                // Render response
                $response = $this->responder->withJson($response, [
                    'error' => $this->getErrorDetails($th, $displayErrorDetails),
                ]);

                return $response->withStatus($this->getHttpStatusCode($exception));
            }
        }

        $response = $this->responseFactory->createResponse();

        // Render response
        $response = $this->responder->withJson($response, [
            'error' => $this->getErrorDetails($exception, $displayErrorDetails),
        ]);

        return $response->withStatus($this->getHttpStatusCode($exception));
    }

    /**
     * Get http status code.
     *
     * @param Throwable $exception The exception
     *
     * @return int The http code
     */
    private function getHttpStatusCode(Throwable $exception): int
    {
        // Detect status code
        $statusCode = StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR;

        if ($exception instanceof HttpException) {
            $statusCode = (int)$exception->getCode();
        }

        if ($exception instanceof DomainException || $exception instanceof InvalidArgumentException) {
            // Bad request
            $statusCode = StatusCodeInterface::STATUS_BAD_REQUEST;
        }

        $file = basename($exception->getFile());
        if ($file === 'CallableResolver.php') {
            $statusCode = StatusCodeInterface::STATUS_NOT_FOUND;
        }

        return $statusCode;
    }

    /**
     * Get error message.
     *
     * @param Throwable $exception The error
     * @param bool $displayErrorDetails Display details
     *
     * @return array The error details
     */
    private function getErrorDetails(Throwable $exception, bool $displayErrorDetails): array
    {
        if ($displayErrorDetails === true) {
            return [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' =>  substr($exception->getFile(), strlen($this->installpath)),
                'line' => $exception->getLine(),
                'previous' => $exception->getPrevious(),
                'trace' => $this->cleanTrace($exception->getTrace()),
            ];
        }

        return [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
        ];
    }

    private function cleanTrace(array $inTrace)
    {
        $result = array();
        foreach ($inTrace as $key => $trace) {
            //Trim install path from the file name
            if (isset($trace['file']) && str_starts_with($trace['file'], $this->installpath)) {
                $trace['file'] = substr($trace['file'], strlen($this->installpath));
            }
            //Trim install path from class name
            if (isset($trace['class'])) {
                $trace['class'] = str_replace("\0".$this->installpath, '::', $trace['class']);
            }

            $result[$key] = $trace;
            //Stop adding more traces once we've hit the middleware
            if (isset($trace['file']) && str_starts_with($trace['file'], '/vendor/slim/slim/Slim/MiddlewareDispatcher.php')) {
                break;
            }
        }
        return $result;
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
