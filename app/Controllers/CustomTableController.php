<?php

namespace App\Controllers;

use App\Core\Database;
use App\Services\TableSchemaService;
use App\Services\ExcelService;
use PDO;
use Exception;

class CustomTableController
{
    /**
     * Hub listing all datasets (both core system tables and custom tables)
     */
    public function index(): string
    {
        $pdo = Database::connection();
        $customTables = TableSchemaService::getCustomTables();

        $systemTables = [];
        foreach (TableSchemaService::SYSTEM_TABLES as $key => $title) {
            $count = 0;
            try {
                $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$key}`")->fetchColumn();
            } catch (\Throwable $e) {}

            $colCountStmt = $pdo->prepare('SELECT COUNT(*) FROM table_columns_meta WHERE table_name = ?');
            $colCountStmt->execute([$key]);
            $colCount = (int)$colCountStmt->fetchColumn();

            $systemTables[] = [
                'table_key' => $key,
                'display_name' => $title,
                'row_count' => $count,
                'col_count' => $colCount,
                'url' => match($key) {
                    'fleet_dispatches' => '/fleet',
                    'trucks' => '/trucks',
                    'trips' => '/trips',
                    'expenses' => '/expenses',
                    'drivers' => '/drivers',
                    'customers' => '/customers',
                    'products' => '/products',
                    default => '/fleet',
                },
                'icon' => match($key) {
                    'fleet_dispatches' => '🚚',
                    'trucks' => '🚛',
                    'trips' => '🗺️',
                    'expenses' => '💳',
                    'drivers' => '🧑‍✈️',
                    'customers' => '🏢',
                    'products' => '⛽',
                    default => '📋',
                }
            ];
        }

        return view('custom_tables.index', [
            'title' => 'Custom Tables & Datasets Hub — Sarura Fuel',
            'customTables' => $customTables,
            'systemTables' => $systemTables,
        ]);
    }

    /**
     * View an individual custom table
     */
    public function view(string $tableKey): string
    {
        $tableInfo = TableSchemaService::getCustomTable($tableKey);
        if (!$tableInfo) {
            flash('custom_error', "Custom table '{$tableKey}' not found.");
            redirect('/custom-tables');
        }

        $columns = TableSchemaService::getTableColumns($tableKey, false);
        $visibleColumns = array_filter($columns, fn($c) => !empty($c['is_visible']));

        $pdo = Database::connection();
        $search = trim($_GET['search'] ?? '');

        $sql = "SELECT * FROM `{$tableKey}` WHERE 1=1";
        $params = [];

        if ($search !== '' && !empty($visibleColumns)) {
            $searchParts = [];
            foreach ($visibleColumns as $col) {
                $searchParts[] = "`{$col['column_key']}` LIKE ?";
                $params[] = "%{$search}%";
            }
            $sql .= ' AND (' . implode(' OR ', $searchParts) . ')';
        }

        $sql .= " ORDER BY id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return view('custom_tables.view', [
            'title' => $tableInfo['display_name'] . ' — Custom Table',
            'tableInfo' => $tableInfo,
            'columns' => $columns,
            'visibleColumns' => array_values($visibleColumns),
            'records' => $records,
            'search' => $search,
        ]);
    }

    /**
     * Store new record in custom table
     */
    public function store(string $tableKey): void
    {
        $tableInfo = TableSchemaService::getCustomTable($tableKey);
        if (!$tableInfo) {
            flash('custom_error', "Custom table '{$tableKey}' not found.");
            redirect('/custom-tables');
        }

        $columns = TableSchemaService::getTableColumns($tableKey, false);
        $pdo = Database::connection();

        $colKeys = [];
        $placeholders = [];
        $values = [];

        foreach ($columns as $col) {
            $key = $col['column_key'];
            $val = $_POST[$key] ?? null;

            if ($col['data_type'] === 'date' && !empty($val)) {
                $val = ExcelService::normalizeDate($val);
            }

            $colKeys[] = "`{$key}`";
            $placeholders[] = '?';
            $values[] = $val;
        }

        $colKeys[] = '`created_at`';
        $placeholders[] = '?';
        $values[] = date('Y-m-d H:i:s');

        $sql = "INSERT INTO `{$tableKey}` (" . implode(',', $colKeys) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        log_audit('Custom Datasets', 'ADD_RECORD', "Added new record to `{$tableKey}`");
        flash('custom_success', "New record added successfully!");
        redirect('/custom-tables/' . $tableKey);
    }

    /**
     * Inline update a cell in custom table
     */
    public function inlineUpdate(string $tableKey): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $field = trim($_POST['field'] ?? '');
        $value = trim($_POST['value'] ?? '');

        if (!$id || $field === '') {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
            exit;
        }

        // Verify field exists
        $columns = TableSchemaService::getTableColumns($tableKey, false);
        $validCol = null;
        foreach ($columns as $c) {
            if ($c['column_key'] === $field) {
                $validCol = $c;
                break;
            }
        }

        if (!$validCol) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => "Unknown column '{$field}'"]);
            exit;
        }

        if ($validCol['data_type'] === 'date' && $value !== '') {
            $value = ExcelService::normalizeDate($value);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("UPDATE `{$tableKey}` SET `{$field}` = ?, updated_at = ? WHERE id = ?");
        $stmt->execute([$value, date('Y-m-d H:i:s'), $id]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'updated_value' => $value]);
        exit;
    }

    /**
     * Delete a record from custom table
     */
    public function deleteRecord(string $tableKey, string $id): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("DELETE FROM `{$tableKey}` WHERE id = ?");
        $stmt->execute([(int)$id]);

        log_audit('Custom Datasets', 'DELETE_RECORD', "Deleted record #{$id} from `{$tableKey}`");
        flash('custom_success', "Record #{$id} deleted successfully.");
        redirect('/custom-tables/' . $tableKey);
    }

    /**
     * Export custom table to .xlsx, .xls, or .csv
     */
    public function export(string $tableKey): void
    {
        $tableInfo = TableSchemaService::getCustomTable($tableKey);
        if (!$tableInfo) {
            redirect('/custom-tables');
        }

        $format = strtolower($_GET['format'] ?? 'xlsx');
        $visibleColumns = TableSchemaService::getTableColumns($tableKey, true);

        $headers = array_column($visibleColumns, 'display_label');
        $colKeys = array_column($visibleColumns, 'column_key');

        $pdo = Database::connection();
        $records = $pdo->query("SELECT * FROM `{$tableKey}` ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

        $rows = [];
        foreach ($records as $r) {
            $rowData = [];
            foreach ($colKeys as $k) {
                $rowData[] = $r[$k] ?? '';
            }
            $rows[] = $rowData;
        }

        $filenameBase = $tableKey . '_' . date('Y-m-d');
        if ($format === 'xls') {
            ExcelService::exportXls($filenameBase . '.xls', $headers, $rows, $tableInfo['display_name']);
        } elseif ($format === 'csv') {
            ExcelService::exportCsv($filenameBase . '.csv', $headers, $rows);
        } else {
            ExcelService::exportXlsx($filenameBase . '.xlsx', $headers, $rows, $tableInfo['display_name']);
        }
    }

    /**
     * Download import template for custom table
     */
    public function template(string $tableKey): void
    {
        $tableInfo = TableSchemaService::getCustomTable($tableKey);
        if (!$tableInfo) {
            redirect('/custom-tables');
        }

        $format = strtolower($_GET['format'] ?? 'xlsx');
        $visibleColumns = TableSchemaService::getTableColumns($tableKey, true);

        $headers = array_column($visibleColumns, 'display_label');
        $sampleRow = [];
        foreach ($visibleColumns as $c) {
            $sampleRow[] = match($c['data_type']) {
                'number' => '100',
                'currency' => '250.00',
                'date' => date('Y-m-d'),
                'status' => 'Active',
                default => 'Sample text',
            };
        }

        $filenameBase = $tableKey . '_import_template';
        if ($format === 'xls') {
            ExcelService::exportXls($filenameBase . '.xls', $headers, [$sampleRow], 'Template');
        } elseif ($format === 'csv') {
            ExcelService::exportCsv($filenameBase . '.csv', $headers, [$sampleRow]);
        } else {
            ExcelService::exportXlsx($filenameBase . '.xlsx', $headers, [$sampleRow], 'Template');
        }
    }

    /**
     * Import spreadsheet into custom table
     */
    public function import(string $tableKey): void
    {
        $tableInfo = TableSchemaService::getCustomTable($tableKey);
        if (!$tableInfo) {
            redirect('/custom-tables');
        }

        $fileInfo = $_FILES['spreadsheet_file'] ?? null;
        if (empty($fileInfo['tmp_name']) || !file_exists($fileInfo['tmp_name'])) {
            flash('custom_error', 'Please select a valid spreadsheet file (.xlsx, .xls, .csv).');
            redirect('/custom-tables/' . $tableKey);
        }

        $origName = $fileInfo['name'] ?? 'upload.csv';
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        try {
            $parsedRows = ExcelService::importFile($fileInfo['tmp_name'], $origName);
        } catch (\Throwable $e) {
            flash('custom_error', 'Error reading spreadsheet: ' . $e->getMessage());
            redirect('/custom-tables/' . $tableKey);
        }

        if (empty($parsedRows)) {
            flash('custom_error', 'The spreadsheet contains no rows.');
            redirect('/custom-tables/' . $tableKey);
        }

        $header = array_shift($parsedRows);
        $cleanHeaders = array_map(function ($h) {
            return strtolower(trim(str_replace([' ', '-', '(', ')', '/', '\\', '.'], '_', (string)$h)));
        }, $header);

        $visibleColumns = TableSchemaService::getTableColumns($tableKey, true);
        $pdo = Database::connection();
        $importedCount = 0;

        foreach ($parsedRows as $row) {
            if (empty(array_filter($row, fn($v) => $v !== null && $v !== ''))) {
                continue;
            }

            $data = [];
            foreach ($cleanHeaders as $idx => $key) {
                $data[$key] = $row[$idx] ?? '';
            }

            $colKeys = [];
            $placeholders = [];
            $values = [];

            foreach ($visibleColumns as $col) {
                $key = $col['column_key'];
                // Check key matches directly or with clean header match
                $cleanColKey = strtolower(str_replace([' ', '-'], '_', $col['display_label']));
                $val = $data[$key] ?? $data[$cleanColKey] ?? '';

                if ($col['data_type'] === 'date' && !empty($val)) {
                    $val = ExcelService::normalizeDate($val);
                }

                $colKeys[] = "`{$key}`";
                $placeholders[] = '?';
                $values[] = $val;
            }

            $colKeys[] = '`created_at`';
            $placeholders[] = '?';
            $values[] = date('Y-m-d H:i:s');

            $sql = "INSERT INTO `{$tableKey}` (" . implode(',', $colKeys) . ") VALUES (" . implode(',', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            $importedCount++;
        }

        log_audit('Custom Datasets', 'IMPORT_SPREADSHEET', "Imported {$importedCount} records into `{$tableKey}`");
        flash('custom_success', "Successfully imported {$importedCount} records into {$tableInfo['display_name']}!");
        redirect('/custom-tables/' . $tableKey);
    }
}
