<?php

namespace App\Controllers;

use App\Core\Database;
use PDO;

class CustomerController
{
    public function index(): string
    {
        $pdo = Database::connection();

        // Auto-sync clients from fleet_dispatches into customers table case-insensitively
        $dispatches = $pdo->query('SELECT client_name, destination, loaded_litres, dispatch_date FROM fleet_dispatches WHERE client_name IS NOT NULL AND client_name != ""')->fetchAll(PDO::FETCH_ASSOC);

        foreach ($dispatches as $d) {
            $client = trim($d['client_name']);
            if ($client === '' || strcasecmp($client, 'Regional Fuel Consignee') === 0) continue;

            $stmt = $pdo->prepare('SELECT id FROM customers WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1');
            $stmt->execute([$client]);
            if (!$stmt->fetch()) {
                $ins = $pdo->prepare('INSERT INTO customers (name, company, country, orders, total_litres, last_dispatch_date, status) VALUES (?, ?, ?, 1, ?, ?, "Active")');
                $ins->execute([$client, $client, $d['destination'] ?? 'Regional', (int) ($d['loaded_litres'] ?? 0), $d['dispatch_date'] ?? date('Y-m-d')]);
            }
        }

        // Fetch customer list with case-insensitive aggregated stats from fleet dispatches
        $sql = 'SELECT c.*, 
            (SELECT COUNT(*) FROM fleet_dispatches WHERE LOWER(TRIM(client_name)) = LOWER(TRIM(c.name))) as trip_count,
            (SELECT COALESCE(SUM(loaded_litres), 0) FROM fleet_dispatches WHERE LOWER(TRIM(client_name)) = LOWER(TRIM(c.name))) as total_volume,
            (SELECT COALESCE(SUM(shortage_litres), 0) FROM fleet_dispatches WHERE LOWER(TRIM(client_name)) = LOWER(TRIM(c.name))) as total_shortage,
            (SELECT MAX(dispatch_date) FROM fleet_dispatches WHERE LOWER(TRIM(client_name)) = LOWER(TRIM(c.name))) as last_trip_date
            FROM customers c ORDER BY c.orders DESC, c.id DESC';

        $customers = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        return view('customers.index', [
            'title' => 'Customer & Consignee Accounts — Sarura Fuel',
            'customers' => $customers,
        ]);
    }
}
