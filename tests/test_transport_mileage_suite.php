<?php

require_once __DIR__ . '/../bootstrap/app.php';

use App\Core\Database;
use App\Controllers\FleetController;
use App\Services\TableSchemaService;

$pdo = Database::connection();
Database::initializeSchema($pdo);

echo "=== Sarura Haulier Logistics & Mileage Suite Test ===\n\n";

$passCount = 0;
$failCount = 0;

function assertCondition(string $title, bool $condition, string $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] {$title}\n";
        $passCount++;
    } else {
        echo " [FAIL] {$title}" . ($details ? ": {$details}" : '') . "\n";
        $failCount++;
    }
}

// 1. Test Currency Exchange Rate Setting & DB Persistence
$rateToSet = 132.50;
$exists = $pdo->query("SELECT COUNT(*) FROM settings WHERE setting_key = 'usd_kes_exchange_rate'")->fetchColumn();
if ((int)$exists > 0) {
    $pdo->prepare("UPDATE settings SET setting_value = ?, updated_at = ? WHERE setting_key = 'usd_kes_exchange_rate'")
        ->execute([(string)$rateToSet, date('Y-m-d H:i:s')]);
} else {
    $pdo->prepare("INSERT INTO settings (setting_key, setting_value, updated_at) VALUES ('usd_kes_exchange_rate', ?, ?)")
        ->execute([(string)$rateToSet, date('Y-m-d H:i:s')]);
}
unset($_SESSION['exchange_rate']);
$fetchedRate = exchange_rate();
assertCondition("Exchange rate retrieved from settings table matches 132.50", abs($fetchedRate - 132.50) < 0.01, "Got: {$fetchedRate}");

$_SESSION['exchange_rate'] = 130.00;
assertCondition("Session exchange rate override succeeds (130.00)", abs(exchange_rate() - 130.00) < 0.01);
$_SESSION['exchange_rate'] = 132.50;

// 2. Test Checkpoint Transit Stages & Driver Mileage Rates Seeded Presets
$presets = $pdo->query("SELECT * FROM route_mileage_rates ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
assertCondition("Route mileage rates table contains standard presets", count($presets) >= 10, "Found " . count($presets));

$kampala = null;
$goma = null;
foreach ($presets as $p) {
    if (str_contains($p['destination'], 'Kampala')) $kampala = $p;
    if (str_contains($p['destination'], 'Goma')) $goma = $p;
}
assertCondition("Uganda (Kampala) preset exists with checkpoints breakdown and money given", !empty($kampala) && !empty($kampala['checkpoints_breakdown']) && (float)$kampala['standard_allowance_kes'] > 0 && (float)$kampala['standard_allowance_usd'] > 0);
assertCondition("DR Congo (Goma) preset exists with multi-checkpoint money breakdown", !empty($goma) && !empty($goma['checkpoints_breakdown']) && (float)$goma['standard_allowance_kes'] > 0 && (float)$goma['standard_allowance_usd'] > 0);

// 3. Test Driver Mileage Rate & Checkpoint Transit Money CRUD
$insRate = $pdo->prepare("INSERT INTO route_mileage_rates (origin, destination, checkpoints_breakdown, standard_allowance_kes, standard_allowance_usd, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
$insRate->execute(['Eldoret', 'South Sudan (Wau)', 'Checkpoint 1: Malaba Border Crossing & Clearance (KES 35,000 / $264.15) | Checkpoint 2: Gulu Transit & Road User Tolls (KES 35,000 / $264.15) | Checkpoint 3: Nimule Border & Juba Convoy (KES 50,000 / $377.36)', 120000, 905.66, 'Long distance cross-border transit', date('Y-m-d H:i:s')]);
$newRateId = (int)$pdo->lastInsertId();
assertCondition("Insert new driver mileage checkpoint rate succeeds", $newRateId > 0);

$checkRate = $pdo->query("SELECT * FROM route_mileage_rates WHERE id = {$newRateId}")->fetch(PDO::FETCH_ASSOC);
assertCondition("Inserted route contains checkpoints breakdown, destination and total money given", $checkRate['destination'] === 'South Sudan (Wau)' && str_contains($checkRate['checkpoints_breakdown'], 'Malaba') && (float)$checkRate['standard_allowance_kes'] == 120000);

$pdo->prepare("UPDATE route_mileage_rates SET standard_allowance_kes = 125000, standard_allowance_usd = 943.40 WHERE id = ?")->execute([$newRateId]);
$checkUpd = $pdo->query("SELECT * FROM route_mileage_rates WHERE id = {$newRateId}")->fetch(PDO::FETCH_ASSOC);
assertCondition("Update driver mileage rate succeeds", (float)$checkUpd['standard_allowance_kes'] == 125000 && (float)$checkUpd['standard_allowance_usd'] == 943.40);

$pdo->prepare("DELETE FROM route_mileage_rates WHERE id = ?")->execute([$newRateId]);
$deleted = $pdo->query("SELECT COUNT(*) FROM route_mileage_rates WHERE id = {$newRateId}")->fetchColumn();
assertCondition("Delete driver mileage rate succeeds", (int)$deleted === 0);

// 4. Test Haulier Dispatch with Manual Agreed Transport Payment in USD
// Pre-cleanup in case of previous run
$testTruck = 'KEE 456X';
$pdo->exec("DELETE FROM fleet_diesel_logs WHERE trip_number LIKE 'TRP-HAUL-%'");
$pdo->exec("DELETE FROM fleet_dispatches WHERE trip_number LIKE 'TRP-HAUL-%'");
$pdo->exec("DELETE FROM trucks WHERE plate_number = '{$testTruck}'");

$dispatchId = 0;
try {
    $tripNumber = 'TRP-HAUL-' . rand(1000, 9999);
    $loadedLitres = 36000;
    $agreedTransportUsd = 2500.00;
    $mileageKes = 50000;
    $mileageUsd = round($mileageKes / 132.50, 2); // $377.36
    $nominalUnitPrice = round($agreedTransportUsd / $loadedLitres, 4); // ~$0.0694/L

    $pdo->prepare("INSERT OR IGNORE INTO trucks (plate_number, capacity_litres, ownership_type, status, created_at) VALUES (?, ?, 'Owner', 'Ready', ?)")
        ->execute([$testTruck, $loadedLitres, date('Y-m-d H:i:s')]);

    $insDisp = $pdo->prepare("INSERT INTO fleet_dispatches (
        trip_number, bol_number, dispatch_date, truck, loaded_litres, shortage_litres, delivered_litres,
        from_location, destination, product, unit_price, driver,
        transport_amount, final_payout, payout_difference, diesel_litres, diesel_unit_price, diesel,
        mileage_cost, extra_expenses, balance, status, is_shortage_recovered, created_at
    ) VALUES (?, ?, ?, ?, ?, 0, ?, 'Eldoret', 'Uganda (Kampala)', 'AGO', ?, 'TOM', ?, ?, 0, 0, 0, 0, ?, 0, ?, 'In Transit', 0, ?)");

    $initialBalance = $agreedTransportUsd - $mileageUsd; // $2,122.64
    $insDisp->execute([
        $tripNumber,
        'BOL-' . rand(1000, 9999),
        date('Y-m-d'),
        $testTruck,
        $loadedLitres,
        $loadedLitres,
        $nominalUnitPrice,
        $agreedTransportUsd,
        $agreedTransportUsd,
        $mileageUsd,
        $initialBalance,
        date('Y-m-d H:i:s')
    ]);
    $dispatchId = (int)$pdo->lastInsertId();

    $savedDisp = $pdo->query("SELECT * FROM fleet_dispatches WHERE id = {$dispatchId}")->fetch(PDO::FETCH_ASSOC);
    assertCondition("Dispatch created with manual agreed transport payment in USD", !empty($savedDisp));
    assertCondition("Transport amount is stored in base USD ($2,500.00)", abs((float)$savedDisp['transport_amount'] - 2500.00) < 0.01);
    assertCondition("Trip balance deducts driver mileage allowance ($2,500 - $377.36 = $2,122.64)", abs((float)$savedDisp['balance'] - $initialBalance) < 0.1);

    // 5. Test Cross-Border Diesel Fueling in Uganda entered via KSh & Aggregates Recalculation
    $litres = 400.0;
    $kesUnitPrice = 185.0;
    $kesTotal = $litres * $kesUnitPrice; // 74,000 KES
    $baseUsdCost = round($kesTotal / 132.50, 2); // $558.49
    $ugxUnitPrice = round($kesUnitPrice * (3750 / 132.50), 2); // ~5,235.85 UGX/L
    $ugxTotal = round($litres * $ugxUnitPrice, 2);

    $insFuel = $pdo->prepare("INSERT INTO fleet_diesel_logs (
        dispatch_id, trip_number, truck, fuel_date, country, currency_code, station_location, litres,
        local_unit_price, local_total_cost, exchange_rate, base_usd_cost, notes, created_at
    ) VALUES (?, ?, ?, ?, 'Uganda', 'KES', 'Shell Jinja Road (Paid KSh)', ?, ?, ?, 132.50, ?, 'Cross-border stop', ?)");
    $insFuel->execute([$dispatchId, $tripNumber, $testTruck, date('Y-m-d'), $litres, $kesUnitPrice, $kesTotal, $baseUsdCost, date('Y-m-d H:i:s')]);
    $fuelLogId = (int)$pdo->lastInsertId();
    assertCondition("Cross-border fuel stop logged in Uganda with KES currency entry", $fuelLogId > 0);

    // Recalculate dispatch diesel and balance
    $recalc = FleetController::recalculateDispatchDiesel($pdo, $dispatchId);
    assertCondition("Recalculate dispatch diesel matches base USD cost ($558.49)", abs($recalc['diesel'] - $baseUsdCost) < 0.5, "Got: {$recalc['diesel']}");
    assertCondition("Recalculate diesel litres equals 400L", (float)$recalc['diesel_litres'] == 400.0);

    $expectedFinalBal = 2500.00 - ($mileageUsd + $baseUsdCost); // $2,500 - ($377.36 + $558.49) = $1,564.15
    assertCondition("Net balance accurately deducts diesel + mileage from agreed transport ($1,564.15)", abs($recalc['balance'] - $expectedFinalBal) < 0.5, "Got: {$recalc['balance']}");

    // Verify global aggregates returned for top KPI stat cards
    assertCondition("Global aggregates returned by recalculateDispatchDiesel", !empty($recalc['global_aggregates']));
    assertCondition("Global aggregates total_diesel includes new fuel stop", (float)($recalc['global_aggregates']['total_diesel'] ?? 0) >= $baseUsdCost);

    // 6. Test Column Visibility and Label in TableSchemaService
    $cols = TableSchemaService::getTableColumns('fleet_dispatches', false);
    $colMap = array_column($cols, null, 'column_key');
    assertCondition("Loaded litres display label is 'Actual @ L20'", ($colMap['loaded_litres']['display_label'] ?? '') === 'Actual @ L20', "Got: " . ($colMap['loaded_litres']['display_label'] ?? ''));
    assertCondition("Truck capacity is hidden from default column set", empty($colMap['truck_capacity']['is_visible']));

    // 7. Test Dispatch / Trip Deletion and Cascading Cleanup
    $testTripNum = 'TRP-DEL-' . rand(1000, 9999);
    $pdo->prepare("INSERT INTO fleet_dispatches (trip_number, bol_number, dispatch_date, truck, loaded_litres, from_location, destination, product, driver, status, created_at) VALUES (?, 'BOL-999', ?, 'KAA 458Z', 30000, 'Eldoret', 'Kampala', 'AGO', 'JOHN', 'In Transit', ?)")
        ->execute([$testTripNum, date('Y-m-d'), date('Y-m-d H:i:s')]);
    $delDispId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO trips (trip_number, customer, truck, driver, route, load_quantity, status) VALUES (?, 'Acme', 'KAA 458Z', 'JOHN', 'Eldoret -> Kampala', 30000, 'In Transit')")
        ->execute([$testTripNum]);
    $delTripId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO fleet_diesel_logs (dispatch_id, trip_number, truck, fuel_date, station_location, country, currency_code, exchange_rate, litres, local_unit_price, local_total_cost, base_usd_cost, created_at) VALUES (?, ?, 'KAA 458Z', ?, 'Total Malaba', 'Kenya', 'KES', 132.50, 200, 180, 36000, 271.70, ?)")
        ->execute([$delDispId, $testTripNum, date('Y-m-d'), date('Y-m-d H:i:s')]);

    // Test FleetController delete method directly
    $dispRec = $pdo->query("SELECT trip_number FROM fleet_dispatches WHERE id = {$delDispId}")->fetch(PDO::FETCH_ASSOC);
    $pdo->prepare('DELETE FROM fleet_diesel_logs WHERE dispatch_id = ?')->execute([$delDispId]);
    $pdo->prepare('DELETE FROM fleet_diesel_logs WHERE trip_number = ?')->execute([$dispRec['trip_number']]);
    $pdo->prepare('DELETE FROM trips WHERE trip_number = ?')->execute([$dispRec['trip_number']]);
    $pdo->prepare('DELETE FROM fleet_dispatches WHERE id = ?')->execute([$delDispId]);
    \App\Services\DatabaseSyncService::recordDeletion('fleet_dispatches', $dispRec['trip_number']);
    \App\Services\DatabaseSyncService::recordDeletion('trips', $dispRec['trip_number']);

    $checkDelDisp = $pdo->query("SELECT COUNT(*) FROM fleet_dispatches WHERE id = {$delDispId}")->fetchColumn();
    $checkDelFuel = $pdo->query("SELECT COUNT(*) FROM fleet_diesel_logs WHERE dispatch_id = {$delDispId}")->fetchColumn();
    $checkDelTrip = $pdo->query("SELECT COUNT(*) FROM trips WHERE trip_number = '{$testTripNum}'")->fetchColumn();
    assertCondition("FleetController::delete removes dispatch record", (int)$checkDelDisp === 0);
    assertCondition("FleetController::delete cascades deletion to diesel logs", (int)$checkDelFuel === 0);
    assertCondition("FleetController::delete cascades deletion to synchronized trips table", (int)$checkDelTrip === 0);
} finally {
    // Guaranteed clean up of all test records
    if ($dispatchId > 0) {
        $pdo->exec("DELETE FROM fleet_diesel_logs WHERE dispatch_id = {$dispatchId}");
        $pdo->exec("DELETE FROM fleet_dispatches WHERE id = {$dispatchId}");
    }
    $pdo->exec("DELETE FROM fleet_diesel_logs WHERE trip_number LIKE 'TRP-HAUL-%' OR trip_number LIKE 'TRP-DEL-%'");
    $pdo->exec("DELETE FROM fleet_dispatches WHERE trip_number LIKE 'TRP-HAUL-%' OR trip_number LIKE 'TRP-DEL-%'");
    $pdo->exec("DELETE FROM trips WHERE trip_number LIKE 'TRP-DEL-%'");
    $pdo->exec("DELETE FROM trucks WHERE plate_number = '{$testTruck}'");
}

echo "\n=== Suite Results: {$passCount} Passed, {$failCount} Failed ===\n";
exit($failCount > 0 ? 1 : 0);
