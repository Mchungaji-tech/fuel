<?php

namespace App\Controllers;

use App\Core\Database;
use App\Services\ExcelService;
use PDO;

class ExpenseController
{
    public function index(): string
    {
        if (!can_view_financials()) {
            flash('dashboard_error', 'Access restricted. Financial ledgers and company expenses are confidential to administration.');
            redirect('/dashboard');
        }

        $pdo = Database::connection();
        $search = trim($_GET['search'] ?? '');
        $selectedTruck = trim($_GET['truck'] ?? '');
        $selectedYear = (int)($_GET['year'] ?? date('Y'));
        if ($selectedYear < 2000 || $selectedYear > 2100) {
            $selectedYear = (int)date('Y');
        }

        $sql = 'SELECT * FROM expenses WHERE 1=1';
        $params = [];

        if ($selectedTruck !== '' && strtolower($selectedTruck) !== 'all') {
            if (strtolower($selectedTruck) === 'general') {
                $sql .= ' AND (truck IS NULL OR truck = "" OR LOWER(truck) LIKE "%general%")';
            } else {
                $sql .= ' AND LOWER(TRIM(truck)) = LOWER(TRIM(?))';
                $params[] = $selectedTruck;
            }
        }

        if ($search !== '') {
            $sql .= ' AND (expense_title LIKE ? OR truck LIKE ? OR garage_vendor LIKE ? OR receipt_number LIKE ? OR receipt_status LIKE ?)';
            $term = "%{$search}%";
            $params = array_merge($params, [$term, $term, $term, $term, $term]);
        }

        $sql .= ' ORDER BY expense_date DESC, id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $aggStmt = $pdo->query('SELECT 
            COUNT(*) as total_count,
            COALESCE(SUM(amount), 0) as total_amount,
            COALESCE(AVG(amount), 0) as avg_amount
            FROM expenses');
        $aggregates = $aggStmt->fetch(PDO::FETCH_ASSOC);

        // Fetch registered trucks with live operational trip status
        $rawTrucks = $pdo->query('SELECT plate_number, status, ownership_type FROM trucks ORDER BY plate_number ASC')->fetchAll(PDO::FETCH_ASSOC);
        $trucksWithStatus = [];

        foreach ($rawTrucks as $t) {
            $plate = $t['plate_number'];
            $activeTripStmt = $pdo->prepare('SELECT trip_number, status, destination FROM fleet_dispatches WHERE truck = ? AND status IN ("Loading", "In Transit") ORDER BY id DESC LIMIT 1');
            $activeTripStmt->execute([$plate]);
            $activeTrip = $activeTripStmt->fetch(PDO::FETCH_ASSOC);

            if ($activeTrip) {
                $liveStatus = 'On Trip (' . $activeTrip['status'] . ')';
                $statusType = 'on_trip';
                $tripNum = $activeTrip['trip_number'];
            } elseif (in_array(strtolower($t['status'] ?? ''), ['in garage', 'garage', 'maintenance', 'repair'])) {
                $liveStatus = 'In Garage / Maintenance';
                $statusType = 'garage';
                $tripNum = null;
            } else {
                $liveStatus = 'Ready';
                $statusType = 'ready';
                $tripNum = null;
            }

            $trucksWithStatus[] = [
                'plate_number' => $plate,
                'status' => $liveStatus,
                'status_type' => $statusType,
                'ownership' => $t['ownership_type'] ?? 'Owner',
                'trip_number' => $tripNum,
            ];
        }

        // Build comprehensive truck expense reports (all-time total, selected year total, and 12-month matrix)
        $truckReports = [];
        foreach ($trucksWithStatus as $trk) {
            $plate = $trk['plate_number'];

            // All-time sum & count
            $allTimeStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as cnt FROM expenses WHERE LOWER(TRIM(truck)) = LOWER(TRIM(?))');
            $allTimeStmt->execute([$plate]);
            $allTimeData = $allTimeStmt->fetch(PDO::FETCH_ASSOC);

            // Available years with expenses
            $yrsStmt = $pdo->prepare('SELECT DISTINCT substr(expense_date, 1, 4) as yr FROM expenses WHERE LOWER(TRIM(truck)) = LOWER(TRIM(?)) AND expense_date IS NOT NULL AND expense_date != "" ORDER BY yr DESC');
            $yrsStmt->execute([$plate]);
            $yrs = $yrsStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            if (!in_array((string)$selectedYear, $yrs, true)) {
                $yrs[] = (string)$selectedYear;
                rsort($yrs);
            }

            // Selected Year total & count
            $yrStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as cnt FROM expenses WHERE LOWER(TRIM(truck)) = LOWER(TRIM(?)) AND (expense_date LIKE ? OR substr(expense_date, 1, 4) = ?)');
            $yrStmt->execute([$plate, "{$selectedYear}%", (string)$selectedYear]);
            $yrData = $yrStmt->fetch(PDO::FETCH_ASSOC);

            // 12-month matrix for the selected year
            $moStmt = $pdo->prepare('SELECT substr(expense_date, 6, 2) as mo, COALESCE(SUM(amount), 0) as total, COUNT(*) as cnt FROM expenses WHERE LOWER(TRIM(truck)) = LOWER(TRIM(?)) AND substr(expense_date, 1, 4) = ? GROUP BY substr(expense_date, 6, 2)');
            $moStmt->execute([$plate, (string)$selectedYear]);
            $moRows = $moStmt->fetchAll(PDO::FETCH_ASSOC);
            $moMap = [];
            foreach ($moRows as $mr) {
                $moMap[$mr['mo']] = [
                    'total' => (float)$mr['total'],
                    'count' => (int)$mr['cnt']
                ];
            }

            $months = [];
            for ($m = 1; $m <= 12; $m++) {
                $mKey = str_pad((string)$m, 2, '0', STR_PAD_LEFT);
                $months[$mKey] = [
                    'month_num' => $mKey,
                    'month_name' => date('M', mktime(0, 0, 0, $m, 10)),
                    'month_full' => date('F', mktime(0, 0, 0, $m, 10)),
                    'total' => $moMap[$mKey]['total'] ?? 0.0,
                    'count' => $moMap[$mKey]['count'] ?? 0,
                ];
            }

            $truckReports[$plate] = [
                'plate_number' => $plate,
                'ownership' => $trk['ownership'] ?? 'Owner',
                'status' => $trk['status'] ?? 'Ready',
                'all_time_total' => (float)($allTimeData['total'] ?? 0),
                'all_time_count' => (int)($allTimeData['cnt'] ?? 0),
                'selected_year' => $selectedYear,
                'yearly_total' => (float)($yrData['total'] ?? 0),
                'yearly_count' => (int)($yrData['cnt'] ?? 0),
                'available_years' => $yrs,
                'monthly_matrix' => $months,
            ];
        }

        $activeTruckReport = null;
        if ($selectedTruck !== '' && strtolower($selectedTruck) !== 'all' && strtolower($selectedTruck) !== 'general') {
            foreach ($truckReports as $p => $rep) {
                if (strtolower($p) === strtolower($selectedTruck)) {
                    $activeTruckReport = $rep;
                    break;
                }
            }
        }

        return view('expenses.index', [
            'title' => 'Business & Fleet Expenses — Sarura Fuel',
            'expenses' => $expenses,
            'aggregates' => $aggregates,
            'trucks' => $trucksWithStatus,
            'search' => $search,
            'selectedTruck' => $selectedTruck,
            'selectedYear' => $selectedYear,
            'activeTruckReport' => $activeTruckReport,
            'truckReports' => $truckReports,
        ]);
    }

    public function store(): void
    {
        $pdo = Database::connection();

        $expenseDate = trim($_POST['expense_date'] ?? date('Y-m-d'));
        $expenseTitle = trim($_POST['expense_title'] ?? '');
        $truck = trim($_POST['truck'] ?? '');
        $garageVendor = trim($_POST['garage_vendor'] ?? '');
        $receiptStatus = trim($_POST['receipt_status'] ?? 'Received');
        $notes = trim($_POST['notes'] ?? '');

        $rate = (float)($_POST['exchange_rate'] ?? exchange_rate());
        if ($rate <= 0) $rate = 130.0;

        $currencyMode = trim($_POST['currency_mode'] ?? '');
        $amountKes = isset($_POST['amount_kes']) && $_POST['amount_kes'] !== '' ? (float)$_POST['amount_kes'] : null;
        $amountUsd = isset($_POST['amount_usd']) && $_POST['amount_usd'] !== '' ? (float)$_POST['amount_usd'] : null;

        if ($currencyMode === 'KES' && $amountKes !== null && $amountKes > 0) {
            $amount = $amountKes / $rate;
        } elseif ($currencyMode === 'USD' && $amountUsd !== null && $amountUsd > 0) {
            $amount = $amountUsd;
        } elseif ($amountUsd !== null && $amountUsd > 0) {
            $amount = $amountUsd;
        } elseif ($amountKes !== null && $amountKes > 0) {
            $amount = $amountKes / $rate;
        } else {
            $rawAmount = (float) ($_POST['amount'] ?? 0);
            $isKes = current_currency() === 'KES';
            $amount = $isKes ? ($rawAmount / $rate) : $rawAmount;
        }

        if ($expenseTitle !== '' && $amount > 0) {
            $stmt = $pdo->prepare('INSERT INTO expenses (
                expense_date, expense_title, truck, amount, exchange_rate, garage_vendor, receipt_status, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');

            $stmt->execute([
                $expenseDate,
                $expenseTitle,
                $truck,
                $amount,
                $rate,
                $garageVendor,
                $receiptStatus,
                $notes,
                date('Y-m-d H:i:s'),
            ]);

            $composite = implode('::', [
                $expenseDate,
                $expenseTitle,
                (string)(float)$amount
            ]);
            \App\Services\DatabaseSyncService::clearDeletion('expenses', $composite);

            // Fleet Linkage: if truck is currently on an active dispatch, link as extra_expense
            if ($truck !== '' && $truck !== 'General Business') {
                $tripQ = $pdo->prepare('SELECT id, trip_number, extra_expenses, balance FROM fleet_dispatches WHERE truck = ? AND status IN ("Loading", "In Transit") ORDER BY id DESC LIMIT 1');
                $tripQ->execute([$truck]);
                $activeTrip = $tripQ->fetch(PDO::FETCH_ASSOC);

                if ($activeTrip) {
                    $dispatchId = $activeTrip['id'];
                    $pdo->prepare('UPDATE fleet_dispatches SET extra_expenses = extra_expenses + ?, balance = balance - ? WHERE id = ?')
                        ->execute([$amount, $amount, $dispatchId]);

                    log_audit('Expenses', 'RECORD_EXPENSE', "Logged expense '{$expenseTitle}' (" . format_money($amount) . ") for truck {$truck} on trip #{$activeTrip['trip_number']} (Linked to Fleet)");
                } else {
                    log_audit('Expenses', 'RECORD_EXPENSE', "Logged expense '{$expenseTitle}' (" . format_money($amount) . ") for {$truck} [Receipt: {$receiptStatus}]");
                }
            } else {
                log_audit('Expenses', 'RECORD_EXPENSE', "Logged general expense '{$expenseTitle}' (" . format_money($amount) . ") [Receipt: {$receiptStatus}]");
            }

            flash('expense_success', "Expense '{$expenseTitle}' recorded successfully.");
        } else {
            flash('expense_error', 'Expense description and a valid amount are required.');
        }

        redirect('/expenses');
    }

    public function inlineUpdate(): void
    {
        $pdo = Database::connection();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Missing expense ID']);
            exit;
        }

        $isKes = current_currency() === 'KES';
        $rate = (float)($_POST['exchange_rate'] ?? exchange_rate());
        if ($rate <= 0) $rate = 130.0;

        $expenseDate = trim($_POST['expense_date'] ?? date('Y-m-d'));
        $expenseTitle = trim($_POST['expense_title'] ?? '');
        $truck = trim($_POST['truck'] ?? '');
        $garageVendor = trim($_POST['garage_vendor'] ?? '');
        $receiptStatus = trim($_POST['receipt_status'] ?? 'Received');
        $notes = trim($_POST['notes'] ?? '');

        $rawAmount = (float) ($_POST['amount'] ?? 0);
        $amount = $isKes ? ($rawAmount / $rate) : $rawAmount;

        $stmt = $pdo->prepare('UPDATE expenses SET 
            expense_date = ?, expense_title = ?, truck = ?, amount = ?, exchange_rate = ?, 
            garage_vendor = ?, receipt_status = ?, notes = ?
            WHERE id = ?');
        $stmt->execute([$expenseDate, $expenseTitle, $truck, $amount, $rate, $garageVendor, $receiptStatus, $notes, $id]);

        log_audit('Expenses', 'UPDATE_EXPENSE', "Updated expense #{$id} ({$expenseTitle}) - " . format_money($amount));

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Expense updated successfully',
            'data' => [
                'id' => $id,
                'expense_date' => $expenseDate,
                'dol_formatted' => format_date_dol($expenseDate),
                'expense_title' => $expenseTitle,
                'truck' => $truck ?: 'General Business',
                'amount' => $amount,
                'exchange_rate' => $rate,
                'amount_formatted' => format_money($amount),
                'amount_evaluated' => ($isKes ? ('$ ' . number_format($amount, 2) . ' USD') : ('KES ' . number_format($amount * $rate))),
                'garage_vendor' => $garageVendor ?: 'General Vendor',
                'receipt_status' => $receiptStatus,
                'notes' => $notes
            ]
        ]);
        exit;
    }

    public function delete(string $id): void
    {
        $pdo = Database::connection();
        $q = $pdo->prepare('SELECT expense_date, expense_title, truck, amount FROM expenses WHERE id = ? LIMIT 1');
        $q->execute([(int) $id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare('DELETE FROM expenses WHERE id = ?');
        $stmt->execute([$id]);

        if ($row) {
            $composite = implode('::', [
                $row['expense_date'] ?? '',
                $row['expense_title'] ?? '',
                (string)(float)($row['amount'] ?? 0)
            ]);
            \App\Services\DatabaseSyncService::recordDeletion('expenses', $composite);
        }

        $expTitle = $row ? ($row['expense_title'] . ' - ' . format_money((float)$row['amount'])) : "ID #{$id}";
        log_audit('Expenses', 'DELETE_EXPENSE', "Deleted expense {$expTitle}", 1);

        flash('expense_success', 'Expense record removed.');
        redirect('/expenses');
    }

    /**
     * Check how many records match the given export filters (returns JSON).
    /**
     * Check how many records match the given export filters (returns JSON).
     * Called by JavaScript before downloading so it can show real-time count.
     */
    public function exportCheck(): void
    {
        if (!can_view_financials()) {
            header('Content-Type: application/json');
            echo json_encode(['count' => 0, 'error' => 'Access denied']);
            exit;
        }

        $pdo = Database::connection();
        $truck = trim($_GET['truck'] ?? '');
        $month = trim($_GET['month'] ?? '');
        $year  = trim($_GET['year'] ?? '');
        $fromDate = trim($_GET['from_date'] ?? '');
        $toDate = trim($_GET['to_date'] ?? '');
        $search = trim($_GET['search'] ?? '');

        // Support month passed as "YYYY-MM" (e.g. 2026-09)
        if (strpos($month, '-') !== false) {
            $parts = explode('-', $month);
            if (empty($year) || strtolower($year) === 'all') {
                $year = $parts[0];
            }
            $month = $parts[1];
        }

        $sql = 'SELECT COUNT(*) FROM expenses WHERE 1=1';
        $params = [];

        // 1. Truck filter (case-insensitive, space-tolerant, and handles General Business)
        if ($truck !== '' && strtolower($truck) !== 'all') {
            if (strtolower($truck) === 'general' || strtolower($truck) === 'general business') {
                $sql .= ' AND (truck IS NULL OR truck = "" OR LOWER(truck) LIKE "%general%")';
            } else {
                $sql .= ' AND (LOWER(TRIM(truck)) = LOWER(TRIM(?)) OR REPLACE(LOWER(TRIM(truck)), " ", "") = REPLACE(LOWER(TRIM(?)), " ", ""))';
                $params[] = $truck;
                $params[] = $truck;
            }
        }

        // 2. Search query filter
        if ($search !== '') {
            $sql .= ' AND (expense_title LIKE ? OR truck LIKE ? OR garage_vendor LIKE ? OR receipt_number LIKE ? OR notes LIKE ?)';
            $sTerm = "%{$search}%";
            $params = array_merge($params, [$sTerm, $sTerm, $sTerm, $sTerm, $sTerm]);
        }

        // 3. Date range filters
        if ($fromDate !== '') {
            $sql .= ' AND expense_date >= ?';
            $params[] = $fromDate;
        }
        if ($toDate !== '') {
            $sql .= ' AND expense_date <= ?';
            $params[] = $toDate;
        }

        // 4. Year & Month filters (cross-database ANSI SQL compatible with MySQL & SQLite)
        $hasYear = ($year !== '' && strtolower($year) !== 'all');
        $hasMonth = ($month !== '' && strtolower($month) !== 'all');

        if ($hasYear && $hasMonth) {
            $mFormatted = str_pad($month, 2, '0', STR_PAD_LEFT);
            $mInt = (int)$month;
            $sql .= ' AND (expense_date LIKE ? OR expense_date LIKE ? OR (substr(expense_date, 1, 4) = ? AND substr(expense_date, 6, 2) = ?))';
            $params[] = "$year-$mFormatted-%";
            $params[] = "$year-$mInt-%";
            $params[] = (string)$year;
            $params[] = $mFormatted;
        } elseif ($hasYear) {
            $sql .= ' AND (expense_date LIKE ? OR substr(expense_date, 1, 4) = ?)';
            $params[] = "$year-%";
            $params[] = (string)$year;
        } elseif ($hasMonth) {
            $mFormatted = str_pad($month, 2, '0', STR_PAD_LEFT);
            $mInt = (int)$month;
            $sql .= ' AND (substr(expense_date, 6, 2) = ? OR expense_date LIKE ? OR expense_date LIKE ?)';
            $params[] = $mFormatted;
            $params[] = "%-$mFormatted-%";
            $params[] = "%-$mInt-%";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count = (int) $stmt->fetchColumn();

        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['count' => $count]);
        if (!defined('TESTING_MODE') || !TESTING_MODE) {
            exit;
        }
    }

    /**
     * Export the business expenses to Excel (.xlsx), Excel 97 (.xls), or CSV.
     * Fully compatible with both MySQL and SQLite database backends.
     */
    public function export(): void
    {
        if (!can_view_financials()) {
            flash('dashboard_error', 'Access restricted to administration.');
            redirect('/dashboard');
        }

        $pdo = Database::connection();
        $truck = trim($_GET['truck'] ?? '');
        $month = trim($_GET['month'] ?? '');
        $year = trim($_GET['year'] ?? '');
        $fromDate = trim($_GET['from_date'] ?? '');
        $toDate = trim($_GET['to_date'] ?? '');
        $search = trim($_GET['search'] ?? '');
        $format = strtolower(trim($_GET['format'] ?? 'xlsx'));

        // Support month passed as "YYYY-MM" (e.g. 2026-09)
        if (strpos($month, '-') !== false) {
            $parts = explode('-', $month);
            if (empty($year) || strtolower($year) === 'all') {
                $year = $parts[0];
            }
            $month = $parts[1];
        }

        $sql = 'SELECT * FROM expenses WHERE 1=1';
        $params = [];

        // 1. Truck filter (case-insensitive, space-tolerant, and handles General Business)
        if ($truck !== '' && strtolower($truck) !== 'all') {
            if (strtolower($truck) === 'general' || strtolower($truck) === 'general business') {
                $sql .= ' AND (truck IS NULL OR truck = "" OR LOWER(truck) LIKE "%general%")';
            } else {
                $sql .= ' AND (LOWER(TRIM(truck)) = LOWER(TRIM(?)) OR REPLACE(LOWER(TRIM(truck)), " ", "") = REPLACE(LOWER(TRIM(?)), " ", ""))';
                $params[] = $truck;
                $params[] = $truck;
            }
        }

        // 2. Search query filter
        if ($search !== '') {
            $sql .= ' AND (expense_title LIKE ? OR truck LIKE ? OR garage_vendor LIKE ? OR receipt_number LIKE ? OR notes LIKE ?)';
            $sTerm = "%{$search}%";
            $params = array_merge($params, [$sTerm, $sTerm, $sTerm, $sTerm, $sTerm]);
        }

        // 3. Date range filters
        if ($fromDate !== '') {
            $sql .= ' AND expense_date >= ?';
            $params[] = $fromDate;
        }
        if ($toDate !== '') {
            $sql .= ' AND expense_date <= ?';
            $params[] = $toDate;
        }

        // 4. Year & Month filters (cross-database ANSI SQL compatible with MySQL & SQLite)
        $hasYear = ($year !== '' && strtolower($year) !== 'all');
        $hasMonth = ($month !== '' && strtolower($month) !== 'all');

        if ($hasYear && $hasMonth) {
            $mFormatted = str_pad($month, 2, '0', STR_PAD_LEFT);
            $mInt = (int)$month;
            $sql .= ' AND (expense_date LIKE ? OR expense_date LIKE ? OR (substr(expense_date, 1, 4) = ? AND substr(expense_date, 6, 2) = ?))';
            $params[] = "$year-$mFormatted-%";
            $params[] = "$year-$mInt-%";
            $params[] = (string)$year;
            $params[] = $mFormatted;
        } elseif ($hasYear) {
            $sql .= ' AND (expense_date LIKE ? OR substr(expense_date, 1, 4) = ?)';
            $params[] = "$year-%";
            $params[] = (string)$year;
        } elseif ($hasMonth) {
            $mFormatted = str_pad($month, 2, '0', STR_PAD_LEFT);
            $mInt = (int)$month;
            $sql .= ' AND (substr(expense_date, 6, 2) = ? OR expense_date LIKE ? OR expense_date LIKE ?)';
            $params[] = $mFormatted;
            $params[] = "%-$mFormatted-%";
            $params[] = "%-$mInt-%";
        }

        $sql .= ' ORDER BY expense_date DESC, id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // When no records match the selected filters, redirect back with friendly notification
        if (empty($expenses)) {
            flash('expense_error', 'No expense records found matching the specified export filters.');
            redirect('/expenses');
        }

        $currencySymbol = app_currency_symbol();
        $isKes = current_currency() === 'KES';
        $defaultRate = exchange_rate();
        if ($defaultRate <= 0) $defaultRate = 128.0;

        $headers = [
            'Expense Date',
            'Expense Description',
            'Vehicle / Truck',
            'Amount (USD $)',
            'Exchange Rate',
            'Amount (KES)',
            'Amount (' . $currencySymbol . ')',
            'Garage / Vendor',
            'Receipt Status',
            'Notes / Remarks',
            'Created At',
        ];

        $rows = [];
        foreach ($expenses as $exp) {
            $baseAmountUsd = (float)$exp['amount'];
            $recordRate = !empty($exp['exchange_rate']) ? (float)$exp['exchange_rate'] : $defaultRate;
            if ($recordRate <= 0) $recordRate = $defaultRate;
            $amountKes = $baseAmountUsd * $recordRate;
            $displayAmount = $isKes ? $amountKes : $baseAmountUsd;

            $rows[] = [
                $exp['expense_date'],
                $exp['expense_title'],
                $exp['truck'] ?: 'General Business',
                round($baseAmountUsd, 2),
                round($recordRate, 2),
                round($amountKes, 2),
                round($displayAmount, 2),
                $exp['garage_vendor'] ?: '—',
                $exp['receipt_status'] ?? 'Received',
                $exp['notes'] ?: '',
                $exp['created_at'] ?? '',
            ];
        }

        $truckPart = ($truck && strtolower($truck) !== 'all') ? preg_replace('/[^a-zA-Z0-9_-]/', '', $truck) . '_' : 'all_vehicles_';
        if ($fromDate !== '' && $toDate !== '') {
            $periodPart = $fromDate . '_to_' . $toDate;
        } elseif ($fromDate !== '') {
            $periodPart = 'from_' . $fromDate;
        } else {
            $periodPart = ($year && strtolower($year) !== 'all' ? $year : 'all_years') . ($month && strtolower($month) !== 'all' ? '_' . str_pad($month, 2, '0', STR_PAD_LEFT) : '');
        }
        $filenameBase = 'expenses_' . $truckPart . $periodPart;

        if ($format === 'xls') {
            ExcelService::exportXls($filenameBase . '.xls', $headers, $rows, 'Business Expenses');
        } elseif ($format === 'csv') {
            ExcelService::exportCsv($filenameBase . '.csv', $headers, $rows);
        } else {
            ExcelService::exportXlsx($filenameBase . '.xlsx', $headers, $rows, 'Business Expenses');
        }

        if (!defined('TESTING_MODE') || !TESTING_MODE) {
            exit;
        }
    }
}

