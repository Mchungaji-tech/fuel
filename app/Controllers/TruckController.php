<?php

namespace App\Controllers;

use App\Core\Database;
use PDO;

class TruckController
{
    public function index(): string
    {
        $pdo = Database::connection();
        $search = trim($_GET['search'] ?? '');

        $sql = 'SELECT * FROM trucks WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (plate_number LIKE ? OR model LIKE ? OR compartments LIKE ? OR owner_name LIKE ?)';
            $term = "%{$search}%";
            $params = [$term, $term, $term, $term];
        }

        $sql .= ' ORDER BY plate_number ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $trucks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch aggregates & recent dispatch for each truck
        foreach ($trucks as &$trk) {
            $recent = $pdo->prepare('SELECT destination, driver, status, dispatch_date FROM fleet_dispatches WHERE truck = ? ORDER BY id DESC LIMIT 1');
            $recent->execute([$trk['plate_number']]);
            $trk['last_dispatch'] = $recent->fetch(PDO::FETCH_ASSOC) ?: null;

            // Summary stats for each truck
            $stats = $pdo->prepare('SELECT 
                COUNT(*) as trip_count,
                COALESCE(SUM(loaded_litres), 0) as total_litres,
                COALESCE(SUM(balance), 0) as total_profit
                FROM fleet_dispatches WHERE truck = ?');
            $stats->execute([$trk['plate_number']]);
            $trk['stats'] = $stats->fetch(PDO::FETCH_ASSOC);
        }

        return view('trucks.index', [
            'title' => 'Trucks & Tankers — Sarura Fuel',
            'trucks' => $trucks,
            'search' => $search,
        ]);
    }

    public function view(string $id): string
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM trucks WHERE id = ? OR plate_number = ? LIMIT 1');
        $stmt->execute([$id, $id]);
        $truck = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$truck) {
            flash('truck_error', 'Truck not found.');
            redirect('/trucks');
        }

        // Query all dispatches for this specific truck
        $dStmt = $pdo->prepare('SELECT * FROM fleet_dispatches WHERE truck = ? ORDER BY dispatch_date DESC, id DESC');
        $dStmt->execute([$truck['plate_number']]);
        $dispatches = $dStmt->fetchAll(PDO::FETCH_ASSOC);

        // Query all garage maintenance expenses for this specific truck
        $eStmt = $pdo->prepare('SELECT * FROM expenses WHERE truck = ? ORDER BY expense_date DESC, id DESC');
        $eStmt->execute([$truck['plate_number']]);
        $expenses = $eStmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate truck-specific performance totals
        $totalTrips = count($dispatches);
        $totalLitres = 0;
        $totalTransport = 0.0;
        $totalMileage = 0.0;
        $totalBreakdown = 0.0;
        $totalDiesel = 0.0;
        $totalBalance = 0.0;

        foreach ($dispatches as $d) {
            $totalLitres += (int) $d['loaded_litres'];
            $totalTransport += (float) $d['transport_amount'];
            $totalMileage += (float) $d['mileage_cost'];
            $totalBreakdown += (float) $d['extra_expenses'];
            $totalDiesel += (float) ($d['diesel'] ?? 0);
            $totalBalance += (float) $d['balance'];
        }

        $totalGarageExpenses = 0.0;
        foreach ($expenses as $e) {
            $totalGarageExpenses += (float) $e['amount'];
        }

        $isSubcontracted = in_array(strtolower($truck['ownership_type'] ?? ''), ['contract', 'subcontracted', 'sub']);

        return view('trucks.view', [
            'title' => "Truck {$truck['plate_number']} Details — Sarura Fuel",
            'truck' => $truck,
            'dispatches' => $dispatches,
            'expenses' => $expenses,
            'totalTrips' => $totalTrips,
            'totalLitres' => $totalLitres,
            'totalTransport' => $totalTransport,
            'totalMileage' => $totalMileage,
            'totalBreakdown' => $totalBreakdown,
            'totalDiesel' => $totalDiesel,
            'totalBalance' => $totalBalance,
            'totalGarageExpenses' => $totalGarageExpenses,
            'isSubcontracted' => $isSubcontracted,
        ]);
    }

    public function store(): void
    {
        $pdo = Database::connection();
        $plate = strtoupper(trim($_POST['plate_number'] ?? ''));
        $model = trim($_POST['model'] ?? '');
        $capacity = (int) ($_POST['capacity_litres'] ?? 0);
        $compartments = trim($_POST['compartments'] ?? '3 compartments');
        $rawOwnership = strtolower(trim($_POST['ownership_type'] ?? 'owner'));
        $ownershipType = in_array($rawOwnership, ['contract', 'subcontracted', 'sub']) ? 'Contract' : 'Owner';
        $ownerName = trim($_POST['owner_name'] ?? '');
        if ($ownerName === '') {
            $ownerName = ($ownershipType === 'Owner') ? 'Sarura Fuel Logistics' : 'Contract Haulier';
        }

        // Convert commission rate if inputted in KES
        $isKes = current_currency() === 'KES';
        $rate = exchange_rate();
        $rawCommission = (float) ($_POST['commission_rate'] ?? 0);
        $commissionRate = $isKes ? ($rawCommission / $rate) : $rawCommission;

        $status = trim($_POST['status'] ?? 'Ready');

        if ($plate !== '' && $capacity > 0) {
            $stmt = $pdo->prepare('INSERT INTO trucks (
                plate_number, model, capacity_litres, compartments, ownership_type, owner_name, commission_rate, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            try {
                $stmt->execute([$plate, $model, $capacity, $compartments, $ownershipType, $ownerName, $commissionRate, $status, date('Y-m-d H:i:s')]);
                flash('truck_success', "Truck {$plate} registered ({$ownershipType}) with capacity " . number_format($capacity) . " L.");
            } catch (\Exception $e) {
                flash('truck_error', "Truck {$plate} already exists or error occurred.");
            }
        } else {
            flash('truck_error', 'Plate number and capacity in litres are required.');
        }

        redirect('/trucks');
    }

    public function inlineUpdate(): void
    {
        $pdo = Database::connection();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Missing truck ID']);
            exit;
        }

        $plate = strtoupper(trim($_POST['plate_number'] ?? ''));
        $model = trim($_POST['model'] ?? '');
        $capacity = (int) ($_POST['capacity_litres'] ?? 0);
        $compartments = trim($_POST['compartments'] ?? '3 compartments');
        $rawOwner = strtolower(trim($_POST['ownership_type'] ?? ''));
        $ownershipType = in_array($rawOwner, ['contract', 'subcontracted', 'sub']) ? 'Contract' : 'Owner';
        $ownerName = trim($_POST['owner_name'] ?? '');
        if ($ownerName === '') {
            $ownerName = ($ownershipType === 'Owner') ? 'Sarura Fuel Logistics' : 'Contract Haulier';
        }
        $status = trim($_POST['status'] ?? 'Ready');

        $stmt = $pdo->prepare('UPDATE trucks SET 
            plate_number = ?, model = ?, capacity_litres = ?, compartments = ?, 
            ownership_type = ?, owner_name = ?, status = ? 
            WHERE id = ?');
        $stmt->execute([$plate, $model, $capacity, $compartments, $ownershipType, $ownerName, $status, $id]);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Truck updated successfully',
            'data' => [
                'id' => $id,
                'plate_number' => $plate,
                'model' => $model,
                'capacity_litres' => $capacity,
                'capacity_formatted' => number_format($capacity) . ' L',
                'compartments' => $compartments,
                'ownership_type' => $ownershipType,
                'owner_name' => $ownerName,
                'status' => $status
            ]
        ]);
        exit;
    }

    public function delete(string $id): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM trucks WHERE id = ?');
        $stmt->execute([$id]);

        flash('truck_success', 'Truck removed from roster.');
        redirect('/trucks');
    }
}
