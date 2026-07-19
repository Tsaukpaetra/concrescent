<?php

namespace CM3_Lib\Modules\Mailers;

use PHPMailer\PHPMailer\PHPMailer;

class MailGun extends PHPMailer
{
    private array $config = [];

    public function configureCustomTransport(array $config): void
    {
        $this->config = $config;
    }

    public function send(): bool
    {
        if (!$this->preSend()) {
            return false;
        }

        $rawMime = $this->getSentMIMEMessage(); 
        return $this->sendViaMailGun($rawMime);
    }

    protected function sendViaMailGun(string $rawMime): bool
    {
        if (empty($this->config['api_key'])) {
            $this->ErrorInfo = 'MailGun API key is not configured.';
            return false;
        }

        $endpoint = $this->config['api_url'] ?? 'https://api.mailgun.net';
        $uri = $endpoint . '/v3/' . $this->config['domain'] .'/messages.mime';

        //Make the to string
        $formattedAddresses = array_map(function($recipient) {
            $email = $recipient[0];
            $name = $recipient[1];
            
            return empty($name) ? $email : '"' . $name . '" <' . $email . '>';
        }, $this->to);

        $payload = [
            'to' => implode(', ', $formattedAddresses),
            'message' => $this->createFieldFile($rawMime, 'message.mime'),
        ];


        foreach ([
            'tag',
            'dkim',
            'secondary_dkim',
            'secondary_dkim_public',
            'deliverytime',
            'deliverytime_optimize_period',
            'time_zone_localize',
            'testmode',
            'tracking',
            'tracking_clicks',
            'tracking_opens',
            'require_tls',
            'skip_verification',
            'sending_ip',
            'sending_ip_pool',
            'tracking_pixel_location_top',
            'archive_to',
            'suppress_headers'
        ] as $option) {
            if (array_key_exists($option, $this->config)) {
                $payload['o:' . str_replace('_', '-', $option)] = $this->config[$option];
            }
        }


        if (function_exists('curl_version')) {
            //return $this->sendWithCurl($uri, $payload);
        }

        return $this->sendWithStream($uri, $payload);
    }

    protected function sendWithCurl(string $endpoint, array $postFields): bool
    {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_USERPWD => "api:{$this->config['api_key']}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: multipart/form-data'],
            CURLOPT_POSTFIELDS => (function($data) {
                    foreach ($data as $k => $v) {
                        if (is_object($v) && method_exists($v, 'getData')) {
                            $curlStringFileClass = '\\CURLStringFile';
                            $data[$k] = new $curlStringFileClass($v->getData(), $v->getFilename(), $v->getMimeType());
                        }
                    }
                    return $data; // Preserves both keys and values perfectly
                })($postFields),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            $this->ErrorInfo = 'cURL error: ' . $curlError;
            return false;
        }

        return $this->handleApiResponse($response, $httpCode);
    }

    protected function sendWithStream(string $endpoint, array $postFields): bool
    {

        $boundary = '--------------------------' . microtime(true);
        $payload = $this->buildMultipartPayload($postFields, $boundary);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Authorization: Basic " . base64_encode("api:{$this->config['api_key']}") . "\r\n" .
                            "Content-Type: multipart/form-data; boundary=" . $boundary . "\r\n" .
                            "Content-Length: " . strlen($payload),
                'content' => $payload,
                'timeout' => 30,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($endpoint, false, $context);
        if ($response === false) {
            $this->ErrorInfo = 'Failed to send MailGun request using stream context.';
            return false;
        }

        $httpCode = 0;
        if (!empty($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (stripos($header, 'HTTP/') === 0) {
                    $parts = explode(' ', $header);
                    if (isset($parts[1])) {
                        $httpCode = (int) $parts[1];
                    }
                    break;
                }
            }
        }

        return $this->handleApiResponse($response, $httpCode);
    }
    function buildMultipartPayload(array $fields, string $boundary): string {
        $payload = '';

        foreach ($fields as $name => $value) {
            $payload .= "--" . $boundary . "\r\n";

            // Check if the field is a CURLFile instance
            if (is_object($value) && method_exists($value, 'getData')) {
                $payload .= "Content-Disposition: form-data; name=\"" . $name . "\"; filename=\"" . $value->getFilename() . "\"\r\n";
                $payload .= "Content-Type: " . $value->getMimeType() . "\r\n\r\n";
                $payload .= $value->getData() . "\r\n";
            } else {
                // Treat the field as a regular text parameter
                $payload .= "Content-Disposition: form-data; name=\"" . $name . "\"\r\n\r\n";
                $payload .= $value . "\r\n";
            }
        }

        // Append final terminating boundary delimiter
        $payload .= "--" . $boundary . "--\r\n";
        return $payload;
    }

    protected function handleApiResponse(string $response, int $httpCode): bool
    {
        $decoded = json_decode($response, true);
        if ($decoded === null) {
            $this->ErrorInfo = 'Invalid MailGun response: ' . $response;
            return false;
        }

        if ($httpCode !== 200) {
            $message = $decoded['message'] ?? ($decoded[0]['message'] ?? $response);
            $this->ErrorInfo = 'MailGun API error: ' . $message;
            return false;
        }


        return true;
    }
    public static function createFieldFile(string $data, string $filename, string $mime = 'application/octet-stream') 
    {
        return new class($data, $filename, $mime) {
            public function __construct(private $d, private $f, private $m) {}
            public function getData() { return $this->d; }
            public function getFilename() { return $this->f; }
            public function getMimeType() { return $this->m; }
        };
    }
}
