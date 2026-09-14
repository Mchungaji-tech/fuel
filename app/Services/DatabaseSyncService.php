<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

class DatabaseSyncService
{
    /**
     * Synchronize all data between MySQL (online) and SQLite (offline).
     *
     * @return array Sync result summary
     */
    public static function syncAll(): array
    {
        try {
            $mysql = Database::getMysqlConnection();
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => 'MySQL is currently offline or unreachable: ' . $e->getMessage(),
                'active_driver' => Database::getActiveDriver(),
            ];
        }

        if (!$mysql) {
            return [
                'success' => false,
                'error' => 'Could not establish connection to MySQL server.',
            ];
        }

        $sqlite = Database::getSqliteConnection();

        $tablesToSync = [
            ['table' => 'users', 'key' => 'email', 'composite' => []],
            ['table' => 'trucks', 'key' => 'plate_number', 'composite' => []],
            ['table' => 'drivers', 'key' => 'name', 'composite' => []],
            ['table' => 'customers', 'key' => 'name', 'composite' => []],
            ['table' => 'products', 'key' => 'code', 'composite' => []],
            ['table' => 'fleet_dispatches', 'key' => 'trip_number', 'composite' => []],
            ['table' => 'trips', 'key' => 'trip_number', 'composite' => []],
            ['table' => 'fleet_diesel_logs', 'key' => null, 'composite' => ['trip_number', 'truck', 'fuel_date', 'litres', 'local_total_cost']],
            ['table' => 'route_mileage_rates', 'key' => null, 'composite' => ['origin', 'destination']],
            ['table' => 'financial_records', 'key' => null, 'composite' => ['entry_date', 'category', 'amount_in', 'amount_out', 'reason']],
            ['table' => 'expenses', 'key' => null, 'composite' => ['expense_date', 'expense_title', 'amount']],
            ['table' => 'driver_salaries', 'key' => null, 'composite' => ['driver_name', 'period_reference', 'amount']],
            ['table' => 'invoices', 'key' => 'invoice_number', 'composite' => []],
            ['table' => 'documents', 'key' => 'file_name', 'composite' => []],
            ['table' => 'settings', 'key' => 'setting_key', 'composite' => []],
            ['table' => 'table_columns_meta', 'key' => null, 'composite' => ['table_name', 'column_key']],
            ['table' => 'custom_tables', 'key' => 'table_key', 'composite' => []],
        ];

        $totalUploaded = 0;
        $totalDownloaded = 0;
        $results = [];

        foreach ($tablesToSync as $t) {
            $tableName = $t['table'];
            $key = $t['key'];
            $composite = $t['composite'];

            try {
                $stat = self::syncTable($sqlite, $mysql, $tableName, $key, $composite);
                $totalUploaded += $stat['uploaded'];
                $totalDownloaded += $stat['downloaded'];
                $results[$tableName] = $stat;
            } catch (Throwable $e) {
                error_log("DatabaseSyncService error on table {$tableName}: " . $e->getMessage());
                $results[$tableName] = ['error' => $e->getMessage(), 'uploaded' => 0, 'downloaded' => 0];
            }
        }

        if (function_exists('log_audit') && ($totalUploaded > 0 || $totalDownloaded > 0)) {
            log_audit(
                'Database Sync',
                'DB_SYNC_SUCCESS',
                "Synchronized MySQL & SQLite: {$totalUploaded} uploaded to MySQL, {$totalDownloaded} mirrored to SQLite"
            );
        }

        return [
            'success' => true,
            'total_uploaded' => $totalUploaded,
            'total_downloaded' => $totalDownloaded,
            'details' => $results,
            'message' => "Database synchronized successfully ({$totalUploaded} uploaded to MySQL, {$totalDownloaded} mirrored to SQLite).",
        ];
    }

    /**
     * Record a deletion event so it propagates across both MySQL and SQLite and is never re-inserted.
     */
    public static function recordDeletion(string $table, string $recordKey): void
    {
        if (trim($recordKey) === '') return;
        $now = date('Y-m-d H:i:s');

        // 1. Record in SQLite
        try {
            $sqlite = Database::getSqliteConnection();
            if ($sqlite) {
                $stmt = $sqlite->prepare("INSERT OR REPLACE INTO sync_deletions (table_name, record_key, deleted_at) VALUES (?, ?, ?)");
                $stmt->execute([$table, $recordKey, $now]);
            }
        } catch (\Throwable $e) {}

        // 2. Record in MySQL if online
        try {
            $mysql = Database::getMysqlConnection();
            if ($mysql) {
                $stmt = $mysql->prepare("INSERT INTO sync_deletions (table_name, record_key, deleted_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE deleted_at = VALUES(deleted_at)");
                $stmt->execute([$table, $recordKey, $now]);
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Clear a deletion tombstone if a record with this key is legitimately created or re-added.
     */
    public static function clearDeletion(string $table, string $recordKey): void
    {
        if (trim($recordKey) === '') return;
        try {
            $sqlite = Database::getSqliteConnection();
            if ($sqlite) {
                $stmt = $sqlite->prepare("DELETE FROM sync_deletions WHERE table_name = ? AND record_key = ?");
                $stmt->execute([$table, $recordKey]);
            }
        } catch (\Throwable $e) {}
        try {
            $mysql = Database::getMysqlConnection();
            if ($mysql) {
                $stmt = $mysql->prepare("DELETE FROM sync_deletions WHERE table_name = ? AND record_key = ?");
                $stmt->execute([$table, $recordKey]);
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Bidirectional sync for a single table with full deletion propagation.
     */
    protected static function syncTable(PDO $sqlite, PDO $mysql, string $table, ?string $key, array $composite): array
    {
        $uploaded = 0;
        $downloaded = 0;

        // 1. Fetch deleted keys for this table from both SQLite and MySQL
        $sqDeleted = [];
        try {
            $sqDeleted = $sqlite->query("SELECT record_key FROM sync_deletions WHERE table_name = " . $sqlite->quote($table))->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (\Throwable $e) {}

        $myDeleted = [];
        try {
            $myDeleted = $mysql->query("SELECT record_key FROM sync_deletions WHERE table_name = " . $mysql->quote($table))->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (\Throwable $e) {}

        $allDeleted = array_flip(array_unique(array_merge($sqDeleted, $myDeleted)));

        // Synchronize sync_deletions between SQLite and MySQL
        foreach (array_keys($allDeleted) as $delKey) {
            try {
                $sqlite->prepare("INSERT OR IGNORE INTO sync_deletions (table_name, record_key, deleted_at) VALUES (?, ?, ?)")
                    ->execute([$table, $delKey, date('Y-m-d H:i:s')]);
            } catch (\Throwable $e) {}
            try {
                $mysql->prepare("INSERT IGNORE INTO sync_deletions (table_name, record_key, deleted_at) VALUES (?, ?, ?)")
                    ->execute([$table, $delKey, date('Y-m-d H:i:s')]);
            } catch (\Throwable $e) {}
        }

        // Fetch all rows from SQLite
        $sqRows = $sqlite->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        // Fetch all rows from MySQL
        $myRows = $mysql->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);

        // Build key index function
        $rowKey = function (array $r) use ($key, $composite) {
            if ($key && isset($r[$key])) {
                return (string) $r[$key];
            }
            if (!empty($composite)) {
                $parts = [];
                foreach ($composite as $c) {
                    $val = $r[$c] ?? '';
                    if (is_numeric($val)) {
                        $val = (string) (float) $val;
                    }
                    $parts[] = (string) $val;
                }
                return implode('::', $parts);
            }
            return (string) ($r['id'] ?? uniqid());
        };

        $sqMap = [];
        foreach ($sqRows as $r) {
            $k = $rowKey($r);
            // If marked deleted, remove from SQLite
            if (isset($allDeleted[$k])) {
                self::deleteRow($sqlite, $table, $k, $key, $composite, $r);
            } else {
                $sqMap[$k] = $r;
            }
        }

        $myMap = [];
        foreach ($myRows as $r) {
            $k = $rowKey($r);
            // If marked deleted, remove from MySQL
            if (isset($allDeleted[$k])) {
                self::deleteRow($mysql, $table, $k, $key, $composite, $r);
            } else {
                $myMap[$k] = $r;
            }
        }

        // Process records found in SQLite
        foreach ($sqMap as $k => $sqRow) {
            if (isset($allDeleted[$k])) continue;

            if (!isset($myMap[$k])) {
                // Exists in SQLite but not MySQL -> Upload to MySQL
                self::insertRow($mysql, $table, $sqRow);
                $uploaded++;
            } else {
                // Exists in both! Check for differences and resolve
                $myRow = $myMap[$k];
                if (self::hasDifferences($sqRow, $myRow)) {
                    $sqTs = self::getRowTimestamp($sqRow);
                    $myTs = self::getRowTimestamp($myRow);

                    if ($sqTs > $myTs) {
                        // SQLite row is newer -> Update MySQL
                        self::updateRow($mysql, $table, $sqRow, $key, $composite);
                        $uploaded++;
                    } else {
                        // MySQL is newer or tie -> Update SQLite
                        self::updateRow($sqlite, $table, $myRow, $key, $composite);
                        $downloaded++;
                    }
                }
            }
        }

        // Process records found in MySQL but not in SQLite
        foreach ($myMap as $k => $myRow) {
            if (isset($allDeleted[$k])) continue;

            if (!isset($sqMap[$k])) {
                // Exists in MySQL but not SQLite -> Download to SQLite
                self::insertRow($sqlite, $table, $myRow);
                $downloaded++;
            }
        }

        return ['uploaded' => $uploaded, 'downloaded' => $downloaded];
    }

    /**
     * Delete a row from a target PDO connection.
     */
    protected static function deleteRow(PDO $pdo, string $table, string $k, ?string $key, array $composite, array $row): void
    {
        try {
            if ($key && isset($row[$key])) {
                $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE `{$key}` = ?");
                $stmt->execute([$row[$key]]);
            } elseif (!empty($composite)) {
                $clauses = [];
                $params = [];
                foreach ($composite as $c) {
                    $clauses[] = "`{$c}` = ?";
                    $params[] = $row[$c] ?? '';
                }
                $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE " . implode(' AND ', $clauses));
                $stmt->execute($params);
            } elseif (isset($row['id'])) {
                $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE `id` = ?");
                $stmt->execute([$row['id']]);
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Extract a comparable UNIX timestamp from common record fields.
     */
    protected static function getRowTimestamp(array $r): int
    {
        foreach (['updated_at', 'last_active_at', 'created_at', 'dispatch_date', 'expense_date'] as $col) {
            if (!empty($r[$col])) {
                $ts = strtotime((string) $r[$col]);
                if ($ts !== false && $ts > 0) {
                    return $ts;
                }
            }
        }
        return 0;
    }

    /**
     * Check if two rows have actual data differences.
     */
    protected static function hasDifferences(array $a, array $b): bool
    {
        foreach ($a as $col => $val) {
            if ($col === 'id') continue;
            if (array_key_exists($col, $b)) {
                $targetVal = $b[$col];

                // Both null or both empty string
                if (($val === null || $val === '') && ($targetVal === null || $targetVal === '')) {
                    continue;
                }

                // Numeric equivalence (e.g. 0 vs "0.00" in DECIMAL fields)
                if (is_numeric($val) && is_numeric($targetVal)) {
                    if ((float)$val == (float)$targetVal) {
                        continue;
                    }
                }

                if ((string)$val !== (string)$targetVal) {
                    return true;
                }
            }
        }

        return false;
    }

    protected static array $columnsCache = [];

    /**
     * Get list of column names for a table on the specified PDO connection.
     */
    protected static function getTableColumns(PDO $pdo, string $table): array
    {
        $hash = spl_object_hash($pdo) . '_' . $table;
        if (isset(self::$columnsCache[$hash])) {
            return self::$columnsCache[$hash];
        }

        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $cols = $pdo->query("PRAGMA table_info(`{$table}`)")->fetchAll(PDO::FETCH_COLUMN, 1);
            } else {
                $cols = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN, 0);
            }
            self::$columnsCache[$hash] = $cols ?: [];
            return self::$columnsCache[$hash];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Ensure all columns present in data exist in the target table.
     * If a custom column is missing, dynamically add it (ALTER TABLE) to avoid sync failure.
     */
    protected static function prepareDataForTarget(PDO $pdo, string $table, array $data): array
    {
        $dataCopy = $data;
        unset($dataCopy['id']);

        if (empty($dataCopy)) {
            return [];
        }

        $existingCols = self::getTableColumns($pdo, $table);
        if (empty($existingCols)) {
            return $dataCopy;
        }

        $filtered = [];
        $hash = spl_object_hash($pdo) . '_' . $table;

        foreach ($dataCopy as $col => $val) {
            if (in_array($col, $existingCols, true)) {
                $filtered[$col] = $val;
            } else {
                // Column missing on target table! Try to auto-create it as TEXT so custom columns sync seamlessly
                try {
                    $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` TEXT NULL");
                    self::$columnsCache[$hash][] = $col;
                    $existingCols[] = $col;
                    $filtered[$col] = $val;
                } catch (\Throwable $e) {
                    // If alter failed (e.g. invalid column name or permission), omit column to avoid crashing
                }
            }
        }

        return $filtered;
    }

    /**
     * Insert row into target database.
     */
    protected static function insertRow(PDO $pdo, string $table, array $data): void
    {
        $filtered = self::prepareDataForTarget($pdo, $table, $data);
        if (empty($filtered)) {
            return;
        }

        $columns = array_keys($filtered);
        $placeholders = array_fill(0, count($columns), '?');

        $colsSql = implode('`, `', $columns);
        $placeSql = implode(', ', $placeholders);

        $sql = "INSERT INTO `{$table}` (`{$colsSql}`) VALUES ({$placeSql})";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($filtered));
    }

    /**
     * Update row in target database.
     */
    protected static function updateRow(PDO $pdo, string $table, array $data, ?string $key, array $composite): void
    {
        $filtered = self::prepareDataForTarget($pdo, $table, $data);
        if (empty($filtered)) {
            return;
        }

        $setParts = [];
        $values = [];
        foreach ($filtered as $c => $v) {
            $setParts[] = "`{$c}` = ?";
            $values[] = $v;
        }

        $whereParts = [];
        if ($key && isset($data[$key])) {
            $whereParts[] = "`{$key}` = ?";
            $values[] = $data[$key];
        } elseif (!empty($composite)) {
            foreach ($composite as $c) {
                $whereParts[] = "`{$c}` = ?";
                $values[] = $data[$c] ?? '';
            }
        } else {
            return;
        }

        $setSql = implode(', ', $setParts);
        $whereSql = implode(' AND ', $whereParts);

        $sql = "UPDATE `{$table}` SET {$setSql} WHERE {$whereSql}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
    }
}
