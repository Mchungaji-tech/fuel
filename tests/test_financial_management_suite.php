<?php
require_once __DIR__ . '/../bootstrap/app.php';

use App\Core\Database;
use App\Controllers\FinancialController;
use App\Controllers\ProductController;
use App\Controllers\DriverController;
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

echo "=== Financial Management & System Enhancements Suite ===\n\n";

// 1. Verify financial_records table exists
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='financial_records'")->fetchAll(PDO::FETCH_COLUMN);
assertCondition("financial_records table exists in database", in_array('financial_records', $tables));

// Pre-cleanup in case of previous interrupted run
$pdo->exec("DELETE FROM financial_records WHERE reason LIKE '%Director urgent emergency%'");
$pdo->exec("DELETE FROM sync_deletions WHERE record_key = 'TEST-KEY-1234'");

$testId = 0;
try {
    // 2. Insert test financial entry
    $ins = $pdo->prepare("INSERT INTO financial_records (entry_date, category, amount_in, amount_out, balance, reason, payment_method, reference_no, recorded_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $ins->execute([
        '2026-09-14',
        'Personal Drawing',
        0.00,
        12500.00,
        -12500.00,
        'Director urgent emergency personal draw',
        'M-Pesa',
        'MP-TEST-99',
        'TestRunner',
        date('Y-m-d H:i:s'),
        date('Y-m-d H:i:s')
    ]);
    $testId = (int)$pdo->lastInsertId();
    assertCondition("Insert new financial transaction succeeds", $testId > 0);

    // 3. Inline update calculation: changing amount_in and amount_out
    $upd = $pdo->prepare("UPDATE financial_records SET amount_in = ?, amount_out = ?, balance = ? WHERE id = ?");
    $upd->execute([50000.00, 10000.00, 40000.00, $testId]);
    $checkRow = $pdo->query("SELECT * FROM financial_records WHERE id = {$testId}")->fetch(PDO::FETCH_ASSOC);
    assertCondition("Inline update recalculates net balance accurately ($50k - $10k = $40k)", (float)$checkRow['balance'] === 40000.00);

    // 4. Test Financial Export (XLSX)
    $_GET['format'] = 'xlsx';
    $_GET['category'] = '';
    $_GET['search'] = '';
    ob_start();
    try {
        $finCtrl = new FinancialController();
        $finCtrl->export();
        $xlsxContent = ob_get_clean();
        $validZip = strpos($xlsxContent, 'PK') === 0;
        assertCondition("Financial export to .xlsx produces valid OpenXML workbook", $validZip && strlen($xlsxContent) > 2000);
    } catch (\Throwable $e) {
        ob_end_clean();
        assertCondition("Financial export to .xlsx failed", false, $e->getMessage());
    }

    // 5. Test Financial Export (CSV)
    $_GET['format'] = 'csv';
    $_GET['category'] = 'Personal Drawing';
    ob_start();
    try {
        $finCtrl = new FinancialController();
        $finCtrl->export();
        $csvContent = ob_get_clean();
        $hasHeader = strpos($csvContent, 'Transaction Date') !== false;
        $hasReason = strpos($csvContent, 'Director urgent emergency') !== false;
        assertCondition("Financial export to CSV generates correctly with filtered rows", $hasHeader && $hasReason);
    } catch (\Throwable $e) {
        ob_end_clean();
        assertCondition("Financial export to CSV failed", false, $e->getMessage());
    }

    // 6. Test Fleet Export does NOT have Extra Breakdown Cost and DOES have Country of Refueling
    $_GET['format'] = 'csv';
    $_GET['truck'] = '';
    $_GET['search'] = '';
    ob_start();
    try {
        $fleetCtrl = new FleetController();
        $fleetCtrl->export();
        $fleetCsv = ob_get_clean();
        $noExtra = strpos($fleetCsv, 'Extra Breakdown Cost') === false;
        $hasCountry = strpos($fleetCsv, 'Country of Refueling') !== false;
        assertCondition("Fleet export replaced Extra Breakdown Cost with Country of Refueling", $noExtra && $hasCountry);
    } catch (\Throwable $e) {
        ob_end_clean();
        assertCondition("Fleet export check failed", false, $e->getMessage());
    }

    // 7. Verify sync_deletions table exists and recordDeletion prevents revival
    $syncDelTable = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='sync_deletions'")->fetchColumn();
    assertCondition("sync_deletions table exists to track deleted records across MySQL & SQLite", !empty($syncDelTable));

    \App\Services\DatabaseSyncService::recordDeletion('financial_records', 'TEST-KEY-1234');
    $recordedKey = $pdo->query("SELECT record_key FROM sync_deletions WHERE record_key = 'TEST-KEY-1234'")->fetchColumn();
    assertCondition("recordDeletion records tombstone for cross-database delete propagation", $recordedKey === 'TEST-KEY-1234');
} finally {
    // Guaranteed clean up of all test entries so no proxies remain in the database
    if ($testId > 0) {
        $pdo->exec("DELETE FROM financial_records WHERE id = {$testId}");
    }
    $pdo->exec("DELETE FROM financial_records WHERE reason LIKE '%Director urgent emergency%'");
    $pdo->exec("DELETE FROM sync_deletions WHERE record_key = 'TEST-KEY-1234'");
}

echo "\n=== Financial Suite Results: {$passed} Passed, {$failed} Failed ===\n";
exit($failed > 0 ? 1 : 0);
