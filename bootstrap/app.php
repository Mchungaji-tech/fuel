<?php

// 1. Lightweight .env loader (supports .env, .env.cpanel, and .env.production)
$envFile = __DIR__ . '/../.env';
if (!file_exists($envFile)) {
    if (file_exists(__DIR__ . '/../.env.cpanel')) {
        $envFile = __DIR__ . '/../.env.cpanel';
    } elseif (file_exists(__DIR__ . '/../.env.production')) {
        $envFile = __DIR__ . '/../.env.production';
    }
}
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);
            $val = trim($val, '"\''); // Remove enclosing quotes
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
            putenv("{$key}={$val}");
        }
    }
}

// 2. PSR-4 Autoloader for App\ namespace
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// 3. Load Helpers
require_once __DIR__ . '/../app/Helpers/helpers.php';

// 4. Session Security Setup
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    if (is_https()) {
        ini_set('session.cookie_secure', '1');
    }
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// 5. Production Error Handling
$isDebug = filter_var($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: true, FILTER_VALIDATE_BOOLEAN);
if (!$isDebug) {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    set_exception_handler(function (\Throwable $e) {
        error_log("Unhandled Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString());
        http_response_code(500);
        if (function_exists('view')) {
            echo view('errors.500', [
                'title' => 'Internal Server Error (500)',
                'message' => 'An unexpected internal server error occurred. Our technical operations team has been notified.',
            ]);
        } else {
            echo '<h1>500 Internal Server Error</h1><p>An unexpected error occurred. Please contact the system administrator.</p>';
        }
        exit;
    });
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// 5. Config Helper function
function config(string $key, mixed $default = null): mixed
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/../config/app.php';
    }

    $segments = explode('.', $key);
    $value = $config;

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}
