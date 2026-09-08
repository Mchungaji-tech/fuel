<?php

namespace App\Controllers;

use App\Core\Database;
use PDO;

class DashboardController
{
    public function index(): string
    {
        $pdo = Database::connection();

        $truckCount = (int) $pdo->query('SELECT COUNT(*) FROM trucks')->fetchColumn();
        $driverCount = (int) $pdo->query('SELECT COUNT(*) FROM drivers')->fetchColumn();
        $dispatchCount = (int) $pdo->query('SELECT COUNT(*) FROM fleet_dispatches')->fetchColumn();
        $invoiceCount = (int) $pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn();

        // Financial aggregates
        $fleetAgg = $pdo->query('SELECT 
            COALESCE(SUM(transport_amount), 0) as total_transport,
            COALESCE(SUM(balance), 0) as total_profit,
            COALESCE(SUM(loaded_litres), 0) as total_litres
            FROM fleet_dispatches')->fetch(PDO::FETCH_ASSOC);

        $expenseTotal = (float) $pdo->query('SELECT COALESCE(SUM(amount), 0) FROM expenses')->fetchColumn();
        $salaryPaid = (float) $pdo->query('SELECT COALESCE(SUM(amount), 0) FROM driver_salaries WHERE status = "Paid"')->fetchColumn();

        // Recent fleet dispatches
        $recentDispatches = $pdo->query('SELECT * FROM fleet_dispatches ORDER BY dispatch_date DESC, id DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);

        return view('dashboard.index', [
            'title' => 'Operations Dashboard — Sarura Fuel',
            'truckCount' => $truckCount,
            'driverCount' => $driverCount,
            'dispatchCount' => $dispatchCount,
            'invoiceCount' => $invoiceCount,
            'totalTransport' => (float) $fleetAgg['total_transport'],
            'totalProfit' => (float) $fleetAgg['total_profit'],
            'totalLitres' => (int) $fleetAgg['total_litres'],
            'expenseTotal' => $expenseTotal,
            'salaryPaid' => $salaryPaid,
            'recentDispatches' => $recentDispatches,
        ]);
    }
}
