<?php

namespace App\Controllers;

use App\Core\Database;
use PDO;

class DriverController
{
    public function index(): string
    {
        if (!can_view_financials()) {
            flash('dashboard_error', 'Access restricted. Driver salary and payroll data is confidential to Super Admin.');
            redirect('/dashboard');
        }

        $pdo = Database::connection();
        $search = trim($_GET['search'] ?? '');

        $sql = 'SELECT * FROM drivers WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (name LIKE ? OR phone LIKE ? OR license_number LIKE ?)';
            $term = "%{$search}%";
            $params = [$term, $term, $term];
        }

        $sql .= ' ORDER BY id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch each driver's active/latest dispatch, total trips, litres, and salary totals
        foreach ($drivers as &$drv) {
            $recent = $pdo->prepare('SELECT trip_number, truck, destination, status, dispatch_date FROM fleet_dispatches WHERE driver = ? ORDER BY id DESC LIMIT 1');
            $recent->execute([$drv['name']]);
            $drv['latest_trip'] = $recent->fetch(PDO::FETCH_ASSOC) ?: null;

            $tripsStmt = $pdo->prepare('SELECT id, trip_number, truck, destination, loaded_litres, delivered_litres, dispatch_date, status, transport_amount, balance FROM fleet_dispatches WHERE driver = ? ORDER BY dispatch_date DESC, id DESC');
            $tripsStmt->execute([$drv['name']]);
            $drv['trips'] = $tripsStmt->fetchAll(PDO::FETCH_ASSOC);
            $drv['total_trips'] = count($drv['trips']);
            $drv['total_litres'] = array_sum(array_column($drv['trips'], 'loaded_litres'));

            $salHistoryStmt = $pdo->prepare('SELECT * FROM driver_salaries WHERE driver_name = ? OR driver_id = ? ORDER BY payment_date DESC, id DESC');
            $salHistoryStmt->execute([$drv['name'], $drv['id']]);
            $drv['salary_history'] = $salHistoryStmt->fetchAll(PDO::FETCH_ASSOC);

            $paid = 0.0;
            $wait = 0.0;
            foreach ($drv['salary_history'] as $sh) {
                if ($sh['status'] === 'Paid') {
                    $paid += (float) $sh['amount'];
                } else {
                    $wait += (float) $sh['amount'];
                }
            }
            $drv['salary_paid'] = $paid;
            $drv['salary_wait'] = $wait;
        }

        // Fetch all driver salaries
        $salaries = $pdo->query('SELECT * FROM driver_salaries ORDER BY payment_date DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);

        // Aggregates for salaries
        $salaryAgg = $pdo->query('SELECT 
            COUNT(*) as total_salaries,
            COALESCE(SUM(CASE WHEN status = "Paid" THEN amount ELSE 0 END), 0) as total_paid,
            COALESCE(SUM(CASE WHEN status = "Wait" THEN amount ELSE 0 END), 0) as total_wait,
            COALESCE(SUM(amount), 0) as total_issued
            FROM driver_salaries')->fetch(PDO::FETCH_ASSOC);

        // Fetch recent dispatches for reference in "Per Trip" salary modal
        $dispatches = $pdo->query('SELECT trip_number, driver, destination, dispatch_date FROM fleet_dispatches ORDER BY id DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);

        return view('drivers.index', [
            'title' => 'Drivers & Salaries — Sarura Fuel',
            'drivers' => $drivers,
            'salaries' => $salaries,
            'salaryAgg' => $salaryAgg,
            'dispatches' => $dispatches,
            'search' => $search,
        ]);
    }

    public function create(): string
    {
        return view('drivers.new', ['title' => 'Add Driver — Sarura Fuel']);
    }

    public function store(): void
    {
        $pdo = Database::connection();
        $name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $license = trim($_POST['license_number'] ?? '');
        $class = trim($_POST['license_class'] ?? 'B-Class');
        $status = trim($_POST['status'] ?? 'Active');

        if ($name !== '') {
            $stmt = $pdo->prepare('INSERT INTO drivers (name, phone, license_number, license_class, truck, status) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $phone, $license, $class, 'Unassigned (Assigned at dispatch)', $status]);

            flash('driver_success', "Driver {$name} registered successfully.");
        } else {
            flash('driver_error', 'Driver name is required.');
        }

        redirect('/drivers');
    }

    public function issueSalary(): void
    {
        $pdo = Database::connection();

        $driverId = (int) ($_POST['driver_id'] ?? 0);
        $driverName = trim($_POST['driver_name'] ?? '');
        $paymentType = trim($_POST['payment_type'] ?? 'per_trip');
        $periodReference = trim($_POST['period_reference'] ?? '');
        $paymentDate = trim($_POST['payment_date'] ?? date('Y-m-d'));
        $status = trim($_POST['status'] ?? 'Wait');
        $notes = trim($_POST['notes'] ?? '');

        // If driver name is empty but ID provided, lookup name
        if ($driverName === '' && $driverId > 0) {
            $stmt = $pdo->prepare('SELECT name FROM drivers WHERE id = ?');
            $stmt->execute([$driverId]);
            $driverName = (string) $stmt->fetchColumn();
        }

        $isKes = current_currency() === 'KES';
        $rate = exchange_rate();
        $rawBase = (float) ($_POST['base_salary'] ?? 0);
        $rawCarried = (float) ($_POST['carried_forward'] ?? 0);
        if ($rawBase === 0.0 && isset($_POST['amount'])) {
            $rawBase = (float) $_POST['amount'];
        }

        $baseSalary = $isKes ? ($rawBase / $rate) : $rawBase;
        $carriedForward = $isKes ? ($rawCarried / $rate) : $rawCarried;
        $amount = $baseSalary + $carriedForward;

        if ($driverName !== '' && $amount > 0) {
            $stmt = $pdo->prepare('INSERT INTO driver_salaries (
                driver_id, driver_name, payment_type, period_reference, base_salary, carried_forward, amount, status, payment_date, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

            $stmt->execute([
                $driverId,
                $driverName,
                $paymentType,
                $periodReference,
                $baseSalary,
                $carriedForward,
                $amount,
                $status,
                $paymentDate,
                $notes,
                date('Y-m-d H:i:s'),
            ]);

            log_audit('Payroll', 'ISSUE_SALARY', "Issued salary to {$driverName}: Base " . format_money($baseSalary) . ", Carried Forward " . format_money($carriedForward) . ", Total " . format_money($amount) . " [Status: {$status}]");
            flash('salary_success', "Salary of " . format_money($amount) . " issued to {$driverName} (Base: " . format_money($baseSalary) . ", Carried Forward: " . format_money($carriedForward) . ") [Status: {$status}].");
        } else {
            flash('salary_error', 'Please provide a valid driver and salary amount.');
        }

        redirect('/drivers#salaries');
    }

    public function updateSalary(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            flash('salary_error', 'Invalid salary record.');
            redirect('/drivers#salaries');
        }

        $pdo = Database::connection();
        $driverName = trim($_POST['driver_name'] ?? '');
        $paymentType = trim($_POST['payment_type'] ?? 'per_trip');
        $periodReference = trim($_POST['period_reference'] ?? '');
        $paymentDate = trim($_POST['payment_date'] ?? date('Y-m-d'));
        $status = trim($_POST['status'] ?? 'Wait');
        $notes = trim($_POST['notes'] ?? '');

        $isKes = current_currency() === 'KES';
        $rate = exchange_rate();
        $rawBase = (float) ($_POST['base_salary'] ?? 0);
        $rawCarried = (float) ($_POST['carried_forward'] ?? 0);

        $baseSalary = $isKes ? ($rawBase / $rate) : $rawBase;
        $carriedForward = $isKes ? ($rawCarried / $rate) : $rawCarried;
        $amount = $baseSalary + $carriedForward;

        $stmt = $pdo->prepare('UPDATE driver_salaries SET 
            driver_name = ?, payment_type = ?, period_reference = ?, base_salary = ?, carried_forward = ?, amount = ?, status = ?, payment_date = ?, notes = ?
            WHERE id = ?');
        $stmt->execute([
            $driverName, $paymentType, $periodReference, $baseSalary, $carriedForward, $amount, $status, $paymentDate, $notes, $id
        ]);

        log_audit('Payroll', 'UPDATE_SALARY', "Updated salary #{$id} for {$driverName}: Total " . format_money($amount));
        flash('salary_success', "Salary record #{$id} updated successfully.");
        redirect('/drivers#salaries');
    }

    public function deleteSalary(string $id): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM driver_salaries WHERE id = ?');
        $stmt->execute([(int)$id]);

        log_audit('Payroll', 'DELETE_SALARY', "Deleted salary record #{$id}", 1);
        flash('salary_success', 'Salary record removed successfully.');
        redirect('/drivers#salaries');
    }

    public function toggleSalary(): void
    {
        $id = (int) ($_POST['salary_id'] ?? 0);
        if ($id > 0) {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT status, driver_name FROM driver_salaries WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $salary = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($salary) {
                $newStatus = ($salary['status'] === 'Paid') ? 'Wait' : 'Paid';
                $update = $pdo->prepare('UPDATE driver_salaries SET status = ? WHERE id = ?');
                $update->execute([$newStatus, $id]);

                flash('salary_success', "Salary for {$salary['driver_name']} status updated to {$newStatus}.");
            }
        }

        redirect('/drivers#salaries');
    }

    public function delete(string $id): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM drivers WHERE id = ?');
        $stmt->execute([$id]);

        flash('driver_success', 'Driver record removed.');
        redirect('/drivers');
    }
}
