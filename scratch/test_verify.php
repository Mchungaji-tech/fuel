<?php
require_once __DIR__ . '/../bootstrap/app.php';

echo "Testing format_date_dol...\n";
$d1 = format_date_dol('2026-09-02');
$d2 = format_date_dol('2026-12-31');
$d3 = format_date_dol('2025-01-05');
echo "2026-09-02 => $d1\n";
echo "2026-12-31 => $d2\n";
echo "2025-01-05 => $d3\n";
assert($d1 === '02/09/26', "Expected 02/09/26, got $d1");
assert($d2 === '31/12/26', "Expected 31/12/26, got $d2");
assert($d3 === '05/01/25', "Expected 05/01/25, got $d3");

echo "\nTesting SQLite Schema...\n";
$pdo = \App\Core\Database::connection();
$cols = $pdo->query("PRAGMA table_info(fleet_dispatches)")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($cols, 'name');
echo "Fleet dispatch columns: " . implode(', ', $colNames) . "\n";
assert(in_array('delivered_litres', $colNames), "Missing delivered_litres");
assert(in_array('final_payout', $colNames), "Missing final_payout");
assert(in_array('payout_difference', $colNames), "Missing payout_difference");
assert(in_array('shortage_notes', $colNames), "Missing shortage_notes");

echo "\nTesting Trucks ownership normalization...\n";
$trucks = $pdo->query("SELECT plate_number, ownership_type, owner_name FROM trucks")->fetchAll(PDO::FETCH_ASSOC);
foreach ($trucks as $t) {
    echo "Truck: {$t['plate_number']} => Ownership: {$t['ownership_type']} (Owner: {$t['owner_name']})\n";
    assert(in_array($t['ownership_type'], ['Owner', 'Contract']), "Unexpected ownership: " . $t['ownership_type']);
}

echo "\nTesting Controller instantiation...\n";
require_once __DIR__ . '/../app/Controllers/FleetController.php';
require_once __DIR__ . '/../app/Controllers/TruckController.php';
require_once __DIR__ . '/../app/Controllers/ExpenseController.php';

$fc = new \App\Controllers\FleetController();
$tc = new \App\Controllers\TruckController();
$ec = new \App\Controllers\ExpenseController();
echo "FleetController, TruckController, ExpenseController instantiated successfully.\n";

echo "\nTesting View Rendering...\n";
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/fleet';
$fleetHtml = $fc->index();
assert(str_contains($fleetHtml, 'Fleet Management'), "Fleet HTML missing title");
assert(str_contains($fleetHtml, 'DOL'), "Fleet HTML missing DOL header");
assert(str_contains($fleetHtml, 'Shortage'), "Fleet HTML missing Shortage label");
echo "Fleet index view rendered successfully (" . strlen($fleetHtml) . " bytes).\n";

$_SERVER['REQUEST_URI'] = '/trucks';
$truckHtml = $tc->index();
assert(str_contains($truckHtml, 'Trucks & Bulk Tankers'), "Trucks HTML missing title");
assert(str_contains($truckHtml, 'Owner / Contract'), "Trucks HTML missing Owner / Contract header");
echo "Trucks index view rendered successfully (" . strlen($truckHtml) . " bytes).\n";

$_SERVER['REQUEST_URI'] = '/expenses';
$expHtml = $ec->index();
assert(str_contains($expHtml, 'Expenses'), "Expenses HTML missing title");
echo "Expenses index view rendered successfully (" . strlen($expHtml) . " bytes).\n";

$_SERVER['REQUEST_URI'] = '/trucks/view/1';
$truckViewHtml = $tc->view('1');
assert(str_contains($truckViewHtml, 'truckDispatchSearch'), "Truck view missing dispatch search input");
assert(str_contains($truckViewHtml, 'truckExpenseSearch'), "Truck view missing expense search input");
echo "Truck view rendered successfully (" . strlen($truckViewHtml) . " bytes).\n";

echo "\nALL RENDERING AND INTEGRATION TESTS PASSED 100% CLEANLY!\n";

