<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Exception;

class TableSchemaService
{
    /**
     * Core tables that are built into the system
     */
    public const SYSTEM_TABLES = [
        'fleet_dispatches' => 'Fleet Logistics Ledger',
        'trucks' => 'Trucks & Tankers',
        'trips' => 'Trips Board',
        'expenses' => 'Operating Expenses',
        'drivers' => 'Drivers & Salaries',
        'customers' => 'Clients & Consignees',
        'products' => 'Fuel Products',
    ];

    /**
     * Default column definitions for built-in tables
     */
    public static function getDefaultColumnsForTable(string $table): array
    {
        switch ($table) {
            case 'fleet_dispatches':
                return [
                    ['dispatch_date', 'Date of Loading (DOL)', 'date', 1],
                    ['trip_number', 'Trip Number', 'text', 1],
                    ['truck', 'Truck Plate', 'text', 1],
                    ['driver', 'Driver Name', 'text', 1],
                    ['status', 'Trip Status', 'status', 1],
                    ['loaded_litres', 'Loaded Litres', 'number', 1],
                    ['shortage_litres', 'Shortage Litres', 'number', 1],
                    ['delivered_litres', 'Delivered Litres', 'number', 1],
                    ['product', 'Fuel Product', 'text', 1],
                    ['unit_price', 'Product Unit Price', 'currency', 1],
                    ['diesel', 'Diesel Fuel Cost', 'currency', 1],
                    ['diesel_litres', 'Diesel Litres', 'number', 0],
                    ['diesel_unit_price', 'Diesel Unit Price', 'currency', 0],
                    ['from_location', 'Origin Loading Depot', 'text', 1],
                    ['destination', 'Destination', 'text', 1],
                    ['client_name', 'Client / Consignee', 'text', 1],
                    ['transport_amount', 'Expected Transport', 'currency', 1],
                    ['final_payout', 'Final Client Payout', 'currency', 1],
                    ['payout_difference', 'Payout Diff (Loss)', 'currency', 1],
                    ['mileage_cost', 'Mileage Expense', 'currency', 1],
                    ['extra_expenses', 'Extra Breakdown Cost', 'currency', 0],
                    ['balance', 'Net Trip Profit', 'currency', 1],
                    ['seal_numbers', 'Seal Numbers', 'text', 1],
                    ['breakdown_notes', 'Breakdown / Remarks', 'text', 0],
                    ['shortage_notes', 'Shortage Notes', 'text', 0],
                    ['bol_number', 'BOL Number', 'text', 0],
                ];

            case 'trucks':
                return [
                    ['plate_number', 'Plate Number', 'text', 1],
                    ['capacity_litres', 'Capacity (Litres)', 'number', 1],
                    ['compartments', 'Compartments', 'text', 1],
                    ['ownership_type', 'Ownership Type', 'status', 1],
                    ['owner_name', 'Owner / Transporter Name', 'text', 1],
                    ['commission_rate', 'Haulier Commission Rate', 'currency', 1],
                    ['status', 'Truck Status', 'status', 1],
                ];

            case 'trips':
                return [
                    ['trip_number', 'Trip Ref', 'text', 1],
                    ['customer', 'Customer / Consignee', 'text', 1],
                    ['truck', 'Truck Plate', 'text', 1],
                    ['driver', 'Driver Name', 'text', 1],
                    ['route', 'Route / Corridor', 'text', 1],
                    ['load_quantity', 'Load Quantity', 'text', 1],
                    ['status', 'Status', 'status', 1],
                ];

            case 'expenses':
                return [
                    ['expense_date', 'Expense Date', 'date', 1],
                    ['category', 'Category', 'status', 1],
                    ['truck', 'Truck Plate', 'text', 1],
                    ['amount', 'Amount', 'currency', 1],
                    ['description', 'Description', 'text', 1],
                    ['status', 'Status', 'status', 1],
                ];

            case 'drivers':
                return [
                    ['name', 'Driver Full Name', 'text', 1],
                    ['phone', 'Phone Number', 'text', 1],
                    ['license_number', 'License Number', 'text', 1],
                    ['license_class', 'License Class', 'text', 1],
                    ['truck', 'Assigned Truck', 'text', 1],
                    ['status', 'Status', 'status', 1],
                ];

            case 'customers':
                return [
                    ['name', 'Client / Station Name', 'text', 1],
                    ['orders', 'Total Orders', 'number', 1],
                    ['outstanding', 'Outstanding Balance', 'currency', 1],
                    ['status', 'Credit Status', 'status', 1],
                ];

            default:
                return [];
        }
    }

    /**
     * Ensure column metadata exists for a given table
     */
    public static function ensureTableColumnsSeeded(string $table): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM table_columns_meta WHERE table_name = ?');
        $stmt->execute([$table]);
        $count = (int)$stmt->fetchColumn();

        if ($count === 0) {
            $defaults = self::getDefaultColumnsForTable($table);
            $ins = $pdo->prepare('INSERT OR IGNORE INTO table_columns_meta (
                table_name, column_key, display_label, data_type, is_visible, is_custom, sort_order, created_at
            ) VALUES (?, ?, ?, ?, ?, 0, ?, ?)');

            $order = 1;
            foreach ($defaults as $col) {
                $ins->execute([
                    $table,
                    $col[0],
                    $col[1],
                    $col[2],
                    $col[3],
                    $order++,
                    date('Y-m-d H:i:s')
                ]);
            }
        } else {
            // Ensure newly added default columns like diesel are seeded into existing installations
            if ($table === 'fleet_dispatches') {
                $ensureMeta = function($key, $label, $type, $vis, $order) use ($pdo) {
                    $c = $pdo->query("SELECT COUNT(*) FROM table_columns_meta WHERE table_name = 'fleet_dispatches' AND column_key = '{$key}'")->fetchColumn();
                    if ((int)$c === 0) {
                        $pdo->prepare("INSERT OR IGNORE INTO table_columns_meta (table_name, column_key, display_label, data_type, is_visible, is_custom, sort_order, created_at) VALUES ('fleet_dispatches', ?, ?, ?, ?, 0, ?, ?)")
                            ->execute([$key, $label, $type, $vis, $order, date('Y-m-d H:i:s')]);
                    }
                };

                $ensureMeta('diesel', 'Diesel Fuel Cost', 'currency', 1, 8);
                $ensureMeta('shortage_litres', 'Shortage Litres', 'number', 1, 6);
                $ensureMeta('unit_price', 'Product Unit Price', 'currency', 1, 7);
                $ensureMeta('diesel_litres', 'Diesel Litres', 'number', 0, 9);
                $ensureMeta('diesel_unit_price', 'Diesel Unit Price', 'currency', 0, 10);
            }
            if ($table === 'trucks') {
                $pdo->exec("UPDATE table_columns_meta SET is_visible = 0 WHERE table_name = 'trucks' AND column_key = 'model'");
            }
        }
    }

    /**
     * Get all column definitions for a table (ordered by sort_order)
     */
    public static function getTableColumns(string $table, bool $onlyVisible = false): array
    {
        self::ensureTableColumnsSeeded($table);
        $pdo = Database::connection();

        $sql = 'SELECT * FROM table_columns_meta WHERE table_name = ?';
        if ($onlyVisible) {
            $sql .= ' AND is_visible = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add a new column to a table (alters the physical table and records metadata)
     */
    public static function addColumn(string $table, string $displayLabel, string $dataType = 'text', string $defaultVal = ''): array
    {
        $pdo = Database::connection();

        // Generate sanitized column key
        $cleanKey = strtolower(trim($displayLabel));
        $cleanKey = preg_replace('/[^a-z0-9_]/', '_', $cleanKey);
        $cleanKey = trim(preg_replace('/_+/', '_', $cleanKey), '_');

        if ($cleanKey === '') {
            $cleanKey = 'col_' . rand(100, 999);
        }

        // Avoid collision with existing keys in table_columns_meta
        $check = $pdo->prepare('SELECT COUNT(*) FROM table_columns_meta WHERE table_name = ? AND column_key = ?');
        $check->execute([$table, $cleanKey]);
        if ((int)$check->fetchColumn() > 0) {
            $cleanKey .= '_' . rand(10, 99);
        }

        // Check if physical column already exists on the table
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $existingCols = [];
        if ($driver === 'sqlite') {
            $info = $pdo->query("PRAGMA table_info(`{$table}`)")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($info as $col) {
                $existingCols[] = strtolower($col['name']);
            }
        } else {
            $info = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($info as $col) {
                $existingCols[] = strtolower($col['Field']);
            }
        }

        // Determine SQL column type
        $sqlType = match ($dataType) {
            'number' => 'DECIMAL(12,2) DEFAULT 0',
            'currency' => 'DECIMAL(12,2) DEFAULT 0',
            'date' => 'VARCHAR(50) DEFAULT NULL',
            default => 'TEXT DEFAULT NULL',
        };

        // Physically alter table if column doesn't exist
        if (!in_array($cleanKey, $existingCols, true)) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$cleanKey}` {$sqlType}");
        }

        // Calculate sort order
        $maxOrderStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM table_columns_meta WHERE table_name = ?');
        $maxOrderStmt->execute([$table]);
        $nextOrder = (int)$maxOrderStmt->fetchColumn() + 1;

        // Insert metadata
        $ins = $pdo->prepare('INSERT INTO table_columns_meta (
            table_name, column_key, display_label, data_type, is_visible, is_custom, sort_order, created_at
        ) VALUES (?, ?, ?, ?, 1, 1, ?, ?)');
        $ins->execute([
            $table,
            $cleanKey,
            $displayLabel,
            $dataType,
            $nextOrder,
            date('Y-m-d H:i:s')
        ]);

        return [
            'table_name' => $table,
            'column_key' => $cleanKey,
            'display_label' => $displayLabel,
            'data_type' => $dataType,
            'is_visible' => 1,
            'is_custom' => 1,
            'sort_order' => $nextOrder
        ];
    }

    /**
     * Update column labels and visibility
     */
    public static function updateColumnSettings(string $table, array $columns): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE table_columns_meta SET 
            display_label = ?, 
            is_visible = ?, 
            sort_order = ? 
            WHERE table_name = ? AND column_key = ?');

        foreach ($columns as $order => $col) {
            $key = $col['key'] ?? '';
            $label = trim($col['label'] ?? '');
            $visible = !empty($col['visible']) ? 1 : 0;
            if ($key !== '' && $label !== '') {
                $stmt->execute([
                    $label,
                    $visible,
                    (int)($col['order'] ?? ($order + 1)),
                    $table,
                    $key
                ]);
            }
        }
    }

    /**
     * Delete a custom column (drops column from physical table and removes metadata)
     */
    public static function deleteColumn(string $table, string $columnKey): bool
    {
        $pdo = Database::connection();

        // Check that this is a custom column, not a protected system column
        $stmt = $pdo->prepare('SELECT is_custom FROM table_columns_meta WHERE table_name = ? AND column_key = ?');
        $stmt->execute([$table, $columnKey]);
        $meta = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$meta) {
            throw new Exception("Column '{$columnKey}' not found on table '{$table}'.");
        }

        if (empty($meta['is_custom'])) {
            throw new Exception("Core system column '{$columnKey}' cannot be deleted. You may hide it from display instead.");
        }

        // Drop physical column
        try {
            $pdo->exec("ALTER TABLE `{$table}` DROP COLUMN `{$columnKey}`");
        } catch (\Throwable $e) {
            // Ignore if already dropped or database driver limitation
        }

        // Delete metadata
        $del = $pdo->prepare('DELETE FROM table_columns_meta WHERE table_name = ? AND column_key = ?');
        $del->execute([$table, $columnKey]);

        return true;
    }

    /**
     * Wipe all rows from a table (Truncate / Reset)
     */
    public static function clearTableRecords(string $table): int
    {
        $pdo = Database::connection();

        // Verify table name is valid and allowed
        $isSystem = isset(self::SYSTEM_TABLES[$table]);
        $isCustom = (bool)$pdo->query("SELECT COUNT(*) FROM custom_tables WHERE table_key = " . $pdo->quote($table))->fetchColumn();

        if (!$isSystem && !$isCustom) {
            throw new Exception("Invalid table '{$table}' requested for record clearing.");
        }

        $countStmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
        $deletedCount = (int)$countStmt->fetchColumn();

        $pdo->exec("DELETE FROM `{$table}`");

        // Reset autoincrement in SQLite
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->exec("DELETE FROM sqlite_sequence WHERE name = " . $pdo->quote($table));
        }

        return $deletedCount;
    }

    /**
     * Create a brand new custom table with custom columns
     */
    public static function createCustomTable(string $displayName, string $description = '', string $icon = '📋', array $initialColumns = []): string
    {
        $pdo = Database::connection();

        // Generate sanitized table key
        $slug = strtolower(trim($displayName));
        $slug = preg_replace('/[^a-z0-9_]/', '_', $slug);
        $slug = trim(preg_replace('/_+/', '_', $slug), '_');
        $tableKey = 'custom_' . $slug;

        // Ensure uniqueness
        $check = $pdo->prepare('SELECT COUNT(*) FROM custom_tables WHERE table_key = ?');
        $check->execute([$tableKey]);
        if ((int)$check->fetchColumn() > 0) {
            $tableKey .= '_' . rand(10, 99);
        }

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $idSql = ($driver === 'sqlite') ? 'id INTEGER PRIMARY KEY AUTOINCREMENT' : 'id INT AUTO_INCREMENT PRIMARY KEY';

        // Physical column definitions
        $colDefs = [];
        $validatedColumns = [];

        if (empty($initialColumns)) {
            $initialColumns = [
                ['label' => 'Item Name / Reference', 'type' => 'text'],
                ['label' => 'Record Date', 'type' => 'date'],
                ['label' => 'Category / Type', 'type' => 'status'],
                ['label' => 'Quantity / Value', 'type' => 'number'],
                ['label' => 'Notes', 'type' => 'text'],
            ];
        }

        foreach ($initialColumns as $c) {
            $label = trim($c['label'] ?? '');
            if ($label === '') continue;

            $type = $c['type'] ?? 'text';
            $key = strtolower(trim($label));
            $key = preg_replace('/[^a-z0-9_]/', '_', $key);
            $key = trim(preg_replace('/_+/', '_', $key), '_');
            if (isset($validatedColumns[$key])) {
                $key .= '_' . rand(1, 9);
            }

            $sqlType = match ($type) {
                'number' => 'DECIMAL(12,2) DEFAULT 0',
                'currency' => 'DECIMAL(12,2) DEFAULT 0',
                'date' => 'VARCHAR(50) DEFAULT NULL',
                default => 'TEXT DEFAULT NULL',
            };

            $colDefs[] = "`{$key}` {$sqlType}";
            $validatedColumns[$key] = [
                'key' => $key,
                'label' => $label,
                'type' => $type
            ];
        }

        $createSql = "CREATE TABLE `{$tableKey}` (
            {$idSql},
            " . implode(",\n", $colDefs) . ",
            created_at VARCHAR(50) DEFAULT NULL,
            updated_at VARCHAR(50) DEFAULT NULL
        )";

        $pdo->exec($createSql);

        // Record custom table registry
        $insTable = $pdo->prepare('INSERT INTO custom_tables (table_key, display_name, description, icon, created_at) VALUES (?, ?, ?, ?, ?)');
        $insTable->execute([
            $tableKey,
            $displayName,
            $description ?: "Custom dataset for {$displayName}",
            $icon ?: '📋',
            date('Y-m-d H:i:s')
        ]);

        // Record columns in table_columns_meta
        $insCol = $pdo->prepare('INSERT INTO table_columns_meta (
            table_name, column_key, display_label, data_type, is_visible, is_custom, sort_order, created_at
        ) VALUES (?, ?, ?, ?, 1, 1, ?, ?)');

        $order = 1;
        foreach ($validatedColumns as $col) {
            $insCol->execute([
                $tableKey,
                $col['key'],
                $col['label'],
                $col['type'],
                $order++,
                date('Y-m-d H:i:s')
            ]);
        }

        return $tableKey;
    }

    /**
     * Drop a custom table completely
     */
    public static function dropCustomTable(string $tableKey): bool
    {
        $pdo = Database::connection();

        // Security check: only custom_ tables can be dropped
        if (!str_starts_with($tableKey, 'custom_')) {
            throw new Exception("Protected system table '{$tableKey}' cannot be deleted.");
        }

        // Drop physical table
        $pdo->exec("DROP TABLE IF EXISTS `{$tableKey}`");

        // Clean registry and metadata
        $pdo->prepare("DELETE FROM custom_tables WHERE table_key = ?")->execute([$tableKey]);
        $pdo->prepare("DELETE FROM table_columns_meta WHERE table_name = ?")->execute([$tableKey]);

        return true;
    }

    /**
     * Get all registered custom tables with row counts and column stats
     */
    public static function getCustomTables(): array
    {
        $pdo = Database::connection();
        $tables = $pdo->query('SELECT * FROM custom_tables ORDER BY display_name ASC')->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tables as &$t) {
            try {
                $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$t['table_key']}`")->fetchColumn();
                $t['row_count'] = $count;
            } catch (\Throwable $e) {
                $t['row_count'] = 0;
            }

            $colCountStmt = $pdo->prepare('SELECT COUNT(*) FROM table_columns_meta WHERE table_name = ?');
            $colCountStmt->execute([$t['table_key']]);
            $t['col_count'] = (int)$colCountStmt->fetchColumn();
        }

        return $tables;
    }

    /**
     * Get metadata for a specific custom table
     */
    public static function getCustomTable(string $tableKey): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM custom_tables WHERE table_key = ? LIMIT 1');
        $stmt->execute([$tableKey]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }
}
