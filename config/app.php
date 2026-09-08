<?php

return [
    'name' => $_ENV['APP_NAME'] ?? getenv('APP_NAME') ?: 'Sarura Fuel Logistics',
    'env' => $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'local',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: true, FILTER_VALIDATE_BOOLEAN),
    'url' => $_ENV['APP_URL'] ?? getenv('APP_URL') ?: '',
    'database' => [
        'driver' => $_ENV['DB_DRIVER'] ?? getenv('DB_DRIVER') ?: 'sqlite',
        'sqlite' => [
            'database' => __DIR__ . '/../' . ($_ENV['DB_SQLITE_PATH'] ?? getenv('DB_SQLITE_PATH') ?: 'storage/database.sqlite'),
        ],
        'mysql' => [
            'host' => $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1',
            'port' => $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306',
            'database' => $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'sarura_fuel',
            'username' => $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
            'timeout' => (int)($_ENV['DB_TIMEOUT'] ?? getenv('DB_TIMEOUT') ?: 2),
        ],
    ],
];
