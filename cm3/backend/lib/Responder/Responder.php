<?php

namespace CM3_Lib\Responder;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\RouteParserInterface;
use function http_build_query;

/**
 * A generic responder.
 */
final class Responder
{
    private RouteParserInterface $routeParser;

    private ResponseFactoryInterface $responseFactory;

    /**
     * The constructor.
     *
     * @param RouteParserInterface $routeParser The route parser
     * @param ResponseFactoryInterface $responseFactory The response factory
     */
    public function __construct(
        RouteParserInterface $routeParser,
        ResponseFactoryInterface $responseFactory
    ) {
        $this->responseFactory = $responseFactory;
        $this->routeParser = $routeParser;
    }

    /**
     * Create a new response.
     *
     * @return ResponseInterface The response
     */
    public function createResponse(): ResponseInterface
    {
        return $this->responseFactory->createResponse()->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Creates a redirect for the given url / route name.
     *
     * This method prepares the response object to return an HTTP Redirect
     * response to the client.
     *
     * @param ResponseInterface $response The response
     * @param string $destination The redirect destination (url or route name)
     * @param array $queryParams Optional query string parameters
     *
     * @return ResponseInterface The response
     */
    public function withRedirect(
        ResponseInterface $response,
        string $destination,
        array $queryParams = []
    ): ResponseInterface {
        if ($queryParams) {
            $destination = sprintf('%s?%s', $destination, http_build_query($queryParams));
        }

        return $response->withStatus(302)->withHeader('Location', $destination);
    }

    /**
     * Determines the appropriate format based on the Accept header and returns the response.
     *
     * @param ServerRequestInterface $request The incoming request
     * @param ResponseInterface $response The response object
     * @param mixed $data The data to be returned
     * @param array $headers Optional custom headers for the data
     *
     * @return ResponseInterface The formatted response
     */
    public function withNegotiatedResponse(
        ServerRequestInterface $request,
        ResponseInterface $response,
        $data,
        array $headers = []
    ): ResponseInterface {
        $acceptHeader = $request->getHeaderLine('Accept');
        $headers['Vary'] = 'Accept';

        // Check for CSV preference
        if (str_contains($acceptHeader, 'text/csv')) {
            if (!isset($headers['Content-Disposition'])) {
                $headers['Content-Disposition'] = 'attachment; filename="export.csv"';
            }
            return $this->withCsv($response, (array) $data, $headers);
        }

        // Default to JSON (matching your current standard)
        return $this->withJson($response, $data, 0, $headers);
    }
    /**
     * Creates a redirect for the given url / route name.
     *
     * This method prepares the response object to return an HTTP Redirect
     * response to the client.
     *
     * @param ResponseInterface $response The response
     * @param string $routeName The redirect route name
     * @param array $data Named argument replacement data
     * @param array $queryParams Optional query string parameters
     *
     * @return ResponseInterface The response
     */
    public function withRedirectFor(
        ResponseInterface $response,
        string $routeName,
        array $data = [],
        array $queryParams = []
    ): ResponseInterface {
        return $this->withRedirect($response, $this->routeParser->urlFor($routeName, $data, $queryParams));
    }

    /**
     * Write JSON to the response body.
     *
     * This method prepares the response object to return an HTTP JSON
     * response to the client.
     *
     * @param ResponseInterface $response The response
     * @param mixed $data The data
     * @param int $options Json encoding options
     * @param array $headers Optional custom headers for the JSON (e.g., Content-Disposition)
     *
     * @return ResponseInterface The response
     */
    public function withJson(
        ResponseInterface $response,
        $data = null,
        int $options = 0,
        array $headers = []
    ): ResponseInterface {
        $response = $response->withHeader('Content-Type', 'application/json');
        //Add the extra headers, if any
        $response = array_reduce(
            array_keys($headers), 
            function ($carryResponse, $key) use ($headers) {
                return $carryResponse->withHeader($key, $headers[$key]);
            }, 
            $response
        );
        $response->getBody()->write((string)json_encode($data, $options));
        if (json_last_error() != JSON_ERROR_NONE) {
            switch (json_last_error()) {
                case JSON_ERROR_DEPTH:
                    $msg = 'Maximum stack depth exceeded';
                    break;
                case JSON_ERROR_STATE_MISMATCH:
                    $msg = 'Underflow or the modes mismatch';
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    $msg = 'Unexpected control character found';
                    break;
                case JSON_ERROR_UTF8:
                    $msg = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                    break;
                default:
                    $msg = 'Unknown error';
            }

            throw new \RuntimeException('JSON encoding failed: '.$msg);
        }
        return $response;
    }
    /**
     * Write CSV data to the response body.
     *
     * @param ResponseInterface $response The response
     * @param array $data The data to be converted to CSV
     * @param array $headers Optional custom headers for the CSV (e.g., Content-Disposition)
     *
     * @return ResponseInterface The response
     */
    public function withCsv(
        ResponseInterface $response,
        array $data,
        array $headers = []
    ): ResponseInterface {
        if (empty($data)) {
            return $response->withStatus(204); // No Content
        }

        $handle = fopen('php://temp', 'r+');
        
        // Write the header row using the keys from the first associative array
        fputcsv($handle, array_keys(reset($data)));

        // Write the data rows
        foreach ($data as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        $response->getBody()->write($csvContent);
        
        $response = $response->withHeader('Content-Type', 'text/csv');

        // Apply additional headers like Content-Disposition for downloads
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
