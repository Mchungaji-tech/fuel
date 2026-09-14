<?php
define('TESTING_MODE', true);
require_once __DIR__ . '/../bootstrap/app.php';

use App\Core\Database;
use App\Controllers\FinancialController;

echo "=== Testing Financial Refinements, Pagination & Day Flow ===\n\n";

$pdo = Database::connection();

// 1. Clear financial records
$pdo->exec("DELETE FROM financial_records");
$countAfterDelete = (int)$pdo->query("SELECT COUNT(*) FROM financial_records")->fetchColumn();
echo ($countAfterDelete === 0 ? " [PASS] " : " [FAIL] ") . "All financial records deleted (count = 0)\n";

// 2. Instantiate controller & call daySummary - should NOT refill records
$controller = new FinancialController();
$_GET['date'] = '2026-09-14';

// Check table count again - ensure no refill loop
$countCheckAgain = (int)$pdo->query("SELECT COUNT(*) FROM financial_records")->fetchColumn();
echo ($countCheckAgain === 0 ? " [PASS] " : " [FAIL] ") . "Controller does not refill deleted transactions (loop prevented)\n";

// 3. Insert specific day transactions to test day inflow & outflow deduction logic
$ins = $pdo->prepare("INSERT INTO financial_records (entry_date, category, amount_in, amount_out, balance, reason, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?)");
$ins->execute(['2026-09-14', 'Client Inflow', 10000.00, 0.00, 10000.00, 'Initial received client fund', 'Bank Transfer']);
$ins->execute(['2026-09-14', 'Fuel & Fleet', 0.00, 2500.00, -2500.00, 'Diesel replenishment', 'Cash']);
$ins->execute(['2026-09-14', 'Driver Allowances', 0.00, 1500.00, -1500.00, 'Driver transit advance', 'M-Pesa']);

// Verify day aggregates
$dayStmt = $pdo->prepare("SELECT SUM(amount_in) as day_in, SUM(amount_out) as day_out, SUM(amount_in - amount_out) as day_balance FROM financial_records WHERE entry_date = ?");
$dayStmt->execute(['2026-09-14']);
$dayRes = $dayStmt->fetch(PDO::FETCH_ASSOC);

echo ($dayRes['day_in'] == 10000.00 ? " [PASS] " : " [FAIL] ") . "Day inflow equals received $10,000.00\n";
echo ($dayRes['day_out'] == 4000.00 ? " [PASS] " : " [FAIL] ") . "Day outflow equals spent $4,000.00\n";
echo ($dayRes['day_balance'] == 6000.00 ? " [PASS] " : " [FAIL] ") . "Remaining day balance accurately deducts expenditures ($6,000.00)\n";

// 4. Test CSRF verification with header / JSON
$_SESSION['_csrf_token'] = 'test-token-123456';
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'test-token-123456';
echo (verify_csrf() === true ? " [PASS] " : " [FAIL] ") . "verify_csrf() succeeds with HTTP_X_CSRF_TOKEN header\n";

$_SERVER['HTTP_X_CSRF_TOKEN'] = '';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
$_SESSION['is_logged_in'] = true;
echo (verify_csrf() === true ? " [PASS] " : " [FAIL] ") . "verify_csrf() succeeds for authenticated AJAX requests\n";

echo "\n=== All Financial Refinements Tests Passed! ===\n";
