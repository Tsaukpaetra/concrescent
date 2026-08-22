<?php

use CM3_Lib\Factory\LoggerFactory;
use CM3_Lib\Factory\ErrorLoggerFactory;
use CM3_Lib\Factory\PaymentModuleFactory;
use CM3_Lib\Middleware\DefaultErrorHandler;
use CM3_Lib\Middleware\AccessLogMiddleware;
use CM3_Lib\util\TokenGenerator;
use CM3_Lib\util\CurrentUserInfo;
use CM3_Lib\AppConfig;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use DI\Factory\RequestedEntry;
use function DI\autowire;
use function DI\get;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Interfaces\RouteParserInterface;
use Slim\Middleware\ErrorMiddleware;
use Slim\Exception\HttpNotFoundException;
use PHPMailer\PHPMailer\PHPMailer;
use League\CommonMark\MarkdownConverter;
use Respect\Validation\Factory as RespectFactory;

use Branca\Branca;
use CM3_Lib\database\DbConnection;
use CM3_Lib\util\FrontendUrlTranslator;
use CM3_Lib\Middleware\PermCheckEventId;


return [
    // Application settings
    AppConfig::class => function () {
        //Load legacy config
        $config = require __DIR__ . '/../config.php';
        //Load .env file if it exists
        Dotenv\Dotenv::createArrayBacked(__DIR__ . '/../')->safeLoad();
        /**
         * Recursively apply environment variable overrides to the config array.
         */
        $applyOverrides = function (&$currentConfig, $prefix = '') use (&$applyOverrides) {
            foreach ($currentConfig as $key => &$value) {
                // Build the expected environment variable name (e.g., DATABASE_HOST)
                $envName = strtoupper($prefix . $key);

                if (is_array($value)) {
                    // If it's a nested array, recurse deeper
                    $applyOverrides($value, $envName . '_');
                } elseif (isset($_ENV[$envName])) {
                    // Only continue processing this if it is in the env    
                    $envValue = $_ENV[$envName];
                    if ($envValue !== false) {
                        // Handle type casting since getenv returns strings
                        if (strtolower($envValue) === 'true')
                            $value = true;
                        elseif (strtolower($envValue) === 'false')
                            $value = false;
                        elseif (is_numeric($envValue)) {
                            $value = (strpos($envValue, '.') !== false) ? (float) $envValue : (int) $envValue;
                        } else {
                            $value = $envValue;
                        }
                    }
                }
            }
        };

        $applyOverrides($config);
        //Special case for the token to make it binary if it's 64 characters instead of 32
        if(strlen($config['environment']['token_secret']) == 64)
            $config['environment']['token_secret'] = hex2bin($config['environment']['token_secret']);
        
        /* Apply the default timezone here */
        date_default_timezone_set($config['environment']['timezone']);
        
        //Disable deprecation warnings and other things that could ruin the output stream before Slim does its thing
        if(!$config['error']['display_error_details']){
            error_reporting(E_ALL ^ (E_NOTICE | E_WARNING | E_DEPRECATED));
        }

        return new AppConfig($config);
    },

    App::class => function (ContainerInterface $container) {
        $app = AppFactory::createFromContainer($container);

        //Set up custom validators
        RespectFactory::setDefaultInstance ((new RespectFactory())
            ->withRuleNamespace('CM3_Lib\\RespectValidation\\Rules')
            ->withExceptionNamespace('CM3_Lib\\RespectValidation\\Exceptions'));

        // Register routes, up to three folders deep
        foreach (glob(__DIR__ . '/../routes/{,*/,*/*/,*/*/*/}*.php', GLOB_BRACE) as $route) {
            (require $route)($app, $container);
        }

        /*
         * Catch-all route to serve a 404 Not Found page if none of the routes match
         * NOTE: make sure this route is defined last
         */
        $app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function ($request, $response) {
            throw new HttpNotFoundException($request);
        });

        // Register middleware
        (require __DIR__ . '/middleware.php')($app, $container->get(AppConfig::class));

        return $app;
    },

    // HTTP factories
    ResponseFactoryInterface::class => function (ContainerInterface $container) {
        return $container->get(Psr17Factory::class);
    },

    ServerRequestFactoryInterface::class => function (ContainerInterface $container) {
        return $container->get(Psr17Factory::class);
    },

    StreamFactoryInterface::class => function (ContainerInterface $container) {
        return $container->get(Psr17Factory::class);
    },

    UploadedFileFactoryInterface::class => function (ContainerInterface $container) {
        return $container->get(Psr17Factory::class);
    },

    UriFactoryInterface::class => function (ContainerInterface $container) {
        return $container->get(Psr17Factory::class);
    },

    // The Slim RouterParser
    RouteParserInterface::class => function (ContainerInterface $container) {
        return $container->get(App::class)->getRouteCollector()->getRouteParser();
    },

    // The logger factory
    LoggerFactory::class => function (ContainerInterface $container) {
        return (new LoggerFactory($container->get(AppConfig::class)->get('logger')))
        ->addDBHandler($container->get(\CM3_Lib\models\admin\access_log::class))
        ->addFileHandler('access.log')
        ;
    },
    //And one for errors specifically
    ErrorLoggerFactory::class => function (ContainerInterface $container) {
        return (new LoggerFactory($container->get(AppConfig::class)->get('logger')))
        ->addDBHandler($container->get(\CM3_Lib\models\admin\error_log::class))
        ->addFileHandler('error.log');
    },
    //The default logger interface will  send to the error log
    LoggerInterface::class => function (ContainerInterface $container) {
        
        $loggerFactory = $container->get(ErrorLoggerFactory::class);
        $logger = $loggerFactory->createLogger();
        $logger->pushProcessor(function ($record) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
            
            // Walk back the trace to find the first class outside of vendor/psr/monolog
            foreach ($trace as $frame) {
                $class = $frame['class'] ?? null;
                if ($class && str_starts_with($class, 'CM3_Lib\\')) {
                    // This is your application class (e.g., App\Services\UserService)
                    $record['channel'] = $class; 
                    break;
                }
            }

            return $record;
        });

        // 2. Global Context Processor (Path, Request Data, User Info)
        $logger->pushProcessor(function ($record) use ($container) {
            $context = ['data' => $record['context']];
        
            //Get the simplified path
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';            
            $basePath = $container->get(AppConfig::class)->get('environment.base_path') ?? '';
            if ($basePath !== '' && str_starts_with($path, $basePath)) {
                $path = substr($path, strlen($basePath));
            }
            $context['path'] = $path;

            //Get the query params
            $queryParams = [];
            if (!empty($_SERVER['QUERY_STRING'])) {
                parse_str($_SERVER['QUERY_STRING'], $queryParams);
            }
            $context['data']['query_params'] = $queryParams;

                
            if ($container->has(Psr\Http\Message\ServerRequestInterface::class)) {
                // $data = [
                //     ... $this->extractData($request)
                // ];
                // $context['data'] = $data;
            }

            // Fetch user info dynamically if service exists
            if ($container->has(CurrentUserInfo::class)) {
                $userInfo = $container->get(CurrentUserInfo::class);
                $context['contact_id'] = $userInfo->GetContactId();
                $context['event_id'] = $userInfo->GetEventId();
            }
            //Spit the updated context back
            $record['context'] = $context;

            return $record;
        });


        return $logger;
    },

    // Database connection
    DbConnection::class => function (ContainerInterface $container) {
        return new DbConnection($container->get(AppConfig::class)->get('database'));
    },

    //Auth signer
    Branca::class => function (ContainerInterface $container) {
        return new Branca($container->get(AppConfig::class)->get('environment')['token_secret']);
    },

    FrontendUrlTranslator::class => function (ContainerInterface $container) {
        $env = $container->get(AppConfig::class)->get('environment');
        $TokenGenerator = $container->get(TokenGenerator::class);
        return new FrontendUrlTranslator($env['frontend_host'], $env['frontend_isHashMode'], $TokenGenerator);
    },

    PaymentModuleFactory::class => function (ContainerInterface $container) {
        return new PaymentModuleFactory($container->get(AppConfig::class)->get('payments'));
    },

    PHPMailer::class => function (ContainerInterface $container) {
        $mc = $container->get(AppConfig::class)->get('mailer');
        $mode = $mc['mode'] ?? 'SMTP';

        // 1. Define built-in legacy modes
        $builtInModes = ['SMTP', 'Sendmail', 'Gmail'];

        if (in_array($mode, $builtInModes, true)) {
            // Use standard PHPMailer for default drivers
            $mail = new PHPMailer(true);
        } else {
            // 2. Map custom modes to your specific namespace
            $mailerClass = "CM3_Lib\\Modules\\Mailers\\" . $mode;

            // Ensure the class exists before trying to instantiate it
            if (!class_exists($mailerClass)) {
                throw new \RuntimeException("Mailer module class '{$mailerClass}' not found for mode '{$mode}'");
            }

            // Security Check: Ensure the custom driver extends PHPMailer
            if (!is_subclass_of($mailerClass, PHPMailer::class)) {
                throw new \RuntimeException("Mailer module '{$mailerClass}' must extend " . PHPMailer::class);
            }

            $mail = new $mailerClass(true);
        }

        // 3. Configure standard built-in transport modes
        switch ($mode) {
            case 'SMTP':
                $mail->isSMTP();
                $mail->Host = $mc['Host'] ?? '';
                $mail->Port = $mc['Port'] ?? 587;
                if (!empty($mc['Username'])) {
                    $mail->SMTPAuth = true;
                    $mail->Username = $mc['Username'];
                    $mail->Password = $mc['Password'];
                }
                break;
            case 'Sendmail':
                $mail->isSendmail();
                break;
            case 'Gmail':
                //TODO: Finish
                $mail->Host = 'smtp.gmail.com';
                $mail->Port = 587;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->SMTPAuth = true;
                $mail->AuthType = 'XOAUTH2';

                //Pass the OAuth provider instance to PHPMailer
                $mail->setOAuth(
                    new \PHPMailer\PHPMailer\OAuth(
                        [
                            'provider' => new \League\OAuth2\Client\Provider\Google(
                                [
                                    'clientId' => $mc['Username'],
                                    'clientSecret' => $mc['Password'],
                                ]
                            ),
                            'clientId' => $mc['Username'],
                            'clientSecret' => $mc['Password'],
                            'refreshToken' => $refreshToken,
                            'userName' => $mc['defaultFrom'],
                        ]
                    )
                );
                break;
            default:
                // 4. Pass configuration directly to custom modules
                if (method_exists($mail, 'configureCustomTransport')) {
                    $mail->configureCustomTransport($mc);
                }
                break;
        }

        if (!empty($mc['defaultFrom'])) {
            $mail->setFrom($mc['defaultFrom']);
        }

        return $mail;
    },

    MarkdownConverter::class => function (ContainerInterface $container) {
        $config = [
            'renderer' => [
                'block_separator' => "\n",
                'inner_separator' => "\n",
                'soft_break'      => "\n",
            ],
            'commonmark' => [
                'enable_em' => true,
                'enable_strong' => true,
                'use_asterisk' => true,
                'use_underscore' => true,
                'unordered_list_markers' => ['-', '*', '+'],
            ],
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => PHP_INT_MAX,
            'slug_normalizer' => [
                'max_length' => 255,
            ],
        ];
        $environment = new \League\CommonMark\Environment\Environment($config);

        // TODO: Extension for the filestore links
        $environment->addExtension(new \League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension());
        $environment->addExtension(new \League\CommonMark\Extension\Autolink\AutolinkExtension());
        $environment->addExtension(new \League\CommonMark\Extension\Table\TableExtension());

        // Go forth and convert you some Markdown!
        return new MarkdownConverter($environment);
    },

    AccessLogMiddleware::class => function (ContainerInterface $container) {
        // You can use a dedicated access logger or your default application logger
        $loggerFactory = $container->get(LoggerFactory::class); 
        $app  = $container->get(App::class);

        return new AccessLogMiddleware($loggerFactory->createLogger('Access'), $container->get(CurrentUserInfo::class), $app->getBasePath());
    },

    DefaultErrorHandler::class => autowire()
        ->constructorParameter('loggerFactory', get(ErrorLoggerFactory::class)),

    ErrorMiddleware::class => function (ContainerInterface $container) {
        $s_config_error = $container->get(AppConfig::class)->get('error');
        $app = $container->get(App::class);

        $errorMiddleware = new ErrorMiddleware(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            (bool)$s_config_error['display_error_details'],
            (bool)$s_config_error['log_errors'],
            (bool)$s_config_error['log_error_details']
        );

        $errorMiddleware->setDefaultErrorHandler(
            $container->get(DefaultErrorHandler::class)
        );

        return $errorMiddleware;
    }
];
