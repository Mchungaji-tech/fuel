<?php
require __DIR__ . '/../bootstrap/app.php';

$pdo = App\Core\Database::connection();

// Sync any dispatches in fleet_dispatches into trips
$dispatches = $pdo->query('SELECT * FROM fleet_dispatches')->fetchAll(PDO::FETCH_ASSOC);
$synced = 0;

foreach ($dispatches as $d) {
    $tripNumber = $d['trip_number'];
    $customer = $d['client_name'] ?: ($d['destination'] . ' Consignee');
    $truck = $d['truck'];
    $driver = $d['driver'];
    $route = ($d['from_location'] ?: 'Eldoret') . ' → ' . $d['destination'];
    $load = (string) $d['loaded_litres'];
    $status = $d['status'];

    $check = $pdo->prepare('SELECT id FROM trips WHERE trip_number = ? LIMIT 1');
    $check->execute([$tripNumber]);
    if ($check->fetch()) {
        $pdo->prepare('UPDATE trips SET customer = ?, truck = ?, driver = ?, route = ?, load_quantity = ?, status = ? WHERE trip_number = ?')
            ->execute([$customer, $truck, $driver, $route, $load, $status, $tripNumber]);
    } else {
        $pdo->prepare('INSERT INTO trips (trip_number, customer, truck, driver, route, load_quantity, status) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$tripNumber, $customer, $truck, $driver, $route, $load, $status]);
        $synced++;
    }
}

echo "Successfully synced {$synced} dispatches into trips table.\n";
