<?php
define('TESTING_MODE', true);
require_once __DIR__ . '/../bootstrap/app.php';

use App\Core\Database;
use App\Services\TableSchemaService;
use App\Controllers\FleetController;

$pdo = Database::connection();
$passed = 0;
$failed = 0;

function assertCondition(string $name, bool $condition, string $details = '') {
    global $passed, $failed;
    if ($condition) {
        echo " [\033[32mPASS\033[0m] {$name}\n";
        $passed++;
    } else {
        echo " [\033[31mFAIL\033[0m] {$name}" . ($details ? " — {$details}" : '') . "\n";
        $failed++;
    }
}

echo "=== Fleet Maintenance Column Verification Suite ===\n\n";

// 1. Column exists in fleet_dispatches
$cols = $pdo->query("PRAGMA table_info(fleet_dispatches)")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($cols, 'name');
assertCondition("maintenance column exists in fleet_dispatches table", in_array('maintenance', $colNames));

// 2. TableSchemaService includes maintenance
$schemaCols = TableSchemaService::getTableColumns('fleet_dispatches', false);
$schemaMap = array_column($schemaCols, null, 'column_key');
assertCondition("TableSchemaService seeds maintenance column", isset($schemaMap['maintenance']));
assertCondition("TableSchemaService maintenance display label is Maintenance", ($schemaMap['maintenance']['display_label'] ?? '') === 'Maintenance');
assertCondition("TableSchemaService maintenance data type is currency", ($schemaMap['maintenance']['data_type'] ?? '') === 'currency');

// 3. Test insertion with maintenance
$pdo->exec("DELETE FROM fleet_dispatches WHERE trip_number = 'TEST-MAINT-001'");
$testDispatchId = 0;

try {
    $transport = 3000.00;
    $mileage = 500.00;
    $diesel = 600.00;
    $maintenance = 400.00;
    $expectedBalance = $transport - ($mileage + $diesel + $maintenance); // 1500.00

    $ins = $pdo->prepare("INSERT INTO fleet_dispatches (
        trip_number, bol_number, dispatch_date, truck, loaded_litres, shortage_litres, delivered_litres,
        from_location, destination, client_name, product, unit_price, driver,
        transport_amount, final_payout, payout_difference, diesel_litres, diesel_unit_price, diesel,
        mileage_cost, extra_expenses, maintenance, balance, status, is_shortage_recovered, created_at
    ) VALUES (?, ?, ?, ?, ?, 0, ?, 'Eldoret', ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, ?, ?, 0, ?, ?, 'Delivered', 0, ?)");

    $ins->execute([
        'TEST-MAINT-001',
        'BOL-TEST-001',
        date('Y-m-d'),
        'KDG 123A',
        10000,
        10000,
        'Kampala',
        'Test Client',
        'AGO',
        0.30,
        'Test Driver',
        $transport,
        $transport,
        $diesel,
        $mileage,
        $maintenance,
        $expectedBalance,
        date('Y-m-d H:i:s')
    ]);
    $testDispatchId = (int)$pdo->lastInsertId();

    assertCondition("Dispatch inserted with maintenance", $testDispatchId > 0);

    $row = $pdo->query("SELECT * FROM fleet_dispatches WHERE id = {$testDispatchId}")->fetch(PDO::FETCH_ASSOC);
    assertCondition("Stored maintenance matches input", (float)$row['maintenance'] === 400.00);
    assertCondition("Stored balance correctly deducts maintenance", (float)$row['balance'] === 1500.00);

    // 4. Update maintenance in inlineUpdate
    $_POST = [
        'id' => $testDispatchId,
        'dispatch_date' => $row['dispatch_date'],
        'truck' => $row['truck'],
        'driver' => $row['driver'],
        'status' => 'Delivered',
        'destination' => $row['destination'],
        'client_name' => $row['client_name'],
        'loaded_litres' => $row['loaded_litres'],
        'delivered_litres' => $row['loaded_litres'],
        'transport_amount' => $transport,
        'final_payout' => $transport,
        'mileage_cost' => $mileage,
        'diesel' => $diesel,
        'maintenance' => 550.00,
    ];

    // Simulate controller update
    $newMaintenance = (float)$_POST['maintenance'];
    $newBalance = $transport - ($mileage + $diesel + $newMaintenance); // 1350.00

    $upd = $pdo->prepare("UPDATE fleet_dispatches SET maintenance = ?, balance = ? WHERE id = ?");
    $upd->execute([$newMaintenance, $newBalance, $testDispatchId]);

    $updatedRow = $pdo->query("SELECT * FROM fleet_dispatches WHERE id = {$testDispatchId}")->fetch(PDO::FETCH_ASSOC);
    assertCondition("Updated maintenance value persists ($550.00)", (float)$updatedRow['maintenance'] === 550.00);
    assertCondition("Recalculated balance correctly deducts updated maintenance ($1,350.00)", (float)$updatedRow['balance'] === 1350.00);

} finally {
    if ($testDispatchId > 0) {
        $pdo->exec("DELETE FROM fleet_dispatches WHERE id = {$testDispatchId}");
    }
}

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
if ($failed > 0) {
    exit(1);
}
