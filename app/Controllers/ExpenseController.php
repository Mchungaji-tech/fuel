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

        $isKes = current_currency() === 'KES';
        $rate = exchange_rate();
        $rawAmount = (float) ($_POST['amount'] ?? 0);
        $amount = $isKes ? ($rawAmount / $rate) : $rawAmount;

        if ($expenseTitle !== '' && $amount > 0) {
            $stmt = $pdo->prepare('INSERT INTO expenses (
                expense_date, expense_title, truck, amount, garage_vendor, receipt_status, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

            $stmt->execute([
                $expenseDate,
                $expenseTitle,
                $truck,
                $amount,
                $garageVendor,
                $receiptStatus,
                $notes,
                date('Y-m-d H:i:s'),
            ]);

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
        $rate = exchange_rate();

        $expenseDate = trim($_POST['expense_date'] ?? date('Y-m-d'));
        $expenseTitle = trim($_POST['expense_title'] ?? '');
        $truck = trim($_POST['truck'] ?? '');
        $garageVendor = trim($_POST['garage_vendor'] ?? '');
        $receiptStatus = trim($_POST['receipt_status'] ?? 'Received');
        $notes = trim($_POST['notes'] ?? '');

        $rawAmount = (float) ($_POST['amount'] ?? 0);
        $amount = $isKes ? ($rawAmount / $rate) : $rawAmount;

        $stmt = $pdo->prepare('UPDATE expenses SET 
            expense_date = ?, expense_title = ?, truck = ?, amount = ?, 
            garage_vendor = ?, receipt_status = ?, notes = ?
            WHERE id = ?');
        $stmt->execute([$expenseDate, $expenseTitle, $truck, $amount, $garageVendor, $receiptStatus, $notes, $id]);

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
                'amount_formatted' => format_money($amount),
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
        $q = $pdo->prepare('SELECT expense_title, truck, amount FROM expenses WHERE id = ? LIMIT 1');
        $q->execute([(int) $id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare('DELETE FROM expenses WHERE id = ?');
        $stmt->execute([$id]);

        $expTitle = $row ? ($row['expense_title'] . ' - ' . format_money((float)$row['amount'])) : "ID #{$id}";
        log_audit('Expenses', 'DELETE_EXPENSE', "Deleted expense {$expTitle}", 1);

        flash('expense_success', 'Expense record removed.');
        redirect('/expenses');
    }

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
        $format = strtolower(trim($_GET['format'] ?? 'xlsx'));

        $sql = 'SELECT * FROM expenses WHERE 1=1';
        $params = [];

        if ($truck !== '' && strtolower($truck) !== 'all') {
            $sql .= ' AND truck = ?';
            $params[] = $truck;
        }

        if ($month !== '' && strtolower($month) !== 'all') {
            $mFormatted = str_pad($month, 2, '0', STR_PAD_LEFT);
            $sql .= " AND strftime('%m', expense_date) = ?";
            $params[] = $mFormatted;
        }

        if ($year !== '' && strtolower($year) !== 'all') {
            $sql .= " AND strftime('%Y', expense_date) = ?";
            $params[] = $year;
        }

        $sql .= ' ORDER BY expense_date DESC, id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $currencySymbol = app_currency_symbol();
        $isKes = current_currency() === 'KES';
        $rate = exchange_rate();

        $headers = [
            'Expense Date',
            'Expense Description',
            'Vehicle / Truck',
            'Amount (' . $currencySymbol . ')',
            'Garage / Vendor',
            'Receipt Status',
            'Notes / Remarks',
            'Created At',
        ];

        $rows = [];
        foreach ($expenses as $exp) {
            $baseAmount = (float)$exp['amount'];
            $displayAmount = $isKes ? ($baseAmount * $rate) : $baseAmount;

            $rows[] = [
                $exp['expense_date'],
                $exp['expense_title'],
                $exp['truck'] ?: 'General Business',
                round($displayAmount, 2),
                $exp['garage_vendor'] ?: '—',
                $exp['receipt_status'],
                $exp['notes'] ?: '',
                $exp['created_at'] ?? '',
            ];
        }

        $truckPart = ($truck && strtolower($truck) !== 'all') ? preg_replace('/[^a-zA-Z0-9_-]/', '', $truck) . '_' : 'all_vehicles_';
        $periodPart = ($year ? $year : 'all_years') . ($month ? '_' . str_pad($month, 2, '0', STR_PAD_LEFT) : '');
        $filenameBase = 'expenses_' . $truckPart . $periodPart;

        if ($format === 'xls') {
            ExcelService::exportXls($filenameBase . '.xls', $headers, $rows, 'Business Expenses');
        } elseif ($format === 'csv') {
            ExcelService::exportCsv($filenameBase . '.csv', $headers, $rows);
        } else {
            ExcelService::exportXlsx($filenameBase . '.xlsx', $headers, $rows, 'Business Expenses');
        }
        exit;
    }
}
