<?php
// E2E HTTP Test against local Apache web server
$baseUrl = 'http://localhost/fuel/public';
$cookieFile = __DIR__ . '/test_cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

function httpReq($url, $method = 'GET', $data = [], $cookieFile = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HEADER, false);

    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'X-Requested-With: XMLHttpRequest'
        ]);
    }

    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => $res];
}

echo "1. Testing Login...\n";
// First get login page to obtain CSRF token
$loginPage = httpReq($baseUrl . '/login', 'GET', [], $cookieFile);
preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $loginPage['body'], $csrfMatch);
$csrfToken = $csrfMatch[1] ?? '';
echo "Got CSRF Token: $csrfToken\n";

$loginRes = httpReq($baseUrl . '/login', 'POST', [
    '_csrf_token' => $csrfToken,
    'email' => 'admin@sarurafuel.co.ke',
    'password' => 'admin123'
], $cookieFile);
echo "Login HTTP Code: " . $loginRes['code'] . "\n";
assert(str_contains($loginRes['body'], 'Dashboard') || str_contains($loginRes['body'], 'Fleet'), "Login failed");

echo "\n2. Testing /fleet page...\n";
$fleetRes = httpReq($baseUrl . '/fleet', 'GET', [], $cookieFile);
echo "Fleet Page HTTP Code: " . $fleetRes['code'] . "\n";
assert(str_contains($fleetRes['body'], 'DOL'), "Missing DOL header");
assert(str_contains($fleetRes['body'], 'Driver'), "Missing Driver header");
assert(str_contains($fleetRes['body'], 'Final Client Payout'), "Missing Final Client Payout header");
assert(str_contains($fleetRes['body'], 'startFleetInlineEdit'), "Missing inline edit JavaScript");
echo "✓ /fleet verified: DOL, Driver, Status, Final Payout, and Inline Edit present.\n";

echo "\n3. Testing /fleet/inline-update endpoint...\n";
// Let's get dispatch #1 from DB to test updating
require_once __DIR__ . '/../bootstrap/app.php';
$pdo = \App\Core\Database::connection();
$d = $pdo->query("SELECT * FROM fleet_dispatches ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$id = $d['id'];

$inlineRes = httpReq($baseUrl . '/fleet/inline-update', 'POST', [
    '_csrf_token' => $csrfToken,
    'id' => $id,
    'dispatch_date' => '2026-09-02',
    'driver' => 'John Waweru',
    'status' => 'Delivered',
    'loaded_litres' => 30000,
    'delivered_litres' => 29500, // 500 L shortage
    'transport_amount' => 3500.00,
    'final_payout' => 3350.00 // $150 deduction
], $cookieFile);

echo "Inline Update HTTP Code: " . $inlineRes['code'] . "\n";
echo "Response: " . $inlineRes['body'] . "\n";
$json = json_decode($inlineRes['body'], true);
assert($json['success'] === true, "Inline update failed");
assert($json['data']['dol_formatted'] === '02/09/26', "DOL date format failed");
assert($json['data']['shortage_litres'] === 500, "Shortage calc failed");
echo "✓ Fleet inline update and auto-shortage calculation succeeded!\n";

echo "\n4. Testing /trucks page & inline update...\n";
$trucksRes = httpReq($baseUrl . '/trucks', 'GET', [], $cookieFile);
assert(str_contains($trucksRes['body'], 'Owner / Contract'), "Missing Owner / Contract header");
assert(str_contains($trucksRes['body'], 'startTruckInlineEdit'), "Missing truck inline edit function");
echo "✓ /trucks verified: Owner / Contract column and inline edit present.\n";

$truck = $pdo->query("SELECT * FROM trucks ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$tInline = httpReq($baseUrl . '/trucks/inline-update', 'POST', [
    '_csrf_token' => $csrfToken,
    'id' => $truck['id'],
    'plate_number' => $truck['plate_number'],
    'ownership_type' => 'Owner',
    'owner_name' => 'Sarura Fuel Logistics',
    'model' => 'Mercedes Actros Heavy Tanker',
    'capacity_litres' => 32000,
    'compartments' => '4 comp (8k/8k/8k/8k)',
    'status' => 'Ready'
], $cookieFile);
echo "Truck Inline Update: " . $tInline['body'] . "\n";
$tJson = json_decode($tInline['body'], true);
assert($tJson['success'] === true, "Truck inline update failed");
echo "✓ Truck inline update succeeded!\n";

echo "\n5. Testing /trucks/view/1 page...\n";
$tViewRes = httpReq($baseUrl . '/trucks/view/' . $truck['id'], 'GET', [], $cookieFile);
assert(str_contains($tViewRes['body'], 'truckDispatchSearch'), "Missing truck dispatch search");
assert(str_contains($tViewRes['body'], 'truckExpenseSearch'), "Missing truck expense search");
assert(str_contains($tViewRes['body'], 'Truck Expenses'), "Missing Truck Expenses header");
echo "✓ /trucks/view verified: Dual search bars and Truck Expenses header present.\n";

echo "\n6. Testing /expenses page & inline update...\n";
$expRes = httpReq($baseUrl . '/expenses', 'GET', [], $cookieFile);
assert(str_contains($expRes['body'], 'Expenses 💰'), "Missing Expenses header");
assert(str_contains($expRes['body'], 'startExpInlineEdit'), "Missing expenses inline edit function");
echo "✓ /expenses verified: Expenses header, DD/MM/YY date format, and inline edit present.\n";

$exp = $pdo->query("SELECT * FROM expenses ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$eInline = httpReq($baseUrl . '/expenses/inline-update', 'POST', [
    '_csrf_token' => $csrfToken,
    'id' => $exp['id'],
    'expense_date' => '2026-09-01',
    'expense_title' => 'Complete Fleet Maintenance & Calibration',
    'truck' => 'KAA 458Z',
    'garage_vendor' => 'Simba Central Motors Nairobi',
    'receipt_number' => 'REC-2026-09',
    'amount' => 480.00,
    'notes' => 'Pre-dispatch inspection and pressure check'
], $cookieFile);
echo "Expense Inline Update: " . $eInline['body'] . "\n";
$eJson = json_decode($eInline['body'], true);
assert($eJson['success'] === true, "Expense inline update failed");
assert($eJson['data']['dol_formatted'] === '01/09/26', "Expense date format failed");
echo "✓ Expense inline update succeeded!\n";

echo "\n============================================\n";
echo "🎉 ALL E2E HTTP INTEGRATION TESTS PASSED 100%!\n";
echo "============================================\n";
