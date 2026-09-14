<?php

namespace App\Controllers;

use App\Core\Database;
use App\Services\ExcelService;
use PDO;

class FleetController
{
    public function index(): string
    {
        $pdo = Database::connection();

        $search = trim($_GET['search'] ?? '');
        $statusFilter = trim($_GET['status'] ?? '');

        $sql = 'SELECT * FROM fleet_dispatches WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (trip_number LIKE ? OR bol_number LIKE ? OR truck LIKE ? OR driver LIKE ? OR from_location LIKE ? OR destination LIKE ? OR product LIKE ?)';
            $searchTerm = "%{$search}%";
            for ($i = 0; $i < 7; $i++) {
                $params[] = $searchTerm;
            }
        }

        if ($statusFilter !== '' && strtolower($statusFilter) !== 'all') {
            $sql .= ' AND LOWER(status) = LOWER(?)';
            $params[] = $statusFilter;
        }

        $sql .= ' ORDER BY dispatch_date DESC, id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dispatches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Automatically sync diesel totals and balances from fleet_diesel_logs
        try {
            $pdo->exec("UPDATE fleet_dispatches SET 
                diesel = (SELECT COALESCE(SUM(base_usd_cost), 0) FROM fleet_diesel_logs WHERE fleet_diesel_logs.dispatch_id = fleet_dispatches.id),
                diesel_litres = (SELECT COALESCE(SUM(litres), 0) FROM fleet_diesel_logs WHERE fleet_diesel_logs.dispatch_id = fleet_dispatches.id)
                WHERE id IN (SELECT DISTINCT dispatch_id FROM fleet_diesel_logs)");
            $pdo->exec("UPDATE fleet_dispatches SET balance = (CASE WHEN is_subcontracted = 1 THEN agreed_commission ELSE COALESCE(final_payout, transport_amount) - (COALESCE(mileage_cost, 0) + COALESCE(diesel, 0) + COALESCE(extra_expenses, 0)) END)");
        } catch (\Throwable $e) {}

        // Calculate aggregates across all dispatches
        $aggStmt = $pdo->query('SELECT 
            COUNT(*) as total_count,
            COALESCE(SUM(transport_amount), 0) as total_transport,
            COALESCE(SUM(mileage_cost), 0) as total_mileage,
            COALESCE(SUM(extra_expenses), 0) as total_extra,
            COALESCE(SUM(diesel), 0) as total_diesel,
            COALESCE(SUM(balance), 0) as total_balance,
            COALESCE(SUM(loaded_litres), 0) as total_loaded
            FROM fleet_dispatches');
        $aggregates = $aggStmt->fetch(PDO::FETCH_ASSOC);

        // Fetch options for the dispatch modal
        $trucks = $pdo->query('SELECT plate_number, model, capacity_litres, ownership_type, owner_name, commission_rate, status FROM trucks ORDER BY plate_number ASC')->fetchAll(PDO::FETCH_ASSOC);
        $drivers = $pdo->query('SELECT id, name, status FROM drivers ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
        $products = $pdo->query('SELECT code, name, unit_price FROM products WHERE status = "Active" AND UPPER(code) IN ("PMS", "AGO") ORDER BY code ASC')->fetchAll(PDO::FETCH_ASSOC);
        if (empty($products)) {
            $products = [
                ['code' => 'AGO', 'name' => 'Automotive Gas Oil (Diesel)', 'unit_price' => 9.50],
                ['code' => 'PMS', 'name' => 'Premium Motor Spirit (Super Petrol)', 'unit_price' => 10.50],
            ];
        }
        $customers = $pdo->query('SELECT DISTINCT name FROM customers ORDER BY name ASC')->fetchAll(PDO::FETCH_COLUMN) ?: [];

        // Fetch route mileage presets
        $mileageRatesStmt = $pdo->query('SELECT * FROM route_mileage_rates ORDER BY destination ASC');
        $mileageRates = $mileageRatesStmt ? $mileageRatesStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $yearsStmt = $pdo->query('SELECT DISTINCT substr(dispatch_date, 1, 4) as yr FROM fleet_dispatches WHERE dispatch_date IS NOT NULL AND dispatch_date != "" ORDER BY yr DESC');
        $availableYears = $yearsStmt ? $yearsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
        $curY = (string)date('Y');
        if (!in_array($curY, $availableYears)) {
            array_unshift($availableYears, $curY);
        }

        return view('fleet.index', [
            'title' => 'Fleet Management — Sarura Fuel',
            'dispatches' => $dispatches,
            'aggregates' => $aggregates,
            'trucks' => $trucks,
            'drivers' => $drivers,
            'products' => $products,
            'customers' => $customers,
            'mileageRates' => $mileageRates,
            'search' => $search,
            'statusFilter' => $statusFilter,
            'availableYears' => $availableYears,
        ]);
    }

    public function store(): void
    {
        $pdo = Database::connection();

        $dispatchDate = ExcelService::normalizeDate($_POST['dispatch_date'] ?? date('Y-m-d'));
        $truck = trim($_POST['truck'] ?? '');
        $truckCapacity = (int) ($_POST['truck_capacity'] ?? 0);
        $loadedLitres = (int) ($_POST['loaded_litres'] ?? $truckCapacity);
        if ($loadedLitres <= 0) {
            $loadedLitres = $truckCapacity;
        }

        $fromLocation = trim($_POST['from_location'] ?? 'Eldoret');
        if ($fromLocation === '') {
            $fromLocation = 'Eldoret';
        }
        $destination = trim($_POST['destination'] ?? '');
        $product = strtoupper(trim($_POST['product'] ?? 'AGO'));
        if (!in_array($product, ['PMS', 'AGO'])) {
            $product = 'AGO';
        }

        $driver = trim($_POST['driver'] ?? 'Unassigned');
        $customDriver = trim($_POST['other_driver_name'] ?? $_POST['custom_driver_name'] ?? '');
        if (($driver === '__other__' || $driver === '') && $customDriver !== '') {
            $driver = $customDriver;
        }

        // Check truck ownership to determine commission logic (Contract vs Owner)
        $truckInfo = null;
        if ($truck !== '') {
            $tStmt = $pdo->prepare('SELECT * FROM trucks WHERE plate_number = ? LIMIT 1');
            $tStmt->execute([$truck]);
            $truckInfo = $tStmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($truckCapacity <= 0 && $truckInfo && !empty($truckInfo['capacity_litres'])) {
            $truckCapacity = (int) $truckInfo['capacity_litres'];
        }
        if ($loadedLitres <= 0 && $truckCapacity > 0) {
            $loadedLitres = $truckCapacity;
        }

        $isSubcontracted = ($truckInfo && in_array(strtolower($truckInfo['ownership_type'] ?? ''), ['contract', 'subcontracted', 'sub'])) ? 1 : 0;

        // Currency conversion
        $isKes = current_currency() === 'KES';
        $rate = exchange_rate();

        // Agreed Initial Transport Payment (USD)
        // Sarura does not sell fuel; company is paid an agreed transportation fee for the trip
        if (isset($_POST['transport_amount_usd']) && $_POST['transport_amount_usd'] !== '') {
            $transportAmount = (float)$_POST['transport_amount_usd'];
        } elseif (isset($_POST['transport_amount_kes']) && $_POST['transport_amount_kes'] !== '') {
            $rawKes = (float)$_POST['transport_amount_kes'];
            $transportAmount = $rate > 0 ? ($rawKes / $rate) : $rawKes;
        } elseif (isset($_POST['transport_amount']) && $_POST['transport_amount'] !== '') {
            $rawTrans = (float)$_POST['transport_amount'];
            $transportAmount = $isKes ? ($rawTrans / $rate) : $rawTrans;
        } else {
            $transportAmount = 0.0;
        }

        // Nominal transport yield per litre for table display & schema compatibility
        $unitPrice = $loadedLitres > 0 ? round($transportAmount / $loadedLitres, 4) : 0.0;

        // Transit Shortage & Delivered Litres logic
        $shortageLitres = max(0, (int)($_POST['shortage_litres'] ?? 0));
        $status = trim($_POST['status'] ?? 'In Transit');
        $isDelivered = (strtolower($status) === 'delivered');

        if ($shortageLitres > 0) {
            $deliveredLitres = max(0, $loadedLitres - $shortageLitres);
            $payoutDiff = ($loadedLitres > 0) ? round(($shortageLitres / $loadedLitres) * $transportAmount, 2) : 0.0;
            $finalPayout = max(0, $transportAmount - $payoutDiff);
        } elseif ($isDelivered) {
            $deliveredLitres = $loadedLitres;
            $finalPayout = $transportAmount;
            $payoutDiff = 0.0;
        } else {
            // In transit: pending client arrival & offloading
            $deliveredLitres = null;
            $finalPayout = null;
            $payoutDiff = 0.0;
        }

        // Single Diesel Fueling Section (Owner's Expense)
        $dieselLitres = (float)($_POST['diesel_litres'] ?? 0);
        $rawDieselUnitPrice = (float)($_POST['diesel_unit_price'] ?? 0);
        $dieselUnitPrice = $isKes ? ($rawDieselUnitPrice / $rate) : $rawDieselUnitPrice;

        if ($dieselLitres > 0 && $dieselUnitPrice > 0) {
            $diesel = $dieselLitres * $dieselUnitPrice;
        } else {
            $rawDiesel = (float)($_POST['diesel'] ?? 0);
            $diesel = $isKes ? ($rawDiesel / $rate) : $rawDiesel;
            if ($dieselLitres > 0 && $diesel > 0) {
                $dieselUnitPrice = $diesel / $dieselLitres;
            }
        }

        // Trip Expenses are strictly Mileage and Diesel Fueling
        $rawMileage = (float) ($_POST['mileage_cost'] ?? 0);
        $mileageCost = $isKes ? ($rawMileage / $rate) : $rawMileage;
        $extraExpenses = 0.0; // Any extra repairs must be logged on the Expenses page
        $breakdownNotes = '';

        $agreedCommission = 0.0;
        if ($isSubcontracted) {
            $rawComm = (float) ($_POST['agreed_commission'] ?? $truckInfo['commission_rate'] ?? 0);
            $agreedCommission = $isKes ? ($rawComm / $rate) : $rawComm;
            $balance = $agreedCommission;
        } else {
            $effectiveRev = ($finalPayout !== null && $finalPayout > 0) ? $finalPayout : $transportAmount;
            $balance = $effectiveRev - ($mileageCost + $diesel);
        }

        $shortageNotes = trim($_POST['shortage_notes'] ?? '');
        $clientName = trim($_POST['client_name'] ?? '');
        if ($clientName === '') {
            $clientName = 'Spot Consignee';
        }
        $sealNumbers = trim($_POST['seal_numbers'] ?? '');

        $tripNumber = trim($_POST['trip_number'] ?? '');
        if ($tripNumber === '') {
            $tripNumber = 'TRP-' . date('Y') . '-' . rand(1000, 9999);
        }

        $bolNumber = 'BOL-' . date('Y') . '-' . rand(1000, 9999);

        if ($truck !== '' && $fromLocation !== '' && $destination !== '') {
            $stmt = $pdo->prepare('INSERT INTO fleet_dispatches (
                trip_number, bol_number, dispatch_date, truck, truck_capacity, loaded_litres, shortage_litres, delivered_litres,
                from_location, destination, product, unit_price, driver, transport_amount, final_payout, payout_difference,
                mileage_cost, diesel_litres, diesel_unit_price, diesel, extra_expenses, breakdown_notes, shortage_notes, client_name, balance, is_subcontracted, agreed_commission,
                status, seal_numbers, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

            $stmt->execute([
                $tripNumber,
                $bolNumber,
                $dispatchDate,
                $truck,
                $truckCapacity,
                $loadedLitres,
                $shortageLitres,
                $deliveredLitres,
                $fromLocation,
                $destination,
                $product,
                $unitPrice,
                $driver,
                $transportAmount,
                $finalPayout,
                $payoutDiff,
                $mileageCost,
                $dieselLitres,
                $dieselUnitPrice,
                $diesel,
                $extraExpenses,
                $breakdownNotes,
                $shortageNotes,
                $clientName,
                $balance,
                $isSubcontracted,
                $agreedCommission,
                $status,
                $sealNumbers,
                date('Y-m-d H:i:s'),
            ]);

            $newDispatchId = (int)$pdo->lastInsertId();
            if ($newDispatchId > 0 && $dieselLitres > 0) {
                if ($dieselUnitPrice <= 0 && $diesel <= 0) {
                    $dieselUnitPrice = 1.3846;
                    $diesel = round($dieselLitres * $dieselUnitPrice, 2);
                }
                $fuelCountry = trim($_POST['diesel_country'] ?? 'Kenya');
                $fuelCurrency = strtoupper(trim($_POST['diesel_currency'] ?? ($isKes ? 'KES' : 'USD')));
                $fuelExRate = (float)($_POST['diesel_exchange_rate'] ?? ($fuelCurrency === 'KES' ? $rate : 1.0));
                if ($fuelExRate <= 0) $fuelExRate = 1.0;
                $fuelLocPrice = (float)($_POST['diesel_local_unit_price'] ?? ($fuelCurrency === 'KES' ? ($dieselUnitPrice * $rate) : $dieselUnitPrice));
                if ($fuelLocPrice <= 0) {
                    $fuelLocPrice = $fuelCurrency === 'KES' ? ($dieselUnitPrice * $rate) : $dieselUnitPrice;
                }
                $fuelLocTotal = round($dieselLitres * $fuelLocPrice, 2);
                $fuelBaseUsd = round($diesel > 0 ? $diesel : ($fuelLocTotal / $fuelExRate), 2);

                $departureStation = trim($_POST['diesel_station'] ?? '');
                if ($departureStation === '') {
                    $departureStation = $fromLocation . ' Depot Station';
                }

                $dLogStmt = $pdo->prepare('INSERT INTO fleet_diesel_logs (
                    dispatch_id, trip_number, truck, fuel_date, station_location, country, currency_code,
                    exchange_rate, litres, local_unit_price, local_total_cost, base_usd_cost,
                    receipt_status, receipt_number, notes, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $dLogStmt->execute([
                    $newDispatchId,
                    $tripNumber,
                    $truck,
                    $dispatchDate,
                    $departureStation,
                    $fuelCountry,
                    $fuelCurrency,
                    $fuelExRate,
                    $dieselLitres,
                    $fuelLocPrice,
                    $fuelLocTotal,
                    $fuelBaseUsd,
                    'Received',
                    trim($_POST['diesel_receipt_number'] ?? 'DEP-INIT'),
                    'Departure fuel logged during dispatch creation',
                    date('Y-m-d H:i:s')
                ]);
            }

            // Check if temporary / new driver was created
            $isNewDriver = false;
            if ($driver !== '' && $driver !== 'Unassigned') {
                $chkDrv = $pdo->prepare('SELECT COUNT(*) FROM drivers WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))');
                $chkDrv->execute([$driver]);
                if ((int)$chkDrv->fetchColumn() === 0) {
                    $isNewDriver = true;
                    flash('new_driver_prompt', [
                        'name' => $driver,
                        'trip_number' => $tripNumber,
                    ]);
                }
            }

            // Set flash for dispatch success modal popup
            flash('dispatch_success_modal', [
                'trip_number' => $tripNumber,
                'truck' => $truck,
                'driver' => $driver,
                'destination' => $destination,
                'loaded_litres' => $loadedLitres,
                'diesel' => $diesel,
                'status' => $status,
                'is_new_driver' => $isNewDriver,
            ]);

            // Auto-sync customer account with case-insensitive matching
            if ($clientName !== '' && $clientName !== 'Regional Fuel Consignee') {
                $cStmt = $pdo->prepare('SELECT id, name FROM customers WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1');
                $cStmt->execute([$clientName]);
                $existingCust = $cStmt->fetch(PDO::FETCH_ASSOC);
                if ($existingCust) {
                    $pdo->prepare('UPDATE customers SET orders = orders + 1, total_litres = total_litres + ?, last_dispatch_date = ? WHERE id = ?')
                        ->execute([$loadedLitres, $dispatchDate, $existingCust['id']]);
                } else {
                    $pdo->prepare('INSERT INTO customers (name, company, country, orders, total_litres, last_dispatch_date, status) VALUES (?, ?, ?, 1, ?, ?, "Active")')
                        ->execute([$clientName, $clientName, $destination, $loadedLitres, $dispatchDate]);
                }
            }

            // Auto-sync into trips table
            $tStmt = $pdo->prepare('SELECT id FROM trips WHERE trip_number = ? LIMIT 1');
            $tStmt->execute([$tripNumber]);
            if ($tStmt->fetch()) {
                $pdo->prepare('UPDATE trips SET customer = ?, truck = ?, driver = ?, route = ?, load_quantity = ?, status = ? WHERE trip_number = ?')
                    ->execute([$clientName, $truck, $driver, ($fromLocation . ' → ' . $destination), (string)$loadedLitres, $status, $tripNumber]);
            } else {
                $pdo->prepare('INSERT INTO trips (trip_number, customer, truck, driver, route, load_quantity, status) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$tripNumber, $clientName, $truck, $driver, ($fromLocation . ' → ' . $destination), (string)$loadedLitres, $status]);
            }

            log_audit('Fleet Dispatch', 'CREATE_DISPATCH', "Created dispatch {$tripNumber} ({$truck}) to {$destination} for client {$clientName} loaded from Eldoret");

            flash('fleet_success', "Fleet dispatch {$tripNumber} registered successfully in status [{$status}].");
        } else {
            flash('fleet_error', 'Please fill in all required dispatch details.');
        }

        redirect('/fleet');
    }

    public function confirmDelivery(string $id): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM fleet_dispatches WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $id]);
        $dispatch = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dispatch) {
            flash('fleet_error', 'Dispatch record not found.');
            redirect('/fleet');
        }

        $isKes = current_currency() === 'KES';
        $rate = exchange_rate();

        $loadedLitres = (int) $dispatch['loaded_litres'];
        $unitPrice = (float) ($dispatch['unit_price'] ?? 0);
        if ($unitPrice <= 0 && $loadedLitres > 0) {
            $unitPrice = (float) $dispatch['transport_amount'] / $loadedLitres;
        }

        $shortageLitres = isset($_POST['shortage_litres']) && $_POST['shortage_litres'] !== '' ? max(0, (int)$_POST['shortage_litres']) : null;
        $rawDelivered = isset($_POST['delivered_litres']) && $_POST['delivered_litres'] !== '' ? (int)$_POST['delivered_litres'] : null;

        if ($shortageLitres !== null) {
            $deliveredLitres = max(0, $loadedLitres - $shortageLitres);
        } elseif ($rawDelivered !== null && $rawDelivered > 0) {
            $deliveredLitres = $rawDelivered;
            $shortageLitres = max(0, $loadedLitres - $deliveredLitres);
        } else {
            $deliveredLitres = $loadedLitres;
            $shortageLitres = 0;
        }

        $transportAmount = (float) $dispatch['transport_amount'];
        $rawFinal = $_POST['final_payout'] ?? null;

        if ($rawFinal !== null && $rawFinal !== '' && (float)$rawFinal > 0) {
            $finalPayout = $isKes ? ((float)$rawFinal / $rate) : (float)$rawFinal;
        } else {
            // Automatically calculated from delivered litres * unit price
            $finalPayout = (float) ($deliveredLitres * $unitPrice);
        }

        $payoutDiff = (float) ($shortageLitres * $unitPrice);
        $shortageNotes = trim($_POST['shortage_notes'] ?? '');

        // Recalculate balance with confirmed final client payout
        $isSub = (int) ($dispatch['is_subcontracted'] ?? 0);
        if ($isSub) {
            $balance = (float) ($dispatch['agreed_commission'] ?? 0);
        } else {
            $mileage = (float) ($dispatch['mileage_cost'] ?? 0);
            $extra = (float) ($dispatch['extra_expenses'] ?? 0);
            $diesel = (float) ($dispatch['diesel'] ?? 0);
            $balance = $finalPayout - ($mileage + $extra + $diesel);
        }

        $upd = $pdo->prepare("UPDATE fleet_dispatches SET 
            shortage_litres = ?,
            delivered_litres = ?, 
            final_payout = ?, 
            payout_difference = ?, 
            shortage_notes = ?, 
            balance = ?, 
            status = 'Delivered' 
            WHERE id = ?");
        $upd->execute([$shortageLitres, $deliveredLitres, $finalPayout, $payoutDiff, $shortageNotes, $balance, (int) $id]);

        // If truck plate exists, update truck status to 'Ready'
        if (!empty($dispatch['truck'])) {
            $pdo->prepare("UPDATE trucks SET status = 'Ready' WHERE plate_number = ?")->execute([$dispatch['truck']]);
        }

        // Sync trips table status
        $pdo->prepare("UPDATE trips SET status = 'Delivered' WHERE trip_number = ?")->execute([$dispatch['trip_number']]);

        $shortageText = ($shortageLitres > 0) ? " (Shortage: -{$shortageLitres}L)" : " (100% delivered)";

        log_audit(
            'Fleet Dispatch',
            'CONFIRM_DELIVERY',
            "Recorded client arrival & delivery for {$dispatch['trip_number']}: {$deliveredLitres}L delivered{$shortageText}, final payout " . format_money($finalPayout)
        );

        flash('fleet_success', "Trip {$dispatch['trip_number']} delivered successfully. Client received {$deliveredLitres}L{$shortageText}. Payout reconciled.");
        redirect('/fleet');
    }

    public function inlineUpdate(): void
    {
        $pdo = Database::connection();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Missing dispatch ID']);
            exit;
        }

        $stmt = $pdo->prepare('SELECT * FROM fleet_dispatches WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$current) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Dispatch record not found']);
            exit;
        }

        // Custom column direct inline edit check
        if (isset($_POST['field']) && isset($_POST['value'])) {
            $field = trim($_POST['field']);
            $val = trim($_POST['value']);
            $allCols = \App\Services\TableSchemaService::getTableColumns('fleet_dispatches', false);
            foreach ($allCols as $c) {
                if ($c['column_key'] === $field) {
                    if ($c['data_type'] === 'date' && $val !== '') {
                        $val = ExcelService::normalizeDate($val);
                    }
                    $upd = $pdo->prepare("UPDATE fleet_dispatches SET `{$field}` = ? WHERE id = ?");
                    $upd->execute([$val, $id]);

                    // If truck was updated, auto-sync tank capacity from truck management
                    $autoCapacity = null;
                    if ($field === 'truck') {
                        $cap = $pdo->prepare("SELECT capacity_litres FROM trucks WHERE plate_number = ? LIMIT 1");
                        $cap->execute([$val]);
                        $autoCapacity = (int)$cap->fetchColumn();
                        if ($autoCapacity > 0) {
                            $pdo->prepare("UPDATE fleet_dispatches SET truck_capacity = ? WHERE id = ?")->execute([$autoCapacity, $id]);
                        }
                    }

                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'updated_value' => $val, 'truck_capacity' => $autoCapacity]);
                    exit;
                }
            }
        }

        $isKes = current_currency() === 'KES';
        $rate = exchange_rate();

        $dispatchDate = ExcelService::normalizeDate($_POST['dispatch_date'] ?? $current['dispatch_date']);
        $truck = trim($_POST['truck'] ?? $current['truck']);

        // Auto-reflect truck capacity from truck management
        $truckInfo = null;
        if ($truck !== '') {
            $tStmt = $pdo->prepare('SELECT capacity_litres FROM trucks WHERE plate_number = ? LIMIT 1');
            $tStmt->execute([$truck]);
            $truckInfo = $tStmt->fetch(PDO::FETCH_ASSOC);
        }
        $truckCapacity = isset($_POST['truck_capacity']) && (int)$_POST['truck_capacity'] > 0
            ? (int)$_POST['truck_capacity']
            : (($truckInfo && !empty($truckInfo['capacity_litres'])) ? (int)$truckInfo['capacity_litres'] : (int)($current['truck_capacity'] ?? 0));
        $driver = trim($_POST['driver'] ?? $current['driver']);
        $status = trim($_POST['status'] ?? $current['status']);
        $product = strtoupper(trim($_POST['product'] ?? $current['product']));
        if (!in_array($product, ['PMS', 'AGO'])) {
            $product = 'AGO';
        }
        $fromLocation = trim($_POST['from_location'] ?? $current['from_location']);
        $destination = trim($_POST['destination'] ?? $current['destination']);
        $clientName = trim($_POST['client_name'] ?? ($current['client_name'] ?? 'Regional Fuel Consignee'));

        // Transport Amount (Agreed Transport Payment in USD)
        if (isset($_POST['transport_amount_usd']) && $_POST['transport_amount_usd'] !== '') {
            $transportAmount = (float)$_POST['transport_amount_usd'];
        } elseif (isset($_POST['transport_amount_kes']) && $_POST['transport_amount_kes'] !== '') {
            $transportAmount = $rate > 0 ? ((float)$_POST['transport_amount_kes'] / $rate) : (float)$_POST['transport_amount_kes'];
        } elseif (isset($_POST['transport_amount']) && $_POST['transport_amount'] !== '') {
            $rawTrans = (float)$_POST['transport_amount'];
            $transportAmount = $isKes ? ($rawTrans / $rate) : $rawTrans;
        } else {
            $transportAmount = (float)($current['transport_amount'] ?? 0);
        }

        $loadedLitres = isset($_POST['loaded_litres']) ? (int)$_POST['loaded_litres'] : (int)$current['loaded_litres'];
        $unitPrice = $loadedLitres > 0 ? round($transportAmount / $loadedLitres, 4) : (float)($current['unit_price'] ?? 0);

        // Shortage & Delivered Litres
        if (isset($_POST['shortage_litres'])) {
            $shortageLitres = max(0, (int)$_POST['shortage_litres']);
            $deliveredLitres = max(0, $loadedLitres - $shortageLitres);
        } elseif (isset($_POST['delivered_litres'])) {
            $deliveredLitres = (int)$_POST['delivered_litres'];
            $shortageLitres = max(0, $loadedLitres - $deliveredLitres);
        } else {
            $shortageLitres = (int)($current['shortage_litres'] ?? 0);
            $deliveredLitres = isset($current['delivered_litres']) && $current['delivered_litres'] !== null ? (int)$current['delivered_litres'] : null;
        }

        // Final Payout
        $isDelivered = (strtolower($status) === 'delivered');
        if (isset($_POST['final_payout']) && $_POST['final_payout'] !== '') {
            $rawFinal = (float)$_POST['final_payout'];
            $finalPayout = $isKes ? ($rawFinal / $rate) : $rawFinal;
        } elseif ($deliveredLitres !== null && $deliveredLitres > 0 && $loadedLitres > 0) {
            $finalPayout = round(($deliveredLitres / $loadedLitres) * $transportAmount, 2);
        } elseif ($isDelivered) {
            $finalPayout = $transportAmount;
        } else {
            $finalPayout = isset($current['final_payout']) ? (float)$current['final_payout'] : null;
        }

        $payoutDiff = ($finalPayout !== null) ? ($transportAmount - $finalPayout) : 0.0;

        // Diesel Fueling
        $dieselLitres = isset($_POST['diesel_litres']) ? (float)$_POST['diesel_litres'] : (float)($current['diesel_litres'] ?? 0);
        if (isset($_POST['diesel_unit_price']) && $_POST['diesel_unit_price'] !== '') {
            $rawDieselUnitPrice = (float)$_POST['diesel_unit_price'];
            $dieselUnitPrice = $isKes ? ($rawDieselUnitPrice / $rate) : $rawDieselUnitPrice;
        } else {
            $dieselUnitPrice = (float)($current['diesel_unit_price'] ?? 0);
        }

        if ($dieselLitres > 0 && $dieselUnitPrice > 0) {
            $diesel = $dieselLitres * $dieselUnitPrice;
        } elseif (isset($_POST['diesel']) && $_POST['diesel'] !== '') {
            $rawDiesel = (float)$_POST['diesel'];
            $diesel = $isKes ? ($rawDiesel / $rate) : $rawDiesel;
        } else {
            $diesel = (float)($current['diesel'] ?? 0);
        }

        if (isset($_POST['mileage_cost']) && $_POST['mileage_cost'] !== '') {
            $rawMileage = (float)$_POST['mileage_cost'];
            $mileageCost = $isKes ? ($rawMileage / $rate) : $rawMileage;
        } else {
            $mileageCost = (float)($current['mileage_cost'] ?? 0);
        }

        if (isset($_POST['extra_expenses']) && $_POST['extra_expenses'] !== '') {
            $rawExtra = (float)$_POST['extra_expenses'];
            $extraExpenses = $isKes ? ($rawExtra / $rate) : $rawExtra;
        } else {
            $extraExpenses = (float)($current['extra_expenses'] ?? 0);
        }

        $isSub = !empty($current['is_subcontracted']);
        if ($isSub) {
            $balance = (float)$current['agreed_commission'];
        } else {
            $effectiveRevenue = ($finalPayout !== null && $finalPayout > 0) ? $finalPayout : $transportAmount;
            $balance = $effectiveRevenue - ($mileageCost + $extraExpenses + $diesel);
        }

        $shortageNotes = trim($_POST['shortage_notes'] ?? ($current['shortage_notes'] ?? ''));
        $breakdownNotes = trim($_POST['breakdown_notes'] ?? ($current['breakdown_notes'] ?? ''));

        $updateStmt = $pdo->prepare('UPDATE fleet_dispatches SET 
            dispatch_date = ?, truck = ?, truck_capacity = ?, driver = ?, status = ?, product = ?, unit_price = ?,
            from_location = ?, destination = ?, client_name = ?, loaded_litres = ?, shortage_litres = ?, delivered_litres = ?, 
            transport_amount = ?, final_payout = ?, payout_difference = ?, 
            mileage_cost = ?, diesel_litres = ?, diesel_unit_price = ?, diesel = ?, extra_expenses = ?, balance = ?, shortage_notes = ?, breakdown_notes = ?
            WHERE id = ?');
        $updateStmt->execute([
            $dispatchDate, $truck, $truckCapacity, $driver, $status, $product, $unitPrice,
            $fromLocation, $destination, $clientName, $loadedLitres, $shortageLitres, $deliveredLitres,
            $transportAmount, $finalPayout, $payoutDiff,
            $mileageCost, $dieselLitres, $dieselUnitPrice, $diesel, $extraExpenses, $balance, $shortageNotes, $breakdownNotes,
            $id
        ]);

        // Auto-sync customer account with case-insensitive matching
        if ($clientName !== '' && $clientName !== 'Regional Fuel Consignee') {
            $cStmt = $pdo->prepare('SELECT id, name FROM customers WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1');
            $cStmt->execute([$clientName]);
            $existingCust = $cStmt->fetch(PDO::FETCH_ASSOC);
            if ($existingCust) {
                $pdo->prepare('UPDATE customers SET last_dispatch_date = ? WHERE id = ?')
                    ->execute([$dispatchDate, $existingCust['id']]);
            } else {
                $pdo->prepare('INSERT INTO customers (name, company, country, orders, total_litres, last_dispatch_date, status) VALUES (?, ?, ?, 1, ?, ?, "Active")')
                    ->execute([$clientName, $clientName, $destination, $loadedLitres, $dispatchDate]);
            }
        }

        log_audit('Fleet Dispatch', 'UPDATE_DISPATCH', "Updated dispatch #{$id} ({$truck}) to {$destination} for client {$clientName}");

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Dispatch updated successfully',
            'data' => [
                'id' => $id,
                'dispatch_date' => $dispatchDate,
                'dol_formatted' => format_date_dol($dispatchDate),
                'truck' => $truck,
                'truck_capacity' => $truckCapacity,
                'driver' => $driver,
                'status' => $status,
                'product' => $product,
                'from_location' => $fromLocation,
                'destination' => $destination,
                'client_name' => $clientName,
                'loaded_litres' => $loadedLitres,
                'delivered_litres' => $deliveredLitres,
                'shortage_litres' => $loadedLitres - $deliveredLitres,
                'diesel' => convert_currency($diesel),
                'diesel_formatted' => $diesel > 0 ? format_money($diesel) : '—',
                'transport_amount' => convert_currency($transportAmount),
                'transport_formatted' => format_money($transportAmount),
                'final_payout' => convert_currency($finalPayout),
                'final_payout_formatted' => format_money($finalPayout),
                'payout_diff' => convert_currency($payoutDiff),
                'payout_diff_formatted' => format_money($payoutDiff),
                'mileage_cost' => convert_currency($mileageCost),
                'mileage_formatted' => format_money($mileageCost),
                'extra_expenses' => convert_currency($extraExpenses),
                'extra_formatted' => format_money($extraExpenses),
                'balance' => convert_currency($balance),
                'balance_formatted' => format_money($balance),
            ]
        ]);
        exit;
    }

    public function bol(string $id): string
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM fleet_dispatches WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $dispatch = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dispatch) {
            http_response_code(404);
            return view('errors.404', [
                'title' => 'BOL Not Found',
                'message' => 'The requested Bill of Lading record does not exist.',
            ]);
        }

        return view('fleet.bol', [
            'title' => 'Bill of Lading — ' . $dispatch['bol_number'],
            'dispatch' => $dispatch,
        ]);
    }

    public function export(): void
    {
        $format = strtolower($_GET['format'] ?? 'xlsx');
        $pdo = Database::connection();

        $truck = trim($_GET['truck'] ?? '');
        $month = trim($_GET['month'] ?? '');
        $year = trim($_GET['year'] ?? '');

        // Support month passed as "YYYY-MM" (e.g. 2026-05)
        if (strpos($month, '-') !== false) {
            $parts = explode('-', $month);
            if (empty($year) || strtolower($year) === 'all') {
                $year = $parts[0];
            }
            $month = $parts[1];
        }

        $sql = 'SELECT * FROM fleet_dispatches WHERE 1=1';
        $params = [];

        // 1. Filter by specific Truck
        if ($truck !== '' && strtolower($truck) !== 'all') {
            $sql .= ' AND truck = ?';
            $params[] = $truck;
        }

        // 2. Filter by Year and/or Month
        $hasYear = ($year !== '' && strtolower($year) !== 'all');
        $hasMonth = ($month !== '' && strtolower($month) !== 'all');

        if ($hasYear && $hasMonth) {
            $mFormatted = str_pad($month, 2, '0', STR_PAD_LEFT);
            $mInt = (int)$month;
            $sql .= ' AND (dispatch_date LIKE ? OR dispatch_date LIKE ?)';
            $params[] = "$year-$mFormatted-%";
            $params[] = "$year-$mInt-%";
        } elseif ($hasYear) {
            $sql .= ' AND dispatch_date LIKE ?';
            $params[] = "$year-%";
        } elseif ($hasMonth) {
            $mFormatted = str_pad($month, 2, '0', STR_PAD_LEFT);
            $mInt = (int)$month;
            $sql .= ' AND (substr(dispatch_date, 6, 2) = ? OR dispatch_date LIKE ? OR dispatch_date LIKE ?)';
            $params[] = $mFormatted;
            $params[] = "%-$mFormatted-%";
            $params[] = "%-$mInt-%";
        }

        $sql .= ' ORDER BY dispatch_date DESC, id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dispatches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $canViewFin = can_view_financials();
        $currencySymbol = app_currency_symbol();
        $isKes = current_currency() === 'KES';
        $rate = exchange_rate();

        $headers = [
            'Trip Number',
            'Loading Date (DOL)',
            'Truck Plate',
            'Carrier / Ownership',
            'Actual @ L20 (Litres)',
            'Shortage Litres',
            'Delivered Litres',
            'Diesel Litres',
            'Diesel Unit Price (' . $currencySymbol . '/L)',
            'Diesel Fuel Cost (' . $currencySymbol . ')',
            'Origin Loading Depot',
            'Destination',
            'Client / Consignee',
            'Fuel Product',
            'Driver Name',
            'Agreed Transport (' . $currencySymbol . ')',
            'Final Client Payout (' . $currencySymbol . ')',
            'Payout Difference (' . $currencySymbol . ')',
            'Mileage Expense (' . $currencySymbol . ')',
            'Extra Breakdown Cost (' . $currencySymbol . ')',
            'Remarks / Breakdown Notes',
            'Shortage Reconciliation Notes',
            'Net Trip Profit (' . $currencySymbol . ')',
            'Trip Status',
            'Seal Numbers',
            'BOL Number',
        ];

        $fleetMetaCols = \App\Services\TableSchemaService::getTableColumns('fleet_dispatches', false);
        $customCols = array_filter($fleetMetaCols, fn($c) => !empty($c['is_custom']) && !empty($c['is_visible']));
        foreach ($customCols as $cc) {
            $headers[] = $cc['display_label'];
        }

        $rows = [];
        foreach ($dispatches as $d) {
            $isContract = !empty($d['is_subcontracted']) || in_array(strtolower($d['truck_ownership'] ?? ''), ['contract', 'subcontracted']);
            $ownership = $isContract ? 'Subcontracted / Haulier' : 'Company Fleet Tanker';
            $loaded = (int) $d['loaded_litres'];
            $shortage = (int) ($d['shortage_litres'] ?? 0);
            $delivered = ($d['delivered_litres'] !== null && $d['delivered_litres'] !== '') ? (int) $d['delivered_litres'] : max(0, $loaded - $shortage);
            $dieselLitres = (float) ($d['diesel_litres'] ?? 0);
            $dieselUnitPriceVal = (float) ($d['diesel_unit_price'] ?? 0);
            $displayDieselUnitPrice = $isKes ? ($dieselUnitPriceVal * $rate) : $dieselUnitPriceVal;
            $dieselVal = (float) ($d['diesel'] ?? 0);
            $displayDiesel = $isKes ? ($dieselVal * $rate) : $dieselVal;
            $transport = (float) $d['transport_amount'];
            $displayTransport = $isKes ? ($transport * $rate) : $transport;
            $finalPayout = ($d['final_payout'] !== null && $d['final_payout'] !== '') ? (float) $d['final_payout'] : $transport;
            $displayFinalPayout = $isKes ? ($finalPayout * $rate) : $finalPayout;
            $payoutDiff = (float) ($d['payout_difference'] ?? ($transport - $finalPayout));
            $displayPayoutDiff = $isKes ? ($payoutDiff * $rate) : $payoutDiff;
            $mileage = (float) $d['mileage_cost'];
            $displayMileage = $isKes ? ($mileage * $rate) : $mileage;
            $extra = (float) $d['extra_expenses'];
            $displayExtra = $isKes ? ($extra * $rate) : $extra;
            $balance = (float) $d['balance'];
            $displayBalance = $isKes ? ($balance * $rate) : $balance;

            $row = [
                $d['trip_number'],
                $d['dispatch_date'],
                $d['truck'],
                $ownership,
                $loaded,
                $shortage,
                $delivered,
                $dieselLitres,
                $canViewFin ? round($displayDieselUnitPrice, 2) : '[Restricted]',
                $canViewFin ? round($displayDiesel, 2) : '[Restricted]',
                $d['from_location'] ?: 'Eldoret',
                $d['destination'],
                $d['client_name'] ?? 'Regional Consignee',
                $d['product'],
                $d['driver'],
                $canViewFin ? round($displayTransport, 2) : '[Restricted]',
                $canViewFin ? round($displayFinalPayout, 2) : '[Restricted]',
                $canViewFin ? round($displayPayoutDiff, 2) : '[Restricted]',
                $canViewFin ? round($displayMileage, 2) : '[Restricted]',
                $canViewFin ? round($displayExtra, 2) : '[Restricted]',
                $d['breakdown_notes'] ?? '',
                $d['shortage_notes'] ?? '',
                $canViewFin ? round($displayBalance, 2) : '[Restricted]',
                $d['status'],
                $d['seal_numbers'] ?? '',
                $d['bol_number'] ?? '',
            ];

            foreach ($customCols as $cc) {
                $row[] = $d[$cc['column_key']] ?? '';
            }

            $rows[] = $row;
        }

        // Summary / Totals Row at the bottom of the export
        if (!empty($dispatches)) {
            $totalLoaded = (int)array_sum(array_column($dispatches, 'loaded_litres'));
            $totalShortage = (int)array_sum(array_column($dispatches, 'shortage_litres'));
            $totalDelivered = 0;
            foreach ($dispatches as $d) {
                $rawDeliv = $d['delivered_litres'];
                $totalDelivered += ($rawDeliv !== null && $rawDeliv !== '') ? (int)$rawDeliv : max(0, (int)$d['loaded_litres'] - (int)($d['shortage_litres'] ?? 0));
            }
            $totalDieselLitres = (float)array_sum(array_column($dispatches, 'diesel_litres'));
            $totalDieselCost = (float)array_sum(array_column($dispatches, 'diesel'));
            $totalTransport = (float)array_sum(array_column($dispatches, 'transport_amount'));
            $totalPayout = 0;
            foreach ($dispatches as $d) {
                $totalPayout += ($d['final_payout'] !== null && $d['final_payout'] !== '') ? (float)$d['final_payout'] : (float)$d['transport_amount'];
            }
            $totalLoss = 0;
            foreach ($dispatches as $d) {
                $pDiff = $d['payout_difference'] ?? ((float)$d['transport_amount'] - (($d['final_payout'] !== null && $d['final_payout'] !== '') ? (float)$d['final_payout'] : (float)$d['transport_amount']));
                $totalLoss += (float)$pDiff;
            }
            $totalMileage = (float)array_sum(array_column($dispatches, 'mileage_cost'));
            $totalExtra = (float)array_sum(array_column($dispatches, 'extra_expenses'));
            $totalBalance = (float)array_sum(array_column($dispatches, 'balance'));

            $summaryRow = [
                'TOTALS (' . count($dispatches) . ' TRIPS)',
                '',
                ($truck && strtolower($truck) !== 'all') ? $truck : 'ALL TRUCKS',
                '',
                '',
                $totalLoaded,
                $totalShortage,
                $totalDelivered,
                '',
                round($totalDieselLitres, 2),
                '',
                $canViewFin ? round($isKes ? $totalDieselCost * $rate : $totalDieselCost, 2) : '[Restricted]',
                '',
                '',
                '',
                '',
                '',
                $canViewFin ? round($isKes ? $totalTransport * $rate : $totalTransport, 2) : '[Restricted]',
                $canViewFin ? round($isKes ? $totalPayout * $rate : $totalPayout, 2) : '[Restricted]',
                $canViewFin ? round($isKes ? $totalLoss * $rate : $totalLoss, 2) : '[Restricted]',
                $canViewFin ? round($isKes ? $totalMileage * $rate : $totalMileage, 2) : '[Restricted]',
                $canViewFin ? round($isKes ? $totalExtra * $rate : $totalExtra, 2) : '[Restricted]',
                '',
                '',
                $canViewFin ? round($isKes ? $totalBalance * $rate : $totalBalance, 2) : '[Restricted]',
                'SUMMARY',
                '',
                '',
            ];
            foreach ($customCols as $cc) {
                $summaryRow[] = '';
            }
            $rows[] = $summaryRow;
        } else {
            $noDataRow = [
                'No dispatches found for selected filter criteria (' . (($truck && strtolower($truck) !== 'all') ? $truck : 'All Trucks') . ', ' . ($hasMonth ? date('F', mktime(0,0,0,(int)$month,10)) : 'All Months') . ' ' . ($hasYear ? $year : 'All Years') . ')'
            ];
            for ($i = 1; $i < count($headers); $i++) {
                $noDataRow[] = '';
            }
            $rows[] = $noDataRow;
        }

        $truckPart = ($truck && strtolower($truck) !== 'all') ? preg_replace('/[^a-zA-Z0-9_-]/', '', $truck) . '_' : 'all_trucks_';
        $periodParts = [];
        if ($hasYear) {
            $periodParts[] = $year;
        }
        if ($hasMonth) {
            $mNum = (int)$month;
            $mName = ($mNum >= 1 && $mNum <= 12) ? date('M', mktime(0, 0, 0, $mNum, 10)) : str_pad($month, 2, '0', STR_PAD_LEFT);
            $periodParts[] = $mName;
        }
        $periodPart = !empty($periodParts) ? implode('_', $periodParts) : date('Y_m_d');
        $filenameBase = 'fleet_dispatches_' . $truckPart . $periodPart;
        $sheetTitle = ($truck && strtolower($truck) !== 'all') ? substr($truck, 0, 31) : 'Fleet Dispatches';

        if ($format === 'xls') {
            ExcelService::exportXls($filenameBase . '.xls', $headers, $rows, $sheetTitle);
        } elseif ($format === 'csv') {
            ExcelService::exportCsv($filenameBase . '.csv', $headers, $rows);
        } else {
            ExcelService::exportXlsx($filenameBase . '.xlsx', $headers, $rows, $sheetTitle);
        }
    }

    public function template(): void
    {
        $format = strtolower($_GET['format'] ?? 'xlsx');
        $currencySymbol = app_currency_symbol();
        $headers = [
            'Loading Date (DOL)',
            'Trip Number',
            'Truck Plate',
            'Truck Capacity (L)',
            'Loaded Litres',
            'Delivered Litres',
            'Diesel Fuel Cost (' . $currencySymbol . ')',
            'Origin Loading Depot',
            'Destination',
            'Client / Consignee',
            'Fuel Product',
            'Driver Name',
            'Transport Revenue (' . $currencySymbol . ')',
            'Final Client Payout (' . $currencySymbol . ')',
            'Mileage Expense (' . $currencySymbol . ')',
            'Extra Breakdown Cost (' . $currencySymbol . ')',
            'Remarks / Breakdown Notes',
            'Shortage Reconciliation Notes',
            'Trip Status',
            'Seal Numbers',
        ];

        // Include any custom columns
        $fleetMetaCols = \App\Services\TableSchemaService::getTableColumns('fleet_dispatches', false);
        $customCols = array_filter($fleetMetaCols, fn($c) => !empty($c['is_custom']) && !empty($c['is_visible']));
        foreach ($customCols as $cc) {
            $headers[] = $cc['display_label'];
        }

        $sampleRows = [
            [
                date('Y-m-d'),
                'TRP-' . date('Y') . '-1001',
                'KAA 458Z',
                30000,
                30000,
                30000,
                current_currency() === 'KES' ? 58000.00 : 450.00,
                'Eldoret',
                'Uganda (Kampala)',
                'Total Uganda Ltd',
                'AGO',
                'John Waweru',
                current_currency() === 'KES' ? 390000.00 : 3000.00,
                current_currency() === 'KES' ? 390000.00 : 3000.00,
                current_currency() === 'KES' ? 145000.00 : 1120.00,
                current_currency() === 'KES' ? 15000.00 : 120.00,
                'None',
                'Delivered 100% full cargo',
                'Delivered',
                'SL-1001, SL-1002, SL-1003',
            ],
            [
                date('Y-m-d'),
                'TRP-' . date('Y') . '-1002',
                'KCC 910L',
                32000,
                32000,
                '', // Pending arrival for en-route
                current_currency() === 'KES' ? 65000.00 : 500.00,
                'Eldoret',
                'South Sudan (Juba)',
                'Nile Petroleum Co',
                'PMS',
                'Peter Njoroge',
                current_currency() === 'KES' ? 440000.00 : 3400.00,
                '', // Pending arrival
                current_currency() === 'KES' ? 175000.00 : 1350.00,
                0.00,
                'None',
                '',
                'In Transit',
                'SL-2001, SL-2002, SL-2003',
            ],
        ];

        // Pad custom column values in sample rows
        if (!empty($customCols)) {
            foreach ($sampleRows as &$sRow) {
                foreach ($customCols as $cc) {
                    $sRow[] = match($cc['data_type']) {
                        'number' => '100',
                        'currency' => '250.00',
                        'date' => date('Y-m-d'),
                        default => 'Sample text',
                    };
                }
            }
            unset($sRow);
        }

        $filenameBase = 'fleet_dispatch_import_template';
        if ($format === 'xls') {
            ExcelService::exportXls($filenameBase . '.xls', $headers, $sampleRows, 'Import Template');
        } elseif ($format === 'csv') {
            ExcelService::exportCsv($filenameBase . '.csv', $headers, $sampleRows);
        } else {
            ExcelService::exportXlsx($filenameBase . '.xlsx', $headers, $sampleRows, 'Import Template');
        }
    }

    public function import(): void
    {
        $fileInfo = $_FILES['spreadsheet_file'] ?? $_FILES['csv_file'] ?? null;
        if (empty($fileInfo['tmp_name']) || !file_exists($fileInfo['tmp_name'])) {
            flash('fleet_error', 'Please select a valid spreadsheet file (.xlsx, .xls, or .csv) to upload.');
            redirect('/fleet');
        }

        $origName = $fileInfo['name'] ?? 'upload.csv';
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($ext, ['xlsx', 'xls', 'csv', 'txt'])) {
            flash('fleet_error', "Unsupported file format (.{$ext}). Please upload an Excel (.xlsx, .xls) or CSV file.");
            redirect('/fleet');
        }

        try {
            $parsedRows = ExcelService::importFile($fileInfo['tmp_name'], $origName);
        } catch (\Throwable $e) {
            flash('fleet_error', 'Error reading spreadsheet: ' . $e->getMessage());
            redirect('/fleet');
        }

        if (empty($parsedRows)) {
            flash('fleet_error', 'The uploaded spreadsheet contains no readable rows.');
            redirect('/fleet');
        }

        $rawHeader = array_shift($parsedRows);
        if (empty($rawHeader) || empty(array_filter($rawHeader))) {
            flash('fleet_error', 'Uploaded spreadsheet is missing a header row.');
            redirect('/fleet');
        }

        $isKesSession = (current_currency() === 'KES');
        $rate = exchange_rate();

        // Build canonical key mapping for each column index
        $headerMap = [];
        $headerIsKes = [];
        foreach ($rawHeader as $idx => $origCol) {
            $origStr = trim((string)$origCol);
            // Check if column explicitly denotes KES or USD
            $hasKes = stripos($origStr, 'kes') !== false;
            $hasDollar = (stripos($origStr, '$') !== false || stripos($origStr, 'usd') !== false);
            $headerIsKes[$idx] = $hasKes || ($isKesSession && !$hasDollar);

            // Strip parentheses and normalize
            $withoutParens = preg_replace('/\s*\([^)]*\)/', '', $origStr);
            $canonical = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $withoutParens), '_'));
            // Also keep a version with raw characters replaced (for backward compatibility)
            $rawClean = strtolower(trim(str_replace([' ', '-', '(', ')', '/', '\\', '.'], '_', $origStr), '_'));

            $headerMap[$idx] = [
                'canonical' => $canonical,
                'rawClean' => $rawClean,
                'original' => $origStr,
            ];
        }

        $pdo = Database::connection();
        $importedCount = 0;
        $updatedCount = 0;

        // Custom columns registered in schema
        $fleetMetaCols = \App\Services\TableSchemaService::getTableColumns('fleet_dispatches', false);
        $customCols = array_filter($fleetMetaCols, fn($c) => !empty($c['is_custom']));

        // Cache trucks for ownership & capacity check
        $truckCache = [];
        $tList = $pdo->query('SELECT plate_number, capacity_litres, ownership_type, commission_rate FROM trucks')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tList as $t) {
            $truckCache[strtoupper(trim($t['plate_number']))] = $t;
        }

        foreach ($parsedRows as $row) {
            if (empty(array_filter($row, fn($v) => $v !== null && trim((string)$v) !== ''))) {
                continue;
            }

            // Map row by various aliases
            $data = [];
            $rowIsKes = [];
            foreach ($headerMap as $idx => $hInfo) {
                $val = $row[$idx] ?? '';
                $data[$hInfo['canonical']] = $val;
                $data[$hInfo['rawClean']] = $val;
                $rowIsKes[$hInfo['canonical']] = $headerIsKes[$idx] ?? false;
            }

            // Helper to clean monetary fields
            $parseMoney = function(array $keys, float $default = 0.0) use ($data, $rowIsKes, $isKesSession, $rate): float {
                foreach ($keys as $k) {
                    if (array_key_exists($k, $data) && $data[$k] !== null && $data[$k] !== '') {
                        $str = trim((string)$data[$k]);
                        if (strtolower($str) === '[restricted]' || strtolower($str) === 'restricted') {
                            return $default;
                        }
                        $cleanNum = preg_replace('/[^0-9.-]/', '', $str);
                        if ($cleanNum === '' || !is_numeric($cleanNum)) {
                            continue;
                        }
                        $num = (float)$cleanNum;
                        $isKes = ($rowIsKes[$k] ?? false) || stripos($str, 'kes') !== false || ($isKesSession && stripos($str, '$') === false);
                        return $isKes ? ($num / $rate) : $num;
                    }
                }
                return $default;
            };

            // Helper to get nullable money
            $parseNullableMoney = function(array $keys) use ($data, $rowIsKes, $isKesSession, $rate): ?float {
                foreach ($keys as $k) {
                    if (array_key_exists($k, $data) && $data[$k] !== null && $data[$k] !== '') {
                        $str = trim((string)$data[$k]);
                        if (strtolower($str) === '[restricted]' || strtolower($str) === 'restricted' || $str === '—' || $str === '-') {
                            return null;
                        }
                        $cleanNum = preg_replace('/[^0-9.-]/', '', $str);
                        if ($cleanNum === '' || !is_numeric($cleanNum)) {
                            continue;
                        }
                        $num = (float)$cleanNum;
                        $isKes = ($rowIsKes[$k] ?? false) || stripos($str, 'kes') !== false || ($isKesSession && stripos($str, '$') === false);
                        return $isKes ? ($num / $rate) : $num;
                    }
                }
                return null;
            };

            // Helper to get string
            $parseString = function(array $keys, string $default = ''): string {
                foreach ($keys as $k) {
                    if (isset($data[$k]) && trim((string)$data[$k]) !== '') {
                        return trim((string)$data[$k]);
                    }
                }
                return $default;
            };

            // Date
            $rawDate = $parseString(['loading_date', 'dispatch_date', 'date_of_loading', 'dol', 'date', 'loading_date__dol_']);
            $dispatchDate = ExcelService::normalizeDate($rawDate);

            // Truck
            $truck = strtoupper($parseString(['truck_plate', 'truck', 'plate', 'vehicle', 'tanker', 'plate_number'], 'KAA 458Z'));

            // Capacities & Litres
            $truckInfo = $truckCache[$truck] ?? null;
            $defaultCap = $truckInfo ? (int)$truckInfo['capacity_litres'] : 30000;
            $capacity = (int)$parseString(['truck_capacity', 'capacity_litres', 'capacity', 'truck_capacity__l_'], (string)$defaultCap);
            if ($capacity <= 0) $capacity = $defaultCap;

            $loaded = (int)$parseString(['loaded_litres', 'litres_loaded', 'loaded', 'load_quantity'], (string)$capacity);
            if ($loaded <= 0) $loaded = $capacity;

            // Status
            $status = $parseString(['trip_status', 'status', 'trip_state'], 'In Transit');
            $statusLower = strtolower($status);
            $isDelivered = str_contains($statusLower, 'deliver') || str_contains($statusLower, 'complete');

            // Delivered litres
            $rawDeliv = $parseString(['delivered_litres', 'litres_delivered', 'delivered', 'offload_litres', 'offloaded']);
            if ($rawDeliv !== '' && (int)$rawDeliv > 0) {
                $deliveredLitres = (int)$rawDeliv;
            } elseif ($isDelivered) {
                $deliveredLitres = $loaded;
            } else {
                $deliveredLitres = null;
            }

            // Route & Client
            $from = $parseString(['origin_loading_depot', 'from_location', 'origin', 'from', 'depot'], 'Eldoret');
            $dest = $parseString(['destination', 'to_location', 'to', 'dest'], 'Uganda (Kampala)');
            $client = $parseString(['client_consignee', 'client_name', 'client', 'consignee', 'customer'], 'Regional Consignee');
            $product = strtoupper($parseString(['fuel_product', 'product', 'fuel'], 'AGO'));
            $driver = $parseString(['driver_name', 'driver'], 'Unassigned');

            // Financials
            $transport = $parseMoney(['transport_revenue', 'transport_amount', 'expected_transport', 'transport', 'transport_billed']);
            $finalPayout = $parseNullableMoney(['final_client_payout', 'final_payout', 'client_payout', 'payout', 'client_paid']);
            if ($finalPayout === null && $isDelivered) {
                $finalPayout = $transport;
            }

            $payoutDiff = ($finalPayout !== null) ? ($transport - $finalPayout) : 0.0;

            // Diesel, Mileage, Extra Expenses (ALL owner/company costs)
            $diesel = $parseMoney(['diesel_fuel_cost', 'diesel_fuel', 'diesel_cost', 'diesel', 'fuel_cost']);
            $mileage = $parseMoney(['mileage_expense', 'mileage_cost', 'mileage', 'trip_cost', 'allowance']);
            $extra = $parseMoney(['extra_breakdown_cost', 'extra_expenses', 'breakdown_cost', 'repairs_cost', 'extra']);

            // Ownership and Net Profit / Balance:
            // Notice: Diesel fuel cost is catered for by the owner, not client, so it MUST deduct from net profit!
            $isSubcontracted = ($truckInfo && in_array(strtolower($truckInfo['ownership_type'] ?? ''), ['contract', 'subcontracted', 'sub'])) ? 1 : 0;
            $agreedCommission = 0.0;
            if ($isSubcontracted) {
                $agreedCommission = (float)($truckInfo['commission_rate'] ?? 0);
                $balance = $agreedCommission;
            } else {
                $effectiveRevenue = ($finalPayout !== null && $finalPayout > 0) ? $finalPayout : $transport;
                $balance = $effectiveRevenue - ($mileage + $extra + $diesel);
            }

            // Notes & References
            $notes = $parseString(['remarks_breakdown_notes', 'breakdown_notes', 'remarks', 'notes']);
            $shortageNotes = $parseString(['shortage_reconciliation_notes', 'shortage_notes', 'shortage_remarks']);
            $seals = $parseString(['seal_numbers', 'seals', 'seal_no']);

            $tripNumber = $parseString(['trip_number', 'trip_ref', 'trip_no', 'trip']);
            if ($tripNumber === '') {
                $tripNumber = 'TRP-' . date('Y') . '-' . rand(1000, 9999);
            }

            $bolNumber = $parseString(['bol_number', 'bol_no', 'bol']);
            if ($bolNumber === '') {
                $bolNumber = 'BOL-' . date('Y') . '-' . rand(1000, 9999);
            }

            // Custom columns data
            $customValues = [];
            foreach ($customCols as $cc) {
                $ck = $cc['column_key'];
                $cleanLabel = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $cc['display_label']), '_'));
                $cVal = $data[$ck] ?? $data[$cleanLabel] ?? null;
                if ($cc['data_type'] === 'date' && !empty($cVal)) {
                    $cVal = ExcelService::normalizeDate($cVal);
                }
                $customValues[$ck] = $cVal;
            }

            // Check if trip already exists (Upsert)
            $chkStmt = $pdo->prepare('SELECT id FROM fleet_dispatches WHERE trip_number = ? LIMIT 1');
            $chkStmt->execute([$tripNumber]);
            $existingId = $chkStmt->fetchColumn();

            if ($existingId) {
                // Update existing record with full details
                $sql = 'UPDATE fleet_dispatches SET 
                    dispatch_date = ?, truck = ?, truck_capacity = ?, loaded_litres = ?, delivered_litres = ?,
                    from_location = ?, destination = ?, client_name = ?, product = ?, driver = ?,
                    transport_amount = ?, final_payout = ?, payout_difference = ?, mileage_cost = ?, extra_expenses = ?,
                    diesel = ?, breakdown_notes = ?, shortage_notes = ?, balance = ?, is_subcontracted = ?, agreed_commission = ?,
                    status = ?, seal_numbers = ?';
                $params = [
                    $dispatchDate, $truck, $capacity, $loaded, $deliveredLitres,
                    $from, $dest, $client, $product, $driver,
                    $transport, $finalPayout, $payoutDiff, $mileage, $extra,
                    $diesel, $notes, $shortageNotes, $balance, $isSubcontracted, $agreedCommission,
                    $status, $seals
                ];

                foreach ($customValues as $ck => $cv) {
                    $sql .= ", `{$ck}` = ?";
                    $params[] = $cv;
                }

                $sql .= ' WHERE id = ?';
                $params[] = $existingId;

                $updStmt = $pdo->prepare($sql);
                $updStmt->execute($params);
                $updatedCount++;
            } else {
                // Insert new record with full details
                $cols = [
                    'trip_number', 'bol_number', 'dispatch_date', 'truck', 'truck_capacity', 'loaded_litres', 'delivered_litres',
                    'from_location', 'destination', 'client_name', 'product', 'driver', 'transport_amount', 'final_payout', 'payout_difference',
                    'mileage_cost', 'extra_expenses', 'diesel', 'breakdown_notes', 'shortage_notes', 'balance', 'is_subcontracted', 'agreed_commission',
                    'status', 'seal_numbers', 'created_at'
                ];
                $params = [
                    $tripNumber, $bolNumber, $dispatchDate, $truck, $capacity, $loaded, $deliveredLitres,
                    $from, $dest, $client, $product, $driver, $transport, $finalPayout, $payoutDiff,
                    $mileage, $extra, $diesel, $notes, $shortageNotes, $balance, $isSubcontracted, $agreedCommission,
                    $status, $seals, date('Y-m-d H:i:s')
                ];

                foreach ($customValues as $ck => $cv) {
                    $cols[] = "`{$ck}`";
                    $params[] = $cv;
                }

                $placeholders = array_fill(0, count($cols), '?');
                $sql = 'INSERT INTO fleet_dispatches (' . implode(',', $cols) . ') VALUES (' . implode(',', $placeholders) . ')';
                $insStmt = $pdo->prepare($sql);
                $insStmt->execute($params);
                $importedCount++;
            }

            // Auto-sync customer account
            if ($client !== '' && $client !== 'Regional Consignee') {
                $cStmt = $pdo->prepare('SELECT id FROM customers WHERE name = ? LIMIT 1');
                $cStmt->execute([$client]);
                if ($cStmt->fetch()) {
                    $pdo->prepare('UPDATE customers SET last_dispatch_date = ? WHERE name = ?')
                        ->execute([$dispatchDate, $client]);
                } else {
                    $pdo->prepare('INSERT INTO customers (name, company, country, orders, total_litres, last_dispatch_date, status) VALUES (?, ?, ?, 1, ?, ?, "Active")')
                        ->execute([$client, $client, $dest, $loaded, $dispatchDate]);
                }
            }

            // Auto-sync into trips table
            $tStmt = $pdo->prepare('SELECT id FROM trips WHERE trip_number = ? LIMIT 1');
            $tStmt->execute([$tripNumber]);
            if ($tStmt->fetch()) {
                $pdo->prepare('UPDATE trips SET customer = ?, truck = ?, driver = ?, route = ?, load_quantity = ?, status = ? WHERE trip_number = ?')
                    ->execute([$client, $truck, $driver, ($from . ' → ' . $dest), (string)$loaded, $status, $tripNumber]);
            } else {
                $pdo->prepare('INSERT INTO trips (trip_number, customer, truck, driver, route, load_quantity, status) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$tripNumber, $client, $truck, $driver, ($from . ' → ' . $dest), (string)$loaded, $status]);
            }
        }

        $totalRecords = $importedCount + $updatedCount;
        log_audit('Fleet Logistics', 'IMPORT_SPREADSHEET', "Imported {$importedCount} new and updated {$updatedCount} fleet records from {$origName} (" . strtoupper($ext) . ")");
        flash('fleet_success', "Successfully processed {$totalRecords} fleet dispatches ({$importedCount} new, {$updatedCount} updated) with full financial, diesel, volume & shortage details!");
        redirect('/fleet');
    }

    public function delete(string $id): void
    {
        $pdo = Database::connection();
        $q = $pdo->prepare('SELECT trip_number, truck, client_name FROM fleet_dispatches WHERE id = ? LIMIT 1');
        $q->execute([(int) $id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare('DELETE FROM fleet_dispatches WHERE id = ?');
        $stmt->execute([$id]);

        $pdo->prepare('DELETE FROM fleet_diesel_logs WHERE dispatch_id = ?')->execute([(int) $id]);

        if ($row && !empty($row['trip_number'])) {
            $pdo->prepare('DELETE FROM trips WHERE trip_number = ?')->execute([$row['trip_number']]);
        }

        $tripRef = $row ? ($row['trip_number'] . ' - ' . $row['truck'] . ' (' . ($row['client_name'] ?? 'Consignee') . ')') : "ID #{$id}";
        log_audit('Fleet Dispatch', 'DELETE_DISPATCH', "Deleted fleet dispatch {$tripRef}", 1);

        flash('fleet_success', 'Dispatch record removed successfully.');
        redirect('/fleet');
    }

    public static function recalculateDispatchDiesel(PDO $pdo, int $dispatchId): array
    {
        $stmt = $pdo->prepare('SELECT 
            COALESCE(SUM(base_usd_cost), 0) as total_diesel_usd,
            COALESCE(SUM(litres), 0) as total_litres
            FROM fleet_diesel_logs WHERE dispatch_id = ?');
        $stmt->execute([$dispatchId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalDiesel = round((float)($stats['total_diesel_usd'] ?? 0), 2);
        $totalLitres = round((float)($stats['total_litres'] ?? 0), 2);
        $unitPrice = $totalLitres > 0 ? round($totalDiesel / $totalLitres, 4) : 0;

        $dStmt = $pdo->prepare('SELECT transport_amount, final_payout, mileage_cost, extra_expenses, is_subcontracted, agreed_commission FROM fleet_dispatches WHERE id = ?');
        $dStmt->execute([$dispatchId]);
        $d = $dStmt->fetch(PDO::FETCH_ASSOC);
        if (!$d) {
            return ['diesel' => $totalDiesel, 'diesel_litres' => $totalLitres, 'diesel_unit_price' => $unitPrice, 'balance' => 0];
        }

        $isSub = (int)($d['is_subcontracted'] ?? 0);
        if ($isSub === 1) {
            $balance = (float)($d['agreed_commission'] ?? 0);
        } else {
            $effectiveRev = ($d['final_payout'] !== null && (float)$d['final_payout'] > 0) ? (float)$d['final_payout'] : (float)$d['transport_amount'];
            $mileage = (float)($d['mileage_cost'] ?? 0);
            $extra = (float)($d['extra_expenses'] ?? 0);
            $balance = round($effectiveRev - ($mileage + $totalDiesel + $extra), 2);
        }

        $upStmt = $pdo->prepare('UPDATE fleet_dispatches SET diesel = ?, diesel_litres = ?, diesel_unit_price = ?, balance = ? WHERE id = ?');
        $upStmt->execute([$totalDiesel, $totalLitres, $unitPrice, $balance, $dispatchId]);

        // Calculate up-to-date global fleet aggregates across all trips
        $aggStmt = $pdo->query('SELECT 
            COUNT(*) as total_count,
            COALESCE(SUM(transport_amount), 0) as total_transport,
            COALESCE(SUM(mileage_cost), 0) as total_mileage,
            COALESCE(SUM(extra_expenses), 0) as total_extra,
            COALESCE(SUM(diesel), 0) as total_diesel,
            COALESCE(SUM(balance), 0) as total_balance
            FROM fleet_dispatches');
        $globalAgg = $aggStmt->fetch(PDO::FETCH_ASSOC);

        return [
            'diesel' => $totalDiesel,
            'diesel_litres' => $totalLitres,
            'diesel_unit_price' => $unitPrice,
            'balance' => $balance,
            'global_aggregates' => [
                'total_transport' => (float)($globalAgg['total_transport'] ?? 0),
                'total_transport_formatted' => format_money($globalAgg['total_transport'] ?? 0),
                'total_diesel' => (float)($globalAgg['total_diesel'] ?? 0),
                'total_diesel_formatted' => format_money($globalAgg['total_diesel'] ?? 0),
                'total_mileage' => (float)($globalAgg['total_mileage'] ?? 0),
                'total_mileage_formatted' => format_money($globalAgg['total_mileage'] ?? 0),
                'total_extra' => (float)($globalAgg['total_extra'] ?? 0),
                'total_extra_formatted' => format_money($globalAgg['total_extra'] ?? 0),
                'total_balance' => (float)($globalAgg['total_balance'] ?? 0),
                'total_balance_formatted' => format_money($globalAgg['total_balance'] ?? 0),
            ]
        ];
    }

    public function storeDieselLog(): void
    {
        $pdo = Database::connection();
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

        $dispatchId = (int)($_POST['dispatch_id'] ?? 0);
        if ($dispatchId <= 0) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Valid dispatch trip is required.']);
                exit;
            }
            flash('fleet_error', 'Invalid dispatch selected for fuel entry.');
            redirect('/fleet');
            return;
        }

        $dStmt = $pdo->prepare('SELECT id, trip_number, truck FROM fleet_dispatches WHERE id = ? LIMIT 1');
        $dStmt->execute([$dispatchId]);
        $dispatch = $dStmt->fetch(PDO::FETCH_ASSOC);
        if (!$dispatch) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Dispatch not found.']);
                exit;
            }
            flash('fleet_error', 'Dispatch record not found.');
            redirect('/fleet');
            return;
        }

        $fuelDate = trim($_POST['fuel_date'] ?? date('Y-m-d'));
        $station = trim($_POST['station_location'] ?? '');
        if ($station === '') {
            $station = 'En-route Fuel Station';
        }
        $country = trim($_POST['country'] ?? 'Kenya');
        $currencyCode = strtoupper(trim($_POST['currency_code'] ?? 'KES'));
        $litres = max(0, (float)($_POST['litres'] ?? 0));
        $localUnitPrice = max(0, (float)($_POST['local_unit_price'] ?? 0));
        $exchangeRate = (float)($_POST['exchange_rate'] ?? 1);

        // Fallback default rates if missing/invalid
        if ($exchangeRate <= 0) {
            if ($currencyCode === 'KES') $exchangeRate = (float)exchange_rate();
            elseif ($currencyCode === 'UGX') $exchangeRate = 3750.0;
            elseif ($currencyCode === 'CDF') $exchangeRate = 2850.0;
            elseif ($currencyCode === 'SSP') $exchangeRate = 1300.0;
            else $exchangeRate = 1.0;
        }

        // Multi-currency calculation: Support entering in KSh (KES) even for Uganda/Congo stops
        $entryCurrency = strtoupper(trim($_POST['entry_currency'] ?? ''));
        $kesRate = (float)exchange_rate();
        if ($kesRate <= 0) $kesRate = 130.0;

        if ($entryCurrency === 'KES' && isset($_POST['kes_unit_price']) && (float)$_POST['kes_unit_price'] > 0) {
            $kesUnitPrice = (float)$_POST['kes_unit_price'];
            $baseUsdUnitPrice = $kesUnitPrice / $kesRate;
            $baseUsdCost = round($litres * $baseUsdUnitPrice, 2);
            if ($currencyCode === 'KES') {
                $localUnitPrice = $kesUnitPrice;
                $localTotal = round($litres * $localUnitPrice, 2);
            } else {
                $localUnitPrice = round($baseUsdUnitPrice * $exchangeRate, 2);
                $localTotal = round($litres * $localUnitPrice, 2);
            }
        } elseif ($entryCurrency === 'USD' && isset($_POST['usd_unit_price']) && (float)$_POST['usd_unit_price'] > 0) {
            $usdUnitPrice = (float)$_POST['usd_unit_price'];
            $baseUsdCost = round($litres * $usdUnitPrice, 2);
            $localUnitPrice = round($usdUnitPrice * $exchangeRate, 2);
            $localTotal = round($litres * $localUnitPrice, 2);
        } else {
            $localTotal = round($litres * $localUnitPrice, 2);
            $baseUsdCost = round($localTotal / $exchangeRate, 2);
        }

        $receiptStatus = trim($_POST['receipt_status'] ?? 'Received');
        $receiptNumber = trim($_POST['receipt_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        $ins = $pdo->prepare('INSERT INTO fleet_diesel_logs (
            dispatch_id, trip_number, truck, fuel_date, station_location, country, currency_code,
            exchange_rate, litres, local_unit_price, local_total_cost, base_usd_cost,
            receipt_status, receipt_number, notes, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

        $ins->execute([
            $dispatchId,
            $dispatch['trip_number'],
            $dispatch['truck'],
            $fuelDate,
            $station,
            $country,
            $currencyCode,
            $exchangeRate,
            $litres,
            $localUnitPrice,
            $localTotal,
            $baseUsdCost,
            $receiptStatus,
            $receiptNumber,
            $notes,
            date('Y-m-d H:i:s')
        ]);
        $newLogId = (int)$pdo->lastInsertId();

        // Recalculate dispatch aggregates and global fleet totals
        $updatedTotals = self::recalculateDispatchDiesel($pdo, $dispatchId);

        log_audit('Diesel Fueling', 'LOG_FUEL_STOP', "Logged {$litres}L fueling in {$country} ({$currencyCode} {$localTotal}) for trip {$dispatch['trip_number']}");

        if ($isAjax) {
            header('Content-Type: application/json');
            $totalsPayload = [
                'diesel_raw' => $updatedTotals['diesel'],
                'diesel_formatted' => format_money($updatedTotals['diesel']),
                'diesel_litres' => $updatedTotals['diesel_litres'],
                'balance_raw' => $updatedTotals['balance'],
                'balance_formatted' => format_money($updatedTotals['balance']),
            ];
            echo json_encode([
                'success' => true,
                'message' => 'Fuel stop recorded successfully!',
                'log_id' => $newLogId,
                'totals' => $totalsPayload,
                'global_aggregates' => $updatedTotals['global_aggregates'] ?? null,
                'data' => [
                    'totals' => $totalsPayload,
                    'global_aggregates' => $updatedTotals['global_aggregates'] ?? null,
                    'log_id' => $newLogId
                ]
            ]);
            exit;
        }

        flash('fleet_success', "Diesel fuel stop logged successfully ({$litres} L in {$country}). Total trip diesel and net balance updated!");
        redirect('/fleet');
    }

    public function getDieselLogs(string $dispatchId): void
    {
        $pdo = Database::connection();
        $id = (int)$dispatchId;

        $dStmt = $pdo->prepare('SELECT id, trip_number, truck, diesel, diesel_litres, diesel_unit_price, from_location, dispatch_date, balance FROM fleet_dispatches WHERE id = ? LIMIT 1');
        $dStmt->execute([$id]);
        $dispatch = $dStmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare('SELECT * FROM fleet_diesel_logs WHERE dispatch_id = ? ORDER BY fuel_date ASC, id ASC');
        $stmt->execute([$id]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Auto-seed initial departure diesel if dispatch has recorded diesel but no logs yet
        if (empty($logs) && $dispatch && ((float)($dispatch['diesel'] ?? 0) > 0 || (float)($dispatch['diesel_litres'] ?? 0) > 0)) {
            $initLitres = (float)($dispatch['diesel_litres'] ?? 0);
            $initBaseUsd = (float)($dispatch['diesel'] ?? 0);
            $initUnitPrice = (float)($dispatch['diesel_unit_price'] ?? 0);
            $fromLoc = trim($dispatch['from_location'] ?? 'Eldoret');
            $dispDate = $dispatch['dispatch_date'] ?? date('Y-m-d');
            $rate = exchange_rate();

            if ($initUnitPrice <= 0 && $initLitres > 0 && $initBaseUsd > 0) {
                $initUnitPrice = $initBaseUsd / $initLitres;
            } elseif ($initLitres <= 0 && $initBaseUsd > 0) {
                $initUnitPrice = 1.3846;
                $initLitres = round($initBaseUsd / $initUnitPrice, 1);
            }

            $localUnitPrice = round($initUnitPrice * $rate, 2);
            $localTotal = round($initLitres * $localUnitPrice, 2);

            $seedStmt = $pdo->prepare('INSERT INTO fleet_diesel_logs (
                dispatch_id, trip_number, truck, fuel_date, station_location, country, currency_code,
                exchange_rate, litres, local_unit_price, local_total_cost, base_usd_cost,
                receipt_status, receipt_number, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, "Kenya", "KES", ?, ?, ?, ?, ?, "Received", "DEP-INIT", "Initial departure fuel from dispatch", ?)');
            $seedStmt->execute([
                $id,
                $dispatch['trip_number'],
                $dispatch['truck'],
                $dispDate,
                $fromLoc . ' Depot Departure Shell',
                $rate,
                $initLitres,
                $localUnitPrice,
                $localTotal,
                $initBaseUsd,
                date('Y-m-d H:i:s')
            ]);

            $stmt->execute([$id]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $isKes = current_currency() === 'KES';
        $rate = exchange_rate();
        $formattedLogs = [];

        foreach ($logs as $l) {
            $baseUsd = (float)$l['base_usd_cost'];
            $formattedLogs[] = [
                'id' => $l['id'],
                'dispatch_id' => $l['dispatch_id'],
                'trip_number' => $l['trip_number'],
                'truck' => $l['truck'],
                'fuel_date' => $l['fuel_date'],
                'fuel_date_formatted' => format_date_dol($l['fuel_date']),
                'station_location' => $l['station_location'],
                'country' => $l['country'],
                'currency_code' => $l['currency_code'],
                'exchange_rate' => (float)$l['exchange_rate'],
                'litres' => (float)$l['litres'],
                'local_unit_price' => (float)$l['local_unit_price'],
                'local_total_cost' => (float)$l['local_total_cost'],
                'base_usd_cost' => $baseUsd,
                'display_cost_formatted' => format_money($baseUsd),
                'receipt_status' => $l['receipt_status'] ?? 'Received',
                'receipt_number' => $l['receipt_number'] ?? '',
                'notes' => $l['notes'] ?? '',
            ];
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'dispatch' => $dispatch ? [
                'id' => $dispatch['id'],
                'trip_number' => $dispatch['trip_number'],
                'truck' => $dispatch['truck'],
                'total_diesel_formatted' => format_money($dispatch['diesel']),
                'total_diesel_litres' => (float)$dispatch['diesel_litres'],
                'balance_formatted' => format_money($dispatch['balance'])
            ] : null,
            'logs' => $formattedLogs,
            'data' => $formattedLogs
        ]);
        exit;
    }

    public function updateDieselLog(string $id): void
    {
        $pdo = Database::connection();
        $logId = (int)$id;
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

        $stmt = $pdo->prepare('SELECT * FROM fleet_diesel_logs WHERE id = ? LIMIT 1');
        $stmt->execute([$logId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Diesel log not found.']);
                exit;
            }
            flash('fleet_error', 'Diesel log not found.');
            redirect('/fleet');
            return;
        }

        $dispatchId = (int)$existing['dispatch_id'];
        $fuelDate = trim($_POST['fuel_date'] ?? $existing['fuel_date']);
        $station = trim($_POST['station_location'] ?? '');
        if ($station === '') {
            $station = !empty($existing['station_location']) ? $existing['station_location'] : 'En-route Fuel Station';
        }
        $country = trim($_POST['country'] ?? $existing['country']);
        $currCode = strtoupper(trim($_POST['currency_code'] ?? $existing['currency_code']));
        $exRate = (float)($_POST['exchange_rate'] ?? $existing['exchange_rate']);
        if ($exRate <= 0) $exRate = 1.0;

        $litres = (float)($_POST['litres'] ?? 0);
        $localUnitPrice = (float)($_POST['local_unit_price'] ?? 0);

        if ($litres <= 0) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Litres pumped must be greater than 0.']);
                exit;
            }
            flash('fleet_error', 'Litres must be greater than 0.');
            redirect('/fleet');
            return;
        }

        // Multi-currency calculation: Support entering in KSh (KES) even for Uganda/Congo stops
        $entryCurrency = strtoupper(trim($_POST['entry_currency'] ?? ''));
        $kesRate = (float)exchange_rate();
        if ($kesRate <= 0) $kesRate = 130.0;

        if ($entryCurrency === 'KES' && isset($_POST['kes_unit_price']) && (float)$_POST['kes_unit_price'] > 0) {
            $kesUnitPrice = (float)$_POST['kes_unit_price'];
            $baseUsdUnitPrice = $kesUnitPrice / $kesRate;
            $baseUsdCost = round($litres * $baseUsdUnitPrice, 2);
            if ($currCode === 'KES') {
                $localUnitPrice = $kesUnitPrice;
                $localTotalCost = round($litres * $localUnitPrice, 2);
            } else {
                $localUnitPrice = round($baseUsdUnitPrice * $exRate, 2);
                $localTotalCost = round($litres * $localUnitPrice, 2);
            }
        } elseif ($entryCurrency === 'USD' && isset($_POST['usd_unit_price']) && (float)$_POST['usd_unit_price'] > 0) {
            $usdUnitPrice = (float)$_POST['usd_unit_price'];
            $baseUsdCost = round($litres * $usdUnitPrice, 2);
            $localUnitPrice = round($usdUnitPrice * $exRate, 2);
            $localTotalCost = round($litres * $localUnitPrice, 2);
        } else {
            $localTotalCost = round($litres * $localUnitPrice, 2);
            $baseUsdCost = round($localTotalCost / $exRate, 2);
        }

        $receiptStatus = trim($_POST['receipt_status'] ?? 'Received');
        $receiptNumber = trim($_POST['receipt_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        $upd = $pdo->prepare('UPDATE fleet_diesel_logs SET
            fuel_date = ?,
            station_location = ?,
            country = ?,
            currency_code = ?,
            exchange_rate = ?,
            litres = ?,
            local_unit_price = ?,
            local_total_cost = ?,
            base_usd_cost = ?,
            receipt_status = ?,
            receipt_number = ?,
            notes = ?
            WHERE id = ?');
        
        $upd->execute([
            $fuelDate,
            $station,
            $country,
            $currCode,
            $exRate,
            $litres,
            $localUnitPrice,
            $localTotalCost,
            $baseUsdCost,
            $receiptStatus,
            $receiptNumber,
            $notes,
            $logId
        ]);

        $updatedTotals = self::recalculateDispatchDiesel($pdo, $dispatchId);
        log_audit('Diesel Fueling', 'UPDATE_FUEL_STOP', "Updated fuel stop #{$logId} for trip {$existing['trip_number']}: {$litres}L in {$country}");

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Fuel stop updated successfully.',
                'log' => [
                    'id' => $logId,
                    'dispatch_id' => $dispatchId,
                    'litres' => $litres,
                    'base_usd_cost' => $baseUsdCost,
                    'local_total_cost' => $localTotalCost,
                    'currency_code' => $currCode,
                ],
                'totals' => [
                    'diesel_raw' => $updatedTotals['diesel'],
                    'diesel_formatted' => format_money($updatedTotals['diesel']),
                    'diesel_litres' => $updatedTotals['diesel_litres'],
                    'diesel_unit_price' => $updatedTotals['diesel_unit_price'],
                    'balance_raw' => $updatedTotals['balance'],
                    'balance_formatted' => format_money($updatedTotals['balance']),
                ],
                'global_aggregates' => $updatedTotals['global_aggregates'] ?? null,
            ]);
            exit;
        }

        flash('fleet_success', 'Fuel stop updated successfully.');
        redirect('/fleet');
    }

    public function deleteDieselLog(string $id): void
    {
        $pdo = Database::connection();
        $logId = (int)$id;
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

        $stmt = $pdo->prepare('SELECT dispatch_id, trip_number, country, litres, currency_code, local_total_cost FROM fleet_diesel_logs WHERE id = ? LIMIT 1');
        $stmt->execute([$logId]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$log) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Diesel log not found.']);
                exit;
            }
            flash('fleet_error', 'Diesel fuel record not found.');
            redirect('/fleet');
            return;
        }

        $dispatchId = (int)$log['dispatch_id'];
        $del = $pdo->prepare('DELETE FROM fleet_diesel_logs WHERE id = ?');
        $del->execute([$logId]);

        $updatedTotals = self::recalculateDispatchDiesel($pdo, $dispatchId);

        log_audit('Diesel Fueling', 'DELETE_FUEL_STOP', "Deleted fuel stop #{$logId} for trip {$log['trip_number']}");

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Fuel stop deleted successfully.',
                'totals' => [
                    'diesel_raw' => $updatedTotals['diesel'],
                    'diesel_formatted' => format_money($updatedTotals['diesel']),
                    'diesel_litres' => $updatedTotals['diesel_litres'],
                    'balance_raw' => $updatedTotals['balance'],
                    'balance_formatted' => format_money($updatedTotals['balance']),
                ],
                'global_aggregates' => $updatedTotals['global_aggregates'] ?? null,
            ]);
            exit;
        }

        flash('fleet_success', 'Fuel record removed and dispatch balance updated.');
        redirect('/fleet');
    }

    /* =========================================================================
       LOCATION MILEAGE RATES MANAGEMENT
       ========================================================================= */
    public function getMileageRates(): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT * FROM route_mileage_rates ORDER BY destination ASC');
        $rates = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'rates' => $rates]);
        exit;
    }

    public function storeMileageRate(): void
    {
        $pdo = Database::connection();
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

        $origin = trim($_POST['origin'] ?? 'Eldoret');
        if ($origin === '') $origin = 'Eldoret';
        $destination = trim($_POST['destination'] ?? '');
        $distanceKm = (int)($_POST['distance_km'] ?? 0);
        $allowanceKes = (float)($_POST['standard_allowance_kes'] ?? 0);
        $allowanceUsd = (float)($_POST['standard_allowance_usd'] ?? 0);
        $rate = (float)exchange_rate();
        if ($rate <= 0) $rate = 130.0;

        if ($allowanceKes > 0 && $allowanceUsd <= 0) {
            $allowanceUsd = round($allowanceKes / $rate, 2);
        } elseif ($allowanceUsd > 0 && $allowanceKes <= 0) {
            $allowanceKes = round($allowanceUsd * $rate, 2);
        }

        $notes = trim($_POST['notes'] ?? '');

        if ($destination === '') {
            if ($isAjax) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Destination name is required.']);
                exit;
            }
            flash('fleet_error', 'Destination name is required.');
            redirect('/fleet');
            return;
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO route_mileage_rates (origin, destination, distance_km, standard_allowance_kes, standard_allowance_usd, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$origin, $destination, $distanceKm, $allowanceKes, $allowanceUsd, $notes, date('Y-m-d H:i:s')]);
            $newId = (int)$pdo->lastInsertId();

            log_audit('Mileage Rates', 'ADD_MILEAGE_RATE', "Added corridor allowance for {$origin} → {$destination}: KES {$allowanceKes} ($ {$allowanceUsd})");

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => "Mileage allowance for {$destination} saved successfully!",
                    'rate' => [
                        'id' => $newId,
                        'origin' => $origin,
                        'destination' => $destination,
                        'distance_km' => $distanceKm,
                        'standard_allowance_kes' => $allowanceKes,
                        'standard_allowance_usd' => $allowanceUsd,
                        'notes' => $notes
                    ]
                ]);
                exit;
            }

            flash('fleet_success', "Mileage allowance for {$destination} created successfully.");
        } catch (\Throwable $e) {
            if ($isAjax) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Error saving route rate: ' . $e->getMessage()]);
                exit;
            }
            flash('fleet_error', 'Error saving route rate: ' . $e->getMessage());
        }

        redirect('/fleet');
    }

    public function updateMileageRate(string $id): void
    {
        $pdo = Database::connection();
        $rateId = (int)$id;
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

        $origin = trim($_POST['origin'] ?? 'Eldoret');
        if ($origin === '') $origin = 'Eldoret';
        $destination = trim($_POST['destination'] ?? '');
        $distanceKm = (int)($_POST['distance_km'] ?? 0);
        $allowanceKes = (float)($_POST['standard_allowance_kes'] ?? 0);
        $allowanceUsd = (float)($_POST['standard_allowance_usd'] ?? 0);
        $rate = (float)exchange_rate();
        if ($rate <= 0) $rate = 130.0;

        if ($allowanceKes > 0 && $allowanceUsd <= 0) {
            $allowanceUsd = round($allowanceKes / $rate, 2);
        } elseif ($allowanceUsd > 0 && $allowanceKes <= 0) {
            $allowanceKes = round($allowanceUsd * $rate, 2);
        }
        $notes = trim($_POST['notes'] ?? '');

        if ($destination === '') {
            if ($isAjax) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Destination name is required.']);
                exit;
            }
            flash('fleet_error', 'Destination name is required.');
            redirect('/fleet');
            return;
        }

        try {
            $stmt = $pdo->prepare('UPDATE route_mileage_rates SET origin = ?, destination = ?, distance_km = ?, standard_allowance_kes = ?, standard_allowance_usd = ?, notes = ? WHERE id = ?');
            $stmt->execute([$origin, $destination, $distanceKm, $allowanceKes, $allowanceUsd, $notes, $rateId]);

            log_audit('Mileage Rates', 'UPDATE_MILEAGE_RATE', "Updated corridor allowance for {$origin} → {$destination}: KES {$allowanceKes} ($ {$allowanceUsd})");

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => "Mileage allowance for {$destination} updated successfully!",
                    'rate' => [
                        'id' => $rateId,
                        'origin' => $origin,
                        'destination' => $destination,
                        'distance_km' => $distanceKm,
                        'standard_allowance_kes' => $allowanceKes,
                        'standard_allowance_usd' => $allowanceUsd,
                        'notes' => $notes
                    ]
                ]);
                exit;
            }

            flash('fleet_success', "Mileage allowance updated successfully.");
        } catch (\Throwable $e) {
            if ($isAjax) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Error updating route: ' . $e->getMessage()]);
                exit;
            }
            flash('fleet_error', 'Error updating route: ' . $e->getMessage());
        }

        redirect('/fleet');
    }

    public function deleteMileageRate(string $id): void
    {
        $pdo = Database::connection();
        $rateId = (int)$id;
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

        try {
            $stmt = $pdo->prepare('DELETE FROM route_mileage_rates WHERE id = ?');
            $stmt->execute([$rateId]);

            log_audit('Mileage Rates', 'DELETE_MILEAGE_RATE', "Deleted corridor allowance rate #{$rateId}");

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Mileage rate deleted successfully.']);
                exit;
            }

            flash('fleet_success', 'Mileage rate deleted successfully.');
        } catch (\Throwable $e) {
            if ($isAjax) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Could not delete mileage rate: ' . $e->getMessage()]);
                exit;
            }
            flash('fleet_error', 'Could not delete mileage rate: ' . $e->getMessage());
        }

        redirect('/fleet');
    }
}

