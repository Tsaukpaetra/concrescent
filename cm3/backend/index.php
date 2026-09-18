<?php

// 1. Start output buffering to trap any unexpected output from third-party bootstrapping
ob_start();
require __DIR__ . '/vendor/autoload.php';

//Build up the container instance

$container = (new \CM3_Lib\Factory\ContainerFactory())->createInstance();

//And prepare it
try {
    //code...
    $app = $container->get(\Slim\App::class);
} catch (\Throwable $th) {
    //throw $th;
    print('App instantiate error: '.$th->getMessage() . "\n" . $th->getTraceAsString());
}

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

if(!isset($app)){
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json; charset=utf-8');
    
    // 3. Log a generic warning to your PHP container terminal
    error_log("BOOTSTRAP FAILED: \$app variable returned null during initialization.");

    // 4. Output the production-safe JSON response
    echo json_encode([
        'message' => 'The application failed to initialize. Please check the bootstrap log.'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
    exit(1);
} else {

    $app->run();
}
