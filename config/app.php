<?php

return [
    'name' => $_ENV['APP_NAME'] ?? getenv('APP_NAME') ?: 'Sarura Fuel Logistics',
    'env' => $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'local',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: true, FILTER_VALIDATE_BOOLEAN),
    'url' => $_ENV['APP_URL'] ?? getenv('APP_URL') ?: '',
    'database' => [
        'driver' => $_ENV['DB_DRIVER'] ?? getenv('DB_DRIVER') ?: 'auto',
        'sqlite' => [
            'database' => __DIR__ . '/../' . ($_ENV['DB_SQLITE_PATH'] ?? getenv('DB_SQLITE_PATH') ?: 'storage/database.sqlite'),
        ],
        'mysql' => [
            'host' => $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost',
            'port' => $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306',
            'database' => $_ENV['DB_DATABASE'] ?? $_ENV['DB_NAME'] ?? getenv('DB_DATABASE') ?: (getenv('DB_NAME') ?: 'tektxbzg_fuel'),
            'username' => $_ENV['DB_USERNAME'] ?? $_ENV['DB_USER'] ?? getenv('DB_USERNAME') ?: (getenv('DB_USER') ?: 'tektxbzg_fuel'),
            'password' => $_ENV['DB_PASSWORD'] ?? $_ENV['DB_PASS'] ?? getenv('DB_PASSWORD') ?: (getenv('DB_PASS') ?: ''),
            'charset' => $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4',
            'timeout' => (int)($_ENV['DB_TIMEOUT'] ?? getenv('DB_TIMEOUT') ?: 3),
        ],
    ],
];
