<?php
require_once __DIR__ . '/../app/Core/Database.php';
// Load .env manually if needed
$env = parse_ini_file(__DIR__ . '/../.env');
$pdo = new PDO(
    "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_DATABASE']};charset=utf8mb4",
    $env['DB_USERNAME'],
    $env['DB_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$cols = $pdo->query('DESCRIBE fleet_dispatches')->fetchAll(PDO::FETCH_COLUMN);
echo "fleet_dispatches: " . implode(', ', $cols) . PHP_EOL;
$dcols = $pdo->query('DESCRIBE fleet_diesel_logs')->fetchAll(PDO::FETCH_COLUMN);
echo "fleet_diesel_logs: " . implode(', ', $dcols) . PHP_EOL;
