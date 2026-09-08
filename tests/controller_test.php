<?php

require_once __DIR__ . '/../bootstrap/app.php';

use App\Controllers\AuthController;
use App\Controllers\DriverController;
use App\Controllers\TripController;
use App\Controllers\InvoiceController;
use App\Core\Database;

echo "=== Running Controller & DB Integration Test Suite ===\n";

$passed = 0;
$failed = 0;

function assertTest(string $name, bool $condition): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$name}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$name}\n";
        $failed++;
    }
}

$_SERVER['SCRIPT_NAME'] = '/fuel/index.php';

// Test AuthController with valid credentials
$pdo = Database::connection();
$auth = new AuthController();
$_POST['email'] = 'admin@sarurafuel.co.ke';
$_POST['password'] = 'admin123';
$_SESSION = [];

// We test user fetch & password verification logic
$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
$stmt->execute(['admin@sarurafuel.co.ke']);
$user = $stmt->fetch();
assertTest("Admin user exists in database", $user !== false && $user['email'] === 'admin@sarurafuel.co.ke');
assertTest("Default password verifies successfully", password_verify('admin123', $user['password']));

// Test DriverController
$driverCtrl = new DriverController();
$driverName = 'Test Driver ' . rand(1000, 9999);
$pdo->prepare('INSERT INTO drivers (name, phone, license_number, license_class, truck, status) VALUES (?, ?, ?, ?, ?, ?)')
    ->execute([$driverName, '+254700000000', 'B-TEST', 'B-Class', 'KAA 000A', 'Active']);

$driversHtml = $driverCtrl->index();
assertTest("DriverController index contains inserted driver", str_contains($driversHtml, $driverName));

// Test TripController
$tripCtrl = new TripController();
$tripNumber = 'TRP-' . rand(1000, 9999);
$pdo->prepare('INSERT INTO trips (trip_number, customer, truck, driver, route, load_quantity, status) VALUES (?, ?, ?, ?, ?, ?, ?)')
    ->execute([$tripNumber, 'Test Customer', 'KAA 000A', $driverName, 'Nairobi -> Eldoret', '10,000 L', 'Planned']);

$tripsHtml = $tripCtrl->index();
assertTest("TripController index contains inserted trip", str_contains($tripsHtml, $tripNumber));

// Test InvoiceController
$invoiceCtrl = new InvoiceController();
$invoiceNumber = 'INV-' . rand(1000, 9999);
$pdo->prepare('INSERT INTO invoices (invoice_number, client, amount, due_date, status) VALUES (?, ?, ?, ?, ?)')
    ->execute([$invoiceNumber, 'Test Customer', 'KES 150,000', date('Y-m-d'), 'Pending']);

$invoicesHtml = $invoiceCtrl->index();
assertTest("InvoiceController index contains inserted invoice", str_contains($invoicesHtml, $invoiceNumber));

echo "\n=== Results: {$passed} Passed, {$failed} Failed ===\n";
exit($failed === 0 ? 0 : 1);
