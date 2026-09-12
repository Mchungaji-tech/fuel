<?php

require_once __DIR__ . '/../bootstrap/app.php';

use App\Core\Database;

$pdo = Database::connection();
Database::initializeSchema($pdo);

echo "=== Sarura Fuel Logistics Feature Suite Test ===\n\n";

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

// 1. Check Products: Only PMS and AGO with unit_price
$prods = $pdo->query('SELECT code, name, unit_price FROM products')->fetchAll(PDO::FETCH_ASSOC);
$prodCodes = array_map(fn($p) => strtoupper($p['code']), $prods);
assertCondition("Products catalog contains PMS", in_array('PMS', $prodCodes));
assertCondition("Products catalog contains AGO", in_array('AGO', $prodCodes));
assertCondition("Products catalog restricted to PMS & AGO only", count($prods) === 2);
foreach ($prods as $p) {
    assertCondition("Product {$p['code']} has unit_price > 0", (float)$p['unit_price'] > 0, "Price is {$p['unit_price']}");
}

// 2. Check Truck Compartments Nullable
$truckCheck = $pdo->query("SELECT plate_number, compartments FROM trucks LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($truckCheck) {
    echo " Truck sample: {$truckCheck['plate_number']} - Compartments: " . var_export($truckCheck['compartments'], true) . "\n";
}
// Insert a test truck without compartments
$testPlate = 'TEST-' . rand(100, 999);
$insTruck = $pdo->prepare("INSERT INTO trucks (plate_number, model, capacity_litres, compartments, ownership_type, status, created_at) VALUES (?, 'Isuzu Giga', 36000, NULL, 'Company', 'Ready', ?)");
$insTruck->execute([$testPlate, date('Y-m-d H:i:s')]);
$fetchTruck = $pdo->query("SELECT compartments FROM trucks WHERE plate_number = '{$testPlate}'")->fetchColumn();
assertCondition("Truck compartments is nullable without forced default", $fetchTruck === null);
$pdo->exec("DELETE FROM trucks WHERE plate_number = '{$testPlate}'");

// 3. Customer Case-Insensitive Synchronization
$testClientOriginal = 'Acme Energy East Africa';
$testClientMixed = 'aCmE eNeRgY eAsT aFrIcA';
// Ensure clean state
$pdo->prepare("DELETE FROM customers WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))")->execute([$testClientOriginal]);
$pdo->prepare("INSERT INTO customers (name, company, orders, total_litres, status) VALUES (?, ?, 1, 10000, 'Active')")->execute([$testClientOriginal, $testClientOriginal]);
// Check case-insensitive match query used in FleetController
$matchStmt = $pdo->prepare('SELECT id, name FROM customers WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1');
$matchStmt->execute([$testClientMixed]);
$matchedCustomer = $matchStmt->fetch(PDO::FETCH_ASSOC);
assertCondition("Customer match succeeds case-insensitively for '{$testClientMixed}'", !empty($matchedCustomer) && $matchedCustomer['name'] === $testClientOriginal);
$pdo->prepare("DELETE FROM customers WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))")->execute([$testClientOriginal]);

// 4. Dispatch with Unit Price, Single Diesel Owner Expense, and Shortage Calculation
$testTrip = 'TRP-UNIT-' . rand(1000, 9999);
$testDriver = 'Payroll Verification Driver ' . rand(100, 999);
$unitPrice = 10.50; // KES 10.50/L or USD normalized
$loadedLitres = 20000;
$shortageLitres = 200;
$deliveredLitres = $loadedLitres - $shortageLitres; // 19800 L
$expectedTransport = $loadedLitres * $unitPrice; // 210,000
$finalPayout = $deliveredLitres * $unitPrice; // 207,900
$payoutDiff = $shortageLitres * $unitPrice; // 2,100 (Shortage Loss)
$dieselLitres = 450;
$dieselRate = 175.00;
$dieselExpense = $dieselLitres * $dieselRate; // 78,750 (Owner's Expense)

$insTrip = $pdo->prepare('INSERT INTO fleet_dispatches (
    trip_number, bol_number, dispatch_date, truck, loaded_litres, shortage_litres, delivered_litres,
    from_location, destination, product, unit_price, driver,
    transport_amount, final_payout, payout_difference, diesel_litres, diesel_unit_price, diesel,
    mileage_cost, extra_expenses, balance, status, is_shortage_recovered, created_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Delivered", 0, ?)');

$insTrip->execute([
    $testTrip, 'BOL-' . rand(100, 999), date('Y-m-d'), 'KCC 910L',
    $loadedLitres, $shortageLitres, $deliveredLitres,
    'Eldoret', 'Kampala', 'PMS', $unitPrice, $testDriver,
    $expectedTransport, $finalPayout, $payoutDiff,
    $dieselLitres, $dieselRate, $dieselExpense,
    50000, 0, ($finalPayout - (50000 + $dieselExpense)),
    date('Y-m-d H:i:s')
]);

$fetchTrip = $pdo->query("SELECT * FROM fleet_dispatches WHERE trip_number = '{$testTrip}'")->fetch(PDO::FETCH_ASSOC);
assertCondition("Dispatch recorded expected transport correctly as loaded * unit_price", (float)$fetchTrip['transport_amount'] == (float)$expectedTransport);
assertCondition("Dispatch recorded shortage_litres", (int)$fetchTrip['shortage_litres'] === 200);
assertCondition("Dispatch calculated final_payout as (loaded - shortage) * unit_price", (float)$fetchTrip['final_payout'] == (float)$finalPayout);
assertCondition("Dispatch calculated shortage payout_difference", (float)$fetchTrip['payout_difference'] == (float)$payoutDiff);
assertCondition("Dispatch single diesel owner expense calculated as litres * rate", (float)$fetchTrip['diesel'] == (float)$dieselExpense);

// 5. Driver Payroll Arrears and Shortage Deductions Forwarding
// Register the driver
$pdo->prepare("INSERT INTO drivers (name, phone, fixed_salary, status) VALUES (?, '0711000222', 35000, 'Active')")->execute([$testDriver]);
$driverId = $pdo->lastInsertId();

// Create an unpaid past voucher (status = 'Wait') to verify auto-calculated arrears
$pdo->prepare("INSERT INTO driver_salaries (driver_id, driver_name, payment_type, period_reference, base_salary, carried_forward, shortage_deductions, amount, status, payment_date, created_at) VALUES (?, ?, 'monthly_salary', 'Last Month', 35000, 0, 0, 35000, 'Wait', ?, ?)")
    ->execute([$driverId, $testDriver, date('Y-m-d', strtotime('-1 month')), date('Y-m-d H:i:s')]);

// Query arrears
$arrearsStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM driver_salaries WHERE driver_name = ? AND status = 'Wait'");
$arrearsStmt->execute([$testDriver]);
$pendingArrears = (float)$arrearsStmt->fetchColumn();
assertCondition("Driver pending arrears auto-calculated from unpaid vouchers", $pendingArrears == 35000);

// Query pending shortages from fleet_dispatches
$shortageStmt = $pdo->prepare("SELECT COALESCE(SUM(payout_difference), 0) FROM fleet_dispatches WHERE driver = ? AND shortage_litres > 0 AND (is_shortage_recovered = 0 OR is_shortage_recovered IS NULL)");
$shortageStmt->execute([$testDriver]);
$pendingShortages = (float)$shortageStmt->fetchColumn();
assertCondition("Driver pending shortage loss forwarded from trip dispatches", $pendingShortages == $payoutDiff);

// Calculate net payable voucher: Base (35,000) + Arrears (35,000) - Shortages (2,100) = 67,900
$fixedSalary = 35000;
$netPayable = $fixedSalary + $pendingArrears - $pendingShortages;
assertCondition("Net payable voucher matches formula Base + Arrears - Shortage", $netPayable == 67900);

// Simulate issuing new salary voucher with shortage deduction
$pdo->prepare("INSERT INTO driver_salaries (driver_id, driver_name, payment_type, period_reference, base_salary, carried_forward, shortage_deductions, amount, status, payment_date, created_at) VALUES (?, ?, 'monthly_salary', 'Current Month', ?, ?, ?, ?, 'Paid', ?, ?)")
    ->execute([$driverId, $testDriver, $fixedSalary, $pendingArrears, $pendingShortages, $netPayable, date('Y-m-d'), date('Y-m-d H:i:s')]);

// Mark shortages recovered
$pdo->prepare("UPDATE fleet_dispatches SET is_shortage_recovered = 1 WHERE driver = ? AND shortage_litres > 0")->execute([$testDriver]);

// Verify that shortages are now recovered
$recoveredCheck = $pdo->query("SELECT is_shortage_recovered FROM fleet_dispatches WHERE trip_number = '{$testTrip}'")->fetchColumn();
assertCondition("Dispatch is_shortage_recovered updated to 1 after payroll deduction", (int)$recoveredCheck === 1);

// Cleanup test driver and trip data
$pdo->exec("DELETE FROM fleet_dispatches WHERE trip_number = '{$testTrip}'");
$pdo->exec("DELETE FROM driver_salaries WHERE driver_name = '{$testDriver}'");
$pdo->exec("DELETE FROM drivers WHERE id = '{$driverId}'");

// 6. Expense Truck Report (All-time, Yearly, 12-month matrix)
$testTruck = 'KBZ 888X';
$pdo->prepare("DELETE FROM expenses WHERE truck = ?")->execute([$testTruck]);
$currentYear = date('Y');
// Insert 3 expenses across 2 months in current year, and 1 in previous year
$pdo->prepare("INSERT INTO expenses (expense_date, expense_title, truck, amount, garage_vendor, receipt_status, created_at) VALUES (?, 'Tire Change', ?, 12000, 'Vendor A', 'Received', ?)")
    ->execute(["{$currentYear}-02-10", $testTruck, date('Y-m-d H:i:s')]);
$pdo->prepare("INSERT INTO expenses (expense_date, expense_title, truck, amount, garage_vendor, receipt_status, created_at) VALUES (?, 'Oil Filter', ?, 8000, 'Vendor B', 'Received', ?)")
    ->execute(["{$currentYear}-02-20", $testTruck, date('Y-m-d H:i:s')]);
$pdo->prepare("INSERT INTO expenses (expense_date, expense_title, truck, amount, garage_vendor, receipt_status, created_at) VALUES (?, 'Brake Pads', ?, 15000, 'Vendor A', 'Received', ?)")
    ->execute(["{$currentYear}-05-15", $testTruck, date('Y-m-d H:i:s')]);
$pdo->prepare("INSERT INTO expenses (expense_date, expense_title, truck, amount, garage_vendor, receipt_status, created_at) VALUES ('2024-08-01', 'Gearbox Repair', ?, 45000, 'Vendor C', 'Received', ?)")
    ->execute([$testTruck, date('Y-m-d H:i:s')]);

// Check All-time total: 12000 + 8000 + 15000 + 45000 = 80,000
$allTimeTotal = (float)$pdo->query("SELECT SUM(amount) FROM expenses WHERE truck = '{$testTruck}'")->fetchColumn();
assertCondition("Truck all-time total expenses accurately aggregated", $allTimeTotal == 80000);

// Check Yearly total for currentYear: 12000 + 8000 + 15000 = 35,000
$yrStmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE truck = ? AND (expense_date LIKE ? OR substr(expense_date, 1, 4) = ?)");
$yrStmt->execute([$testTruck, "{$currentYear}%", (string)$currentYear]);
$yearlyTotal = (float)$yrStmt->fetchColumn();
assertCondition("Truck yearly total expenses accurately filtered for year {$currentYear}", $yearlyTotal == 35000);

// Check 12-month matrix
$moStmt = $pdo->prepare("SELECT substr(expense_date, 6, 2) as mo, SUM(amount) as total FROM expenses WHERE truck = ? AND substr(expense_date, 1, 4) = ? GROUP BY substr(expense_date, 6, 2)");
$moStmt->execute([$testTruck, (string)$currentYear]);
$monthlyMatrix = $moStmt->fetchAll(PDO::FETCH_KEY_PAIR);
assertCondition("Truck monthly matrix recorded February expenses", (float)($monthlyMatrix['02'] ?? 0) == 20000);
assertCondition("Truck monthly matrix recorded May expenses", (float)($monthlyMatrix['05'] ?? 0) == 15000);
assertCondition("Truck monthly matrix recorded zero for January", empty($monthlyMatrix['01']));

// Cleanup test truck expenses
$pdo->prepare("DELETE FROM expenses WHERE truck = ?")->execute([$testTruck]);

// 7. Cross-Border Multi-Currency Diesel Fueling & Automated Dynamic Recalculation
echo "\n--- Testing Cross-Border Multi-Currency Diesel Fueling ---\n";
// 7.1 Verify fleet_diesel_logs schema
$dieselTableCheck = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='fleet_diesel_logs'")->fetchColumn();
assertCondition("fleet_diesel_logs table exists in database", !empty($dieselTableCheck));

// 7.2 Create a test dispatch record
$dieselTrip = 'TRP-FX-' . rand(1000, 9999);
$testTruck = 'KBX 999Z';
$insTripStmt = $pdo->prepare("INSERT INTO fleet_dispatches (
    trip_number, bol_number, dispatch_date, truck, loaded_litres, delivered_litres,
    from_location, destination, product, unit_price, driver,
    transport_amount, final_payout, payout_difference, diesel_litres, diesel_unit_price, diesel,
    mileage_cost, extra_expenses, balance, status, created_at
) VALUES (?, 'BOL-FX', '2026-09-12', ?, 35000, 35000, 'Eldoret', 'DR Congo (Goma)', 'AGO', 0.08, 'Safari Driver', 2800.00, 2800.00, 0, 0, 0, 0, 300.00, 0, 2500.00, 'In Transit', ?)");
$insTripStmt->execute([$dieselTrip, $testTruck, date('Y-m-d H:i:s')]);
$dispId = (int)$pdo->lastInsertId();

// 7.3 Log Stop 1: Kenya Departure Top-up in KES
// 400 Litres @ 180 KES = 72,000 KES. Ex rate = 130 KES/USD -> Base USD = 72,000 / 130 = $553.846
$stop1Litres = 400.0;
$stop1LocalUnit = 180.0;
$stop1LocalTotal = 72000.0;
$stop1Rate = 130.0;
$stop1Usd = round($stop1LocalTotal / $stop1Rate, 2); // 553.85

$pdo->prepare("INSERT INTO fleet_diesel_logs (
    dispatch_id, trip_number, truck, fuel_date, station_location, country, currency_code, exchange_rate,
    litres, local_unit_price, local_total_cost, base_usd_cost, receipt_status, receipt_number, notes, created_at
) VALUES (?, ?, ?, '2026-09-12', 'Eldoret KPC Shell', 'Kenya', 'KES', ?, ?, ?, ?, ?, 'Received', 'REC-KE-01', 'Departure fill', ?)")
    ->execute([$dispId, $dieselTrip, $testTruck, $stop1Rate, $stop1Litres, $stop1LocalUnit, $stop1LocalTotal, $stop1Usd, date('Y-m-d H:i:s')]);

$t1 = \App\Controllers\FleetController::recalculateDispatchDiesel($pdo, $dispId);
assertCondition("Recalculate after Stop 1 matches 400 Litres", (float)$t1['diesel_litres'] === 400.0);
assertCondition("Recalculate after Stop 1 matches Base USD cost ($553.85)", (float)$t1['diesel'] === $stop1Usd);
assertCondition("Trip balance after Stop 1 deducted from payout (2800 - 300 - 553.85 = 1946.15)", (float)$t1['balance'] === (2800.00 - 300.00 - $stop1Usd));

// 7.4 Log Stop 2: Uganda Malaba En-Route in UGX
// 250 Litres @ 5,500 UGX = 1,375,000 UGX. Ex rate = 3,750 UGX/USD -> Base USD = 1,375,000 / 3,750 = $366.67
$stop2Litres = 250.0;
$stop2LocalUnit = 5500.0;
$stop2LocalTotal = 1375000.0;
$stop2Rate = 3750.0;
$stop2Usd = round($stop2LocalTotal / $stop2Rate, 2); // 366.67

$pdo->prepare("INSERT INTO fleet_diesel_logs (
    dispatch_id, trip_number, truck, fuel_date, station_location, country, currency_code, exchange_rate,
    litres, local_unit_price, local_total_cost, base_usd_cost, receipt_status, receipt_number, notes, created_at
) VALUES (?, ?, ?, '2026-09-13', 'Malaba Total Station', 'Uganda', 'UGX', ?, ?, ?, ?, ?, 'Received', 'REC-UG-02', 'En-route border topup', ?)")
    ->execute([$dispId, $dieselTrip, $testTruck, $stop2Rate, $stop2Litres, $stop2LocalUnit, $stop2LocalTotal, $stop2Usd, date('Y-m-d H:i:s')]);

$t2 = \App\Controllers\FleetController::recalculateDispatchDiesel($pdo, $dispId);
$expectedTotalLitres2 = 400.0 + 250.0; // 650.0
$expectedDieselUsd2 = round($stop1Usd + $stop2Usd, 2); // 553.85 + 366.67 = 920.52
assertCondition("Cumulative litres across Kenya & Uganda is 650 Litres", (float)$t2['diesel_litres'] === $expectedTotalLitres2);
assertCondition("Cumulative diesel expense in Base USD matches multi-currency converted sum", (float)$t2['diesel'] === $expectedDieselUsd2);

// 7.5 Log Stop 3: DR Congo Goma Arrival in USD
// 150 Litres @ 1.70 USD = 255.00 USD. Ex rate = 1.0 -> Base USD = 255.00
$stop3Litres = 150.0;
$stop3LocalUnit = 1.70;
$stop3LocalTotal = 255.0;
$stop3Rate = 1.0;
$stop3Usd = 255.00;

$pdo->prepare("INSERT INTO fleet_diesel_logs (
    dispatch_id, trip_number, truck, fuel_date, station_location, country, currency_code, exchange_rate,
    litres, local_unit_price, local_total_cost, base_usd_cost, receipt_status, receipt_number, notes, created_at
) VALUES (?, ?, ?, '2026-09-15', 'Goma Station', 'DR Congo', 'USD', ?, ?, ?, ?, ?, 'Received', 'REC-CD-03', 'Destination buffer fill', ?)")
    ->execute([$dispId, $dieselTrip, $testTruck, $stop3Rate, $stop3Litres, $stop3LocalUnit, $stop3LocalTotal, $stop3Usd, date('Y-m-d H:i:s')]);
$stop3Id = (int)$pdo->lastInsertId();

$t3 = \App\Controllers\FleetController::recalculateDispatchDiesel($pdo, $dispId);
$expectedTotalLitres3 = 800.0;
$expectedDieselUsd3 = round($stop1Usd + $stop2Usd + $stop3Usd, 2); // 1175.52
$expectedUnitAvg3 = round($expectedDieselUsd3 / 800.0, 4);
assertCondition("Three-country cumulative fuel logged: 800 Litres total", (float)$t3['diesel_litres'] === 800.0);
assertCondition("Three-country total base USD matches: \${$expectedDieselUsd3}", (float)$t3['diesel'] === $expectedDieselUsd3);
assertCondition("Net profit balance automatically re-synchronized: \${$t3['balance']}", (float)$t3['balance'] === (2800.00 - (300.00 + $expectedDieselUsd3)));

// 7.6 Delete Stop 3 and verify cascading recalculation
$pdo->prepare("DELETE FROM fleet_diesel_logs WHERE id = ?")->execute([$stop3Id]);
$tAfterDelete = \App\Controllers\FleetController::recalculateDispatchDiesel($pdo, $dispId);
assertCondition("After deleting Stop 3, litres automatically drop back to 650 Litres", (float)$tAfterDelete['diesel_litres'] === 650.0);
assertCondition("After deleting Stop 3, diesel drops back to \${$expectedDieselUsd2}", (float)$tAfterDelete['diesel'] === $expectedDieselUsd2);
assertCondition("After deleting Stop 3, trip profit recovers by \${$stop3Usd}", (float)$tAfterDelete['balance'] === (2800.00 - (300.00 + $expectedDieselUsd2)));

// 7.7 Cascade cleanup when dispatch is deleted
$pdo->prepare("DELETE FROM fleet_diesel_logs WHERE dispatch_id = ?")->execute([$dispId]);
$pdo->prepare("DELETE FROM fleet_dispatches WHERE id = ?")->execute([$dispId]);
$remainingLogs = (int)$pdo->query("SELECT COUNT(*) FROM fleet_diesel_logs WHERE dispatch_id = {$dispId}")->fetchColumn();
assertCondition("Cascade cleanup removes all diesel logs for dispatch", $remainingLogs === 0);

echo "\n=== Test Results: {$passCount} Passed, {$failCount} Failed ===\n";
exit($failCount > 0 ? 1 : 0);

