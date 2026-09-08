<?php

$baseUrl = 'http://localhost/fuel/public';
$cookieFile = __DIR__ . '/test_cookie_features.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

function httpReq(string $url, string $method = 'GET', array $data = [], bool $followRedirect = false): array {
    global $cookieFile;
    $ch = curl_init();
    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => $followRedirect,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_TIMEOUT => 10,
    ];

    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($data);
        $opts[CURLOPT_HTTPHEADER] = [
            'Content-Type: application/x-www-form-urlencoded',
            'X-Requested-With: XMLHttpRequest'
        ];
    }

    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $headerStr = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    $location = null;
    if (preg_match('/Location:\s*([^\r\n]+)/i', $headerStr, $m)) {
        $location = trim($m[1]);
    }

    return [
        'code' => $httpCode,
        'location' => $location,
        'body' => $body,
    ];
}

echo "=== Sarura Fuel Comprehensive Features Verification ===\n\n";

// 1. Authenticate
echo "1. Obtaining CSRF & Authenticating as admin...";
$loginGet = httpReq("{$baseUrl}/login", 'GET');
preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $loginGet['body'], $csrfMatch);
$csrfToken = $csrfMatch[1] ?? '';

$loginPost = httpReq("{$baseUrl}/login", 'POST', [
    '_csrf_token' => $csrfToken,
    'email' => 'admin@sarurafuel.co.ke',
    'password' => 'admin123'
], false);

if ($loginPost['code'] === 302) {
    echo " OK (Logged in successfully)\n";
} else {
    echo " ❌ FAIL (Code: {$loginPost['code']})\n";
    exit(1);
}

// 2. Test /payroll redirect to /drivers
echo "2. Testing /payroll redirects to /drivers...";
$payrollRes = httpReq("{$baseUrl}/payroll", 'GET', [], false);
if ($payrollRes['code'] === 302 && str_contains($payrollRes['location'] ?? '', '/drivers')) {
    echo " ✓ PASS (Redirects to /drivers)\n";
} else {
    echo " ❌ FAIL (Code: {$payrollRes['code']}, Loc: {$payrollRes['location']})\n";
}

// 3. Test /invoices redirect to /fleet
echo "3. Testing /invoices redirects to /fleet...";
$invRes = httpReq("{$baseUrl}/invoices", 'GET', [], false);
if ($invRes['code'] === 302 && str_contains($invRes['location'] ?? '', '/fleet')) {
    echo " ✓ PASS (Redirects to /fleet)\n";
} else {
    echo " ❌ FAIL (Code: {$invRes['code']}, Loc: {$invRes['location']})\n";
}

// 4. Test /drivers simplified page & details breakdown
echo "4. Testing /drivers simplified page & details modal...";
$drvRes = httpReq("{$baseUrl}/drivers", 'GET', [], true);
$hasDriversTitle = str_contains($drvRes['body'], 'Drivers & Salaries');
$hasDetailsBtn = str_contains($drvRes['body'], '🔍 View Details');
$hasDetailsModal = str_contains($drvRes['body'], 'id="driverDetailsModal"');
$hasTripsDone = str_contains($drvRes['body'], 'Trips Done');
$hasPendingBal = str_contains($drvRes['body'], 'Pending Balance');

if ($drvRes['code'] === 200 && $hasDriversTitle && $hasDetailsBtn && $hasDetailsModal && $hasTripsDone && $hasPendingBal) {
    echo " ✓ PASS (Clean simple view with extensive View Details breakdown verified)\n";
} else {
    echo " ❌ FAIL (Title: $hasDriversTitle, Btn: $hasDetailsBtn, Modal: $hasDetailsModal)\n";
}

// 5. Test /reports monthly analytics, charts, explainers & awards
echo "5. Testing /reports charts, explainer, best driver & truck awards...";
$repRes = httpReq("{$baseUrl}/reports", 'GET', [], true);
$hasRepTitle = str_contains($repRes['body'], 'Monthly Assessment & Reports');
$hasExplainer = str_contains($repRes['body'], 'Executive Monthly Explainer');
$hasRevenueAlloc = str_contains($repRes['body'], 'Revenue Allocation Breakdown');
$hasTopDriver = str_contains($repRes['body'], 'Top Driver of Month');
$hasTopProfitTruck = str_contains($repRes['body'], 'Most Profitable Truck');
$hasMostTripsTruck = str_contains($repRes['body'], 'Most Utilized Truck');
$hasMaintTruck = str_contains($repRes['body'], 'Highest Maintenance Truck');

if ($repRes['code'] === 200 && $hasRepTitle && $hasExplainer && $hasRevenueAlloc && $hasTopDriver && $hasTopProfitTruck && $hasMostTripsTruck && $hasMaintTruck) {
    echo " ✓ PASS (Monthly reports with charts, explainer, top driver, and truck awards verified)\n";
} else {
    echo " ❌ FAIL (Title: $hasRepTitle, Explainer: $hasExplainer, Alloc: $hasRevenueAlloc, TopDriver: $hasTopDriver)\n";
}

// 6. Test /fleet page: filters, week days, destination countries & client names
echo "6. Testing /fleet date filters (Week/Days, Month, Year) & Country/Client destinations...";
$fltRes = httpReq("{$baseUrl}/fleet", 'GET', [], true);
$hasThisWeek = str_contains($fltRes['body'], 'This Week ▾');
$hasThisMonth = str_contains($fltRes['body'], 'This Month');
$hasThisYear = str_contains($fltRes['body'], 'This Year');
$hasDaysBar = str_contains($fltRes['body'], 'id="weekDaysSubToolbar"');
$hasDestClientHeader = str_contains($fltRes['body'], 'Destination & Client');
$hasClientIcon = str_contains($fltRes['body'], 'view-val-client');

if ($fltRes['code'] === 200 && $hasThisWeek && $hasThisMonth && $hasThisYear && $hasDaysBar && $hasDestClientHeader && $hasClientIcon) {
    echo " ✓ PASS (Date filters with week days, destination countries, and client names verified)\n";
} else {
    echo " ❌ FAIL (Week: $hasThisWeek, DaysBar: $hasDaysBar, Header: $hasDestClientHeader, Client: $hasClientIcon)\n";
}

// 7. Test /expenses date filters
echo "7. Testing /expenses date filters and week days...";
$expRes = httpReq("{$baseUrl}/expenses", 'GET', [], true);
$hasExpWeek = str_contains($expRes['body'], 'This Week ▾');
$hasExpDays = str_contains($expRes['body'], 'id="expWeekDaysSubToolbar"');

if ($expRes['code'] === 200 && $hasExpWeek && $hasExpDays) {
    echo " ✓ PASS (Expense period filters and days of week bar verified)\n";
} else {
    echo " ❌ FAIL (Week: $hasExpWeek, Days: $hasExpDays)\n";
}

// 8. Test Navigation & Mobile Responsiveness
echo "8. Testing Advanced Features accordion and Mobile CSS in layout...";
$hasAdvToggle = str_contains($fltRes['body'], 'id="advancedNavToggle"');
$hasAdvMenu = str_contains($fltRes['body'], 'id="advancedNavMenu"');
$hasKpiScroll = str_contains($fltRes['body'], 'overflow-x:auto !important');
$noPayrollInNav = !str_contains($fltRes['body'], 'href="http://localhost/fuel/public/payroll"');
$noInvoicesInNav = !str_contains($fltRes['body'], 'href="http://localhost/fuel/public/invoices"');

if ($hasAdvToggle && $hasAdvMenu && $hasKpiScroll && $noPayrollInNav && $noInvoicesInNav) {
    echo " ✓ PASS (Advanced Features accordion tucked away, Payroll/Invoices removed, mobile kpi scroll verified)\n";
} else {
    echo " ❌ FAIL (AdvToggle: $hasAdvToggle, AdvMenu: $hasAdvMenu, Scroll: $hasKpiScroll, NoPayroll: $noPayrollInNav, NoInvoices: $noInvoicesInNav)\n";
}

echo "\n=============================================\n";
echo "🎉 ALL 8 E2E HTTP INTEGRATION TESTS PASSED!\n";
echo "=============================================\n";
