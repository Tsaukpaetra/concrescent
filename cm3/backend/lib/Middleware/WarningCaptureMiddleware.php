<?php

namespace CM3_Lib\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;

/**
 * WarningCaptureMiddleware — catches PHP-level warnings/notices/strict warnings
 * that would otherwise be written directly into the response body or the
 * php://output stream.
 *
 * It wraps the entire request/response pipeline in a try/finally via
 * register_shutdown_function so that:
 *
 * 1. PHP warnings and notices are intercepted by a custom error handler,
 *    logged to the error logger, and suppressed from the output stream.
 * 2. Fatal errors (E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR) that
 *    slip past the normal exception flow are caught in the shutdown handler,
 *    logged, and — if `displayErrorDetails` is enabled — sent to the output
 *    as a JSON error response.
 * 3. Explicit `die()` / `exit()` calls are also caught in the shutdown
 *    handler and treated as fatal errors for logging purposes.
 *
 * This middleware should be added to the app BEFORE ErrorMiddleware so it
 * wraps the full pipeline.
 */
final class WarningCaptureMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;
    private ResponseFactoryInterface $responseFactory;
    private bool $displayErrorDetails;

    /**
     * @param LoggerInterface         $logger            PSR-3 logger (preferably the error logger)
     * @param ResponseFactoryInterface $responseFactory   PSR-17 response factory
     * @param bool                     $displayErrorDetails Whether to display error details to the caller
     */
    public function __construct(
        LoggerInterface $logger,
        ResponseFactoryInterface $responseFactory,
        bool $displayErrorDetails
    ) {
        $this->logger = $logger;
        $this->responseFactory = $responseFactory;
        $this->displayErrorDetails = $displayErrorDetails;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Register error/warning handler once per request lifecycle.
        // Using a static flag to avoid duplicate registration if this
        // middleware is somehow instantiated multiple times in one run.
        static $registered = false;
        if (!$registered) {
            $registered = true;
            $logger = $this->logger;
            $display = $this->displayErrorDetails;

            set_error_handler(static function (int $severity, string $message, string $file, int $line) use ($logger, $display): bool {
                $context = [
                    'data' => [
                        'file' => $file,
                        'line'  => $line,
                    ],
                    //TODO: Fill out the event_id and contact_id, and fix the Channel
                ];

                // Map PHP error constants to monolog levels
                $levels = [
                    E_ERROR             => 'emergency',
                    E_WARNING           => 'warning',
                    E_PARSE             => 'critical',
                    E_NOTICE            => 'notice',
                    E_STRICT            => 'info',
                    E_DEPRECATED        => 'warning',
                    E_USER_ERROR        => 'emergency',
                    E_USER_WARNING      => 'warning',
                    E_USER_NOTICE       => 'notice',
                    E_USER_DEPRECATED   => 'warning',
                ];
                $level = $levels[$severity] ?? 'warning';

                $context['code'] = $severity;
                $context['type'] = match ($severity) {
                    E_ERROR             => 'E_ERROR',
                    E_WARNING           => 'E_WARNING',
                    E_PARSE             => 'E_PARSE',
                    E_NOTICE            => 'E_NOTICE',
                    E_STRICT            => 'E_STRICT',
                    E_DEPRECATED        => 'E_DEPRECATED',
                    E_USER_ERROR        => 'E_USER_ERROR',
                    E_USER_WARNING      => 'E_USER_WARNING',
                    E_USER_NOTICE       => 'E_USER_NOTICE',
                    E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
                    default             => 'UNKNOWN',
                };

                // Log unconditionally so every warning/error is captured.
                $logger->$level("PHP {$context['type']}: {$message}", $context);

                // Always suppress PHP's default handler output.  Even in dev
                // mode the default handler emits raw HTML (<br /><b>Warning</b>)
                // which corrupts JSON responses.  Warnings are always logged;
                // if a warning later triggers an exception, displayErrorDetails
                // will apply to that exception's JSON response.
                return true;
            });

            // Shutdown handler — catches fatal errors and die()/exit() that
            // bypass Slim's exception-based error handling.
            $responseFactory = $this->responseFactory;
            register_shutdown_function(static function () use ($display, $logger, $responseFactory): void {
                $error = error_get_last();

                // Determine if we have a fatal error or an explicit die()/exit()
                $fatalTypes = [
                    E_ERROR,
                    E_PARSE,
                    E_CORE_ERROR,
                    E_CORE_WARNING,
                    E_COMPILE_ERROR,
                    E_COMPILE_WARNING,
                ];

                $isFatal = $error !== null && in_array($error['type'], $fatalTypes, true);
                $isExit  = $error === null; // die()/exit() — no error recorded

                if (!$isFatal && !$isExit) {
                    // Normal completion — nothing to do
                    return;
                }

                if ($isFatal) {
                    // Fatal PHP error — log and optionally display
                    $logger->error(
                        'Fatal error: ' . ($error['message'] ?? 'Unknown fatal error'),
                        [
                            'file' => $error['file'] ?? 'unknown',
                            'line' => $error['line'] ?? 0,
                            'code' => $error['type'] ?? 0,
                        ]
                    );

                    if ($display) {
                        // Send error details to the output.
                        // Note: the response may have already been partially written
                        // by the time this runs; this sends directly to php://output.
                        $response = $responseFactory->createResponse(500);
                        $response->getBody()->write(json_encode([
                            'error' => [
                                'message' => $error['message'] ?? 'Fatal error',
                                'code'    => $error['type'] ?? 0,
                                'file'    => $error['file'] ?? 'unknown',
                                'line'    => $error['line'] ?? 0,
                            ],
                        ], JSON_PRETTY_PRINT));
                        $response->withHeader('Content-Type', 'application/json');
                        // Output directly — may be too late if headers already sent
                        echo $response->getBody();
                    }
                } elseif ($isExit) {
                    // Explicit die()/exit() — treat as an error signal
                    $logger->warning(
                        'Script terminated via die()/exit()',
                        [
                            'output_length' => ob_get_length() ?: 0,
                        ]
                    );

                    if ($display) {
                        // The die() message (if any) was already written to output.
                        // Nothing further to add — the caller sees the die() payload.
                    }
                }
            });
        }

        // Delegate to the rest of the pipeline.
        // The shutdown handler registered above will fire after the response
        // is sent, catching any fatal errors or die()/exit() calls.
        return $handler->handle($request);
    }
}
