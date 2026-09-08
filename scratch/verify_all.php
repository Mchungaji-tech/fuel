<?php
$files = [
    'routes/web.php',
    'app/Core/Database.php',
    'app/Controllers/FleetController.php',
    'app/Controllers/DriverController.php',
    'app/Controllers/ReportController.php',
    'resources/views/layouts/app.php',
    'resources/views/drivers/index.php',
    'resources/views/reports/index.php',
    'resources/views/fleet/index.php',
    'resources/views/expenses/index.php',
];

$allOk = true;
foreach ($files as $f) {
    $out = [];
    $ret = 0;
    exec("c:\\xampp\\php\\php.exe -l " . escapeshellarg($f), $out, $ret);
    if ($ret !== 0) {
        echo "❌ Syntax error in {$f}:\n" . implode("\n", $out) . "\n";
        $allOk = false;
    } else {
        echo "✓ OK: {$f}\n";
    }
}

if (!$allOk) {
    exit(1);
}
echo "\nAll files passed PHP syntax verification!\n";
