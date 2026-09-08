<?php

namespace App\Controllers;

use App\Core\Database;
use PDO;

class AuditController
{
    public function index(): string
    {
        if (empty($_SESSION['is_logged_in'])) {
            redirect('/login');
        }

        $pdo = Database::connection();

        $search = trim($_GET['search'] ?? '');
        $categoryFilter = trim($_GET['category'] ?? 'all');
        $period = trim($_GET['period'] ?? 'all');
        $fromDate = trim($_GET['from_date'] ?? '');
        $toDate = trim($_GET['to_date'] ?? '');

        $sql = 'SELECT * FROM audit_logs WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (user_name LIKE ? OR user_email LIKE ? OR action LIKE ? OR description LIKE ? OR ip_address LIKE ?)';
            $term = "%{$search}%";
            $params = [$term, $term, $term, $term, $term];
        }

        if ($categoryFilter !== '' && $categoryFilter !== 'all') {
            $sql .= ' AND category = ?';
            $params[] = $categoryFilter;
        }

        if ($fromDate !== '') {
            $sql .= ' AND substr(created_at, 1, 10) >= ?';
            $params[] = $fromDate;
        }

        if ($toDate !== '') {
            $sql .= ' AND substr(created_at, 1, 10) <= ?';
            $params[] = $toDate;
        }

        $sql .= ' ORDER BY created_at DESC, id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $allEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch distinct categories for filter dropdown
        $categories = $pdo->query('SELECT DISTINCT category FROM audit_logs ORDER BY category ASC')->fetchAll(PDO::FETCH_COLUMN) ?: [
            'Fleet Dispatch', 'Trucks', 'Expenses', 'Driver Salaries', 'User Security', 'Customers'
        ];

        // Aggregates
        $totalLogs = (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
        $deletedCount = (int) $pdo->query('SELECT COUNT(*) FROM audit_logs WHERE is_deleted = 1 OR action LIKE "%DELETE%"')->fetchColumn();
        $fleetActionsCount = (int) $pdo->query('SELECT COUNT(*) FROM audit_logs WHERE category = "Fleet Dispatch"')->fetchColumn();
        $securityActionsCount = (int) $pdo->query('SELECT COUNT(*) FROM audit_logs WHERE category = "User Security"')->fetchColumn();

        return view('audit.index', [
            'title' => 'System Audit Trail & Compliance — Sarura Fuel',
            'entries' => $allEntries,
            'categories' => $categories,
            'search' => $search,
            'categoryFilter' => $categoryFilter,
            'period' => $period,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'totalLogs' => $totalLogs,
            'deletedCount' => $deletedCount,
            'fleetActionsCount' => $fleetActionsCount,
            'securityActionsCount' => $securityActionsCount,
        ]);
    }
}
