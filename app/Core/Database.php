<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    protected static ?PDO $connection = null;
    protected static ?PDO $mysqlConnection = null;
    protected static ?PDO $sqliteConnection = null;
    protected static string $activeDriver = 'sqlite';
    protected static bool $isFallback = false;
    protected static ?string $fallbackReason = null;

    /**
     * Get the active database connection.
     * In 'auto' or 'mysql' mode, connects to MySQL if online,
     * and automatically falls back to local SQLite when offline.
     */
    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = config('database');
        $driver = strtolower($config['driver'] ?? 'auto');

        if ($driver === 'sqlite') {
            self::$connection = self::getSqliteConnection();
            self::$activeDriver = 'sqlite';
            self::$isFallback = false;
            return self::$connection;
        }

        // 'auto' or 'mysql' mode
        // Check if MySQL was recently unreachable (within 20s) to keep offline browsing fast
        $offlineMarker = __DIR__ . '/../../storage/mysql_offline.tmp';
        $recentlyFailed = false;
        if (file_exists($offlineMarker)) {
            $mtime = filemtime($offlineMarker);
            if (time() - $mtime < 20) {
                $recentlyFailed = true;
            } else {
                @unlink($offlineMarker);
            }
        }

        if (!$recentlyFailed) {
            try {
                $mysqlPdo = self::getMysqlConnection();
                if ($mysqlPdo instanceof PDO) {
                    if (file_exists($offlineMarker)) {
                        @unlink($offlineMarker);
                    }
                    self::$connection = $mysqlPdo;
                    self::$activeDriver = 'mysql';
                    self::$isFallback = false;
                    self::$fallbackReason = null;
                    return self::$connection;
                }
            } catch (\Throwable $e) {
                // MySQL is offline or unreachable
                @touch($offlineMarker);
                self::$isFallback = true;
                self::$fallbackReason = $e->getMessage();
                error_log("Database: MySQL offline/unreachable (" . $e->getMessage() . "). Falling back to SQLite.");
            }
        } else {
            self::$isFallback = true;
            self::$fallbackReason = 'MySQL marked temporarily offline (waiting for recovery probe)';
        }

        // Fall back to SQLite
        self::$connection = self::getSqliteConnection();
        self::$activeDriver = 'sqlite';
        return self::$connection;
    }

    /**
     * Connect directly to MySQL.
     */
    public static function getMysqlConnection(): ?PDO
    {
        if (self::$mysqlConnection instanceof PDO) {
            return self::$mysqlConnection;
        }

        $config = config('database');
        $mysql = $config['mysql'] ?? [];
        $dbName = $mysql['database'] ?? 'sarura_fuel';
        $timeout = (int) ($mysql['timeout'] ?? 2);

        $dsn = sprintf(
            'mysql:host=%s;port=%s;charset=%s',
            $mysql['host'] ?? '127.0.0.1',
            $mysql['port'] ?? '3306',
            $mysql['charset'] ?? 'utf8mb4'
        );

        $pdoOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => $timeout,
        ];

        // 1. Connection DSN targeting the database
        $dsnWithDb = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $mysql['host'] ?? '127.0.0.1',
            $mysql['port'] ?? '3306',
            $dbName,
            $mysql['charset'] ?? 'utf8mb4'
        );

        try {
            // Direct connection to target database (ideal for cPanel and production environments)
            self::$mysqlConnection = new PDO(
                $dsnWithDb,
                $mysql['username'] ?? 'root',
                $mysql['password'] ?? '',
                $pdoOptions
            );
        } catch (\Throwable $connEx) {
            // If direct connection failed, attempt to create database if privileged (e.g. local dev)
            try {
                $rootConnection = new PDO(
                    $dsn,
                    $mysql['username'] ?? 'root',
                    $mysql['password'] ?? '',
                    $pdoOptions
                );
                $rootConnection->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                self::$mysqlConnection = new PDO(
                    $dsnWithDb,
                    $mysql['username'] ?? 'root',
                    $mysql['password'] ?? '',
                    $pdoOptions
                );
            } catch (\Throwable $rootEx) {
                // Re-throw original connection exception so failover/caller handles it properly
                throw $connEx;
            }
        }

        self::initializeSchema(self::$mysqlConnection);
        return self::$mysqlConnection;
    }

    /**
     * Connect directly to SQLite.
     */
    public static function getSqliteConnection(): PDO
    {
        if (self::$sqliteConnection instanceof PDO) {
            return self::$sqliteConnection;
        }

        $config = config('database');
        $dbPath = $config['sqlite']['database'] ?? __DIR__ . '/../../storage/database.sqlite';
        $directory = dirname($dbPath);

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        if (!file_exists($dbPath)) {
            touch($dbPath);
        }

        self::$sqliteConnection = new PDO('sqlite:' . $dbPath);
        self::$sqliteConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$sqliteConnection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::$sqliteConnection->exec('PRAGMA journal_mode = WAL;');
        self::$sqliteConnection->exec('PRAGMA busy_timeout = 5000;');
        self::$sqliteConnection->exec('PRAGMA synchronous = NORMAL;');
        self::initializeSchema(self::$sqliteConnection);

        return self::$sqliteConnection;
    }

    public static function getActiveDriver(): string
    {
        return self::$activeDriver;
    }

    public static function isOfflineFallback(): bool
    {
        return self::$isFallback;
    }

    public static function getFallbackReason(): ?string
    {
        return self::$fallbackReason;
    }

    public static function isMysqlAvailable(): bool
    {
        try {
            $config = config('database');
            $mysql = $config['mysql'] ?? [];
            $timeout = (int) ($mysql['timeout'] ?? 2);
            $dbName = $mysql['database'] ?? '';
            $dsn = sprintf(
                'mysql:host=%s;port=%s%s;charset=utf8mb4',
                $mysql['host'] ?? '127.0.0.1',
                $mysql['port'] ?? '3306',
                $dbName !== '' ? ";dbname={$dbName}" : ''
            );
            new PDO($dsn, $mysql['username'] ?? 'root', $mysql['password'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => $timeout,
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function clearOfflineMarker(): void
    {
        $offlineMarker = __DIR__ . '/../../storage/mysql_offline.tmp';
        if (file_exists($offlineMarker)) {
            @unlink($offlineMarker);
        }
    }

    public static function resetConnection(): void
    {
        self::$connection = null;
        self::$mysqlConnection = null;
        self::$sqliteConnection = null;
    }

    public static function initializeSchema(?PDO $targetPdo = null): void
    {
        $pdo = $targetPdo ?? self::$connection;

        if (!$pdo) {
            return;
        }

        $driverName = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $idColumnSql = $driverName === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id INT AUTO_INCREMENT PRIMARY KEY';

        $tables = [
            "CREATE TABLE IF NOT EXISTS users (
                {$idColumnSql},
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(50) DEFAULT 'admin'
            )",
            "CREATE TABLE IF NOT EXISTS drivers (
                {$idColumnSql},
                name VARCHAR(255) NOT NULL,
                phone VARCHAR(50),
                license_number VARCHAR(100),
                license_class VARCHAR(100),
                truck VARCHAR(100),
                status VARCHAR(50) DEFAULT 'Active'
            )",
            "CREATE TABLE IF NOT EXISTS customers (
                {$idColumnSql},
                name VARCHAR(255) NOT NULL,
                orders INT DEFAULT 0,
                outstanding VARCHAR(100) DEFAULT 'KES 0',
                status VARCHAR(50) DEFAULT 'Good'
            )",
            "CREATE TABLE IF NOT EXISTS trips (
                {$idColumnSql},
                trip_number VARCHAR(100) NOT NULL,
                customer VARCHAR(255) NOT NULL,
                truck VARCHAR(100) NOT NULL,
                driver VARCHAR(255) NOT NULL,
                route VARCHAR(255),
                load_quantity VARCHAR(100),
                status VARCHAR(50) DEFAULT 'Planned'
            )",
            "CREATE TABLE IF NOT EXISTS invoices (
                {$idColumnSql},
                invoice_number VARCHAR(100) NOT NULL,
                client VARCHAR(255) NOT NULL,
                amount VARCHAR(100) NOT NULL,
                due_date VARCHAR(50),
                status VARCHAR(50) DEFAULT 'Pending'
            )",
            "CREATE TABLE IF NOT EXISTS documents (
                {$idColumnSql},
                name VARCHAR(255) NOT NULL,
                type VARCHAR(100),
                file_name VARCHAR(255),
                uploaded_at VARCHAR(50),
                status VARCHAR(50) DEFAULT 'Uploaded'
            )",
            "CREATE TABLE IF NOT EXISTS trucks (
                {$idColumnSql},
                plate_number VARCHAR(100) NOT NULL UNIQUE,
                model VARCHAR(150),
                capacity_litres INT NOT NULL DEFAULT 0,
                compartments VARCHAR(100) DEFAULT NULL,
                ownership_type VARCHAR(50) DEFAULT 'Company',
                owner_name VARCHAR(150) DEFAULT 'Sarura Fuel Logistics',
                commission_rate DECIMAL(10,2) DEFAULT 0,
                status VARCHAR(50) DEFAULT 'Ready',
                created_at VARCHAR(50)
            )",
            "CREATE TABLE IF NOT EXISTS products (
                {$idColumnSql},
                code VARCHAR(50) NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                category VARCHAR(100) DEFAULT 'Fuel',
                unit VARCHAR(50) DEFAULT 'Litres',
                unit_price DECIMAL(10,2) DEFAULT 0,
                status VARCHAR(50) DEFAULT 'Active',
                created_at VARCHAR(50)
            )",
            "CREATE TABLE IF NOT EXISTS fleet_dispatches (
                {$idColumnSql},
                trip_number VARCHAR(100) NOT NULL,
                bol_number VARCHAR(100) NOT NULL,
                dispatch_date VARCHAR(50) NOT NULL,
                truck VARCHAR(100) NOT NULL,
                truck_capacity INT DEFAULT 0,
                loaded_litres INT DEFAULT 0,
                shortage_litres INT DEFAULT 0,
                from_location VARCHAR(255) NOT NULL,
                destination VARCHAR(255) NOT NULL,
                product VARCHAR(100) NOT NULL,
                unit_price DECIMAL(10,2) DEFAULT 0,
                driver VARCHAR(255) NOT NULL,
                transport_amount DECIMAL(12,2) DEFAULT 0,
                mileage_cost DECIMAL(12,2) DEFAULT 0,
                diesel_litres DECIMAL(10,2) DEFAULT 0,
                diesel_unit_price DECIMAL(10,2) DEFAULT 0,
                diesel DECIMAL(12,2) DEFAULT 0,
                extra_expenses DECIMAL(12,2) DEFAULT 0,
                breakdown_notes TEXT,
                balance DECIMAL(12,2) DEFAULT 0,
                is_subcontracted INT DEFAULT 0,
                agreed_commission DECIMAL(12,2) DEFAULT 0,
                delivered_litres INT DEFAULT NULL,
                final_payout DECIMAL(12,2) DEFAULT NULL,
                payout_difference DECIMAL(12,2) DEFAULT 0,
                shortage_notes TEXT,
                is_shortage_recovered INT DEFAULT 0,
                status VARCHAR(50) DEFAULT 'Planned',
                seal_numbers VARCHAR(255),
                created_at VARCHAR(50)
            )",
            "CREATE TABLE IF NOT EXISTS expenses (
                {$idColumnSql},
                expense_date VARCHAR(50) NOT NULL,
                expense_title VARCHAR(255) NOT NULL,
                truck VARCHAR(100),
                amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                garage_vendor VARCHAR(255),
                receipt_number VARCHAR(100),
                notes TEXT,
                created_at VARCHAR(50)
            )",
            "CREATE TABLE IF NOT EXISTS driver_salaries (
                {$idColumnSql},
                driver_id INT,
                driver_name VARCHAR(255) NOT NULL,
                payment_type VARCHAR(50) NOT NULL DEFAULT 'monthly_salary',
                period_reference VARCHAR(255) NOT NULL,
                base_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
                carried_forward DECIMAL(12,2) NOT NULL DEFAULT 0,
                shortage_deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
                amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                status VARCHAR(50) NOT NULL DEFAULT 'Wait',
                payment_date VARCHAR(50),
                notes TEXT,
                created_at VARCHAR(50)
            )",
            "CREATE TABLE IF NOT EXISTS audit_logs (
                {$idColumnSql},
                user_name VARCHAR(255) NOT NULL,
                user_email VARCHAR(255) NOT NULL,
                category VARCHAR(100) NOT NULL,
                action VARCHAR(100) NOT NULL,
                description TEXT,
                ip_address VARCHAR(100),
                is_deleted INT DEFAULT 0,
                created_at VARCHAR(50)
            )",
            "CREATE TABLE IF NOT EXISTS user_sessions (
                {$idColumnSql},
                user_id INT NOT NULL,
                session_token VARCHAR(255) NOT NULL,
                ip_address VARCHAR(100),
                user_agent TEXT,
                status VARCHAR(50) DEFAULT 'active',
                created_at VARCHAR(50),
                last_active_at VARCHAR(50)
            )",
            "CREATE TABLE IF NOT EXISTS settings (
                {$idColumnSql},
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT,
                updated_at VARCHAR(50)
            )",
            "CREATE TABLE IF NOT EXISTS table_columns_meta (
                {$idColumnSql},
                table_name VARCHAR(100) NOT NULL,
                column_key VARCHAR(100) NOT NULL,
                display_label VARCHAR(150) NOT NULL,
                data_type VARCHAR(50) DEFAULT 'text',
                is_visible INT DEFAULT 1,
                is_custom INT DEFAULT 0,
                sort_order INT DEFAULT 0,
                options_json TEXT,
                created_at VARCHAR(50),
                UNIQUE(table_name, column_key)
            )",
            "CREATE TABLE IF NOT EXISTS custom_tables (
                {$idColumnSql},
                table_key VARCHAR(100) NOT NULL UNIQUE,
                display_name VARCHAR(150) NOT NULL,
                description TEXT,
                icon VARCHAR(50) DEFAULT '📋',
                created_at VARCHAR(50)
            )",
        ];

        foreach ($tables as $sql) {
            $pdo->exec($sql);
        }

        // Universal schema migration: ensure column parity for both SQLite and MySQL
        $getColumns = function (string $tableName) use ($pdo, $driverName): array {
            try {
                if ($driverName === 'sqlite') {
                    return $pdo->query("PRAGMA table_info(`{$tableName}`)")->fetchAll(PDO::FETCH_COLUMN, 1);
                } else {
                    return $pdo->query("SHOW COLUMNS FROM `{$tableName}`")->fetchAll(PDO::FETCH_COLUMN, 0);
                }
            } catch (\Throwable $e) {
                return [];
            }
        };

        $ensureColumn = function (string $tableName, string $colName, string $colDef) use ($pdo, $getColumns) {
            $cols = $getColumns($tableName);
            if (!empty($cols) && !in_array($colName, $cols, true)) {
                try {
                    $pdo->exec("ALTER TABLE `{$tableName}` ADD COLUMN `{$colName}` {$colDef}");
                } catch (\Throwable $e) {
                    // Ignore if column already exists
                }
            }
        };

        // Users
        $ensureColumn('users', 'last_active_at', 'VARCHAR(50) DEFAULT NULL');
        $ensureColumn('users', 'last_login_at', 'VARCHAR(50) DEFAULT NULL');
        $ensureColumn('users', 'is_active', 'INT DEFAULT 1');

        // Trucks
        $ensureColumn('trucks', 'ownership_type', "VARCHAR(50) DEFAULT 'Owner'");
        $ensureColumn('trucks', 'owner_name', "VARCHAR(150) DEFAULT 'Sarura Fuel Logistics'");
        // Products
        $ensureColumn('products', 'unit_price', 'DECIMAL(10,2) DEFAULT 0');

        // Users
        $ensureColumn('users', 'last_active_at', 'VARCHAR(50) DEFAULT NULL');
        $ensureColumn('users', 'last_login_at', 'VARCHAR(50) DEFAULT NULL');
        $ensureColumn('users', 'is_active', 'INT DEFAULT 1');

        // Trucks
        $ensureColumn('trucks', 'ownership_type', "VARCHAR(50) DEFAULT 'Owner'");
        $ensureColumn('trucks', 'owner_name', "VARCHAR(150) DEFAULT 'Sarura Fuel Logistics'");
        $ensureColumn('trucks', 'commission_rate', 'DECIMAL(10,2) DEFAULT 0');

        // Drivers
        $ensureColumn('drivers', 'fixed_salary', 'DECIMAL(12,2) DEFAULT 35000.00');

        // Fleet Dispatches
        $ensureColumn('fleet_dispatches', 'unit_price', 'DECIMAL(10,2) DEFAULT 0');
        $ensureColumn('fleet_dispatches', 'shortage_litres', 'INT DEFAULT 0');
        $ensureColumn('fleet_dispatches', 'diesel_litres', 'DECIMAL(10,2) DEFAULT 0');
        $ensureColumn('fleet_dispatches', 'diesel_unit_price', 'DECIMAL(10,2) DEFAULT 0');
        $ensureColumn('fleet_dispatches', 'is_shortage_recovered', 'INT DEFAULT 0');
        $ensureColumn('fleet_dispatches', 'is_subcontracted', 'INT DEFAULT 0');
        $ensureColumn('fleet_dispatches', 'agreed_commission', 'DECIMAL(12,2) DEFAULT 0');
        $ensureColumn('fleet_dispatches', 'delivered_litres', 'INT DEFAULT NULL');
        $ensureColumn('fleet_dispatches', 'final_payout', 'DECIMAL(12,2) DEFAULT NULL');
        $ensureColumn('fleet_dispatches', 'payout_difference', 'DECIMAL(12,2) DEFAULT 0');
        $ensureColumn('fleet_dispatches', 'shortage_notes', 'TEXT');
        $ensureColumn('fleet_dispatches', 'client_name', 'VARCHAR(255) DEFAULT NULL');
        $ensureColumn('fleet_dispatches', 'diesel', 'DECIMAL(12,2) DEFAULT 0');

        // Driver Salaries
        $ensureColumn('driver_salaries', 'base_salary', 'DECIMAL(12,2) DEFAULT 0');
        $ensureColumn('driver_salaries', 'carried_forward', 'DECIMAL(12,2) DEFAULT 0');
        $ensureColumn('driver_salaries', 'shortage_deductions', 'DECIMAL(12,2) DEFAULT 0');

        // Expenses
        $ensureColumn('expenses', 'receipt_status', "VARCHAR(50) DEFAULT 'Received'");

        // Customers
        $ensureColumn('customers', 'company', 'VARCHAR(255) DEFAULT NULL');
        $ensureColumn('customers', 'phone', 'VARCHAR(100) DEFAULT NULL');
        $ensureColumn('customers', 'email', 'VARCHAR(100) DEFAULT NULL');
        $ensureColumn('customers', 'country', 'VARCHAR(100) DEFAULT NULL');
        $ensureColumn('customers', 'total_litres', 'INT DEFAULT 0');
        $ensureColumn('customers', 'last_dispatch_date', 'VARCHAR(50) DEFAULT NULL');

        // Initialize default maintenance setting if empty
        $maintCheck = $pdo->query("SELECT COUNT(*) FROM settings WHERE setting_key = 'maintenance_mode'")->fetchColumn();
        if ((int)$maintCheck === 0) {
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value, updated_at) VALUES ('maintenance_mode', '0', ?)")
                ->execute([date('Y-m-d H:i:s')]);
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value, updated_at) VALUES ('maintenance_message', 'Sarura Fuel Logistics Cloud is currently undergoing scheduled technical maintenance. Please check back shortly.', ?)")
                ->execute([date('Y-m-d H:i:s')]);
        }

        // Initialize default fixed salary setting for drivers
        $driverSalCheck = $pdo->query("SELECT COUNT(*) FROM settings WHERE setting_key = 'driver_fixed_salary'")->fetchColumn();
        if ((int)$driverSalCheck === 0) {
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value, updated_at) VALUES ('driver_fixed_salary', '35000', ?)")
                ->execute([date('Y-m-d H:i:s')]);
        }

        // Initialize default Super Admin if user roster is empty
        $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($userCount === 0) {
            $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)')
                ->execute(['Sarura Admin', 'admin@sarurafuel.co.ke', $passwordHash, 'super_admin']);
        }

        // Products: User explicitly instructed: "product just leave pms and ago only"
        // Ensure only PMS and AGO remain active with Kenyan transport payout unit prices
        try {
            // Delete non PMS/AGO products
            $pdo->exec("DELETE FROM products WHERE UPPER(code) NOT IN ('PMS', 'AGO')");
        } catch (\Throwable $e) {}

        // Ensure PMS and AGO exist with unit prices
        $pmsCheck = $pdo->query("SELECT COUNT(*) FROM products WHERE UPPER(code) = 'PMS'")->fetchColumn();
        if ((int)$pmsCheck === 0) {
            $pdo->prepare('INSERT INTO products (code, name, category, unit, unit_price, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute(['PMS', 'Premium Motor Spirit (Super Petrol)', 'Clean Fuel', 'Litres', 10.50, 'Active', date('Y-m-d H:i:s')]);
        } else {
            $pdo->exec("UPDATE products SET unit_price = 10.50 WHERE UPPER(code) = 'PMS' AND (unit_price = 0 OR unit_price IS NULL)");
        }

        $agoCheck = $pdo->query("SELECT COUNT(*) FROM products WHERE UPPER(code) = 'AGO'")->fetchColumn();
        if ((int)$agoCheck === 0) {
            $pdo->prepare('INSERT INTO products (code, name, category, unit, unit_price, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute(['AGO', 'Automotive Gas Oil (Diesel)', 'Heavy Fuel', 'Litres', 9.50, 'Active', date('Y-m-d H:i:s')]);
        } else {
            $pdo->exec("UPDATE products SET unit_price = 9.50 WHERE UPPER(code) = 'AGO' AND (unit_price = 0 OR unit_price IS NULL)");
        }
    }
}

