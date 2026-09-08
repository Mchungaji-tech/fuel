<?php

namespace App\Controllers;

use App\Core\Database;
use PDO;

class TripController
{
    public function index(): string
    {
        $pdo = Database::connection();

        // Query fleet_dispatches (the single source of truth for operations & dispatches)
        $dispatches = $pdo->query('SELECT id, trip_number, client_name, destination, from_location, truck, driver, status, loaded_litres, dispatch_date, bol_number FROM fleet_dispatches ORDER BY dispatch_date DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);

        $tripList = [];
        $seen = [];

        foreach ($dispatches as $d) {
            $seen[$d['trip_number']] = true;
            $tripList[] = [
                'id' => $d['id'],
                'trip' => $d['trip_number'],
                'customer' => $d['client_name'] ?: ($d['destination'] . ' Consignee'),
                'truck' => $d['truck'],
                'driver' => $d['driver'] ?: 'Unassigned',
                'status' => $d['status'],
                'route' => ($d['from_location'] ?: 'Eldoret') . ' → ' . $d['destination'],
                'load' => number_format((int)$d['loaded_litres']) . ' L',
                'date' => format_date_dol($d['dispatch_date']),
                'bol_url' => url('fleet/bol/' . $d['id']),
            ];
        }

        // Also include any standalone records from trips table if not already listed
        $standaloneTrips = $pdo->query('SELECT * FROM trips ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($standaloneTrips as $trip) {
            if (!empty($trip['trip_number']) && !isset($seen[$trip['trip_number']])) {
                $tripList[] = [
                    'id' => $trip['id'],
                    'trip' => $trip['trip_number'],
                    'customer' => $trip['customer'],
                    'truck' => $trip['truck'],
                    'driver' => $trip['driver'] ?: 'Unassigned',
                    'status' => $trip['status'],
                    'route' => $trip['route'] ?? 'Regional Delivery',
                    'load' => $trip['load_quantity'] ? ($trip['load_quantity'] . ' L') : '—',
                    'date' => '—',
                    'bol_url' => url('fleet'),
                ];
            }
        }

        $data = [
            'title' => 'Trips — Sarura Fuel',
            'trips' => $tripList,
        ];

        return view('trips.index', $data);
    }

    public function create(): string
    {
        $pdo = Database::connection();
        $trucks = $pdo->query('SELECT plate_number, model, capacity_litres FROM trucks ORDER BY plate_number ASC')->fetchAll(PDO::FETCH_ASSOC);
        $drivers = $pdo->query('SELECT id, name FROM drivers ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
        $products = $pdo->query('SELECT code, name FROM products WHERE status = "Active" ORDER BY code ASC')->fetchAll(PDO::FETCH_ASSOC);

        return view('trips.new', [
            'title' => 'Create Trip — Sarura Fuel',
            'trucks' => $trucks,
            'drivers' => $drivers,
            'products' => $products,
        ]);
    }

    public function store(): void
    {
        $tripNumber = trim($_POST['trip_number'] ?? '');
        $customer = trim($_POST['customer'] ?? '');
        $truck = trim($_POST['truck'] ?? '');
        $driver = trim($_POST['driver'] ?? '');
        $route = trim($_POST['route'] ?? '');
        $load = trim($_POST['load_quantity'] ?? '');
        $status = trim($_POST['status'] ?? 'Planned');

        if ($tripNumber !== '') {
            $pdo = Database::connection();
            $pdo->prepare('INSERT INTO trips (trip_number, customer, truck, driver, route, load_quantity, status) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$tripNumber, $customer, $truck, $driver, $route, $load, $status]);

            // Also ensure fleet_dispatches has a synchronized record
            $check = $pdo->prepare('SELECT id FROM fleet_dispatches WHERE trip_number = ? LIMIT 1');
            $check->execute([$tripNumber]);
            if (!$check->fetch()) {
                $bolNumber = 'BOL-' . date('Y') . '-' . rand(1000, 9999);
                $dest = $route ?: 'Regional Consignee';
                $loaded = (int) $load;
                $pdo->prepare('INSERT INTO fleet_dispatches (trip_number, bol_number, dispatch_date, truck, truck_capacity, loaded_litres, from_location, destination, product, driver, status, client_name, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$tripNumber, $bolNumber, date('Y-m-d'), $truck, $loaded, $loaded, 'Eldoret Depot', $dest, 'AGO', $driver, $status, $customer, date('Y-m-d H:i:s')]);
            }
        }

        flash('trip_success', "Trip {$tripNumber} registered successfully.");
        redirect('/trips');
    }
}
