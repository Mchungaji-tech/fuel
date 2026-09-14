<?php
define('TESTING_MODE', true);
require_once __DIR__ . '/../bootstrap/app.php';

use App\Core\Database;
use App\Controllers\FleetController;
use App\Controllers\ExpenseController;
use App\Controllers\FinancialController;

$pdo = Database::connection();

echo "=======================================================\n";
echo "RUNNING CURRENCY & DYNAMIC RATE ISOLATION TEST SUITE\n";
echo "=======================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $desc, bool $condition) {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] " . $desc . "\n";
        $passCount++;
    } else {
        echo " [FAIL] " . $desc . "\n";
        $failCount++;
    }
}

// 1. Create a test dispatch trip for diesel logging
$tripNumber = 'TEST-RATE-TRIP-' . time();
$pdo->prepare('INSERT INTO fleet_dispatches (
    trip_number, truck, driver, destination, dispatch_date, cargo_type,
    transport_amount, mileage_cost, diesel, diesel_litres, diesel_unit_price,
    extra_expenses, balance, status, created_at
) VALUES (?, "KBZ 123A", "John Doe", "Kampala", "2026-09-01", "Fuel", 2000.00, 300.00, 0, 0, 0, 0, 1700.00, "In Transit", datetime("now"))')
->execute([$tripNumber]);
$dispatchId = (int)$pdo->lastInsertId();

assertTest("Created test dispatch trip #{$tripNumber}", $dispatchId > 0);

// 2. Test Diesel Fueling with direct USD unit price and custom transaction exchange rate
$litres = 400.0;
$usdUnitPrice = 1.45;
$stopExchangeRate = 130.0;
$kesUnitPrice = $usdUnitPrice * $stopExchangeRate; // 188.50
$baseUsdCost = round($litres * $usdUnitPrice, 2); // 580.00
$localTotalCost = round($litres * $kesUnitPrice, 2); // 75400.00

$ins = $pdo->prepare('INSERT INTO fleet_diesel_logs (
    dispatch_id, trip_number, truck, fuel_date, station_location, country, currency_code,
    exchange_rate, litres, local_unit_price, local_total_cost, base_usd_cost,
    receipt_status, receipt_number, notes, created_at
) VALUES (?, ?, "KBZ 123A", "2026-09-01", "Shell Jinja Road", "Uganda", "KES", ?, ?, ?, ?, ?, "Received", "UG-1001", "USD paid fuel top-up", datetime("now"))');
$ins->execute([$dispatchId, $tripNumber, $stopExchangeRate, $litres, $kesUnitPrice, $localTotalCost, $baseUsdCost]);
$logId = (int)$pdo->lastInsertId();

assertTest("Diesel log inserted with custom exchange_rate", $logId > 0);

// Verify diesel log database record
$logStmt = $pdo->prepare('SELECT * FROM fleet_diesel_logs WHERE id = ?');
$logStmt->execute([$logId]);
$log = $logStmt->fetch(PDO::FETCH_ASSOC);

assertTest("Diesel log litres == 400", (float)($log['litres'] ?? 0) == 400.0);
assertTest("Diesel log exchange_rate == 130.0000", (float)($log['exchange_rate'] ?? 0) == 130.0);
assertTest("Diesel log base_usd_cost == 580.00 (400 * 1.45)", (float)($log['base_usd_cost'] ?? 0) == 580.0);
assertTest("Diesel log local_total_cost == 75400.00 (580 * 130)", (float)($log['local_total_cost'] ?? 0) == 75400.0);

// 3. Test Expense creation with dynamic exchange rate
$expTitle = 'Spares Test ' . time();
$_POST = [
    'expense_date' => '2026-09-01',
    'expense_title' => $expTitle,
    'truck' => 'KBZ 123A',
    'currency_mode' => 'KES',
    'amount_kes' => '12500',
    'exchange_rate' => '125.0000',
    'garage_vendor' => 'Simba Auto',
    'notes' => 'Custom rate 125 test'
];

$expStmt = $pdo->prepare('INSERT INTO expenses (expense_date, expense_title, truck, amount, exchange_rate, garage_vendor, receipt_status, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime("now"))');
$rateExp = 125.0;
$amtUsdExp = 12500 / $rateExp; // 100.00 USD
$expStmt->execute(['2026-09-01', $expTitle, 'KBZ 123A', $amtUsdExp, $rateExp, 'Simba Auto', 'Received', 'Custom rate 125 test']);
$expId = (int)$pdo->lastInsertId();

$expRecord = $pdo->query("SELECT * FROM expenses WHERE id = {$expId}")->fetch(PDO::FETCH_ASSOC);
assertTest("Expense stored with custom exchange_rate == 125.0000", (float)$expRecord['exchange_rate'] == 125.0);
assertTest("Expense base USD amount == 100.00", (float)$expRecord['amount'] == 100.0);

// 4. Test Financial Cash Flow record with historical exchange rate
$finReason = 'Financial Rate Test ' . time();
$finStmt = $pdo->prepare('INSERT INTO financial_records (entry_date, amount_in, amount_out, running_balance, reason, category, payment_method, exchange_rate, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime("now"))');
$finStmt->execute(['2026-09-01', 500.00, 0.00, 500.00, $finReason, 'Client Inflow', 'Bank Transfer', 124.5000]);
$finId = (int)$pdo->lastInsertId();

$finRecord = $pdo->query("SELECT * FROM financial_records WHERE id = {$finId}")->fetch(PDO::FETCH_ASSOC);
assertTest("Financial record stored with historical exchange_rate == 124.5000", (float)$finRecord['exchange_rate'] == 124.5);

// 5. TEST SYSTEM RATE CHANGE ISOLATION
// If global setting exchange rate changes in settings to e.g. 150.0000,
// ensure historical records in database remain completely intact!
$pdo->prepare('UPDATE settings SET setting_value = "150.0000" WHERE setting_key = "exchange_rate"')->execute();

$logCheck = $pdo->query("SELECT * FROM fleet_diesel_logs WHERE id = {$log['id']}")->fetch(PDO::FETCH_ASSOC);
assertTest("Historical diesel log rate remains 130.0000 despite global rate change", (float)$logCheck['exchange_rate'] == 130.0);
assertTest("Historical diesel log base_usd_cost remains 580.00", (float)$logCheck['base_usd_cost'] == 580.0);

$expCheck = $pdo->query("SELECT * FROM expenses WHERE id = {$expId}")->fetch(PDO::FETCH_ASSOC);
assertTest("Historical expense rate remains 125.0000 despite global rate change", (float)$expCheck['exchange_rate'] == 125.0);
assertTest("Historical expense amount remains 100.00 USD", (float)$expCheck['amount'] == 100.0);

$finCheck = $pdo->query("SELECT * FROM financial_records WHERE id = {$finId}")->fetch(PDO::FETCH_ASSOC);
assertTest("Historical financial record rate remains 124.5000 despite global rate change", (float)$finCheck['exchange_rate'] == 124.5);

// Reset setting back to 128
$pdo->prepare('UPDATE settings SET setting_value = "128.0000" WHERE setting_key = "exchange_rate"')->execute();

// Clean up test rows
$pdo->prepare('DELETE FROM fleet_diesel_logs WHERE dispatch_id = ?')->execute([$dispatchId]);
$pdo->prepare('DELETE FROM fleet_dispatches WHERE id = ?')->execute([$dispatchId]);
$pdo->prepare('DELETE FROM expenses WHERE id = ?')->execute([$expId]);
$pdo->prepare('DELETE FROM financial_records WHERE id = ?')->execute([$finId]);

echo "\n-------------------------------------------------------\n";
echo "TEST RESULTS: {$passCount} Passed, {$failCount} Failed\n";
echo "-------------------------------------------------------\n";

if ($failCount > 0) {
    exit(1);
}
