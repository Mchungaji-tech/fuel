<?php

namespace App\Controllers;

use App\Core\Database;
use PDO;

class ReportController
{
    public function index(): string
    {
        if (!can_view_financials()) {
            flash('dashboard_error', 'Access restricted. Financial reports are confidential to administration.');
            redirect('/dashboard');
        }

        $pdo = Database::connection();

        // Detect all distinct months from dispatches, expenses, and salaries
        $monthsQuery = $pdo->query('
            SELECT DISTINCT substr(dispatch_date, 1, 7) as month FROM fleet_dispatches
            UNION
            SELECT DISTINCT substr(expense_date, 1, 7) as month FROM expenses
            UNION
            SELECT DISTINCT substr(payment_date, 1, 7) as month FROM driver_salaries
            ORDER BY month DESC
        ')->fetchAll(PDO::FETCH_COLUMN);

        if (empty($monthsQuery)) {
            $monthsQuery = [date('Y-m')];
        }

        $selectedMonth = trim($_GET['month'] ?? $monthsQuery[0]);

        $monthlyAssessments = [];
        foreach ($monthsQuery as $m) {
            if (!$m) continue;

            // Dispatches for this month
            $dispStmt = $pdo->prepare('SELECT 
                COUNT(*) as count,
                COALESCE(SUM(transport_amount), 0) as transport,
                COALESCE(SUM(mileage_cost), 0) as mileage,
                COALESCE(SUM(extra_expenses), 0) as extra,
                COALESCE(SUM(balance), 0) as fleet_profit,
                COALESCE(SUM(loaded_litres), 0) as litres
                FROM fleet_dispatches WHERE substr(dispatch_date, 1, 7) = ?');
            $dispStmt->execute([$m]);
            $disp = $dispStmt->fetch(PDO::FETCH_ASSOC);

            // Outside Expenses for this month
            $expStmt = $pdo->prepare('SELECT 
                COUNT(*) as count,
                COALESCE(SUM(amount), 0) as garage_expenses
                FROM expenses WHERE substr(expense_date, 1, 7) = ?');
            $expStmt->execute([$m]);
            $exp = $expStmt->fetch(PDO::FETCH_ASSOC);

            // Driver Salaries for this month
            $salStmt = $pdo->prepare('SELECT 
                COALESCE(SUM(CASE WHEN status = "Paid" THEN amount ELSE 0 END), 0) as paid_salary,
                COALESCE(SUM(CASE WHEN status = "Wait" THEN amount ELSE 0 END), 0) as wait_salary,
                COALESCE(SUM(amount), 0) as total_salary
                FROM driver_salaries WHERE substr(payment_date, 1, 7) = ?');
            $salStmt->execute([$m]);
            $sal = $salStmt->fetch(PDO::FETCH_ASSOC);

            $fleetProfit = (float) $disp['fleet_profit'];
            $garageExpenses = (float) $exp['garage_expenses'];
            $totalSalaries = (float) $sal['total_salary'];
            $netProfit = $fleetProfit - $garageExpenses - $totalSalaries;

            $transport = (float) $disp['transport'];
            $margin = $transport > 0 ? (($netProfit / $transport) * 100) : 0;

            $monthlyAssessments[$m] = [
                'month' => $m,
                'dispatches_count' => (int) $disp['count'],
                'litres_delivered' => (int) $disp['litres'],
                'transport_revenue' => $transport,
                'mileage_costs' => (float) $disp['mileage'],
                'extra_expenses' => (float) $disp['extra'],
                'fleet_profit' => $fleetProfit,
                'garage_expenses' => $garageExpenses,
                'salaries_paid' => (float) $sal['paid_salary'],
                'salaries_wait' => (float) $sal['wait_salary'],
                'total_salaries' => $totalSalaries,
                'net_profit' => $netProfit,
                'margin' => round($margin, 1),
            ];
        }

        $activeReport = $monthlyAssessments[$selectedMonth] ?? reset($monthlyAssessments);

        // Drivers performance for selected month
        $drvPerfStmt = $pdo->prepare('SELECT 
            driver,
            COUNT(*) as trips_count,
            COALESCE(SUM(loaded_litres), 0) as total_litres,
            COALESCE(SUM(delivered_litres), 0) as delivered_litres,
            COALESCE(SUM(transport_amount), 0) as transport_revenue,
            COALESCE(SUM(balance), 0) as total_profit
            FROM fleet_dispatches 
            WHERE substr(dispatch_date, 1, 7) = ? AND driver IS NOT NULL AND driver != "" AND driver != "Unassigned"
            GROUP BY driver
            ORDER BY trips_count DESC, total_litres DESC');
        $drvPerfStmt->execute([$selectedMonth]);
        $driverPerformance = $drvPerfStmt->fetchAll(PDO::FETCH_ASSOC);

        $topDriverTrips = $driverPerformance[0] ?? null;

        $driverByVolume = $driverPerformance;
        usort($driverByVolume, function($a, $b) {
            return $b['total_litres'] <=> $a['total_litres'];
        });
        $topDriverVolume = $driverByVolume[0] ?? null;

        // Truck performance for selected month (dispatches)
        $trkDispStmt = $pdo->prepare('SELECT 
            truck,
            COUNT(*) as trips_count,
            COALESCE(SUM(loaded_litres), 0) as total_litres,
            COALESCE(SUM(transport_amount), 0) as transport_revenue,
            COALESCE(SUM(balance), 0) as total_profit
            FROM fleet_dispatches
            WHERE substr(dispatch_date, 1, 7) = ? AND truck IS NOT NULL AND truck != ""
            GROUP BY truck
            ORDER BY total_profit DESC');
        $trkDispStmt->execute([$selectedMonth]);
        $truckPerformance = $trkDispStmt->fetchAll(PDO::FETCH_ASSOC);

        $topTruckProfit = $truckPerformance[0] ?? null;

        $truckByTrips = $truckPerformance;
        usort($truckByTrips, function($a, $b) {
            return $b['trips_count'] <=> $a['trips_count'];
        });
        $topTruckTrips = $truckByTrips[0] ?? null;

        // Truck maintenance expenses for selected month
        $trkExpStmt = $pdo->prepare('SELECT 
            truck,
            COUNT(*) as expense_count,
            COALESCE(SUM(amount), 0) as total_maintenance,
            GROUP_CONCAT(expense_title, " • ") as notes
            FROM expenses
            WHERE substr(expense_date, 1, 7) = ? AND truck IS NOT NULL AND truck != "" AND truck != "General"
            GROUP BY truck
            ORDER BY total_maintenance DESC');
        $trkExpStmt->execute([$selectedMonth]);
        $truckExpenses = $trkExpStmt->fetchAll(PDO::FETCH_ASSOC);
        $topTruckMaintenance = $truckExpenses[0] ?? null;

        // Shortage calculations for selected month
        $shortageStmt = $pdo->prepare('SELECT 
            COALESCE(SUM(CASE WHEN loaded_litres > delivered_litres THEN loaded_litres - delivered_litres ELSE 0 END), 0) as total_shortage_litres,
            COALESCE(SUM(payout_difference), 0) as total_shortage_cost
            FROM fleet_dispatches
            WHERE substr(dispatch_date, 1, 7) = ?');
        $shortageStmt->execute([$selectedMonth]);
        $shortageStats = $shortageStmt->fetch(PDO::FETCH_ASSOC);

        return view('reports.index', [
            'title' => 'Monthly Assessment & Financial Reports — Sarura Fuel',
            'monthlyAssessments' => $monthlyAssessments,
            'selectedMonth' => $selectedMonth,
            'report' => $activeReport,
            'availableMonths' => $monthsQuery,
            'driverPerformance' => $driverPerformance,
            'topDriverTrips' => $topDriverTrips,
            'topDriverVolume' => $topDriverVolume,
            'topTruckProfit' => $topTruckProfit,
            'topTruckTrips' => $topTruckTrips,
            'topTruckMaintenance' => $topTruckMaintenance,
            'shortageStats' => $shortageStats,
            'trucks' => $pdo->query('SELECT plate_number FROM trucks ORDER BY plate_number ASC')->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    public function truckReport(): string
    {
        if (!can_view_financials()) {
            flash('dashboard_error', 'Access restricted. Truck performance and profit reports are confidential to administration.');
            redirect('/dashboard');
        }

        $pdo = Database::connection();

        $trucks = $pdo->query('SELECT * FROM trucks ORDER BY plate_number ASC')->fetchAll(PDO::FETCH_ASSOC);

        $truckRows = [];
        $totalCompanyProfit = 0.0;
        $totalSubcontractedCommission = 0.0;
        $totalLitresAll = 0;
        $totalTransportAll = 0.0;
        $totalGarageAll = 0.0;

        foreach ($trucks as $trk) {
            $plate = $trk['plate_number'];
            $isSub = strtolower($trk['ownership_type'] ?? '') === 'subcontracted';

            $dStmt = $pdo->prepare('SELECT 
                COUNT(*) as trips_count,
                COALESCE(SUM(loaded_litres), 0) as total_litres,
                COALESCE(SUM(transport_amount), 0) as total_transport,
                COALESCE(SUM(mileage_cost), 0) as total_mileage,
                COALESCE(SUM(extra_expenses), 0) as total_breakdown,
                COALESCE(SUM(diesel), 0) as total_diesel,
                COALESCE(SUM(balance), 0) as total_balance
                FROM fleet_dispatches WHERE truck = ?');
            $dStmt->execute([$plate]);
            $dStats = $dStmt->fetch(PDO::FETCH_ASSOC);

            $gStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) as garage_total FROM expenses WHERE truck = ?');
            $gStmt->execute([$plate]);
            $garageTotal = (float) $gStmt->fetchColumn();

            $tripsCount = (int) $dStats['trips_count'];
            $litres = (int) $dStats['total_litres'];
            $transport = (float) $dStats['total_transport'];
            $mileage = (float) $dStats['total_mileage'];
            $breakdown = (float) $dStats['total_breakdown'];
            $diesel = (float) $dStats['total_diesel'];
            $balance = (float) $dStats['total_balance'];

            if ($isSub) {
                $netReturn = $balance; // Total commission earned
                $totalSubcontractedCommission += $netReturn;
            } else {
                $netReturn = $balance - $garageTotal; // Company profit less workshop
                $totalCompanyProfit += $netReturn;
            }

            $totalLitresAll += $litres;
            $totalTransportAll += $transport;
            $totalGarageAll += $garageTotal;

            $returnPerLitre = $litres > 0 ? ($netReturn / $litres) : 0;

            $truckRows[] = [
                'id' => $trk['id'],
                'plate_number' => $plate,
                'model' => $trk['model'],
                'capacity_litres' => (int) $trk['capacity_litres'],
                'ownership_type' => $trk['ownership_type'] ?? 'Company',
                'owner_name' => $trk['owner_name'] ?? 'Sarura Fuel Logistics',
                'commission_rate' => (float) ($trk['commission_rate'] ?? 0),
                'is_subcontracted' => $isSub,
                'trips_count' => $tripsCount,
                'total_litres' => $litres,
                'total_transport' => $transport,
                'total_mileage' => $mileage,
                'total_breakdown' => $breakdown,
                'total_diesel' => $diesel,
                'trip_balance' => $balance,
                'garage_expenses' => $garageTotal,
                'net_return' => $netReturn,
                'return_per_litre' => $returnPerLitre,
            ];
        }

        return view('reports.trucks', [
            'title' => 'Detailed Truck Performance Report — Sarura Fuel',
            'truckRows' => $truckRows,
            'totalCompanyProfit' => $totalCompanyProfit,
            'totalSubcontractedCommission' => $totalSubcontractedCommission,
            'totalLitresAll' => $totalLitresAll,
            'totalTransportAll' => $totalTransportAll,
            'totalGarageAll' => $totalGarageAll,
        ]);
    }

    public function exportTrucks(): void
    {
        if (!can_view_financials()) {
            flash('dashboard_error', 'Access restricted.');
            redirect('/dashboard');
        }

        $pdo = Database::connection();
        $truck = trim($_GET['truck'] ?? '');
        $month = trim($_GET['month'] ?? '');
        $year = trim($_GET['year'] ?? '');
        $format = strtolower(trim($_GET['format'] ?? 'xlsx'));

        if ($truck !== '' && strtolower($truck) !== 'all') {
            $stmt = $pdo->prepare('SELECT * FROM trucks WHERE plate_number = ? LIMIT 1');
            $stmt->execute([$truck]);
            $trucks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $trucks = $pdo->query('SELECT * FROM trucks ORDER BY plate_number ASC')->fetchAll(PDO::FETCH_ASSOC);
        }

        $headers = [
            'Truck_Plate', 'Ownership', 'Owner_Name', 'Commission_Rate',
            'Trips_Count', 'Total_Litres', 'Diesel_Fuel_Cost', 'Total_Transport_Billed', 'Total_Mileage_Cost',
            'Breakdown_Fixes', 'Fleet_Balance', 'Garage_Expenses', 'Net_Company_Return', 'Return_Per_Litre'
        ];

        $isKes = current_currency() === 'KES';
        $rate = exchange_rate();

        $rows = [];
        foreach ($trucks as $trk) {
            $plate = $trk['plate_number'];
            $isSub = strtolower($trk['ownership_type'] ?? '') === 'subcontracted';

            $dSql = 'SELECT 
                COUNT(*) as trips_count,
                COALESCE(SUM(loaded_litres), 0) as total_litres,
                COALESCE(SUM(diesel), 0) as total_diesel,
                COALESCE(SUM(transport_amount), 0) as total_transport,
                COALESCE(SUM(mileage_cost), 0) as total_mileage,
                COALESCE(SUM(extra_expenses), 0) as total_breakdown,
                COALESCE(SUM(balance), 0) as total_balance
                FROM fleet_dispatches WHERE truck = ?';
            $dParams = [$plate];

            if ($month !== '' && strtolower($month) !== 'all') {
                $mFormatted = str_pad($month, 2, '0', STR_PAD_LEFT);
                $dSql .= " AND strftime('%m', dispatch_date) = ?";
                $dParams[] = $mFormatted;
            }
            if ($year !== '' && strtolower($year) !== 'all') {
                $dSql .= " AND strftime('%Y', dispatch_date) = ?";
                $dParams[] = $year;
            }

            $dStmt = $pdo->prepare($dSql);
            $dStmt->execute($dParams);
            $dStats = $dStmt->fetch(PDO::FETCH_ASSOC);

            $gSql = 'SELECT COALESCE(SUM(amount), 0) as garage_total FROM expenses WHERE truck = ?';
            $gParams = [$plate];
            if ($month !== '' && strtolower($month) !== 'all') {
                $mFormatted = str_pad($month, 2, '0', STR_PAD_LEFT);
                $gSql .= " AND strftime('%m', expense_date) = ?";
                $gParams[] = $mFormatted;
            }
            if ($year !== '' && strtolower($year) !== 'all') {
                $gSql .= " AND strftime('%Y', expense_date) = ?";
                $gParams[] = $year;
            }

            $gStmt = $pdo->prepare($gSql);
            $gStmt->execute($gParams);
            $garageTotal = (float) $gStmt->fetchColumn();

            $tripsCount = (int) $dStats['trips_count'];
            $litres = (int) $dStats['total_litres'];
            $diesel = (float) $dStats['total_diesel'];
            $displayDiesel = $isKes ? ($diesel * $rate) : $diesel;
            $transport = (float) $dStats['total_transport'];
            $mileage = (float) $dStats['total_mileage'];
            $breakdown = (float) $dStats['total_breakdown'];
            $balance = (float) $dStats['total_balance'];

            $netReturn = $isSub ? $balance : ($balance - $garageTotal);
            $rpl = $litres > 0 ? round($netReturn / $litres, 4) : 0;

            $rows[] = [
                $plate,
                $isSub ? 'Subcontracted' : 'Company Fleet',
                $trk['owner_name'] ?? 'Sarura Fuel',
                (float) ($trk['commission_rate'] ?? 0),
                $tripsCount,
                $litres,
                round($displayDiesel, 2),
                $transport,
                $mileage,
                $breakdown,
                $balance,
                $garageTotal,
                $netReturn,
                $rpl,
            ];
        }

        $truckPart = ($truck && strtolower($truck) !== 'all') ? preg_replace('/[^a-zA-Z0-9_-]/', '', $truck) . '_' : 'all_trucks_';
        $periodPart = ($year ? $year : 'all_years') . ($month ? '_' . str_pad($month, 2, '0', STR_PAD_LEFT) : '');
        $filenameBase = 'truck_performance_' . $truckPart . $periodPart;

        if ($format === 'xls') {
            \App\Services\ExcelService::exportXls($filenameBase . '.xls', $headers, $rows, 'Truck Performance');
        } elseif ($format === 'csv') {
            \App\Services\ExcelService::exportCsv($filenameBase . '.csv', $headers, $rows);
        } else {
            \App\Services\ExcelService::exportXlsx($filenameBase . '.xlsx', $headers, $rows, 'Truck Performance');
        }
        exit;
    }

    public function exportMonthly(): void
    {
        if (!can_view_financials()) {
            flash('dashboard_error', 'Access restricted.');
            redirect('/dashboard');
        }

        $pdo = Database::connection();
        $month = trim($_GET['month'] ?? '');
        $year = trim($_GET['year'] ?? '');
        $truck = trim($_GET['truck'] ?? '');
        $format = strtolower(trim($_GET['format'] ?? 'xlsx'));

        $sql = 'SELECT * FROM fleet_dispatches WHERE 1=1';
        $params = [];

        if ($truck !== '' && strtolower($truck) !== 'all') {
            $sql .= ' AND truck = ?';
            $params[] = $truck;
        }

        if ($month !== '' && strtolower($month) !== 'all') {
            if (strlen($month) === 7 && str_contains($month, '-')) {
                $sql .= ' AND substr(dispatch_date, 1, 7) = ?';
                $params[] = $month;
            } else {
                $mFormatted = str_pad($month, 2, '0', STR_PAD_LEFT);
                $sql .= " AND strftime('%m', dispatch_date) = ?";
                $params[] = $mFormatted;
            }
        }

        if ($year !== '' && strtolower($year) !== 'all') {
            $sql .= " AND strftime('%Y', dispatch_date) = ?";
            $params[] = $year;
        }

        $sql .= ' ORDER BY dispatch_date DESC, id DESC';
        $dStmt = $pdo->prepare($sql);
        $dStmt->execute($params);
        $dispatches = $dStmt->fetchAll(PDO::FETCH_ASSOC);

        $currencySymbol = app_currency_symbol();
        $isKes = current_currency() === 'KES';
        $rate = exchange_rate();

        $headers = [
            'Trip_Number', 'DOL', 'Truck', 'Destination', 'Client', 'Product', 'Driver',
            'Loaded_Litres', 'Delivered_Litres', 'Diesel_Fuel_Cost (' . $currencySymbol . ')', 'Transport_Revenue', 'Mileage_Expense', 'Breakdown_Cost', 'Net_Trip_Profit', 'Status'
        ];

        $rows = [];
        foreach ($dispatches as $d) {
            $dieselVal = (float)($d['diesel'] ?? 0);
            $displayDiesel = $isKes ? ($dieselVal * $rate) : $dieselVal;

            $rows[] = [
                $d['trip_number'],
                $d['dispatch_date'],
                $d['truck'],
                $d['destination'],
                $d['client_name'] ?? '',
                $d['product'],
                $d['driver'],
                (int)$d['loaded_litres'],
                (int)($d['delivered_litres'] ?? $d['loaded_litres']),
                round($displayDiesel, 2),
                (float)$d['transport_amount'],
                (float)$d['mileage_cost'],
                (float)$d['extra_expenses'],
                (float)$d['balance'],
                $d['status'],
            ];
        }

        $truckPart = ($truck && strtolower($truck) !== 'all') ? preg_replace('/[^a-zA-Z0-9_-]/', '', $truck) . '_' : 'all_cars_';
        $periodPart = ($year ? $year : 'all_years') . ($month ? '_' . str_replace('-', '_', $month) : '');
        $filenameBase = 'monthly_assessment_' . $truckPart . $periodPart;

        if ($format === 'xls') {
            \App\Services\ExcelService::exportXls($filenameBase . '.xls', $headers, $rows, 'Monthly Assessment');
        } elseif ($format === 'csv') {
            \App\Services\ExcelService::exportCsv($filenameBase . '.csv', $headers, $rows);
        } else {
            \App\Services\ExcelService::exportXlsx($filenameBase . '.xlsx', $headers, $rows, 'Monthly Assessment');
        }
        exit;
    }
}

