<?php
define('TESTING_MODE', true);
require_once __DIR__ . '/../bootstrap/app.php';

use App\Core\Database;

$pdo = Database::connection();
echo "Checking & updating schema for exchange_rate support...\n";

// 1. financial_records
try {
    $cols = $pdo->query("PRAGMA table_info(financial_records)")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'name');
    if (!in_array('exchange_rate', $colNames)) {
        $pdo->exec("ALTER TABLE financial_records ADD COLUMN exchange_rate DECIMAL(12,4) DEFAULT 128.0000");
        echo " [OK] Added exchange_rate to financial_records (SQLite)\n";
    } else {
        echo " [OK] exchange_rate already exists in financial_records (SQLite)\n";
    }
} catch (\Throwable $e) {
    try {
        $pdo->exec("ALTER TABLE financial_records ADD COLUMN exchange_rate DECIMAL(12,4) NOT NULL DEFAULT 128.0000");
        echo " [OK] Added exchange_rate to financial_records (MySQL)\n";
    } catch (\Throwable $e2) {
        echo " [INFO] financial_records: " . $e2->getMessage() . "\n";
    }
}

// 2. expenses
try {
    $cols = $pdo->query("PRAGMA table_info(expenses)")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'name');
    if (!in_array('exchange_rate', $colNames)) {
        $pdo->exec("ALTER TABLE expenses ADD COLUMN exchange_rate DECIMAL(12,4) DEFAULT 128.0000");
        echo " [OK] Added exchange_rate to expenses (SQLite)\n";
    } else {
        echo " [OK] exchange_rate already exists in expenses (SQLite)\n";
    }
} catch (\Throwable $e) {
    try {
        $pdo->exec("ALTER TABLE expenses ADD COLUMN exchange_rate DECIMAL(12,4) NOT NULL DEFAULT 128.0000");
        echo " [OK] Added exchange_rate to expenses (MySQL)\n";
    } catch (\Throwable $e2) {
        echo " [INFO] expenses: " . $e2->getMessage() . "\n";
    }
}

// 3. fleet_diesel_logs
try {
    $cols = $pdo->query("PRAGMA table_info(fleet_diesel_logs)")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'name');
    if (!in_array('exchange_rate', $colNames)) {
        $pdo->exec("ALTER TABLE fleet_diesel_logs ADD COLUMN exchange_rate DECIMAL(12,4) DEFAULT 130.0000");
        echo " [OK] Added exchange_rate to fleet_diesel_logs (SQLite)\n";
    } else {
        echo " [OK] exchange_rate already exists in fleet_diesel_logs (SQLite)\n";
    }
} catch (\Throwable $e) {
    echo " [INFO] fleet_diesel_logs: " . $e->getMessage() . "\n";
}

echo "Schema update check completed.\n";
