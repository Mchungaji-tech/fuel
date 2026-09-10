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

        return view('fleet.index', [
            'title' => 'Fleet Management — Sarura Fuel',
            'dispatches' => $dispatches,
            'aggregates' => $aggregates,
            'trucks' => $trucks,
            'drivers' => $drivers,
            'products' => $products,
            'customers' => $customers,
            'search' => $search,
            'statusFilter' => $statusFilter,
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

        // Cargo Product Unit Price (transport payout rate per litre)
        $prodStmt = $pdo->prepare('SELECT unit_price FROM products WHERE UPPER(code) = ? LIMIT 1');
        $prodStmt->execute([$product]);
        if (isset($_POST['unit_price']) && $_POST['unit_price'] !== '') {
            $rawUnitPrice = (float)$_POST['unit_price'];
            $unitPrice = $isKes ? ($rawUnitPrice / $rate) : $rawUnitPrice;
        } else {
            $unitPrice = $catalogPrice;
        }

        // Automatically calculated Expected Transport Billed: Loaded Litres × Unit Price
        $transportAmount = (float)($loadedLitres * $unitPrice);

        // Transit Shortage & Delivered Litres logic
        $shortageLitres = max(0, (int)($_POST['shortage_litres'] ?? 0));
        $status = trim($_POST['status'] ?? 'In Transit');
        $isDelivered = (strtolower($status) === 'delivered');

        if ($shortageLitres > 0) {
            $deliveredLitres = max(0, $loadedLitres - $shortageLitres);
            $finalPayout = (float)($deliveredLitres * $unitPrice);
            $payoutDiff = (float)($shortageLitres * $unitPrice);
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
            $clientName = 'Regional Fuel Consignee';
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

        // Unit Price
        if (isset($_POST['unit_price']) && $_POST['unit_price'] !== '') {
            $rawUnitPrice = (float)$_POST['unit_price'];
            $unitPrice = $isKes ? ($rawUnitPrice / $rate) : $rawUnitPrice;
        } else {
            $unitPrice = (float)($current['unit_price'] ?? 0);
            if ($unitPrice <= 0) {
                $prodStmt = $pdo->prepare('SELECT unit_price FROM products WHERE UPPER(code) = ? LIMIT 1');
                $prodStmt->execute([$product]);
                $unitPrice = (float)($prodStmt->fetchColumn() ?: ($product === 'PMS' ? 10.50 : 9.50));
            }
        }

        $loadedLitres = isset($_POST['loaded_litres']) ? (int)$_POST['loaded_litres'] : (int)$current['loaded_litres'];

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

        // Expected Transport is automatically: Loaded Litres × Unit Price
        $transportAmount = (float)($loadedLitres * $unitPrice);

        // Final Payout
        $isDelivered = (strtolower($status) === 'delivered');
        if (isset($_POST['final_payout']) && $_POST['final_payout'] !== '') {
            $rawFinal = (float)$_POST['final_payout'];
            $finalPayout = $isKes ? ($rawFinal / $rate) : $rawFinal;
        } elseif ($deliveredLitres !== null && $deliveredLitres > 0) {
            $finalPayout = (float)($deliveredLitres * $unitPrice);
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

        $sql = 'SELECT * FROM fleet_dispatches WHERE 1=1';
        $params = [];

        if ($truck !== '' && strtolower($truck) !== 'all') {
            $sql .= ' AND truck = ?';
            $params[] = $truck;
        }

        if ($month !== '' && strtolower($month) !== 'all') {
            $sql .= ' AND substr(dispatch_date, 1, 7) = ?';
            $params[] = $month;
        } elseif ($year !== '' && strtolower($year) !== 'all') {
            $sql .= ' AND substr(dispatch_date, 1, 4) = ?';
            $params[] = $year;
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
            'Truck Capacity (L)',
            'Loaded Litres',
            'Shortage Litres',
            'Delivered Litres',
            'Product Unit Price (' . $currencySymbol . '/L)',
            'Diesel Litres',
            'Diesel Unit Price (' . $currencySymbol . '/L)',
            'Diesel Fuel Cost (' . $currencySymbol . ')',
            'Origin Loading Depot',
            'Destination',
            'Client / Consignee',
            'Fuel Product',
            'Driver Name',
            'Transport Revenue (' . $currencySymbol . ')',
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
            $unitPriceVal = (float) ($d['unit_price'] ?? 0);
            $displayUnitPrice = $isKes ? ($unitPriceVal * $rate) : $unitPriceVal;
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
                (int) $d['truck_capacity'],
                $loaded,
                $shortage,
                $delivered,
                $canViewFin ? round($displayUnitPrice, 2) : '[Restricted]',
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

        $truckPart = ($truck && strtolower($truck) !== 'all') ? preg_replace('/[^a-zA-Z0-9_-]/', '', $truck) . '_' : 'all_cars_';
        $periodPart = $month ? str_replace('-', '_', $month) : ($year ? $year : date('Y_m_d'));
        $filenameBase = 'fleet_dispatches_' . $truckPart . $periodPart;

        if ($format === 'xls') {
            ExcelService::exportXls($filenameBase . '.xls', $headers, $rows, 'Fleet Dispatches');
        } elseif ($format === 'csv') {
            ExcelService::exportCsv($filenameBase . '.csv', $headers, $rows);
        } else {
            ExcelService::exportXlsx($filenameBase . '.xlsx', $headers, $rows, 'Fleet Dispatches');
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

        if ($row && !empty($row['trip_number'])) {
            $pdo->prepare('DELETE FROM trips WHERE trip_number = ?')->execute([$row['trip_number']]);
        }

        $tripRef = $row ? ($row['trip_number'] . ' - ' . $row['truck'] . ' (' . ($row['client_name'] ?? 'Consignee') . ')') : "ID #{$id}";
        log_audit('Fleet Dispatch', 'DELETE_DISPATCH', "Deleted fleet dispatch {$tripRef}", 1);

        flash('fleet_success', 'Dispatch record removed successfully.');
        redirect('/fleet');
    }
}
