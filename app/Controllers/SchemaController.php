<?php

namespace App\Controllers;

use App\Services\TableSchemaService;
use Exception;

class SchemaController
{
    /**
     * Update column display labels, visibility toggles, and sort order
     */
    public function updateColumns(): void
    {
        $table = trim($_POST['table_name'] ?? '');
        $redirectUrl = $_POST['redirect_to'] ?? ('/custom-tables/' . $table);

        if ($table === '') {
            flash('schema_error', 'Invalid table name provided.');
            redirect($redirectUrl);
        }

        $colsData = $_POST['columns'] ?? [];
        if (!is_array($colsData)) {
            $colsData = [];
        }

        try {
            TableSchemaService::updateColumnSettings($table, $colsData);
            log_audit('Schema Management', 'UPDATE_COLUMNS', "Updated column settings and visibility for table {$table}");
            flash('schema_success', "Table columns and visibility settings saved successfully!");
        } catch (Exception $e) {
            flash('schema_error', 'Error updating columns: ' . $e->getMessage());
        }

        redirect($redirectUrl);
    }

    /**
     * Add a new custom column to a table
     */
    public function addColumn(): void
    {
        $table = trim($_POST['table_name'] ?? '');
        $redirectUrl = $_POST['redirect_to'] ?? ('/custom-tables/' . $table);
        $colLabel = trim($_POST['column_label'] ?? '');
        $dataType = trim($_POST['data_type'] ?? 'text');
        $defaultVal = trim($_POST['default_value'] ?? '');

        if ($table === '' || $colLabel === '') {
            flash('schema_error', 'Column title and table name are required.');
            redirect($redirectUrl);
        }

        try {
            $newCol = TableSchemaService::addColumn($table, $colLabel, $dataType, $defaultVal);
            log_audit('Schema Management', 'ADD_COLUMN', "Added column '{$newCol['display_label']}' ({$dataType}) to table {$table}");
            flash('schema_success', "Successfully added column '{$colLabel}' to table {$table}!");
        } catch (Exception $e) {
            flash('schema_error', 'Error adding column: ' . $e->getMessage());
        }

        redirect($redirectUrl);
    }

    /**
     * Delete a custom column from a table
     */
    public function deleteColumn(): void
    {
        $table = trim($_POST['table_name'] ?? '');
        $colKey = trim($_POST['column_key'] ?? '');
        $redirectUrl = $_POST['redirect_to'] ?? ('/custom-tables/' . $table);

        if ($table === '' || $colKey === '') {
            flash('schema_error', 'Invalid column or table name.');
            redirect($redirectUrl);
        }

        try {
            TableSchemaService::deleteColumn($table, $colKey);
            log_audit('Schema Management', 'DELETE_COLUMN', "Deleted custom column '{$colKey}' from table {$table}");
            flash('schema_success', "Successfully deleted column '{$colKey}'.");
        } catch (Exception $e) {
            flash('schema_error', 'Cannot delete column: ' . $e->getMessage());
        }

        redirect($redirectUrl);
    }

    /**
     * Clear / truncate all records from a table
     */
    public function clearTable(): void
    {
        $table = trim($_POST['table_name'] ?? '');
        $confirm = trim($_POST['confirm_table_name'] ?? '');
        $redirectUrl = $_POST['redirect_to'] ?? ('/custom-tables/' . $table);

        if ($table === '' || strtolower($confirm) !== strtolower($table)) {
            flash('schema_error', "Confirmation failed. You must type the exact table name '{$table}' to clear its records.");
            redirect($redirectUrl);
        }

        try {
            $deleted = TableSchemaService::clearTableRecords($table);
            log_audit('Data Reset', 'CLEAR_TABLE', "Cleared {$deleted} records from table {$table}");
            flash('schema_success', "Successfully cleared {$deleted} records from table {$table}. The table is now empty and ready.");
        } catch (Exception $e) {
            flash('schema_error', 'Error clearing table: ' . $e->getMessage());
        }

        redirect($redirectUrl);
    }

    /**
     * Create a brand new custom table
     */
    public function createTable(): void
    {
        $displayName = trim($_POST['display_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? '📋');

        if ($displayName === '') {
            flash('schema_error', 'Table name is required.');
            redirect('/custom-tables');
        }

        // Parse initial columns from inputs
        $colLabels = $_POST['col_labels'] ?? [];
        $colTypes = $_POST['col_types'] ?? [];
        $initialCols = [];

        foreach ($colLabels as $idx => $lbl) {
            $lbl = trim($lbl);
            if ($lbl !== '') {
                $initialCols[] = [
                    'label' => $lbl,
                    'type' => $colTypes[$idx] ?? 'text'
                ];
            }
        }

        try {
            $tableKey = TableSchemaService::createCustomTable($displayName, $description, $icon, $initialCols);
            log_audit('Custom Datasets', 'CREATE_TABLE', "Created new custom table {$displayName} (`{$tableKey}`)");
            flash('schema_success', "Created new custom table '{$displayName}' successfully!");
            redirect('/custom-tables/' . $tableKey);
        } catch (Exception $e) {
            flash('schema_error', 'Error creating table: ' . $e->getMessage());
            redirect('/custom-tables');
        }
    }

    /**
     * Delete an entire custom table
     */
    public function dropTable(): void
    {
        $tableKey = trim($_POST['table_key'] ?? '');
        $confirm = trim($_POST['confirm_table_key'] ?? '');

        if ($tableKey === '' || strtolower($confirm) !== strtolower($tableKey)) {
            flash('schema_error', "Confirmation failed. You must type the exact table name '{$tableKey}' to delete it.");
            redirect('/custom-tables');
        }

        try {
            TableSchemaService::dropCustomTable($tableKey);
            log_audit('Custom Datasets', 'DROP_TABLE', "Deleted custom table `{$tableKey}`");
            flash('schema_success', "Successfully deleted custom table `{$tableKey}`.");
        } catch (Exception $e) {
            flash('schema_error', 'Error deleting table: ' . $e->getMessage());
        }

        redirect('/custom-tables');
    }
}
