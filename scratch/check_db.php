<?php
require __DIR__ . '/../bootstrap/app.php';

$pdo = App\Core\Database::connection();

echo "=== TABLES ===\n";
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);

echo "\n=== AUDIT LOGS ===\n";
$logs = $pdo->query('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
foreach ($logs as $l) {
    echo "{$l['created_at']} | {$l['category']} | {$l['action']} | {$l['description']}\n";
}

echo "\n=== CUSTOM TABLES ===\n";
try {
    $ct = $pdo->query('SELECT * FROM custom_tables')->fetchAll(PDO::FETCH_ASSOC);
    print_r($ct);
} catch (Exception $e) {
    echo $e->getMessage();
}

echo "\n=== TRUCKS ===\n";
$trucks = $pdo->query('SELECT * FROM trucks')->fetchAll(PDO::FETCH_ASSOC);
print_r($trucks);

echo "\n=== ALL FLEET DISPATCHES ===\n";
$dispatches = $pdo->query('SELECT * FROM fleet_dispatches')->fetchAll(PDO::FETCH_ASSOC);
print_r($dispatches);

echo "\n=== ALL TABLE COLUMNS META ===\n";
print_r($pdo->query("SELECT id, table_name, column_key, display_label, is_visible, is_custom FROM table_columns_meta WHERE table_name='fleet_dispatches'")->fetchAll(PDO::FETCH_ASSOC));




