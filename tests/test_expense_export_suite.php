<?php
/**
 * Test Suite: Expense Export & Cross-Database Compatibility
 * Validates that specific filters (truck case variations, general business, date range, month/year, search)
 * and spreadsheet export formats work properly without HTTP 500 errors.
 */

require_once __DIR__ . '/../bootstrap/app.php';

if (!defined('TESTING_MODE')) {
    define('TESTING_MODE', true);
}

// Helper assertion function
function assertCondition(string $name, bool $pass, string $detail = '') {
    if ($pass) {
        echo " [\033[32mPASS\033[0m] {$name}\n";
    } else {
        echo " [\033[31mFAIL\033[0m] {$name}" . ($detail ? " - {$detail}" : '') . "\n";
        exit(1);
    }
}

echo "=== Expense Export & Cross-Database Suite ===\n\n";

$pdo = \App\Core\Database::connection();

// 1. Verify no strftime remaining in Controllers
$controllerDir = __DIR__ . '/../app/Controllers';
$files = glob($controllerDir . '/*.php');
$foundStrftime = false;
$foundIn = [];
foreach ($files as $f) {
    $content = file_get_contents($f);
    if (stripos($content, 'strftime(') !== false) {
        $foundStrftime = true;
        $foundIn[] = basename($f);
    }
}
assertCondition("Zero occurrences of SQLite-only strftime() in Controllers", !$foundStrftime, "Found in: " . implode(', ', $foundIn));

// 2. Set up authenticated session for testing
$_SESSION['user'] = [
    'id' => 1,
    'name' => 'Test Admin',
    'role' => 'Administrator',
    'permissions' => ['all', 'financials']
];

// Ensure we have test expense records including General Business
$genCheck = (int)$pdo->query("SELECT COUNT(*) FROM expenses WHERE truck IS NULL OR truck = '' OR LOWER(truck) LIKE '%general%'")->fetchColumn();
if ($genCheck === 0) {
    $pdo->exec("INSERT INTO expenses (expense_date, expense_title, truck, amount, exchange_rate, garage_vendor, receipt_status, notes, created_at)
        VALUES ('2026-09-10', 'Yard Security Lights', 'General Business', 60.00, 128.0000, 'Hardware City', 'Received', 'Yard maintenance', '2026-09-10 12:00:00')");
}
$truckCheck = (int)$pdo->query("SELECT COUNT(*) FROM expenses WHERE LOWER(TRIM(truck)) = 'kaa 458z'")->fetchColumn();
if ($truckCheck === 0) {
    $pdo->exec("INSERT INTO expenses (expense_date, expense_title, truck, amount, exchange_rate, garage_vendor, receipt_status, notes, created_at)
        VALUES ('2026-09-04', 'Brake Linings', 'KAA 458Z', 150.00, 128.0000, 'Simba Garage', 'Received', 'Brakes replacement', '2026-09-04 10:00:00')");
}

$expCtrl = new \App\Controllers\ExpenseController();

// 3. Test exportCheck: Case-insensitive truck matching
ob_start();
$_GET = ['truck' => 'kaa 458z'];
try {
    $expCtrl->exportCheck();
} catch (\Throwable $e) {}
$json = json_decode(ob_get_clean(), true);
assertCondition("exportCheck matches lowercase truck 'kaa 458z'", ($json['count'] ?? 0) >= 1, "Count was: " . ($json['count'] ?? 0));

// 4. Test exportCheck: Space-insensitive truck matching (e.g. 'kaa458z')
ob_start();
$_GET = ['truck' => 'kaa458z'];
try {
    $expCtrl->exportCheck();
} catch (\Throwable $e) {}
$json = json_decode(ob_get_clean(), true);
assertCondition("exportCheck matches space-insensitive truck 'kaa458z'", ($json['count'] ?? 0) >= 1, "Count was: " . ($json['count'] ?? 0));

// 5. Test exportCheck: General Business matching
ob_start();
$_GET = ['truck' => 'General Business'];
try {
    $expCtrl->exportCheck();
} catch (\Throwable $e) {}
$json = json_decode(ob_get_clean(), true);
assertCondition("exportCheck matches 'General Business'", ($json['count'] ?? 0) >= 1, "Count was: " . ($json['count'] ?? 0));

// 6. Test exportCheck: 'general' matching
ob_start();
$_GET = ['truck' => 'general'];
try {
    $expCtrl->exportCheck();
} catch (\Throwable $e) {}
$json = json_decode(ob_get_clean(), true);
assertCondition("exportCheck matches 'general'", ($json['count'] ?? 0) >= 1, "Count was: " . ($json['count'] ?? 0));

// 7. Test exportCheck: Specific Month & Year
ob_start();
$_GET = ['month' => '9', 'year' => '2026'];
try {
    $expCtrl->exportCheck();
} catch (\Throwable $e) {}
$json = json_decode(ob_get_clean(), true);
assertCondition("exportCheck matches specific Month 9 & Year 2026", ($json['count'] ?? 0) >= 1, "Count was: " . ($json['count'] ?? 0));

// 8. Test exportCheck: Date Range
ob_start();
$_GET = ['from_date' => '2026-09-01', 'to_date' => '2026-09-30'];
try {
    $expCtrl->exportCheck();
} catch (\Throwable $e) {}
$json = json_decode(ob_get_clean(), true);
assertCondition("exportCheck matches date range 2026-09-01 to 2026-09-30", ($json['count'] ?? 0) >= 1, "Count was: " . ($json['count'] ?? 0));

// 9. Test exportCheck: Search keyword
ob_start();
$_GET = ['search' => 'Brake'];
try {
    $expCtrl->exportCheck();
} catch (\Throwable $e) {}
$json = json_decode(ob_get_clean(), true);
assertCondition("exportCheck matches search keyword 'Brake'", ($json['count'] ?? 0) >= 1, "Count was: " . ($json['count'] ?? 0));

// 10. Test full export execution to CSV with specific truck & month
if (!defined('TESTING_MODE')) {
    define('TESTING_MODE', true);
}

$_GET = [
    'truck' => 'kaa 458z',
    'month' => '9',
    'year' => '2026',
    'format' => 'csv'
];

ob_start();
try {
    $expCtrl->export();
} catch (\Throwable $e) {}
$csvOutput = ob_get_clean();

$hasDateHeader = strpos($csvOutput, 'Expense Date') !== false;
$hasBrakeLinings = stripos($csvOutput, 'Brake') !== false;
assertCondition("Expense export to CSV generates successfully for specific truck & month", $hasDateHeader && $hasBrakeLinings);

// 11. Test full export execution to XLSX with specific date range
$_GET = [
    'from_date' => '2026-09-01',
    'to_date' => '2026-09-30',
    'format' => 'xlsx'
];

ob_start();
try {
    $expCtrl->export();
} catch (\Throwable $e) {}
$xlsxOutput = ob_get_clean();

$isZip = (substr($xlsxOutput, 0, 4) === "PK\x03\x04");
assertCondition("Expense export to XLSX produces valid OpenXML workbook for date range", $isZip && strlen($xlsxOutput) > 1500);

echo "\n=== All Expense Export Tests Passed Successfully! ===\n";
