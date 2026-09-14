<?php

namespace App\Controllers;

use App\Core\Database;
use App\Services\ExcelService;
use PDO;
use Exception;

class FinancialController
{
    /**
     * Display the financial management ledger and daily cash flow dashboard
     */
    public function index(): string
    {
        $pdo = Database::connection();

        $search = trim($_GET['search'] ?? '');
        $category = trim($_GET['category'] ?? '');
        $method = trim($_GET['payment_method'] ?? '');
        $startDate = trim($_GET['start_date'] ?? '');
        $endDate = trim($_GET['end_date'] ?? '');
        $month = trim($_GET['month'] ?? '');
        $year = trim($_GET['year'] ?? '');

        // Base query conditions
        $where = ['1=1'];
        $params = [];

        if ($search !== '') {
            $where[] = '(reason LIKE ? OR category LIKE ? OR reference_no LIKE ? OR payment_method LIKE ? OR recorded_by LIKE ?)';
            $term = "%{$search}%";
            $params = array_merge($params, [$term, $term, $term, $term, $term]);
        }

        if ($category !== '') {
            $where[] = 'category = ?';
            $params[] = $category;
        }

        if ($method !== '') {
            $where[] = 'payment_method = ?';
            $params[] = $method;
        }

        if ($startDate !== '') {
            $where[] = 'entry_date >= ?';
            $params[] = $startDate;
        }

        if ($endDate !== '') {
            $where[] = 'entry_date <= ?';
            $params[] = $endDate;
        }

        if ($month !== '') {
            $where[] = "strftime('%m', entry_date) = ? OR entry_date LIKE ?";
            $params[] = str_pad($month, 2, '0', STR_PAD_LEFT);
            $params[] = "%-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-%";
        }

        if ($year !== '') {
            $where[] = "strftime('%Y', entry_date) = ? OR entry_date LIKE ?";
            $params[] = $year;
            $params[] = "{$year}-%";
        }

        $whereClause = implode(' AND ', $where);

        // Fetch all chronological records to accurately calculate running balance
        $chronStmt = $pdo->prepare("SELECT id, entry_date, amount_in, amount_out FROM financial_records ORDER BY entry_date ASC, id ASC");
        $chronStmt->execute();
        $allChron = $chronStmt->fetchAll(PDO::FETCH_ASSOC);

        $runningBalances = [];
        $cumBalance = 0.0;
        foreach ($allChron as $item) {
            $in = (float)($item['amount_in'] ?? 0);
            $out = (float)($item['amount_out'] ?? 0);
            $cumBalance += ($in - $out);
            $runningBalances[$item['id']] = $cumBalance;
        }

        // Fetch filtered records for display (latest first)
        $sql = "SELECT * FROM financial_records WHERE {$whereClause} ORDER BY entry_date DESC, id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Attach running balance & formatted fields
        foreach ($records as &$r) {
            $r['amount_in'] = (float)($r['amount_in'] ?? 0);
            $r['amount_out'] = (float)($r['amount_out'] ?? 0);
            $r['balance'] = (float)($r['balance'] ?? ($r['amount_in'] - $r['amount_out']));
            $r['running_balance'] = $runningBalances[$r['id']] ?? $r['balance'];
        }
        unset($r);

        // Compute KPI Summary Aggregates
        $kpiStmt = $pdo->query("SELECT 
            COALESCE(SUM(amount_in), 0) as total_in,
            COALESCE(SUM(amount_out), 0) as total_out,
            COALESCE(SUM(amount_in - amount_out), 0) as net_balance,
            COUNT(*) as total_records
            FROM financial_records");
        $kpis = $kpiStmt->fetch(PDO::FETCH_ASSOC);

        $today = date('Y-m-d');
        $todayStmt = $pdo->prepare("SELECT 
            COALESCE(SUM(amount_in), 0) as today_in,
            COALESCE(SUM(amount_out), 0) as today_out,
            COALESCE(SUM(amount_in - amount_out), 0) as today_net
            FROM financial_records WHERE entry_date = ?");
        $todayStmt->execute([$today]);
        $todayKpis = $todayStmt->fetch(PDO::FETCH_ASSOC);

        // Filter categories & payment methods
        $categories = $pdo->query("SELECT DISTINCT category FROM financial_records WHERE category IS NOT NULL AND category != '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
        $methods = $pdo->query("SELECT DISTINCT payment_method FROM financial_records WHERE payment_method IS NOT NULL AND payment_method != '' ORDER BY payment_method ASC")->fetchAll(PDO::FETCH_COLUMN);

        return view('financial.index', [
            'title' => 'Financial Management & Cash Flow — Sarura Fuel',
            'records' => $records,
            'kpis' => $kpis,
            'todayKpis' => $todayKpis,
            'categories' => $categories,
            'methods' => $methods,
            'filters' => [
                'search' => $search,
                'category' => $category,
                'payment_method' => $method,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'month' => $month,
                'year' => $year,
            ]
        ]);
    }

    /**
     * Store a new financial cash flow entry
     */
    public function store(): void
    {
        $pdo = Database::connection();

        $entryDate = trim($_POST['entry_date'] ?? '');
        if (empty($entryDate)) {
            $entryDate = date('Y-m-d');
        } else {
            $entryDate = ExcelService::normalizeDate($entryDate);
        }

        $category = trim($_POST['category'] ?? 'General');
        $amountIn = max(0.0, (float)($_POST['amount_in'] ?? 0));
        $amountOut = max(0.0, (float)($_POST['amount_out'] ?? 0));
        $balance = $amountIn - $amountOut;
        $reason = trim($_POST['reason'] ?? $_POST['notes'] ?? '');
        $method = trim($_POST['payment_method'] ?? 'Cash');
        $refNo = trim($_POST['reference_no'] ?? '');
        $user = $_SESSION['user_name'] ?? 'Admin';

        if ($reason === '' && $amountIn <= 0 && $amountOut <= 0) {
            if ($this->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Please provide an amount in, amount out, or reason.']);
                exit;
            }
            flash('financial_error', 'Please provide an amount in, amount out, or reason.');
            redirect('/financial');
        }

        $stmt = $pdo->prepare('INSERT INTO financial_records (
            entry_date, category, amount_in, amount_out, balance, reason, payment_method, reference_no, recorded_by, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

        $now = date('Y-m-d H:i:s');
        $stmt->execute([
            $entryDate,
            $category,
            $amountIn,
            $amountOut,
            $balance,
            $reason,
            $method,
            $refNo,
            $user,
            $now,
            $now
        ]);

        $newId = (int)$pdo->lastInsertId();

        $compositeKey = implode('::', [
            $entryDate,
            $category,
            (string)(float)$amountIn,
            (string)(float)$amountOut,
            $reason
        ]);
        \App\Services\DatabaseSyncService::clearDeletion('financial_records', $compositeKey);

        if ($this->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Transaction recorded successfully.',
                'id' => $newId,
                'balance' => $balance
            ]);
            exit;
        }

        flash('financial_success', 'Financial transaction recorded successfully.');
        redirect('/financial');
    }

    /**
     * Inline update for financial table cells/rows
     */
    public function inlineUpdate(): void
    {
        header('Content-Type: application/json');
        $pdo = Database::connection();

        // Support both JSON body and x-www-form-urlencoded
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid transaction record ID.']);
            exit;
        }

        // Fetch current record
        $curr = $pdo->prepare('SELECT * FROM financial_records WHERE id = ? LIMIT 1');
        $curr->execute([$id]);
        $row = $curr->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Transaction record not found.']);
            exit;
        }

        // Check if updating a single column or full row
        if (isset($input['field']) && array_key_exists('value', $input)) {
            $field = trim($input['field']);
            $val = trim($input['value']);

            $allowed = ['entry_date', 'category', 'amount_in', 'amount_out', 'reason', 'payment_method', 'reference_no'];
            if (!in_array($field, $allowed, true)) {
                echo json_encode(['success' => false, 'message' => "Field '{$field}' cannot be edited directly."]);
                exit;
            }

            $row[$field] = $val;
        } else {
            // Full row inline edit
            if (isset($input['entry_date'])) $row['entry_date'] = trim($input['entry_date']);
            if (isset($input['category'])) $row['category'] = trim($input['category']);
            if (isset($input['amount_in'])) $row['amount_in'] = (float)$input['amount_in'];
            if (isset($input['amount_out'])) $row['amount_out'] = (float)$input['amount_out'];
            if (isset($input['reason'])) $row['reason'] = trim($input['reason']);
            if (isset($input['payment_method'])) $row['payment_method'] = trim($input['payment_method']);
            if (isset($input['reference_no'])) $row['reference_no'] = trim($input['reference_no']);
        }

        // Recalculate net day balance: Amount In - Amount Out
        $amtIn = max(0.0, (float)($row['amount_in'] ?? 0));
        $amtOut = max(0.0, (float)($row['amount_out'] ?? 0));
        $row['balance'] = $amtIn - $amtOut;
        $entryDate = ExcelService::normalizeDate($row['entry_date'] ?? date('Y-m-d'));

        $upd = $pdo->prepare('UPDATE financial_records SET 
            entry_date = ?,
            category = ?,
            amount_in = ?,
            amount_out = ?,
            balance = ?,
            reason = ?,
            payment_method = ?,
            reference_no = ?,
            updated_at = ?
            WHERE id = ?');

        $upd->execute([
            $entryDate,
            $row['category'] ?? 'General',
            $amtIn,
            $amtOut,
            $row['balance'],
            $row['reason'] ?? '',
            $row['payment_method'] ?? 'Cash',
            $row['reference_no'] ?? '',
            date('Y-m-d H:i:s'),
            $id
        ]);

        // Global KPI update
        $kpi = $pdo->query("SELECT 
            COALESCE(SUM(amount_in), 0) as total_in,
            COALESCE(SUM(amount_out), 0) as total_out,
            COALESCE(SUM(amount_in - amount_out), 0) as net_balance
            FROM financial_records")->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'message' => 'Record updated successfully.',
            'row' => [
                'id' => $id,
                'entry_date' => $entryDate,
                'category' => $row['category'],
                'amount_in' => $amtIn,
                'amount_out' => $amtOut,
                'balance' => $row['balance'],
                'reason' => $row['reason'],
                'payment_method' => $row['payment_method'],
                'reference_no' => $row['reference_no'],
            ],
            'kpis' => $kpi
        ]);
        exit;
    }

    /**
     * Delete a financial entry
     */
    public function delete(int $id): void
    {
        $pdo = Database::connection();
        $curr = $pdo->prepare('SELECT * FROM financial_records WHERE id = ? LIMIT 1');
        $curr->execute([$id]);
        $row = $curr->fetch(PDO::FETCH_ASSOC);

        $del = $pdo->prepare('DELETE FROM financial_records WHERE id = ?');
        $del->execute([$id]);

        if ($row) {
            $compositeKey = implode('::', [
                $row['entry_date'] ?? '',
                $row['category'] ?? '',
                (string)(float)($row['amount_in'] ?? 0),
                (string)(float)($row['amount_out'] ?? 0),
                $row['reason'] ?? ''
            ]);
            \App\Services\DatabaseSyncService::recordDeletion('financial_records', $compositeKey);
        }

        if ($this->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Transaction deleted successfully.']);
            exit;
        }

        flash('financial_success', 'Transaction record deleted.');
        redirect('/financial');
    }

    /**
     * Export the financial records to Excel (.xlsx) or CSV
     * User requirement: "ensure the new table of fince can be exported"
     */
    public function export(): void
    {
        $pdo = Database::connection();

        $format = strtolower(trim($_GET['format'] ?? 'xlsx'));
        $search = trim($_GET['search'] ?? '');
        $category = trim($_GET['category'] ?? '');
        $method = trim($_GET['payment_method'] ?? '');
        $startDate = trim($_GET['start_date'] ?? '');
        $endDate = trim($_GET['end_date'] ?? '');
        $month = trim($_GET['month'] ?? '');
        $year = trim($_GET['year'] ?? '');

        // Build query matching filters
        $where = ['1=1'];
        $params = [];

        if ($search !== '') {
            $where[] = '(reason LIKE ? OR category LIKE ? OR reference_no LIKE ? OR payment_method LIKE ? OR recorded_by LIKE ?)';
            $term = "%{$search}%";
            $params = array_merge($params, [$term, $term, $term, $term, $term]);
        }

        if ($category !== '') {
            $where[] = 'category = ?';
            $params[] = $category;
        }

        if ($method !== '') {
            $where[] = 'payment_method = ?';
            $params[] = $method;
        }

        if ($startDate !== '') {
            $where[] = 'entry_date >= ?';
            $params[] = $startDate;
        }

        if ($endDate !== '') {
            $where[] = 'entry_date <= ?';
            $params[] = $endDate;
        }

        if ($month !== '') {
            $where[] = "strftime('%m', entry_date) = ? OR entry_date LIKE ?";
            $params[] = str_pad($month, 2, '0', STR_PAD_LEFT);
            $params[] = "%-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-%";
        }

        if ($year !== '') {
            $where[] = "strftime('%Y', entry_date) = ? OR entry_date LIKE ?";
            $params[] = $year;
            $params[] = "{$year}-%";
        }

        $whereClause = implode(' AND ', $where);

        // Fetch records in chronological order for export so running balance is sequential
        $sql = "SELECT * FROM financial_records WHERE {$whereClause} ORDER BY entry_date ASC, id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Headers for Export (Clean, comprehensive, 10 columns)
        $headers = [
            '#',
            'Transaction Date',
            'Category',
            'Expenditure Note / Reason',
            'Payment Method',
            'Reference / Receipt #',
            'Amount In (KSh / Inflow)',
            'Amount Out (KSh / Spent)',
            'Net Day Balance',
            'Running Balance',
            'Recorded By',
            'Recorded At'
        ];

        $exportRows = [];
        $totalIn = 0.0;
        $totalOut = 0.0;
        $cumBalance = 0.0;
        $idx = 1;

        foreach ($records as $r) {
            $amtIn = (float)($r['amount_in'] ?? 0);
            $amtOut = (float)($r['amount_out'] ?? 0);
            $net = $amtIn - $amtOut;
            $cumBalance += $net;

            $totalIn += $amtIn;
            $totalOut += $amtOut;

            $exportRows[] = [
                $idx++,
                $r['entry_date'] ?? '',
                $r['category'] ?? 'General',
                $r['reason'] ?? '—',
                $r['payment_method'] ?? 'Cash',
                $r['reference_no'] ?? '—',
                $amtIn > 0 ? $amtIn : 0.00,
                $amtOut > 0 ? $amtOut : 0.00,
                $net,
                $cumBalance,
                $r['recorded_by'] ?? 'Staff',
                $r['created_at'] ?? ''
            ];
        }

        // Add summary totals row
        $netTotal = $totalIn - $totalOut;
        $exportRows[] = [
            'TOTALS',
            count($records) . ' Transactions',
            '',
            'Consolidated Net Cash Flow',
            '',
            '',
            $totalIn,
            $totalOut,
            $netTotal,
            $cumBalance,
            '',
            date('Y-m-d H:i')
        ];

        $filename = 'Financial_Cash_Flow_' . date('Y-m-d_His');

        if ($format === 'csv') {
            ExcelService::exportCsv($filename . '.csv', $headers, $exportRows);
        } else {
            ExcelService::exportXlsx($filename . '.xlsx', $headers, $exportRows, 'Cash Flow');
        }
        exit;
    }

    /**
     * Check if current request is AJAX / JSON
     */
    private function isAjax(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
            || (isset($_GET['ajax']) && $_GET['ajax'] == 1);
    }
}
