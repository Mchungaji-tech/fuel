<?php
require_once 'bootstrap/app.php';

use App\Core\Database;

echo "Testing MySQL Connection & Schema Initialization...\n";
$pdo = Database::getMysqlConnection();
if ($pdo instanceof PDO) {
    echo "✓ MySQL Connected Successfully!\n";
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in MySQL database: " . implode(', ', $tables) . "\n";
    echo "Active driver: " . Database::getActiveDriver() . "\n";
} else {
    echo "✗ Failed to connect to MySQL\n";
}
