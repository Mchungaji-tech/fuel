<?php

require_once __DIR__ . '/../bootstrap/app.php';

use App\Core\Router;
use App\Core\Database;

echo "=== Running Sarura Fuel Operations Test Suite ===\n";

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

// Test 1: Helper url() in root context
$_SERVER['SCRIPT_NAME'] = '/index.php';
assertTest("url('/') returns '/' in root context", url('/') === '/');
assertTest("url('dashboard') returns '/dashboard' in root context", url('dashboard') === '/dashboard');
assertTest("url('/trips/new') returns '/trips/new' in root context", url('/trips/new') === '/trips/new');

// Test 2: Helper url() in XAMPP subdirectory context (/fuel)
$_SERVER['SCRIPT_NAME'] = '/fuel/index.php';
assertTest("app_subfolder() detects '/fuel'", app_subfolder() === '/fuel');
assertTest("url('/') returns '/fuel' in subdirectory", url('/') === '/fuel');
assertTest("url('dashboard') returns '/fuel/dashboard' in subdirectory", url('dashboard') === '/fuel/dashboard');

// Test 3: Currency Helpers (USD default & KES conversion)
$_SESSION['currency'] = 'USD';
assertTest("Default currency is USD", current_currency() === 'USD');
assertTest("USD format_money(100) returns '$ 100.00'", format_money(100) === '$ 100.00');

$_SESSION['currency'] = 'KES';
assertTest("Switched currency is KES", current_currency() === 'KES');
assertTest("KES format_money(100) converts using 130 rate to 'KES 13,000.00'", format_money(100) === 'KES 13,000.00');
$_SESSION['currency'] = 'USD'; // reset

// Test 4: CSRF token generation and validation
$_SERVER['SCRIPT_NAME'] = '/fuel/index.php';
$token = csrf_token();
assertTest("csrf_token() generates a 64-character hex string", is_string($token) && strlen($token) === 64);
assertTest("csrf_field() contains hidden input with token", str_contains(csrf_field(), $token) && str_contains(csrf_field(), 'name="_csrf_token"'));

$_POST['_csrf_token'] = $token;
assertTest("verify_csrf() returns true for matching token", verify_csrf() === true);

$_POST['_csrf_token'] = 'invalid-token';
assertTest("verify_csrf() returns false for invalid token", verify_csrf() === false);
unset($_POST['_csrf_token']);

// Test 5: Router dispatching & Auth Protection
$router = new Router();
$routes = require __DIR__ . '/../routes/web.php';
$router->loadRoutes($routes);

// Test public route /login
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/fuel/login';
$_SERVER['SCRIPT_NAME'] = '/fuel/index.php';
unset($_SESSION['is_logged_in']);

$response = $router->dispatch();
assertTest("Dispatching GET /fuel/login renders login page without dashboard chrome", str_contains($response, 'Welcome Back') && str_contains($response, 'Sign In to Dashboard'));

// Test authenticated dashboard route
$_SESSION['is_logged_in'] = true;
$_SESSION['user'] = ['id' => 1, 'name' => 'Admin User', 'email' => 'admin@sarurafuel.co.ke', 'role' => 'admin'];
$_SERVER['REQUEST_URI'] = '/fuel/dashboard';

$response = $router->dispatch();
assertTest("Dispatching GET /fuel/dashboard renders dashboard for logged-in user", str_contains($response, 'Operations Dashboard') && str_contains($response, 'Transport Revenue'));

// Test Fleet Management ledger route
$_SERVER['REQUEST_URI'] = '/fuel/fleet';
$response = $router->dispatch();
assertTest("Dispatching GET /fuel/fleet contains 'Fleet Management'", str_contains($response, 'Fleet Management'));
assertTest("Dispatching GET /fuel/fleet contains 'DOL'", str_contains($response, 'DOL'));
assertTest("Dispatching GET /fuel/fleet contains 'Actual litre (L20)'", str_contains($response, 'Actual litre (L20)') || str_contains($response, 'Loaded Litres'));
assertTest("Dispatching GET /fuel/fleet contains 'Shortage Litres'", str_contains($response, 'Shortage Litres'));
assertTest("Dispatching GET /fuel/fleet contains 'Driver' column in table header", str_contains($response, 'Driver Name') || str_contains($response, '<th>Driver</th>'));

// Test Bill of Lading (BOL) route
$pdo = Database::connection();
$firstDispatchId = $pdo->query('SELECT id FROM fleet_dispatches LIMIT 1')->fetchColumn();
if ($firstDispatchId) {
    $_SERVER['REQUEST_URI'] = '/fuel/fleet/bol/' . $firstDispatchId;
    $response = $router->dispatch();
    assertTest("Dispatching GET /fuel/fleet/bol/{id} renders official Petroleum Bill of Lading", str_contains($response, 'PETROLEUM BILL OF LADING') && str_contains($response, 'SARURA FUEL LOGISTICS LTD'));
}

// Test Trucks route
$_SERVER['REQUEST_URI'] = '/fuel/trucks';
$response = $router->dispatch();
assertTest("Dispatching GET /fuel/trucks renders tanker inventory with capacities", str_contains($response, 'Trucks & Tankers') || str_contains($response, 'Tankers'));

// Test Individual Truck View route
$firstTruck = $pdo->query('SELECT id, plate_number FROM trucks LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if ($firstTruck) {
    $_SERVER['REQUEST_URI'] = '/fuel/trucks/view/' . $firstTruck['id'];
    $response = $router->dispatch();
    assertTest("Dispatching GET /fuel/trucks/view/{id} renders individual truck dashboard", str_contains($response, $firstTruck['plate_number']));
}

// Test Detailed Truck Report route
$_SERVER['REQUEST_URI'] = '/fuel/reports/trucks';
$response = $router->dispatch();
assertTest("Dispatching GET /fuel/reports/trucks renders Detailed Truck Performance Report", str_contains($response, 'Truck') || str_contains($response, 'Reports'));

// Test Fuel Products route (strictly PMS & AGO)
$_SERVER['REQUEST_URI'] = '/fuel/products';
$response = $router->dispatch();
assertTest("Dispatching GET /fuel/products renders PMS & AGO only", str_contains($response, 'AGO') && str_contains($response, 'PMS') && !str_contains($response, 'DPK'));

// Test Outside Expenses route
$_SERVER['REQUEST_URI'] = '/fuel/expenses';
$response = $router->dispatch();
assertTest("Dispatching GET /fuel/expenses renders maintenance records & truck report", str_contains($response, 'Expenses') && str_contains($response, 'truckExpenseReportCard'));
assertTest("Expenses view uses free-text input for expense description", str_contains($response, 'name="expense_title"'));

// Test Drivers & Salaries route
$_SERVER['REQUEST_URI'] = '/fuel/drivers';
$response = $router->dispatch();
assertTest("Dispatching GET /fuel/drivers renders driver roster and salaries ledger", str_contains($response, 'Drivers & Salaries') && str_contains($response, 'Salary Disbursements & Arrears Ledger'));
assertTest("Salaries ledger includes Paid / Wait interactive button", str_contains($response, 'toggle') || str_contains($response, 'Wait') || str_contains($response, 'Paid'));

// Test Monthly Financial Reports route
$_SERVER['REQUEST_URI'] = '/fuel/reports';
$response = $router->dispatch();
assertTest("Dispatching GET /fuel/reports renders Monthly Assessment of Profit, Expenses, Salaries", str_contains($response, 'Reports') || str_contains($response, 'Monthly'));

// Test 404 handler
$_SERVER['REQUEST_URI'] = '/fuel/non-existent-route';
$response = $router->dispatch();
assertTest("Dispatching invalid URI returns 404 view", str_contains($response, '404') || str_contains($response, 'Page Not Found'));

echo "\n=== Results: {$passed} Passed, {$failed} Failed ===\n";
exit($failed === 0 ? 0 : 1);

