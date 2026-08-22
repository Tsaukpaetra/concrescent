<?php

// 1. Start output buffering to trap any unexpected output from third-party bootstrapping
ob_start();
require __DIR__ . '/vendor/autoload.php';

//Build up the container instance

$container = (new \CM3_Lib\Factory\ContainerFactory())->createInstance();

//And prepare it
$app = $container->get(\Slim\App::class);

// 2. Grab whatever junk output was generated (like deprecation warnings)
$bootstrapper_output = ob_get_clean();

// 3. If the autoloader/third-party code spat out text, log it to a file instead of breaking the response
if (!empty(trim($bootstrapper_output))) {
try {
        $log_file = __DIR__ . '/logs/bootstrap_warnings.log';
        $log_dir = dirname($log_file);
        if (!file_exists($log_file)) {
            if (!is_dir($log_dir)) {
                @mkdir($log_dir, 0755, true);
            }
            // Write only if the directory is writable or successfully created
            if (is_dir($log_dir)) {
                file_put_contents($log_file, $bootstrapper_output);
            }
        }
    } catch (\Throwable $e) {
        // Silently fail—never let a logging permission error crash the application request
    }
}

$app->run();
