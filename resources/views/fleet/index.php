<?php
    $content = function () use ($title, $dispatches, $aggregates, $trucks, $drivers, $products, $search, $statusFilter, $availableYears, $customers, $mileageRates) {
        $canViewFin = can_view_financials();
?>
<section class="view active" id="view-fleet">
    <!-- Header & Action Bar -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
        <div class="hello">
            <h1>Fleet Management 🚚</h1>
            <p>Operations ledger, fuel volumes loaded & delivered, client final payouts, shortage reconciliations, and net profit.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <a href="<?= url('reports/trucks') ?>" class="btn btn-ghost" title="Detailed Truck Performance Report">📊 Truck Reports</a>
            <button class="btn btn-ghost" onclick="document.getElementById('columnManagerModal').classList.add('active')" title="Customize column titles, show/hide columns, add custom columns, or reset table">
                ⚙️ Columns & Schema
            </button>
            <button type="button" class="btn btn-ghost" onclick="openMileageRatesModal()" style="font-weight:700;" title="Manage corridor destination mileage rates & driver allowances">
                🛣️ Mileage Rates
            </button>
            <button class="btn btn-ghost" onclick="document.getElementById('importModal').classList.add('active')">📥 Import Spreadsheet</button>
            <div style="display:inline-flex;border-radius:10px;overflow:hidden;border:1.5px solid var(--border-2);box-shadow:var(--shadow-sm);">
                <button type="button" class="btn btn-ghost" onclick="openFleetExportModal()" style="border-radius:0;border:0;background:var(--card);font-weight:700;padding:8px 12px;" title="Export reports per car, month, or year">
                    📊 Export Reports ▾
                </button>
                <a href="<?= url('fleet/export?format=xlsx') ?>" download="fleet_dispatches_<?= date('Y-m-d') ?>.xlsx" class="btn btn-ghost" style="border-radius:0;border:0;border-left:1px solid var(--border);padding:8px 10px;font-size:12.5px;" title="Quick export all to Excel">
                    .xlsx
                </a>
                <a href="<?= url('fleet/export?format=csv') ?>" download="fleet_dispatches_<?= date('Y-m-d') ?>.csv" class="btn btn-ghost" style="border-radius:0;border:0;border-left:1px solid var(--border);padding:8px 10px;font-size:12.5px;" title="Quick export standard CSV">
                    CSV
                </a>
            </div>
            <button type="button" class="btn btn-amber" onclick="openDieselLogModal()" style="font-weight:800;background:var(--amber);color:#fff;box-shadow:0 4px 14px rgba(217,119,6,0.3);" title="Record cross-border diesel fuel top-up for any trip (Kenya, Uganda, Congo)">
                ⛽ Log Diesel
            </button>
            <button type="button" class="btn btn-brand" onclick="openProceduralDispatchModal()">＋ New Dispatch</button>
        </div>
    </div>

    <!-- KPI Aggregates Summary Cards -->
    <div class="kpis" style="margin-top:20px;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:14px;">
        <div class="kpi">
            <div class="lbl">Expected Transport Billed</div>
            <div class="val" id="statTotalTransport" style="color:var(--brand);"><?= $canViewFin ? format_money($aggregates['total_transport'] ?? 0) : '[Restricted]' ?></div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Across <?= (int) ($aggregates['total_count'] ?? 0) ?> dispatches</div>
        </div>
        <div class="kpi" style="border:1.5px solid rgba(245,158,11,0.5);background:rgba(245,158,11,0.04);">
            <div class="lbl" style="color:var(--amber);font-weight:800;">⛽ Diesel Fuel (Owner Expense)</div>
            <div class="val" id="statTotalDiesel" style="color:var(--amber);"><?= $canViewFin ? format_money($aggregates['total_diesel'] ?? 0) : '[Restricted]' ?></div>
            <div style="font-size:12.5px;color:var(--amber);margin-top:4px;font-weight:700;">Owner paid (Deducted from profit)</div>
        </div>
        <div class="kpi">
            <div class="lbl">Mileage & En-Route Costs</div>
            <div class="val" id="statTotalMileage" style="color:var(--amber);"><?= $canViewFin ? format_money($aggregates['total_mileage'] ?? 0) : '[Restricted]' ?></div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Driver allowances & road tolls</div>
        </div>
        <div class="kpi" style="border:2px solid var(--green);background:linear-gradient(135deg,var(--card),var(--green-soft));">
            <div class="lbl" style="color:var(--green);font-weight:800;">Net Balance (Profit / Commission)</div>
            <div class="val" id="statTotalBalance" style="color:var(--green);font-size:28px;"><?= $canViewFin ? format_money($aggregates['total_balance'] ?? 0) : '[Restricted]' ?></div>
            <div style="font-size:12.5px;color:var(--green);font-weight:700;margin-top:4px;">Revenue - (Diesel + Mileage)</div>
        </div>
    </div>

    <!-- Compact Search & Filter Toolbar with Time Horizon & Day Selectors -->
    <div style="display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin:22px 0 14px;">
        <!-- Search Input -->
        <div style="display:flex;align-items:center;gap:10px;background:var(--card);border:1.5px solid var(--border-2);border-radius:10px;padding:6px 14px;min-width:280px;max-width:380px;box-shadow:var(--shadow);">
            <span style="color:var(--text-3);font-size:16px;">🔍</span>
            <input type="text" id="fleetTableSearch" placeholder="Search DOL, truck, driver, destination, client…" style="border:0;outline:0;background:transparent;width:100%;font-size:14px;color:var(--text);">
        </div>

        <!-- Status Filter -->
        <select id="fleetStatusFilter" onchange="applyFleetFilters()" style="padding:8px 12px;border:1.5px solid var(--border-2);border-radius:10px;background:var(--card);font-size:13.5px;font-weight:700;color:var(--text);box-shadow:var(--shadow);outline:0;">
            <option value="all">All Statuses</option>
            <option value="in transit">In Transit</option>
            <option value="delivered">Delivered / Complete</option>
            <option value="loading">Loading</option>
            <option value="planned">Planned</option>
        </select>

        <!-- Truck Filter -->
        <select id="fleetTruckFilter" onchange="applyFleetFilters()" style="padding:8px 12px;border:1.5px solid var(--border-2);border-radius:10px;background:var(--card);font-size:13.5px;font-weight:700;color:var(--text);box-shadow:var(--shadow);outline:0;">
            <option value="all">🚛 All Trucks</option>
            <?php foreach ($trucks as $trk): ?>
                <option value="<?= htmlspecialchars($trk['plate_number']) ?>"><?= htmlspecialchars($trk['plate_number']) ?></option>
            <?php endforeach; ?>
        </select>

        <!-- Time Horizon Filter Buttons: All, This Week, This Month, This Year -->
        <div style="display:inline-flex;align-items:center;gap:4px;background:var(--card);border:1.5px solid var(--border-2);border-radius:10px;padding:3px;box-shadow:var(--shadow);">
            <button type="button" class="btn btn-sm btn-ghost time-filter-btn active" data-period="all" onclick="setTimeHorizonFilter('all', this)" style="padding:4px 10px;font-size:12.5px;font-weight:700;">All Time</button>
            <button type="button" class="btn btn-sm btn-ghost time-filter-btn" data-period="week" onclick="setTimeHorizonFilter('week', this)" style="padding:4px 10px;font-size:12.5px;font-weight:700;">This Week ▾</button>
            <button type="button" class="btn btn-sm btn-ghost time-filter-btn" data-period="month" onclick="setTimeHorizonFilter('month', this)" style="padding:4px 10px;font-size:12.5px;font-weight:700;">This Month</button>
            <button type="button" class="btn btn-sm btn-ghost time-filter-btn" data-period="year" onclick="setTimeHorizonFilter('year', this)" style="padding:4px 10px;font-size:12.5px;font-weight:700;">This Year</button>
        </div>

        <!-- Sub-Toolbar for Days of Week (Mon - Sun) -->
        <div id="weekDaysSubToolbar" style="display:none;align-items:center;gap:3px;background:var(--card-2);border:1.5px solid var(--border);border-radius:10px;padding:3px 6px;">
            <span style="font-size:11px;font-weight:800;color:var(--text-3);margin-right:2px;">DAY:</span>
            <button type="button" class="btn btn-sm btn-ghost day-filter-btn active" data-day="all" onclick="setWeekDayFilter('all', this)" style="padding:2px 7px;font-size:11.5px;font-weight:700;">All Week</button>
            <button type="button" class="btn btn-sm btn-ghost day-filter-btn" data-day="1" onclick="setWeekDayFilter('1', this)" style="padding:2px 6px;font-size:11.5px;font-weight:700;">Mon</button>
            <button type="button" class="btn btn-sm btn-ghost day-filter-btn" data-day="2" onclick="setWeekDayFilter('2', this)" style="padding:2px 6px;font-size:11.5px;font-weight:700;">Tue</button>
            <button type="button" class="btn btn-sm btn-ghost day-filter-btn" data-day="3" onclick="setWeekDayFilter('3', this)" style="padding:2px 6px;font-size:11.5px;font-weight:700;">Wed</button>
            <button type="button" class="btn btn-sm btn-ghost day-filter-btn" data-day="4" onclick="setWeekDayFilter('4', this)" style="padding:2px 6px;font-size:11.5px;font-weight:700;">Thu</button>
            <button type="button" class="btn btn-sm btn-ghost day-filter-btn" data-day="5" onclick="setWeekDayFilter('5', this)" style="padding:2px 6px;font-size:11.5px;font-weight:700;">Fri</button>
            <button type="button" class="btn btn-sm btn-ghost day-filter-btn" data-day="6" onclick="setWeekDayFilter('6', this)" style="padding:2px 6px;font-size:11.5px;font-weight:700;">Sat</button>
            <button type="button" class="btn btn-sm btn-ghost day-filter-btn" data-day="0" onclick="setWeekDayFilter('0', this)" style="padding:2px 6px;font-size:11.5px;font-weight:700;">Sun</button>
        </div>

        <div style="font-size:13px;color:var(--text-2);font-weight:700;margin-left:auto;">
            Showing <span id="fleetVisibleCount"><?= count($dispatches) ?></span> of <?= count($dispatches) ?> records
        </div>
    </div>

    <!-- Fleet Management Table with Inline Editing -->
    <?php
        $fleetMetaCols = \App\Services\TableSchemaService::getTableColumns('fleet_dispatches', false);
        $metaMap = array_column($fleetMetaCols, null, 'column_key');
        $fleetCustomCols = array_filter($fleetMetaCols, fn($c) => !empty($c['is_custom']) && !empty($c['is_visible']));
        $truckCapMap = array_column($trucks ?? [], 'capacity_litres', 'plate_number');
    ?>
    <div class="panel">
        <div class="table-responsive">
            <table id="fleetTable">
                <thead>
                    <tr>
                        <th style="min-width:95px;" title="Date of Loading"><?= htmlspecialchars($metaMap['dispatch_date']['display_label'] ?? 'DOL') ?></th>
                        <th><?= htmlspecialchars($metaMap['truck']['display_label'] ?? 'Truck') ?></th>
                        <th><?= htmlspecialchars($metaMap['driver']['display_label'] ?? 'Driver') ?></th>
                        <th><?= htmlspecialchars($metaMap['status']['display_label'] ?? 'Status') ?></th>
                        <th title="Actual volume carried in litres at 20°C (DOL Volume)"><?= htmlspecialchars($metaMap['loaded_litres']['display_label'] ?? 'Actual @ L20') ?></th>
                        <th style="min-width:90px;text-align:center;"><?= htmlspecialchars($metaMap['shortage_litres']['display_label'] ?? 'Shortage (L)') ?></th>
                        <th><?= htmlspecialchars($metaMap['destination']['display_label'] ?? 'Destination & Client') ?></th>
                        <th><?= htmlspecialchars($metaMap['product']['display_label'] ?? 'Product') ?></th>
                        <th style="min-width:95px;" title="Diesel fuel cost catered for by owner (Deducted from profit)"><?= htmlspecialchars($metaMap['diesel']['display_label'] ?? 'Diesel (Owner Exp)') ?></th>
                        <th><?= htmlspecialchars($metaMap['transport_amount']['display_label'] ?? 'Expected Transport') ?></th>
                        <th><?= htmlspecialchars($metaMap['final_payout']['display_label'] ?? 'Final Client Payout') ?></th>
                        <th><?= htmlspecialchars($metaMap['payout_difference']['display_label'] ?? 'Payout Diff (Loss)') ?></th>
                        <th><?= htmlspecialchars($metaMap['mileage_cost']['display_label'] ?? 'Trip Costs') ?></th>
                        <th><?= htmlspecialchars($metaMap['balance']['display_label'] ?? 'Net Balance') ?></th>
                        <?php if (!empty($metaMap['breakdown_notes']['is_visible'])): ?>
                            <th><?= htmlspecialchars($metaMap['breakdown_notes']['display_label'] ?? 'Breakdown Remarks') ?></th>
                        <?php endif; ?>
                        <?php if (!empty($metaMap['shortage_notes']['is_visible'])): ?>
                            <th><?= htmlspecialchars($metaMap['shortage_notes']['display_label'] ?? 'Shortage Notes') ?></th>
                        <?php endif; ?>
                        <?php foreach ($fleetCustomCols as $cc): ?>
                            <th style="white-space:nowrap;background:rgba(79,70,229,0.06);"><?= htmlspecialchars($cc['display_label']) ?></th>
                        <?php endforeach; ?>
                        <th style="text-align:center;min-width:160px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                        $extraColCount = (int)!empty($metaMap['breakdown_notes']['is_visible'])
                            + (int)!empty($metaMap['shortage_notes']['is_visible'])
                            + count($fleetCustomCols);
                    ?>
                    <?php if (empty($dispatches)): ?>
                        <tr>
                            <td colspan="<?= 15 + $extraColCount ?>" style="text-align:center;padding:40px;color:var(--text-3);">
                                No fleet dispatches found. Click "New Dispatch" or "Import CSV" to get started.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                            $fleetDetailsMap = []; 
                            $isKes = current_currency() === 'KES';
                            $rate = exchange_rate();
                        ?>
                        <?php foreach ($dispatches as $d): ?>
                            <?php
                                $balanceVal = (float) $d['balance'];
                                $isContract = !empty($d['is_subcontracted']) || in_array(strtolower($d['truck_ownership'] ?? ''), ['contract', 'subcontracted']);
                                $ownership = $isContract ? 'Subcontracted' : 'Company Fleet';
                                $loaded = (int) $d['loaded_litres'];
                                $rawDelivered = $d['delivered_litres'];
                                $isEnRoute = in_array(strtolower($d['status']), ['in transit', 'loading', 'planned']) || ($rawDelivered === null && strtolower($d['status']) !== 'delivered');
                                $delivered = ($rawDelivered !== null && $rawDelivered !== '') ? (int) $rawDelivered : ($isEnRoute ? 0 : $loaded);
                                $litresLoss = $loaded - $delivered;

                                $expectedTransport = (float) $d['transport_amount'];
                                $finalPayout = ($d['final_payout'] !== null && $d['final_payout'] !== '') ? (float) $d['final_payout'] : ($isEnRoute ? 0 : $expectedTransport);
                                $payoutDiff = (float) ($d['payout_difference'] ?? ($expectedTransport - $finalPayout));
                                $dVal = (float) ($d['diesel'] ?? 0);

                                $fleetDetailsMap[$d['id']] = [
                                    'id' => $d['id'],
                                    'trip_number' => $d['trip_number'],
                                    'bol_number' => $d['bol_number'] ?? '',
                                    'dispatch_date' => $d['dispatch_date'],
                                    'dol_formatted' => format_date_dol($d['dispatch_date']),
                                    'truck' => $d['truck'],
                                    'truck_capacity' => (int)($d['truck_capacity'] ?? 0),
                                    'ownership' => $ownership,
                                    'is_subcontracted' => (int)($d['is_subcontracted'] ?? 0),
                                    'agreed_commission' => (float)($d['agreed_commission'] ?? 0),
                                    'driver' => $d['driver'] ?: 'Unassigned',
                                                                      'loaded_litres' => $loaded,
                                    'delivered_litres' => $delivered,
                                    'shortage_litres' => (int)($d['shortage_litres'] ?? ($isEnRoute ? 0 : $litresLoss)),
                                    'litres_loss' => $litresLoss,
                                    'unit_price' => (float)($d['unit_price'] ?? 0),
                                    'from_location' => $d['from_location'] ?: 'Eldoret',
                                    'destination' => $d['destination'],
                                    'client_name' => $d['client_name'] ?? 'Regional Consignee',
                                    'product' => $d['product'],
                                    'diesel_litres' => (float)($d['diesel_litres'] ?? 0),
                                    'diesel_unit_price' => (float)($d['diesel_unit_price'] ?? 0),
                                    'diesel' => $dVal,
                                    'diesel_formatted' => $canViewFin ? ($dVal > 0 ? format_money($dVal) : '—') : '[Restricted]',
                                    'transport_amount' => $expectedTransport,
                                    'transport_formatted' => $canViewFin ? format_money($expectedTransport) : '[Restricted]',
                                    'final_payout' => $finalPayout,
                                    'final_payout_formatted' => $canViewFin ? format_money($finalPayout) : '[Restricted]',
                                    'payout_diff' => $payoutDiff,
                                    'payout_diff_formatted' => $canViewFin ? format_money($payoutDiff) : '[Restricted]',
                                    'mileage_cost' => (float)$d['mileage_cost'],
                                    'mileage_formatted' => $canViewFin ? format_money($d['mileage_cost']) : '[Restricted]',
                                    'extra_expenses' => (float)$d['extra_expenses'],
                                    'extra_formatted' => $canViewFin ? format_money($d['extra_expenses']) : '[Restricted]',
                                    'balance' => $balanceVal,
                                    'balance_formatted' => $canViewFin ? format_money($balanceVal) : '[Restricted]',
                                    'seal_numbers' => $d['seal_numbers'] ?? '',
                                    'breakdown_notes' => $d['breakdown_notes'] ?? '',
                                    'shortage_notes' => $d['shortage_notes'] ?? '',
                                    'created_at' => $d['created_at'] ?? '',
                                    'custom_fields' => array_intersect_key($d, array_flip(array_column($fleetCustomCols, 'column_key'))),
                                ];

                                $s = strtolower($d['status']);
                                $sClass = 's-plan';
                                if (str_contains($s, 'transit')) $sClass = 's-transit';
                                elseif (str_contains($s, 'load')) $sClass = 's-load';
                                elseif (str_contains($s, 'deliver') || str_contains($s, 'complete')) $sClass = 's-done';
                                elseif (str_contains($s, 'hold') || str_contains($s, 'cancel') || str_contains($s, 'dispute')) $sClass = 's-hold';
                            ?>
                            <tr id="fleet-row-<?= $d['id'] ?>" data-id="<?= $d['id'] ?>" data-truck="<?= htmlspecialchars(strtolower($d['truck'])) ?>" data-dispatch-date="<?= htmlspecialchars($d['dispatch_date']) ?>" data-mileage-cost="<?= (float)convert_currency($d['mileage_cost']) ?>" data-extra-expenses="<?= (float)convert_currency($d['extra_expenses']) ?>" data-diesel="<?= (float)convert_currency($d['diesel'] ?? 0) ?>" data-unit-price="<?= (float)convert_currency($d['unit_price'] ?? 0) ?>" data-shortage-litres="<?= (int)($d['shortage_litres'] ?? 0) ?>">
                                <!-- DOL (Date of Loading formatted DD/MM/YY) -->
                                <td class="cell-dol" style="font-weight:700;white-space:nowrap;">
                                    <span class="view-val"><?= format_date_dol($d['dispatch_date']) ?></span>
                                    <div style="font-size:11.5px;color:var(--text-3);font-weight:500;"><?= htmlspecialchars($d['trip_number']) ?></div>
                                </td>

                                <!-- Truck -->
                                <td class="cell-truck">
                                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                        <b class="view-val-truck" style="font-size:14.5px;"><?= htmlspecialchars($d['truck']) ?></b>
                                        <?php if ($isContract): ?>
                                            <span style="background:var(--amber-soft);color:var(--amber);padding:2px 6px;border-radius:6px;font-size:11px;font-weight:800;border:1px solid rgba(217,119,6,.2);">
                                                Contract
                                            </span>
                                        <?php else: ?>
                                            <span style="background:var(--green-soft);color:var(--green);padding:2px 6px;border-radius:6px;font-size:11px;font-weight:800;border:1px solid rgba(5,150,105,.2);">
                                                Owner
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>



                                <!-- Driver (Dedicated Column) -->
                                <td class="cell-driver" style="font-weight:600;white-space:nowrap;">
                                    <span class="view-val"><?= htmlspecialchars($d['driver'] ?: 'Unassigned') ?></span>
                                </td>

                                <!-- Status (Dedicated Column) -->
                                <td class="cell-status">
                                    <span class="status <?= $sClass ?> view-val">
                                        <i></i><?= htmlspecialchars($d['status']) ?>
                                    </span>
                                </td>

                                <!-- Litres Loaded vs Delivered -->
                                <td class="cell-litres" style="white-space:nowrap;">
                                    <div style="font-weight:800;color:var(--brand);font-size:14px;">
                                        <span class="view-val-loaded"><?= number_format($loaded) ?></span> L
                                    </div>
                                    <?php if ($isEnRoute): ?>
                                        <div style="font-size:11px;color:var(--amber);font-weight:800;background:var(--amber-soft);padding:1px 6px;border-radius:4px;display:inline-block;margin-top:2px;">
                                            🚚 En Route (Awaiting Offload)
                                        </div>
                                    <?php else: ?>
                                        <div style="font-size:12px;color:var(--text-2);margin-top:2px;">
                                            Deliv: <b class="view-val-delivered"><?= number_format($delivered) ?></b> L
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Shortage (Litres) Dedicated Column -->
                                <td class="cell-shortage" style="white-space:nowrap;text-align:center;">
                                    <?php $sLitres = (int)($d['shortage_litres'] ?? ($isEnRoute ? 0 : $litresLoss)); ?>
                                    <?php if ($isEnRoute): ?>
                                        <span style="font-size:11.5px;color:var(--text-3);font-weight:600;">—</span>
                                    <?php elseif ($sLitres > 0): ?>
                                        <span style="background:var(--red-soft);color:var(--red);padding:3px 8px;border-radius:6px;font-weight:800;font-size:12px;display:inline-block;">
                                            -<?= number_format($sLitres) ?> L
                                        </span>
                                    <?php else: ?>
                                        <span style="background:var(--green-soft);color:var(--green);padding:3px 8px;border-radius:6px;font-weight:700;font-size:12px;display:inline-block;">
                                            0 L
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Destination & Client -->
                                <td class="cell-route">
                                    <?php
                                        $dest = $d['destination'] ?? '';
                                        $flag = '🌍';
                                        $dLower = strtolower($dest);
                                        if (str_contains($dLower, 'congo') || str_contains($dLower, 'drc') || str_contains($dLower, 'goma') || str_contains($dLower, 'lubumbashi')) $flag = '🇨🇩';
                                        elseif (str_contains($dLower, 'uganda') || str_contains($dLower, 'kampala')) $flag = '🇺🇬';
                                        elseif (str_contains($dLower, 'south sudan') || str_contains($dLower, 'juba')) $flag = '🇸🇸';
                                        elseif (str_contains($dLower, 'sudan') || str_contains($dLower, 'khartoum')) $flag = '🇸🇩';
                                        elseif (str_contains($dLower, 'rwanda') || str_contains($dLower, 'kigali')) $flag = '🇷🇼';
                                        elseif (str_contains($dLower, 'tanzania') || str_contains($dLower, 'dar')) $flag = '🇹🇿';
                                        elseif (str_contains($dLower, 'kenya') || str_contains($dLower, 'kisumu') || str_contains($dLower, 'eldoret') || str_contains($dLower, 'nairobi') || str_contains($dLower, 'mombasa')) $flag = '🇰🇪';
                                    ?>
                                    <div style="font-size:13.5px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:5px;">
                                        <span style="font-size:15px;"><?= $flag ?></span>
                                        <span class="view-val-destination"><?= htmlspecialchars($dest) ?></span>
                                    </div>
                                    <div style="font-size:12px;color:var(--brand);font-weight:700;margin-top:2px;">
                                        👤 <span class="view-val-client"><?= htmlspecialchars($d['client_name'] ?? 'Regional Consignee') ?></span>
                                    </div>
                                    <div style="font-size:11.5px;color:var(--text-3);margin-top:2px;">
                                        <b>From: <?= htmlspecialchars($d['from_location'] ?: 'Eldoret') ?></b>
                                    </div>
                                </td>

                                <!-- Transported Fuel Product (Freight Only) -->
                                <td class="cell-product" style="white-space:nowrap;">
                                    <span style="background:var(--brand-soft);color:var(--brand);padding:3px 7px;border-radius:6px;font-weight:800;font-size:12px;">
                                        <?= htmlspecialchars($d['product']) ?>
                                    </span>
                                </td>

                                <!-- Diesel Fuel Cost (Owner Expense: Dual USD & KSh + Refuel Country) -->
                                <td class="cell-diesel" style="white-space:nowrap;font-weight:700;cursor:pointer;" onclick="openDieselLogModal(<?= $d['id'] ?>)" title="Click to view & log cross-border diesel fuel stops for <?= htmlspecialchars($d['trip_number']) ?>">
                                    <?php 
                                        $dVal = (float)($d['diesel'] ?? 0); 
                                        $dLitres = (float)($d['diesel_litres'] ?? 0);
                                        $dUnitPrice = (float)($d['diesel_unit_price'] ?? 0);
                                        $exRate = (float)exchange_rate();
                                        $usdVal = $dVal;
                                        $kesVal = round($dVal * $exRate, 2);
                                        $refuelCountryStr = trim($d['refuel_countries'] ?? '');
                                    ?>
                                    <?php if (!$canViewFin): ?>
                                        <span class="view-val">[Restricted]</span>
                                    <?php elseif ($dVal <= 0): ?>
                                        <span class="view-val" style="color:var(--text-3);">—</span>
                                    <?php else: ?>
                                        <div class="val-diesel-usd" style="font-weight:900;color:var(--amber);font-size:13.5px;">
                                            $<?= number_format($usdVal, 2) ?>
                                        </div>
                                        <div class="val-diesel-kes" style="font-size:11.5px;color:var(--text-2);font-weight:700;margin-top:1px;">
                                            KSh <?= number_format($kesVal, 2) ?>
                                        </div>
                                        <?php if ($dLitres > 0): ?>
                                            <div style="font-size:10.5px;color:var(--text-3);font-weight:600;margin-top:2px;">
                                                ⛽ <?= number_format($dLitres, 1) ?> L
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($refuelCountryStr)): ?>
                                            <div class="val-diesel-country" style="font-size:10px;color:var(--amber);font-weight:700;margin-top:2px;">
                                                📍 <?= htmlspecialchars($refuelCountryStr) ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>

                                <!-- Expected Transport -->
                                <td class="cell-transport" style="white-space:nowrap;font-weight:700;">
                                    <span class="view-val"><?= $canViewFin ? format_money($expectedTransport) : '[Restricted]' ?></span>
                                </td>

                                <!-- Final Client Payout -->
                                <td class="cell-final-payout" style="white-space:nowrap;font-weight:800;color:var(--brand);">
                                    <?php if (!$canViewFin): ?>
                                        <span class="view-val">[Restricted]</span>
                                    <?php elseif ($isEnRoute): ?>
                                        <span style="font-size:12px;color:var(--amber);font-weight:700;background:var(--amber-soft);padding:3px 7px;border-radius:6px;display:inline-block;">
                                            ⏳ Awaiting Offload
                                        </span>
                                    <?php else: ?>
                                        <span class="view-val"><?= format_money($finalPayout) ?></span>
                                        <div style="font-size:11px;color:var(--text-3);font-weight:600;">Client Paid</div>
                                    <?php endif; ?>
                                </td>

                                <!-- Payout Difference / Shortage Deduction -->
                                <td class="cell-diff" style="white-space:nowrap;">
                                    <?php if (!$canViewFin): ?>
                                        <span>—</span>
                                    <?php elseif ($isEnRoute): ?>
                                        <span style="font-size:11.5px;color:var(--text-3);font-weight:600;">Pending Arrival</span>
                                    <?php elseif ($payoutDiff > 0.01): ?>
                                        <div style="color:var(--red);font-weight:900;font-size:14px;">
                                            -<?= format_money($payoutDiff) ?>
                                        </div>
                                        <div style="font-size:11px;color:var(--red);font-weight:700;">Shortage Loss</div>
                                        <?php if (!empty($d['shortage_notes'])): ?>
                                            <div style="font-size:11px;color:var(--text-3);max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:2px;" title="<?= htmlspecialchars($d['shortage_notes']) ?>">
                                                📝 <?= htmlspecialchars($d['shortage_notes']) ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--green);font-weight:700;font-size:12px;background:var(--green-soft);padding:2px 7px;border-radius:6px;">
                                            ✓ Full (0 Diff)
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Trip Costs (Mileage) -->
                                <td class="cell-costs" style="white-space:nowrap;">
                                    <?php if (!$canViewFin): ?>
                                        <span>[Restricted]</span>
                                    <?php else: ?>
                                        <div class="val-mileage" style="color:var(--amber);font-weight:700;font-size:13.5px;">
                                            <?= format_money($d['mileage_cost']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Net Balance (Profit / Commission) -->
                                <td class="cell-balance" style="white-space:nowrap;font-weight:900;font-size:15px;color:<?= $balanceVal >= 0 ? 'var(--green)' : 'var(--red)' ?>;">
                                    <?php if (!$canViewFin): ?>
                                        <span class="view-val">[Restricted]</span>
                                    <?php else: ?>
                                        <span class="view-val"><?= format_money($balanceVal) ?></span>
                                        <small style="display:block;font-size:11px;color:<?= $isEnRoute ? 'var(--amber)' : ($isContract ? 'var(--amber)' : 'var(--green)') ?>;font-weight:700;">
                                            <?= $isEnRoute ? '⏳ Provisional Yield' : ($isContract ? 'Commission' : 'Company Profit') ?>
                                        </small>
                                    <?php endif; ?>
                                </td>

                                <?php if (!empty($metaMap['breakdown_notes']['is_visible'])): ?>
                                    <td style="font-size:12px;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($d['breakdown_notes'] ?? '') ?>"><?= htmlspecialchars($d['breakdown_notes'] ?: '—') ?></td>
                                <?php endif; ?>
                                <?php if (!empty($metaMap['shortage_notes']['is_visible'])): ?>
                                    <td style="font-size:12px;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($d['shortage_notes'] ?? '') ?>"><?= htmlspecialchars($d['shortage_notes'] ?: '—') ?></td>
                                <?php endif; ?>

                                <!-- Custom User-Added Columns -->
                                <?php foreach ($fleetCustomCols as $cc): ?>
                                    <?php
                                        $ck = $cc['column_key'];
                                        $cval = $d[$ck] ?? '';
                                    ?>
                                    <td class="editable-cell" data-id="<?= $d['id'] ?>" data-field="<?= htmlspecialchars($ck) ?>" data-type="<?= htmlspecialchars($cc['data_type']) ?>" title="Double-click to inline edit">
                                        <span class="view-val"><?= htmlspecialchars($cval !== '' ? $cval : '—') ?></span>
                                    </td>
                                <?php endforeach; ?>

                                <!-- Actions: View Details, Offload Delivery, Inline Edit, Manage Expenses, Delete -->
                                <td class="cell-actions" style="text-align:center;white-space:nowrap;">
                                    <div class="row-normal-actions" style="display:inline-flex;gap:5px;align-items:center;">
                                        <button type="button" class="btn btn-sm btn-ghost" style="color:var(--brand);font-weight:800;padding:4px 8px;border:1px solid var(--border);" onclick="openViewDispatchModal(<?= $d['id'] ?>)" title="View Complete Dispatch Details">
                                            👁️ Details
                                        </button>
                                        <?php if ($isEnRoute): ?>
                                            <button type="button" class="btn btn-sm btn-brand" style="padding:4px 9px;font-size:12px;font-weight:800;" onclick="openConfirmDeliveryModal(<?= htmlspecialchars(json_encode([
                                                'id' => $d['id'],
                                                'trip_number' => $d['trip_number'],
                                                'truck' => $d['truck'],
                                                'driver' => $d['driver'],
                                                'destination' => $d['destination'],
                                                'client' => $d['client_name'] ?? 'Regional Consignee',
                                                'loaded_litres' => $loaded,
                                                'transport_amount' => $expectedTransport,
                                                'transport_formatted' => format_money($expectedTransport),
                                            ])) ?>)" title="Truck arrived at client: Record received litres and final payout">
                                                📦 Offload
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-ghost" onclick="startFleetInlineEdit(<?= $d['id'] ?>)" title="Edit this fleet record directly on table">
                                            ✏️ Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-ghost" style="color:var(--amber);" onclick="openTripExpenseModal(<?= htmlspecialchars(json_encode([
                                            'id' => $d['id'],
                                            'trip_number' => $d['trip_number'],
                                            'truck' => $d['truck'],
                                            'mileage_cost' => (float)$d['mileage_cost'],
                                            'extra_expenses' => (float)$d['extra_expenses'],
                                            'diesel' => (float)($d['diesel'] ?? 0),
                                            'breakdown_notes' => $d['breakdown_notes'] ?? '',
                                        ])) ?>)" title="Manage trip mileage, diesel, and breakdown repair expenses">
                                            🛠️ Expense
                                        </button>
                                        <form method="POST" action="<?= url('fleet/delete/' . $d['id']) ?>" style="display:inline;" onsubmit="return confirm('Delete fleet dispatch <?= htmlspecialchars($d['trip_number']) ?>?');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--red);border-color:transparent;padding:4px 7px;" title="Delete Record">✕</button>
                                        </form>
                                    </div>
                                    <div class="row-editing-actions" style="display:none;gap:6px;align-items:center;">
                                        <button type="button" class="row-save-btn" onclick="saveFleetInlineEdit(<?= $d['id'] ?>)" title="Save Changes">✓ Save</button>
                                        <button type="button" class="row-cancel-btn" onclick="cancelFleetInlineEdit(<?= $d['id'] ?>)" title="Cancel">✕</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Table Pagination Footer -->
        <div class="table-pagination-footer" id="fleetPagination"></div>
    </div>
</section>

<style>
/* Procedural Wizard Stepper Styles */
.wizard-stepper {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
    margin-bottom: 20px;
    background: var(--card-2);
    padding: 6px;
    border-radius: 12px;
    border: 1.5px solid var(--border);
}
.wizard-step-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 8px;
    cursor: pointer;
    transition: .18s;
    user-select: none;
}
.wizard-step-indicator .step-num {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: var(--border-2);
    color: var(--text-2);
    display: grid;
    place-items: center;
    font-size: 11.5px;
    font-weight: 800;
    transition: .18s;
}
.wizard-step-indicator .step-lbl {
    font-size: 12.5px;
    font-weight: 700;
    color: var(--text-3);
    white-space: nowrap;
    transition: .18s;
}
.wizard-step-indicator.active {
    background: var(--card);
    box-shadow: var(--shadow);
}
.wizard-step-indicator.active .step-num {
    background: var(--brand);
    color: #fff;
    box-shadow: 0 2px 8px rgba(79,70,229,0.35);
}
.wizard-step-indicator.active .step-lbl {
    color: var(--brand);
    font-weight: 800;
}
.wizard-step-indicator.completed .step-num {
    background: var(--green-soft);
    color: var(--green);
    border: 1px solid var(--green);
}
.wizard-step-indicator.completed .step-lbl {
    color: var(--text);
}
@media (max-width: 768px) {
    .wizard-stepper { grid-template-columns: repeat(2, 1fr); }
}
</style>

<!-- New Dispatch Procedural Wizard Modal -->
<div class="modal-backdrop" id="dispatchModal">
    <div class="modal-card" style="max-width:760px;">
        <div class="modal-head" style="margin-bottom:14px;">
            <div>
                <h2 style="font-size:20px;display:flex;align-items:center;gap:8px;">
                    <span>🚚</span>
                    <span>New Fleet Dispatch Wizard</span>
                </h2>
                <div style="font-size:12.5px;color:var(--text-3);margin-top:2px;">
                    Guided 4-step workflow: Vehicle crew, cargo specs, route & diesel fuel, and financial review.
                </div>
            </div>
            <button type="button" class="close-modal" onclick="closeProceduralDispatchModal()">✕</button>
        </div>

        <!-- Wizard Step Progress Stepper Bar -->
        <div class="wizard-stepper">
            <div class="wizard-step-indicator active" id="stepPill1" onclick="goToWizardStep(1)">
                <span class="step-num">1</span>
                <span class="step-lbl">Tanker & Crew</span>
            </div>
            <div class="wizard-step-indicator" id="stepPill2" onclick="goToWizardStep(2)">
                <span class="step-num">2</span>
                <span class="step-lbl">Cargo & Client</span>
            </div>
            <div class="wizard-step-indicator" id="stepPill3" onclick="goToWizardStep(3)">
                <span class="step-num">3</span>
                <span class="step-lbl">Route & Fuel</span>
            </div>
            <div class="wizard-step-indicator" id="stepPill4" onclick="goToWizardStep(4)">
                <span class="step-num">4</span>
                <span class="step-lbl">Review & Launch</span>
            </div>
        </div>

        <form method="POST" action="<?= url('fleet/store') ?>" id="dispatchWizardForm" novalidate onsubmit="return onWizardFormSubmit(event)">
            <?= csrf_field() ?>

            <!-- STEP 1: Tanker Truck & Driver Crew -->
            <div class="wizard-step-panel" id="wizardStep1">
                <div style="background:var(--card-2);border-left:3px solid var(--brand);padding:10px 14px;border-radius:8px;margin-bottom:16px;">
                    <b style="font-size:13.5px;color:var(--text);">Step 1: Vehicle & Driver Assignment (Trip Departure)</b>
                    <div style="font-size:12px;color:var(--text-3);margin-top:2px;">Choose loading date, trip reference number, assigned tanker truck and driver.</div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>DOL (Date of Loading) *</label>
                        <input type="date" name="dispatch_date" id="wizardDispatchDate" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Trip Reference (Optional)</label>
                        <input type="text" name="trip_number" id="wizardTripNumber" placeholder="Auto e.g. TRP-<?= date('Y') ?>-015">
                    </div>

                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Select Tanker Truck *</label>
                        <select name="truck" id="dispatchTruckSelect" required onchange="onTruckSelected()">
                            <option value="">-- Choose Tanker Truck --</option>
                            <?php foreach ($trucks as $trk): ?>
                                <?php $isContractTruck = in_array(strtolower($trk['ownership_type'] ?? ''), ['contract', 'subcontracted', 'sub']); ?>
                                <option value="<?= htmlspecialchars($trk['plate_number']) ?>" 
                                    data-capacity="<?= (int) $trk['capacity_litres'] ?>"
                                    data-subcontracted="<?= $isContractTruck ? '1' : '0' ?>"
                                    data-owner="<?= htmlspecialchars($trk['owner_name'] ?? 'Sarura Fuel') ?>"
                                    data-commission="<?= (float) ($trk['commission_rate'] ?? 0) ?>">
                                    <?= htmlspecialchars($trk['plate_number']) ?> (<?= number_format($trk['capacity_litres']) ?> L) 
                                    <?= $isContractTruck ? '— [Contract Tanker]' : '— [Owner Fleet]' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Truck Ownership Notice -->
                    <div id="truckOwnershipNotice" style="display:none;grid-column:1/-1;padding:12px 16px;border-radius:10px;background:var(--amber-soft);border:1px solid var(--amber);font-size:13px;color:var(--text);">
                        <b style="color:var(--amber);">⚠️ Contract Haulier Truck Detected:</b>
                        <div id="subcontractedDetailsText" style="margin-top:2px;">
                            Only the agreed company commission will act as profit for this vehicle.
                        </div>
                    </div>

                    <input type="hidden" name="truck_capacity" id="dispatchTruckCapacity" value="0">

                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Loading Depot (Origin) *</label>
                        <input type="text" name="from_location" id="wizardFromLocation" value="Eldoret" required placeholder="e.g. Eldoret Depot / Kisumu">
                    </div>

                    <div class="form-group" style="grid-column:1/-1;">
                        <label style="display:flex;justify-content:space-between;align-items:center;">
                            <span style="font-weight:700;">Assigned Driver *</span>
                            <span style="font-size:12px;font-weight:400;color:var(--text-3);">Pick from roster or type any driver's name</span>
                        </label>
                        <div style="position:relative;">
                            <input type="text" name="driver" id="dispatchDriverInput" list="driversList" required 
                                placeholder="Type or select driver name..." 
                                autocomplete="off"
                                oninput="if(document.getElementById('otherDriverInput')) document.getElementById('otherDriverInput').value = this.value;"
                                style="width:100%;padding:10px 14px;font-size:14px;border:1.5px solid var(--border-2);border-radius:10px;background:var(--card);color:var(--text);font-weight:700;">
                            <datalist id="driversList">
                                <?php foreach ($drivers as $drv): ?>
                                    <option value="<?= htmlspecialchars($drv['name']) ?>"><?= htmlspecialchars($drv['name']) ?> (<?= htmlspecialchars($drv['status']) ?>)</option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <input type="hidden" name="other_driver_name" id="otherDriverInput" value="">
                        <small style="color:var(--text-3);display:block;margin-top:4px;">
                            💡 You can select an existing driver from the dropdown or directly key in any new/relief driver name.
                        </small>
                    </div>
                </div>

                <div style="margin-top:22px;display:flex;justify-content:space-between;align-items:center;">
                    <button type="button" class="btn btn-ghost" onclick="closeProceduralDispatchModal()">Cancel</button>
                    <button type="button" class="btn btn-brand" onclick="goToWizardStep(2)">Next: Cargo & Consignee →</button>
                </div>
            </div>

            <!-- STEP 2: Fuel Cargo & Consignee Customer -->
            <div class="wizard-step-panel" id="wizardStep2" style="display:none;">
                <div style="background:var(--card-2);border-left:3px solid var(--green);padding:10px 14px;border-radius:8px;margin-bottom:16px;">
                    <b style="font-size:13.5px;color:var(--text);">Step 2: Cargo Hauled, Actual @ L20 & Agreed Transport Payment</b>
                    <div style="font-size:12px;color:var(--text-3);margin-top:2px;">Cargo type, actual litres carried (@ L20), agreed transportation payment in USD ($), and destination.</div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Product Carried (Cargo Type) *</label>
                        <select name="product" id="dispatchProductSelect" required>
                            <?php foreach ($products as $pr): ?>
                                <option value="<?= htmlspecialchars($pr['code']) ?>">
                                    <?= htmlspecialchars($pr['code']) ?> — <?= htmlspecialchars($pr['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span style="font-size:11.5px;color:var(--text-3);display:block;margin-top:2px;">Fuel product being transported</span>
                    </div>

                    <div class="form-group">
                        <label>Actual Litres Carried (@ L20) *</label>
                        <input type="number" name="loaded_litres" id="dispatchLoadedLitres" placeholder="Volume loaded into tanker" required oninput="calcExpectedTransport()">
                        <span style="font-size:11.5px;color:var(--text-3);display:block;margin-top:2px;">Litres loaded into tanker (Actual @ L20)</span>
                    </div>

                    <div class="form-group">
                        <label>Agreed Transport Payment [USD $] *</label>
                        <div style="position:relative;">
                            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-weight:800;color:var(--brand);font-size:14px;">$</span>
                            <input type="number" step="0.01" name="transport_amount_usd" id="dispatchTransportUsd" placeholder="e.g. 2400.00" required oninput="onTransportUsdInput(this.value)" style="width:100%;padding:9px 10px 9px 28px;border:1.5px solid var(--brand);border-radius:8px;font-weight:800;font-size:15px;color:var(--brand);background:var(--card);">
                        </div>
                        <span style="font-size:11.5px;color:var(--text-3);display:block;margin-top:2px;">Agreed haulier payment for this trip</span>
                    </div>

                    <div class="form-group">
                        <label>Agreed Transport in KSh (KES)</label>
                        <div style="position:relative;">
                            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-weight:800;color:var(--amber);font-size:12px;">KES</span>
                            <input type="number" step="1" id="dispatchTransportKes" placeholder="0" oninput="onTransportKesInput(this.value)" style="width:100%;padding:9px 10px 9px 44px;border:1.5px solid var(--amber);border-radius:8px;font-weight:700;font-size:15px;color:var(--amber);background:var(--card);">
                        </div>
                        <span style="font-size:11.5px;color:var(--text-3);display:block;margin-top:2px;">Live sync: 1 USD = <?= exchange_rate() ?> KES</span>
                    </div>

                    <!-- Hidden system fields for database compatibility -->
                    <input type="hidden" name="transport_amount" id="dispTransport" value="0.00">
                    <input type="hidden" name="unit_price" id="dispatchUnitPrice" value="0.00">

                    <!-- Dynamic Live Expected Transport Display Banner -->
                    <div id="dispTransportLiveCard" style="grid-column:1/-1;background:linear-gradient(135deg, var(--card), var(--brand-soft));border:1.5px solid var(--brand);border-radius:10px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-top:4px;">
                        <div>
                            <div style="font-size:11px;font-weight:800;color:var(--brand);text-transform:uppercase;letter-spacing:0.5px;">Agreed Transportation Payment (Trip Revenue)</div>
                            <div id="dispTransportFormulaText" style="font-size:13px;color:var(--text-2);margin-top:2px;">Fixed agreed transportation haulier fee</div>
                        </div>
                        <div style="text-align:right;">
                            <div id="dispTransportBigDisplay" style="font-size:22px;font-weight:900;color:var(--brand);">
                                $ 0.00
                            </div>
                            <div id="dispTransportAltDisplay" style="font-size:12px;color:var(--text-3);font-weight:700;">≈ KES 0.00</div>
                        </div>
                    </div>

                    <!-- Hidden Shortage & Status -->
                    <input type="hidden" name="shortage_litres" id="dispatchShortageLitres" value="0">
                    <input type="hidden" name="final_payout" id="dispFinalPayout" value="">
                    <input type="hidden" name="status" value="In Transit">

                    <div class="form-group">
                        <label>Destination Country / City *</label>
                        <input list="destPresets" name="destination" id="wizardDestination" placeholder="e.g. Uganda (Kampala), DR Congo (Goma)" required onchange="onDestinationChanged(this.value)">
                        <datalist id="destPresets">
                            <?php foreach ($mileageRates as $mr): ?>
                                <option value="<?= htmlspecialchars($mr['destination']) ?>" data-kes="<?= (float)$mr['standard_allowance_kes'] ?>" data-usd="<?= (float)$mr['standard_allowance_usd'] ?>" data-km="<?= (int)$mr['distance_km'] ?>">
                                    <?= htmlspecialchars($mr['destination']) ?> (<?= number_format($mr['distance_km']) ?> km • KES <?= number_format($mr['standard_allowance_kes']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </datalist>
                        <span style="font-size:11.5px;color:var(--text-3);display:block;margin-top:2px;">Corridor selection auto-suggests driver mileage allowance in Step 3</span>
                    </div>

                    <div class="form-group">
                        <label>👤 Client / Consignee Name (Optional)</label>
                        <input type="text" list="customersList" name="client_name" id="dispatchClientName" placeholder="e.g. TotalEnergies Uganda Ltd (or leave blank)" style="width:100%;">
                    </div>
                </div>

                <div style="margin-top:22px;display:flex;justify-content:space-between;align-items:center;">
                    <button type="button" class="btn btn-ghost" onclick="goToWizardStep(1)">← Back to Tanker & Crew</button>
                    <button type="button" class="btn btn-brand" onclick="goToWizardStep(3)">Next: Route & Fuel →</button>
                </div>
            </div>

            <!-- STEP 3: Route Allowances & Diesel Fueling -->
            <div class="wizard-step-panel" id="wizardStep3" style="display:none;">
                <div style="background:var(--card-2);border-left:3px solid var(--amber);padding:10px 14px;border-radius:8px;margin-bottom:16px;">
                    <b style="font-size:13.5px;color:var(--text);">Step 3: Route Allowances & Diesel Fueling (Owner Expense)</b>
                    <div style="font-size:12px;color:var(--text-3);margin-top:2px;">Driver en-route mileage allowance and departure diesel with multi-currency conversion.</div>
                </div>

                <div class="form-grid">
                    <!-- Driver Mileage Dual-Currency Card (KSh & USD) -->
                    <div style="grid-column:1/-1;background:var(--card);border:1.5px solid var(--border);border-radius:12px;padding:16px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                            <div>
                                <b style="font-size:13.5px;color:var(--text);display:flex;align-items:center;gap:6px;">
                                    <span>🛣️</span>
                                    <span>Driver Mileage & Transit Allowance</span>
                                </b>
                                <div style="font-size:12px;color:var(--text-3);margin-top:2px;">
                                    Per diem, toll fees & allowances paid in KSh (KES) with real-time Dollar equivalent.
                                </div>
                            </div>
                            <span style="font-size:11.5px;background:var(--card-2);padding:4px 10px;border-radius:6px;border:1px solid var(--border);font-weight:700;color:var(--text-2);">
                                1 USD = <?= exchange_rate() ?> KES
                            </span>
                        </div>

                        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:14px;">
                            <div>
                                <label style="font-size:12px;font-weight:700;color:var(--amber);display:block;margin-bottom:4px;">
                                    🇰🇪 Amount in Kenya Shillings (KSh / KES) *
                                </label>
                                <div style="position:relative;">
                                    <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-weight:800;color:var(--amber);font-size:12px;">KES</span>
                                    <input type="number" step="1" id="dispMileageKes" placeholder="e.g. 50000" oninput="onMileageKesInput(this.value)" style="width:100%;padding:9px 10px 9px 44px;border:1.5px solid var(--amber);border-radius:8px;font-weight:800;font-size:15px;color:var(--amber);background:var(--card);">
                                </div>
                                <span style="font-size:11px;color:var(--text-3);display:block;margin-top:3px;">Cash / M-Pesa allowance handed to driver</span>
                            </div>

                            <div>
                                <label style="font-size:12px;font-weight:700;color:var(--brand);display:block;margin-bottom:4px;">
                                    💵 Base Dollar Equivalent (USD $) *
                                </label>
                                <div style="position:relative;">
                                    <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-weight:800;color:var(--brand);font-size:13px;">$</span>
                                    <input type="number" step="0.01" id="dispMileageUsd" placeholder="0.00" oninput="onMileageUsdInput(this.value)" style="width:100%;padding:9px 10px 9px 28px;border:1.5px solid var(--brand);border-radius:8px;font-weight:800;font-size:15px;color:var(--brand);background:var(--card);">
                                </div>
                                <span style="font-size:11px;color:var(--text-3);display:block;margin-top:3px;">Accounting base cost deducted from trip yield</span>
                            </div>
                        </div>

                        <!-- Real-time Dual Badge Banner -->
                        <div style="margin-top:12px;padding:10px 14px;background:var(--card-2);border-radius:8px;display:flex;justify-content:space-between;align-items:center;font-size:13px;">
                            <span style="color:var(--text-2);">Active Deduction:</span>
                            <div style="font-weight:800;">
                                <span id="mileageBadgeKes" style="color:var(--amber);">KES 0.00</span>
                                <span style="color:var(--text-3);margin:0 6px;">⇄</span>
                                <span id="mileageBadgeUsd" style="color:var(--brand);">$ 0.00</span>
                            </div>
                        </div>

                        <!-- Hidden synced field submitted to backend -->
                        <input type="hidden" name="mileage_cost" id="dispMileage" value="0.00">
                    </div>

                    <!-- Departure Diesel Fueling Card -->
                    <div style="grid-column:1/-1;background:rgba(245,158,11,0.06);border:1.5px solid var(--amber);border-radius:12px;padding:16px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="font-size:18px;">⛽</span>
                                <b style="font-size:14px;color:var(--amber);">Departure Diesel Fueling (Owner's Expense)</b>
                            </div>
                            <label style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:700;cursor:pointer;">
                                <input type="checkbox" id="wizardEnableDepartureDiesel" checked onchange="toggleWizardDepartureDiesel(this.checked)">
                                <span>Record departure top-up now</span>
                            </label>
                        </div>

                        <div id="wizardDepartureFuelInputs">
                            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:12px;margin-bottom:12px;">
                                <div>
                                    <label style="font-size:11.5px;font-weight:800;color:var(--text);display:block;margin-bottom:4px;">📍 Refueling Country *</label>
                                    <select id="wzDieselCountry" onchange="onWizardDieselCountryChange()" style="width:100%;padding:8px;border:1.5px solid var(--border-2);border-radius:8px;background:var(--card);font-weight:700;">
                                        <option value="Kenya" data-currency="KES" data-rate="<?= exchange_rate() ?>" data-flag="🇰🇪" selected>🇰🇪 Kenya</option>
                                        <option value="Uganda" data-currency="UGX" data-rate="3750" data-flag="🇺🇬">🇺🇬 Uganda</option>
                                        <option value="DR Congo" data-currency="USD" data-rate="1.0" data-flag="🇨🇩">🇨🇩 DR Congo</option>
                                        <option value="South Sudan" data-currency="SSP" data-rate="1300" data-flag="🇸🇸">🇸🇸 South Sudan</option>
                                        <option value="Rwanda" data-currency="RWF" data-rate="1350" data-flag="🇷🇼">🇷🇼 Rwanda</option>
                                        <option value="Tanzania" data-currency="TZS" data-rate="2600" data-flag="🇹🇿">🇹🇿 Tanzania</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-size:11.5px;font-weight:700;color:var(--text-2);display:block;margin-bottom:4px;">Departure Station / Location</label>
                                    <input type="text" name="diesel_station" id="wzDieselStation" value="Eldoret KPC Depot Shell" placeholder="e.g. Shell Eldoret" style="width:100%;padding:8px;border:1px solid var(--border-2);border-radius:8px;">
                                </div>
                                <div>
                                    <label style="font-size:11.5px;font-weight:700;color:var(--text-2);display:block;margin-bottom:4px;">Litres Pumped *</label>
                                    <input type="number" step="0.01" id="wzDieselLitres" placeholder="e.g. 400" value="400" oninput="calcWizardDiesel()" style="width:100%;padding:8px;border:1px solid var(--border-2);border-radius:8px;font-weight:700;">
                                </div>
                                <div>
                                    <label style="font-size:11.5px;font-weight:800;color:var(--amber);display:block;margin-bottom:4px;">🇰🇪 Price in KSh (KES) *</label>
                                    <div style="position:relative;">
                                        <span style="position:absolute;left:8px;top:50%;transform:translateY(-50%);font-weight:800;color:var(--amber);font-size:11px;">KES</span>
                                        <input type="number" step="0.01" id="wzDieselKesPrice" placeholder="180.00" value="180.00" oninput="onWizardDieselKesInput(this.value)" style="width:100%;padding:8px 8px 8px 36px;border:1.5px solid var(--amber);border-radius:8px;font-weight:800;color:var(--amber);background:var(--card);">
                                    </div>
                                </div>
                                <div>
                                    <label style="font-size:11.5px;font-weight:800;color:var(--brand);display:block;margin-bottom:4px;">💵 Price in USD ($) *</label>
                                    <div style="position:relative;">
                                        <span style="position:absolute;left:8px;top:50%;transform:translateY(-50%);font-weight:800;color:var(--brand);font-size:12px;">$</span>
                                        <input type="number" step="0.0001" id="wzDieselUsdPrice" placeholder="1.38" value="" oninput="onWizardDieselUsdInput(this.value)" style="width:100%;padding:8px 8px 8px 24px;border:1.5px solid var(--brand);border-radius:8px;font-weight:800;color:var(--brand);background:var(--card);">
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" id="wzDieselExRate" value="<?= exchange_rate() ?>">
                            <input type="hidden" id="wzDieselLocalPrice" value="180.00">

                            <!-- Live Multi-Currency Conversion Card -->
                            <div style="background:var(--card);border:1px solid var(--border);border-radius:8px;padding:12px 14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                                <div>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <span style="font-size:11px;font-weight:800;color:var(--text-3);text-transform:uppercase;">Departure Refueling Cost:</span>
                                        <span id="wzDieselCountryBadge" style="font-size:11px;font-weight:800;color:var(--amber);background:rgba(245,158,11,0.1);padding:2px 8px;border-radius:4px;border:1px solid rgba(245,158,11,0.3);">📍 🇰🇪 Kenya</span>
                                    </div>
                                    <div style="font-size:13px;color:var(--text-2);margin-top:2px;">
                                        Payment in KSh: <b id="wzDieselKesTotalDisplay" style="color:var(--amber);">KES 72,000.00</b> • Base USD: <b id="wzDieselUsdDisplay" style="color:var(--brand);">$ 553.85</b>
                                    </div>
                                </div>
                                <div>
                                    <div style="font-size:11px;font-weight:800;color:var(--amber);text-transform:uppercase;text-align:right;">Trip Deduction (<?= app_currency_symbol() ?>):</div>
                                    <div id="wzDieselSysDisplay" style="font-size:18px;font-weight:900;color:var(--amber);text-align:right;">
                                        <?= app_currency_symbol() ?> 72,000.00
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="wizardDepartureFuelDisabledNotice" style="display:none;padding:10px;text-align:center;color:var(--text-3);font-size:12.5px;">
                            ℹ️ Departure fueling skipped. You can record en-route fuel stops anytime using the <b>⛽ Log Diesel</b> button next to New Dispatch.
                        </div>

                        <!-- Hidden Inputs synced for database -->
                        <input type="hidden" name="diesel_litres" id="dispDieselLitres" value="400">
                        <input type="hidden" name="diesel_unit_price" id="dispDieselUnitPrice" value="180">
                        <input type="hidden" name="diesel" id="dispDiesel" value="72000">
                        <input type="hidden" name="diesel_country" id="wzDieselCountryHidden" value="Kenya">
                        <input type="hidden" name="diesel_currency" id="wzDieselCurrencyHidden" value="KES">
                        <input type="hidden" name="diesel_exchange_rate" id="wzDieselExRateHidden" value="<?= exchange_rate() ?>">
                        <input type="hidden" name="diesel_local_unit_price" id="wzDieselLocalPriceHidden" value="180">
                    </div>

                    <div class="form-group" id="commissionGroup" style="display:none;grid-column:1/-1;">
                        <label>Agreed Company Commission [<?= app_currency_symbol() ?>] *</label>
                        <input type="number" step="0.01" name="agreed_commission" id="dispCommission" placeholder="e.g. 350.00" oninput="calcBalance()">
                    </div>
                </div>

                <div style="margin-top:22px;display:flex;justify-content:space-between;align-items:center;">
                    <button type="button" class="btn btn-ghost" onclick="goToWizardStep(2)">← Back to Cargo & Consignee</button>
                    <button type="button" class="btn btn-brand" onclick="goToWizardStep(4)">Next: Review & Launch →</button>
                </div>
            </div>

            <!-- STEP 4: Review, Financial Yield & Launch Dispatch -->
            <div class="wizard-step-panel" id="wizardStep4" style="display:none;">
                <div style="background:var(--card-2);border-left:3px solid var(--green);padding:10px 14px;border-radius:8px;margin-bottom:16px;">
                    <b style="font-size:13.5px;color:var(--text);">Step 4: Final Trip Review & Launch Confirmation</b>
                    <div style="font-size:12px;color:var(--text-3);margin-top:2px;">Review trip assignment, billed revenue vs owner expenses, and launch the dispatch record.</div>
                </div>

                <!-- Executive Briefing Card -->
                <div style="background:var(--card-2);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:16px;">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:12px;">
                        <div>
                            <div style="font-size:11px;font-weight:800;color:var(--text-3);text-transform:uppercase;">Trip & Vehicle</div>
                            <div id="wzRevTripTruck" style="font-weight:800;font-size:14px;color:var(--text);margin-top:2px;">—</div>
                            <div id="wzRevDriver" style="font-size:12px;color:var(--text-2);margin-top:1px;">—</div>
                        </div>
                        <div>
                            <div style="font-size:11px;font-weight:800;color:var(--text-3);text-transform:uppercase;">Route & Consignee</div>
                            <div id="wzRevRoute" style="font-weight:800;font-size:14px;color:var(--brand);margin-top:2px;">—</div>
                            <div id="wzRevClient" style="font-size:12px;color:var(--text-2);margin-top:1px;">—</div>
                        </div>
                        <div>
                            <div style="font-size:11px;font-weight:800;color:var(--text-3);text-transform:uppercase;">Fuel Cargo Volume</div>
                            <div id="wzRevCargo" style="font-weight:800;font-size:14px;color:var(--text);margin-top:2px;">—</div>
                            <div id="wzRevDate" style="font-size:12px;color:var(--text-3);margin-top:1px;">—</div>
                        </div>
                    </div>
                </div>

                <!-- Financial Yield Audit Grid -->
                <div style="background:var(--card);border:1.5px solid var(--border);border-radius:12px;padding:16px;margin-bottom:16px;">
                    <div style="font-size:11.5px;font-weight:800;color:var(--text-3);text-transform:uppercase;margin-bottom:10px;">Financial Yield Breakdown</div>
                    
                    <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:13.5px;">
                        <span style="color:var(--text-2);">Expected Transport Billed (Gross Revenue):</span>
                        <b id="wzRevRevenue" style="color:var(--green);">—</b>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:13.5px;border-top:1px dashed var(--border);">
                        <span style="color:var(--text-2);">Driver Mileage Allowance:</span>
                        <b id="wzRevMileage" style="color:var(--amber);">—</b>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:13.5px;border-top:1px dashed var(--border);">
                        <span style="color:var(--text-2);">⛽ Diesel Fuel (Owner Expense):</span>
                        <b id="wzRevDiesel" style="color:var(--amber);">—</b>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0 4px;border-top:2px solid var(--border);margin-top:6px;">
                        <div>
                            <b style="font-size:14px;color:var(--text);" id="wzRevYieldLabel">Estimated Net Balance (Profit / Commission):</b>
                            <div style="font-size:11.5px;color:var(--text-3);">Revenue - (Diesel + Mileage)</div>
                        </div>
                        <div id="wzRevBalance" style="font-size:24px;font-weight:900;color:var(--green);">
                            —
                        </div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Security Seal Numbers (Optional)</label>
                        <input type="text" name="seal_numbers" placeholder="e.g. KPC-89211, KPC-89212">
                    </div>
                    <div class="form-group">
                        <label>Trip Instructions / Notes (Optional)</label>
                        <input type="text" name="shortage_notes" placeholder="e.g. Escort required at Malaba customs">
                    </div>
                </div>

                <div style="margin-top:16px;padding:12px 14px;background:var(--brand-soft);border:1px solid var(--brand);border-radius:10px;font-size:12.5px;color:var(--text);">
                    ℹ️ <b>Ready to Dispatch:</b> Clicking <b>"🚀 Launch & Create Dispatch"</b> will register this trip into Fleet Management, generate electronic BOL documents, and update the Trips Board.
                </div>

                <div style="margin-top:22px;display:flex;justify-content:space-between;align-items:center;">
                    <button type="button" class="btn btn-ghost" onclick="goToWizardStep(3)">← Back to Route & Fuel</button>
                    <button type="submit" class="btn btn-brand" style="font-size:15px;padding:10px 24px;box-shadow:0 4px 16px rgba(79,70,229,0.4);">
                        🚀 Launch & Create Dispatch
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Dedicated Cross-Border Diesel Fueling Modal -->
<div class="modal-backdrop" id="dieselLogModal">
    <div class="modal-card" style="max-width:680px;">
        <div class="modal-head">
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:24px;background:var(--amber-soft);padding:6px;border-radius:10px;">⛽</span>
                <div>
                    <h2 style="margin:0;font-size:19px;">Log Cross-Border Diesel Fueling</h2>
                    <div style="font-size:12.5px;color:var(--text-3);margin-top:2px;">Kenya, Uganda & DR Congo en-route fuel stops (Owner Expense)</div>
                </div>
            </div>
            <button type="button" class="close-modal" onclick="document.getElementById('dieselLogModal').classList.remove('active')">✕</button>
        </div>

        <form id="dieselLogForm" onsubmit="return handleDieselLogSubmit(event)">
            <?= csrf_field() ?>
            <input type="hidden" name="edit_log_id" id="dlmEditLogId" value="">

            <div id="dlmFeedbackBanner" style="display:none;padding:10px 14px;background:var(--green-soft);border:1px solid var(--green);border-radius:10px;margin-bottom:14px;color:var(--green);font-weight:700;font-size:13px;"></div>

            <div id="dlmEditModeBanner" style="display:none;padding:10px 14px;background:var(--brand-soft);border:1.5px solid var(--brand);border-radius:10px;margin-bottom:14px;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                    <div>
                        <b id="dlmEditModeTitle" style="color:var(--brand);font-size:13.5px;">✏️ Editing Fuel Stop</b>
                        <div style="font-size:12px;color:var(--text-2);margin-top:1px;">Modify litres, pump price, exchange rate, or station. Dispatch totals will automatically recalculate.</div>
                    </div>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="cancelDieselLogEdit(false)" style="color:var(--red);border:1px solid var(--red);font-weight:700;">✕ Cancel Edit</button>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group" style="grid-column:1/-1;">
                    <label>Select Dispatch Trip *</label>
                    <select name="dispatch_id" id="dlmDispatchSelect" required onchange="onDlmDispatchChanged(this.value)">
                        <option value="">-- Choose Active Dispatch --</option>
                        <?php foreach ($dispatches as $disp): ?>
                            <option value="<?= $disp['id'] ?>" 
                                data-trip="<?= htmlspecialchars($disp['trip_number']) ?>"
                                data-truck="<?= htmlspecialchars($disp['truck']) ?>"
                                data-destination="<?= htmlspecialchars($disp['destination']) ?>"
                                data-driver="<?= htmlspecialchars($disp['driver']) ?>"
                                data-diesel="<?= (float)$disp['diesel'] ?>">
                                <?= htmlspecialchars($disp['trip_number']) ?> • <?= htmlspecialchars($disp['truck']) ?> (<?= htmlspecialchars($disp['destination']) ?>) — <?= htmlspecialchars($disp['driver']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Date of Fueling *</label>
                    <input type="date" name="fuel_date" id="dlmFuelDate" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label style="font-weight:800;color:var(--text);">📍 Refueling Country *</label>
                    <select name="country" id="dlmCountrySelect" required onchange="onDlmCountryChange()" style="font-weight:700;">
                        <option value="Uganda" data-currency="UGX" data-rate="3750" data-flag="🇺🇬">🇺🇬 Uganda (UGX)</option>
                        <option value="Kenya" data-currency="KES" data-rate="<?= exchange_rate() ?>" data-flag="🇰🇪" selected>🇰🇪 Kenya (KES)</option>
                        <option value="DR Congo" data-currency="USD" data-rate="1.0" data-local-cur="CDF" data-local-rate="2850" data-flag="🇨🇩">🇨🇩 DR Congo (USD / CDF)</option>
                        <option value="South Sudan" data-currency="SSP" data-rate="1300" data-flag="🇸🇸">🇸🇸 South Sudan (SSP)</option>
                        <option value="Rwanda" data-currency="RWF" data-rate="1350" data-flag="🇷🇼">🇷🇼 Rwanda (RWF)</option>
                        <option value="Tanzania" data-currency="TZS" data-rate="2600" data-flag="🇹🇿">🇹🇿 Tanzania (TZS)</option>
                    </select>
                    <input type="hidden" name="currency_code" id="dlmCurrencyCode" value="KES">
                </div>

                <div class="form-group">
                    <label style="font-weight:800;color:var(--text);display:flex;justify-content:space-between;align-items:center;">
                        <span>Exchange Rate (1 USD = ? KES) *</span>
                        <span style="font-size:11px;color:var(--brand);cursor:pointer;font-weight:700;" onclick="resetDlmRate()" title="Reset to current default">↺ Reset Rate</span>
                    </label>
                    <div style="position:relative;">
                        <input type="number" step="0.0001" min="0.0001" name="exchange_rate" id="dlmExchangeRate" value="<?= exchange_rate() ?>" oninput="onDlmRateInput(this.value)" style="width:100%;padding:9px 50px 9px 12px;border:1.5px solid var(--border-2);border-radius:8px;font-weight:800;font-size:13.5px;color:var(--text);background:var(--card);" required>
                        <span id="dlmRateCurrencyCode" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);font-weight:800;color:var(--text-3);font-size:12px;">KES</span>
                    </div>
                    <small style="color:var(--text-3);font-size:11px;">Dynamic transaction rate saved per fuel stop</small>
                </div>

                <div class="form-group" style="grid-column:1/-1;">
                    <div style="background:var(--card-2);padding:10px 14px;border-radius:10px;border:1.5px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                        <span style="font-size:12px;font-weight:800;color:var(--text);text-transform:uppercase;letter-spacing:0.5px;">💰 Payment Currency:</span>
                        <div style="display:flex;gap:16px;align-items:center;">
                            <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:800;cursor:pointer;color:var(--amber);">
                                <input type="radio" name="dlm_pay_mode" id="dlmPayModeKes" value="KES" checked onchange="setDlmPayMode('KES')">
                                <span>🇰🇪 Pay in KSh (KES)</span>
                            </label>
                            <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:800;cursor:pointer;color:var(--brand);">
                                <input type="radio" name="dlm_pay_mode" id="dlmPayModeUsd" value="USD" onchange="setDlmPayMode('USD')">
                                <span>💵 Pay in USD ($)</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="grid-column:1/-1;">
                    <label>Fuel Station / Road Location (Optional)</label>
                    <input type="text" name="station_location" id="dlmStationLocation" placeholder="e.g. Total Malaba Border, Shell Jinja Road, Goma Depot Station (or leave blank)">
                </div>

                <div class="form-group">
                    <label style="font-weight:800;">Litres Pumped *</label>
                    <div style="position:relative;">
                        <input type="number" step="0.01" name="litres" id="dlmLitres" placeholder="e.g. 350" required oninput="calcDlmDiesel()" style="font-weight:800;font-size:14px;padding-right:32px;">
                        <span style="position:absolute;right:12px;top:50%;transform:translateY(-50%);font-weight:800;color:var(--text-3);">L</span>
                    </div>
                </div>

                <!-- Price inputs: KES and USD -->
                <div class="form-group">
                    <label id="dlmPriceHeaderLabel" style="font-weight:800;color:var(--amber);display:flex;justify-content:space-between;">
                        <span>Price per Litre (KSh or USD) *</span>
                        <span id="dlmRateStatusBadge" style="font-size:11px;color:var(--text-3);font-weight:bold;">1 USD = <?= exchange_rate() ?> KES</span>
                    </label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        <div>
                            <div style="position:relative;">
                                <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-weight:800;color:var(--amber);font-size:11px;">KES</span>
                                <input type="number" step="0.01" id="dlmKesPrice" name="kes_unit_price" placeholder="185.00" oninput="onDlmKesInput(this.value)" style="width:100%;padding:9px 8px 9px 38px;border:1.5px solid var(--amber);border-radius:8px;font-weight:800;font-size:13.5px;color:var(--amber);background:var(--card);">
                            </div>
                            <span style="font-size:10.5px;color:var(--text-3);margin-top:2px;display:block;">Price in Kenya Shillings</span>
                        </div>
                        <div>
                            <div style="position:relative;">
                                <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-weight:800;color:var(--brand);font-size:12px;">$</span>
                                <input type="number" step="0.0001" id="dlmUsdPrice" name="usd_unit_price" placeholder="1.40" oninput="onDlmUsdInput(this.value)" style="width:100%;padding:9px 8px 9px 26px;border:1.5px solid var(--brand);border-radius:8px;font-weight:800;font-size:13.5px;color:var(--brand);background:var(--card);">
                            </div>
                            <span style="font-size:10.5px;color:var(--text-3);margin-top:2px;display:block;">Price in US Dollars</span>
                        </div>
                    </div>
                    <input type="hidden" name="entry_currency" id="dlmEntryCurrency" value="KES">
                    <input type="hidden" name="local_unit_price" id="dlmLocalPrice" value="0">
                </div>

                <!-- Live Auto-Calculation Box reflecting Country of Refueling -->
                <div style="grid-column:1/-1;background:var(--card-2);border:1.5px solid var(--border);border-radius:10px;padding:14px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:6px;">
                        <span style="font-size:11px;font-weight:800;color:var(--text-3);text-transform:uppercase;letter-spacing:0.5px;">Auto-Calculated Fueling Breakdown</span>
                        <span id="dlmCountryReflectBadge" style="font-size:12px;font-weight:800;color:var(--amber);background:rgba(245,158,11,0.1);padding:3px 10px;border-radius:6px;border:1px solid rgba(245,158,11,0.3);">
                            📍 Refueling in 🇰🇪 Kenya
                        </span>
                    </div>
                    
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(140px, 1fr));gap:12px;">
                        <div>
                            <span style="font-size:12px;color:var(--amber);font-weight:700;">🇰🇪 Total in KSh (KES):</span>
                            <div id="dlmKesTotalDisplay" style="font-size:16px;font-weight:900;color:var(--amber);margin-top:2px;">KES 0.00</div>
                        </div>
                        <div>
                            <span style="font-size:12px;color:var(--brand);font-weight:700;">💵 Base USD ($):</span>
                            <div id="dlmUsdDisplay" style="font-size:16px;font-weight:900;color:var(--brand);margin-top:2px;">$ 0.00</div>
                        </div>
                        <div>
                            <span style="font-size:12px;color:var(--text-2);font-weight:700;" id="dlmLocalTotalLabel">Local Pump Total:</span>
                            <div id="dlmLocalTotalDisplay" style="font-size:15px;font-weight:800;color:var(--text);margin-top:2px;">—</div>
                        </div>
                        <div>
                            <span style="font-size:12px;color:var(--green);font-weight:700;">Trip Deduction:</span>
                            <div id="dlmSysDisplay" style="font-size:16px;font-weight:900;color:var(--green);margin-top:2px;"><?= app_currency_symbol() ?> 0.00</div>
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top:20px;display:flex;justify-content:flex-end;gap:12px;align-items:center;">
                <button type="button" id="dlmCancelBtn" class="btn btn-ghost" onclick="cancelDieselLogEdit(true)">Cancel</button>
                <button type="submit" id="dlmSubmitBtn" class="btn btn-amber" style="background:var(--amber);color:#fff;font-weight:800;">
                    ⛽ Record Fuel Stop
                </button>
            </div>
        </form>

        <!-- Existing Trip Fuel Stops History Table -->
        <div id="dlmHistorySection" style="margin-top:24px;border-top:1.5px solid var(--border);padding-top:18px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                <b style="font-size:13.5px;color:var(--text);">📍 Recorded Fuel Stops for this Trip</b>
                <span id="dlmHistorySummary" style="font-size:12px;color:var(--amber);font-weight:700;">0 stops • Total: $0.00</span>
            </div>
            
            <div class="table-responsive" style="max-height:220px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;">
                <table style="width:100%;font-size:12.5px;margin:0;">
                    <thead>
                        <tr>
                            <th style="padding:8px 10px;">Date</th>
                            <th style="padding:8px 10px;">Refuel Country & Station</th>
                            <th style="padding:8px 10px;">Litres</th>
                            <th style="padding:8px 10px;">Payment in KSh</th>
                            <th style="padding:8px 10px;">Base USD ($)</th>
                            <th style="padding:8px 10px;">Local Equivalent</th>
                            <th style="padding:8px 10px;text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="dlmHistoryTableBody">
                        <tr>
                            <td colspan="7" style="text-align:center;padding:20px;color:var(--text-3);">
                                Select a dispatch trip above to view its recorded fuel stops.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Corridor Mileage Rates & Transit Allowances Management Modal -->
<div class="modal-backdrop" id="mileageRatesModal">
    <div class="modal-card" style="max-width:860px;">
        <div class="modal-head">
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:24px;background:rgba(79,70,229,0.1);padding:6px;border-radius:10px;">🛣️</span>
                <div>
                    <h2 style="margin:0;font-size:19px;">Corridor Mileage Rates & Driver Allowances</h2>
                    <div style="font-size:12.5px;color:var(--text-3);margin-top:2px;">
                        Manage standard corridor transit allowances (KES & USD) auto-suggested when dispatching trucks.
                    </div>
                </div>
            </div>
            <button type="button" class="close-modal" onclick="closeMileageRatesModal()">✕</button>
        </div>

        <div id="mrFeedbackBanner" style="display:none;padding:10px 14px;background:var(--green-soft);border:1px solid var(--green);border-radius:10px;margin-bottom:14px;color:var(--green);font-weight:700;font-size:13px;"></div>

        <!-- Add / Edit Route Rate Card Form -->
        <div style="background:var(--card-2);border:1.5px solid var(--border-2);border-radius:12px;padding:16px;margin-bottom:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <b id="mrFormTitle" style="font-size:13.5px;color:var(--text);">➕ Add Corridor Destination Rate</b>
                <button type="button" id="mrCancelEditBtn" onclick="resetMileageRateForm()" style="display:none;background:transparent;border:0;color:var(--red);font-size:12px;font-weight:700;cursor:pointer;">✕ Cancel Edit</button>
            </div>
            <form id="mileageRateForm" onsubmit="return handleMileageRateSubmit(event)">
                <input type="hidden" id="mrRateId" value="">
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:12px;">
                    <div>
                        <label style="font-size:11.5px;font-weight:700;color:var(--text-2);display:block;margin-bottom:4px;">Origin Depot *</label>
                        <input type="text" id="mrOrigin" value="Eldoret" required style="width:100%;padding:8px 10px;border:1px solid var(--border-2);border-radius:8px;font-weight:700;">
                    </div>
                    <div>
                        <label style="font-size:11.5px;font-weight:700;color:var(--text-2);display:block;margin-bottom:4px;">Corridor Destination *</label>
                        <input type="text" id="mrDestination" placeholder="e.g. Uganda (Kampala)" required style="width:100%;padding:8px 10px;border:1px solid var(--border-2);border-radius:8px;font-weight:700;">
                    </div>
                    <div>
                        <label style="font-size:11.5px;font-weight:700;color:var(--text-2);display:block;margin-bottom:4px;">Distance (km)</label>
                        <input type="number" id="mrDistanceKm" placeholder="e.g. 350" style="width:100%;padding:8px 10px;border:1px solid var(--border-2);border-radius:8px;">
                    </div>
                    <div>
                        <label style="font-size:11.5px;font-weight:700;color:var(--amber);display:block;margin-bottom:4px;">Standard Allowance (KES) *</label>
                        <input type="number" step="1" id="mrAllowanceKes" placeholder="e.g. 50000" required oninput="onMrKesInput(this.value)" style="width:100%;padding:8px 10px;border:1.5px solid var(--amber);border-radius:8px;font-weight:800;color:var(--amber);">
                    </div>
                    <div>
                        <label style="font-size:11.5px;font-weight:700;color:var(--brand);display:block;margin-bottom:4px;">Standard Allowance (USD $) *</label>
                        <input type="number" step="0.01" id="mrAllowanceUsd" placeholder="0.00" required oninput="onMrUsdInput(this.value)" style="width:100%;padding:8px 10px;border:1.5px solid var(--brand);border-radius:8px;font-weight:800;color:var(--brand);">
                    </div>
                </div>
                <div style="margin-top:10px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                    <input type="text" id="mrNotes" placeholder="Optional notes (e.g. Malaba border toll inclusive)" style="flex:1;min-width:240px;padding:8px 10px;border:1px solid var(--border-2);border-radius:8px;font-size:12.5px;">
                    <button type="submit" id="mrSubmitBtn" class="btn btn-brand btn-sm" style="font-weight:800;padding:8px 18px;">
                        💾 Save Corridor Rate
                    </button>
                </div>
            </form>
        </div>

        <!-- Corridor Routes Table -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <b style="font-size:13.5px;color:var(--text);">Active Corridor Route Presets</b>
            <span id="mrTableCountBadge" style="font-size:12px;color:var(--text-3);font-weight:700;"><?= count($mileageRates) ?> routes</span>
        </div>
        <div class="table-responsive" style="max-height:300px;overflow-y:auto;border:1.5px solid var(--border);border-radius:10px;">
            <table style="width:100%;font-size:13px;margin:0;">
                <thead>
                    <tr>
                        <th style="padding:9px 12px;">Origin ➔ Destination</th>
                        <th style="padding:9px 12px;text-align:right;">Distance</th>
                        <th style="padding:9px 12px;text-align:right;">Allowance (KES)</th>
                        <th style="padding:9px 12px;text-align:right;">Allowance (USD)</th>
                        <th style="padding:9px 12px;">Notes</th>
                        <th style="padding:9px 12px;text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="mrTableBody">
                    <?php if (empty($mileageRates)): ?>
                        <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-3);">No corridor rates configured yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($mileageRates as $mr): ?>
                            <tr id="mr-row-<?= $mr['id'] ?>">
                                <td style="padding:9px 12px;font-weight:700;">
                                    <span><?= htmlspecialchars($mr['origin']) ?> ➔ <span style="color:var(--brand);"><?= htmlspecialchars($mr['destination']) ?></span></span>
                                </td>
                                <td style="padding:9px 12px;text-align:right;color:var(--text-2);"><?= $mr['distance_km'] ? number_format($mr['distance_km']) . ' km' : '—' ?></td>
                                <td style="padding:9px 12px;text-align:right;font-weight:800;color:var(--amber);">KES <?= number_format($mr['standard_allowance_kes']) ?></td>
                                <td style="padding:9px 12px;text-align:right;font-weight:800;color:var(--brand);">$ <?= number_format($mr['standard_allowance_usd'], 2) ?></td>
                                <td style="padding:9px 12px;color:var(--text-3);font-size:12px;"><?= htmlspecialchars($mr['notes'] ?? '—') ?></td>
                                <td style="padding:9px 12px;text-align:center;">
                                    <div style="display:inline-flex;gap:4px;">
                                        <button type="button" class="btn btn-ghost btn-xs" onclick='editMileageRate(<?= json_encode($mr) ?>)' title="Edit Rate">✏️</button>
                                        <button type="button" class="btn btn-ghost btn-xs" onclick="deleteMileageRate(<?= $mr['id'] ?>)" style="color:var(--red);" title="Delete Rate">🗑️</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<!-- Import CSV Modal -->
<div class="modal-backdrop" id="importModal">
    <div class="modal-card" style="max-width:520px;">
        <div class="modal-head">
            <h2>📥 Import Fleet Spreadsheet</h2>
            <button class="close-modal" onclick="document.getElementById('importModal').classList.remove('active')">✕</button>
        </div>
        <p style="color:var(--text-2);font-size:14px;margin-top:0;">
            Upload your Excel workbook (<b>.xlsx</b>, <b>.xls</b>) or <b>.csv</b> containing loading date, truck plates, capacities, litres loaded/delivered, transport revenue, and client details.
        </p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
            <a href="<?= url('fleet/template?format=xlsx') ?>" download="fleet_dispatch_template.xlsx" class="btn btn-ghost btn-sm" style="font-size:12.5px;font-weight:700;">
                📊 Download .xlsx Template
            </a>
            <a href="<?= url('fleet/template?format=xls') ?>" download="fleet_dispatch_template.xls" class="btn btn-ghost btn-sm" style="font-size:12.5px;">
                📗 Download .xls Template
            </a>
            <a href="<?= url('fleet/template?format=csv') ?>" download="fleet_dispatch_template.csv" class="btn btn-ghost btn-sm" style="font-size:12.5px;">
                📄 Download .csv Template
            </a>
        </div>
        <form method="POST" action="<?= url('fleet/import') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="form-group" style="margin-bottom:20px;">
                <label>Select Spreadsheet File (.xlsx, .xls, .csv) *</label>
                <input type="file" name="spreadsheet_file" accept=".xlsx,.xls,.csv" required style="padding:10px;">
            </div>
            <div style="display:flex;justify-content:flex-end;gap:12px;">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('importModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-brand">Upload & Import Spreadsheet</button>
            </div>
        </form>
    </div>
</div>

<!-- Dynamic Column & Schema Manager Modal -->
<?php
    $tableName = 'fleet_dispatches';
    $tableDisplayName = 'Fleet Logistics Ledger';
    $redirectTo = '/fleet';
    $isCustomTable = false;
    require __DIR__ . '/../schema/column_modal.php';
?>

<!-- Confirm Delivery & Client Offloading Modal -->
<div class="modal-backdrop" id="confirmDeliveryModal">
    <div class="modal-card" style="max-width:560px;">
        <div class="modal-head">
            <h2>📦 Confirm Client Delivery & Offload</h2>
            <button class="close-modal" onclick="document.getElementById('confirmDeliveryModal').classList.remove('active')">✕</button>
        </div>
        <form method="POST" id="confirmDeliveryForm" action="">
            <?= csrf_field() ?>
            <div style="background:var(--card-2);border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:16px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                    <span style="font-size:12.5px;color:var(--text-3);font-weight:700;">TRIP REF:</span>
                    <b id="cdmTripNumber" style="color:var(--text);">—</b>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                    <span style="font-size:12.5px;color:var(--text-3);font-weight:700;">TRUCK & DRIVER:</span>
                    <b id="cdmTruckDriver" style="color:var(--brand);">—</b>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                    <span style="font-size:12.5px;color:var(--text-3);font-weight:700;">DESTINATION & CLIENT:</span>
                    <b id="cdmDestClient" style="color:var(--text);">—</b>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="font-size:12.5px;color:var(--text-3);font-weight:700;">LOADED AT ELDORET:</span>
                    <b id="cdmLoadedLitres" style="color:var(--green);font-size:15px;">— L</b>
                </div>
            </div>

            <div class="form-grid single" style="gap:14px;">
                <div class="form-group">
                    <label>Shortage Litres (If Any)</label>
                    <input type="number" name="shortage_litres" id="cdmShortageInput" value="0" placeholder="0" oninput="recalcDeliveryFromShortage()">
                    <small style="color:var(--text-3);display:block;margin-top:2px;">Volume shortage deducted at product unit price and forwarded to driver salary deduction.</small>
                </div>

                <div class="form-group">
                    <label>Client Received / Delivered Litres (Dipped Volume) *</label>
                    <input type="number" name="delivered_litres" id="cdmDeliveredInput" required placeholder="e.g. 29750" oninput="recalcDeliveryModal()">
                </div>

                <div id="cdmShortageBanner" style="padding:10px 14px;border-radius:10px;font-size:13px;font-weight:700;display:none;"></div>

                <div class="form-group">
                    <label>Final Agreed Client Payout [<?= app_currency_symbol() ?>] *</label>
                    <input type="number" step="0.01" name="final_payout" id="cdmPayoutInput" required placeholder="0.00">
                    <small style="color:var(--text-3);display:block;margin-top:2px;">Auto-calculated: (Loaded - Shortage) × Unit Price. Adjust if needed.</small>
                </div>

                <div class="form-group">
                    <label>Shortage / Offload Notes</label>
                    <input type="text" name="shortage_notes" id="cdmShortageNotes" placeholder="e.g. 250L temperature loss deducted by consignee depot manager">
                </div>
            </div>

            <div style="margin-top:20px;display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('confirmDeliveryModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-brand">✓ Confirm Delivery & Reconcile Payout</button>
            </div>
        </form>
    </div>
</div>

<!-- Trip Expense Management Modal -->
<div class="modal-backdrop" id="tripExpenseModal">
    <div class="modal-card" style="max-width:540px;">
        <div class="modal-head">
            <h2>🛠️ Manage Trip Expenses</h2>
            <button class="close-modal" onclick="document.getElementById('tripExpenseModal').classList.remove('active')">✕</button>
        </div>
        <form id="tripExpenseForm" onsubmit="saveTripExpenses(event)">
            <input type="hidden" name="id" id="temTripId">
            <div style="background:var(--card-2);border:1px solid var(--border);border-radius:10px;padding:12px 16px;margin-bottom:16px;">
                <div style="display:flex;justify-content:space-between;">
                    <span style="font-size:12.5px;color:var(--text-3);font-weight:700;">TRIP REF:</span>
                    <b id="temTripRef" style="color:var(--text);">—</b>
                </div>
                <div style="display:flex;justify-content:space-between;margin-top:4px;">
                    <span style="font-size:12.5px;color:var(--text-3);font-weight:700;">TANKER:</span>
                    <b id="temTruck" style="color:var(--brand);">—</b>
                </div>
            </div>
            <div class="form-grid single" style="gap:14px;">
                <div class="form-group">
                    <label>Mileage & En-route Cost [<?= app_currency_symbol() ?>] *</label>
                    <input type="number" step="0.01" name="mileage_cost" id="temMileageCost" required placeholder="0.00">
                    <small style="color:var(--text-3);">Driver allowances & tolls during dispatch</small>
                </div>
                <div class="form-group">
                    <label>⛽ Diesel Fuel Cost (Owner Expense) [<?= app_currency_symbol() ?>] *</label>
                    <input type="number" step="0.01" name="diesel" id="temDiesel" placeholder="0.00">
                    <small style="color:var(--amber);">Fuel cost catered for by owner (deducted from profit)</small>
                </div>
                <div style="background:rgba(59,130,246,0.05);border:1px dashed var(--brand);border-radius:8px;padding:10px 14px;font-size:12px;color:var(--text-2);">
                    ℹ️ <b>Trip Expenses Policy:</b> Dispatches strictly account for <b>Mileage</b> and <b>Diesel</b>. Any mechanical repairs, tyre replacements, or punctures must be logged on the <a href="<?= url('expenses') ?>" target="_blank" style="color:var(--brand);font-weight:700;text-decoration:underline;">Expenses</a> page.
                </div>
            </div>
            <div style="margin-top:20px;display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('tripExpenseModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-brand" id="temSubmitBtn">✓ Update Trip Expenses</button>
            </div>
        </form>
    </div>
</div>

<!-- Fleet Export Filter Modal -->
<div class="modal-backdrop" id="fleetExportModal">
    <div class="modal-card" style="max-width:540px;">
        <div class="modal-head">
            <div>
                <h2 style="margin:0;font-size:20px;">📊 Export Fleet Dispatches Ledger</h2>
                <div style="font-size:12.5px;color:var(--text-3);margin-top:2px;">Filter dispatches per individual tanker truck, month, and year</div>
            </div>
            <button class="close-modal" onclick="document.getElementById('fleetExportModal').classList.remove('active')">✕</button>
        </div>
        <form method="GET" action="<?= url('fleet/export') ?>" target="_blank">
            <div class="form-grid single" style="gap:14px;">
                <!-- Live Target Preview Banner -->
                <div id="exportScopeBanner" style="background:var(--card-2);border:1.5px solid var(--brand);border-radius:10px;padding:12px 14px;font-size:13px;display:flex;align-items:center;gap:10px;">
                    <span style="font-size:20px;">📋</span>
                    <div>
                        <div style="font-size:11px;font-weight:800;color:var(--text-3);text-transform:uppercase;">Extraction Target:</div>
                        <div id="exportScopeText" style="color:var(--brand);font-weight:800;font-size:14px;margin-top:2px;">All Vehicles / Tankers • All Time</div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Vehicle / Tanker Truck</label>
                    <select name="truck" id="fleetExportTruckSelect" onchange="updateExportSummaryNote()" style="font-weight:700;">
                        <option value="">All Vehicles / Tankers (Entire Fleet)</option>
                        <?php foreach ($trucks as $trk): ?>
                            <?php $isContractTruck = in_array(strtolower($trk['ownership_type'] ?? ''), ['contract', 'subcontracted', 'sub']); ?>
                            <option value="<?= htmlspecialchars($trk['plate_number']) ?>">
                                <?= htmlspecialchars($trk['plate_number']) ?> (<?= number_format($trk['capacity_litres']) ?>L) <?= $isContractTruck ? '— [Contract Tanker]' : '— [Company Fleet]' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <!-- Quick Time Horizon Presets -->
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                        <label style="margin:0;font-size:13px;font-weight:700;color:var(--text-2);">Time Horizon Filter:</label>
                        <div style="display:inline-flex;gap:4px;">
                            <button type="button" class="btn btn-sm btn-ghost" onclick="setExportPreset('this_month')" style="padding:2px 9px;font-size:11.5px;font-weight:700;">This Month</button>
                            <button type="button" class="btn btn-sm btn-ghost" onclick="setExportPreset('this_year')" style="padding:2px 9px;font-size:11.5px;font-weight:700;">This Year</button>
                            <button type="button" class="btn btn-sm btn-ghost" onclick="setExportPreset('all_time')" style="padding:2px 9px;font-size:11.5px;font-weight:700;">All Time</button>
                        </div>
                    </div>

                    <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group">
                            <label>Calendar Month</label>
                            <select name="month" id="fleetExportMonthSelect" onchange="updateExportSummaryNote()">
                                <option value="">All Months (Full Year)</option>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>"><?= date('F', mktime(0, 0, 0, $m, 10)) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Calendar Year</label>
                            <select name="year" id="fleetExportYearSelect" onchange="updateExportSummaryNote()">
                                <option value="">All Years</option>
                                <?php 
                                    $yearsToDisplay = $availableYears ?? [];
                                    $curY = (int)date('Y');
                                    if (empty($yearsToDisplay)) {
                                        for ($y = $curY; $y >= $curY - 5; $y--) {
                                            $yearsToDisplay[] = (string)$y;
                                        }
                                    }
                                    foreach ($yearsToDisplay as $y): 
                                ?>
                                    <option value="<?= htmlspecialchars($y) ?>"><?= htmlspecialchars($y) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Export Format</label>
                    <div style="display:flex;gap:15px;margin-top:6px;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:600;font-size:13px;">
                            <input type="radio" name="format" value="xlsx" checked> Excel (.xlsx)
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:600;font-size:13px;">
                            <input type="radio" name="format" value="xls"> Excel 97-2003 (.xls)
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:600;font-size:13px;">
                            <input type="radio" name="format" value="csv"> CSV (.csv)
                        </label>
                    </div>
                </div>
            </div>
            <div style="margin-top:22px;display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('fleetExportModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-brand" onclick="setTimeout(() => document.getElementById('fleetExportModal').classList.remove('active'), 300)">📊 Export Spreadsheet</button>
            </div>
        </form>
    </div>
</div>

<!-- Dispatch Success Modal -->
<?php $dispatchSuccess = flash('dispatch_success_modal'); ?>
<?php if ($dispatchSuccess && is_array($dispatchSuccess)): ?>
<div class="modal-backdrop active" id="dispatchSuccessModal">
    <div class="modal-card" style="max-width:480px;text-align:center;padding:28px 24px;position:relative;">
        <button type="button" class="close-modal" style="position:absolute;top:16px;right:18px;" onclick="document.getElementById('dispatchSuccessModal').classList.remove('active')">✕</button>
        <div style="width:64px;height:64px;border-radius:50%;background:var(--green-soft);color:var(--green);display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 16px auto;">
            ✓
        </div>
        <h2 style="margin:0 0 8px 0;font-size:22px;color:var(--text);">Dispatch Created Successfully!</h2>
        <p style="color:var(--text-2);font-size:14px;margin-bottom:18px;line-height:1.5;">
            Trip <b><?= htmlspecialchars($dispatchSuccess['trip_number'] ?? '') ?></b> for tanker <b><?= htmlspecialchars($dispatchSuccess['truck'] ?? '') ?></b> assigned to <b><?= htmlspecialchars($dispatchSuccess['driver'] ?? '') ?></b> has been registered and is now <b>In Transit</b>.
            <?php if (!empty($dispatchSuccess['diesel']) && (float)$dispatchSuccess['diesel'] > 0): ?>
                <br><span style="display:inline-block;margin-top:6px;background:var(--card-2);border:1px solid var(--border);border-radius:6px;padding:3px 10px;font-size:12.5px;color:var(--brand);font-weight:700;">⛽ <?= format_money((float)$dispatchSuccess['diesel']) ?> Diesel Fuel Cost</span>
            <?php endif; ?>
        </p>
        <button type="button" class="btn btn-brand" style="width:100%;" onclick="document.getElementById('dispatchSuccessModal').classList.remove('active')">
            Got it, Continue
        </button>
    </div>
</div>
<?php endif; ?>

<!-- New Driver Prompt Modal -->
<?php $newDriverPrompt = flash('new_driver_prompt'); ?>
<?php if ($newDriverPrompt && is_array($newDriverPrompt)): ?>
<div class="modal-backdrop active" id="newDriverPromptModal" style="z-index:9999;">
    <div class="modal-card" style="max-width:520px;padding:26px;position:relative;">
        <button type="button" class="close-modal" style="position:absolute;top:16px;right:18px;" onclick="document.getElementById('newDriverPromptModal').classList.remove('active')">✕</button>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
            <div style="width:48px;height:48px;border-radius:50%;background:var(--brand-soft);color:var(--brand);display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;">
                👤
            </div>
            <div>
                <h3 style="margin:0;font-size:18px;color:var(--text);">New Driver Assigned</h3>
                <p style="margin:2px 0 0 0;font-size:13px;color:var(--text-3);">Temporary driver on trip <?= htmlspecialchars($newDriverPrompt['trip_number'] ?? '') ?></p>
            </div>
        </div>
        <p style="font-size:14.5px;color:var(--text);line-height:1.5;">
            You have created a new driver <b style="color:var(--brand);"><?= htmlspecialchars($newDriverPrompt['name'] ?? '') ?></b>. Would you want to save this information to your Drivers roster?
        </p>
        <div style="margin-top:22px;display:flex;justify-content:flex-end;gap:10px;">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('newDriverPromptModal').classList.remove('active')">
                Maybe Later
            </button>
            <form method="POST" action="<?= url('drivers/store') ?>" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="name" value="<?= htmlspecialchars($newDriverPrompt['name'] ?? '') ?>">
                <input type="hidden" name="status" value="Active">
                <input type="hidden" name="phone" value="">
                <input type="hidden" name="license_number" value="">
                <button type="submit" class="btn btn-brand">
                    ✓ Save Driver to Roster
                </button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- View Full Dispatch Details Inspection Modal -->
<div class="modal-backdrop" id="viewDispatchModal">
    <div class="modal-card" style="max-width:860px;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;padding:0;">
        <div class="modal-head" style="padding:18px 24px;border-bottom:1px solid var(--border);background:var(--card-2);margin:0;">
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <h2 style="font-size:20px;margin:0;display:flex;align-items:center;gap:8px;">
                    <span>🚚 Fleet Dispatch:</span>
                    <span id="vdmTripNumber" style="color:var(--brand);font-family:monospace;">—</span>
                </h2>
                <span id="vdmStatusBadge" class="status s-done" style="font-size:12px;"><i></i>—</span>
            </div>
            <button class="close-modal" onclick="document.getElementById('viewDispatchModal').classList.remove('active')">✕</button>
        </div>

        <div style="overflow-y:auto;padding:24px;flex:1;display:flex;flex-direction:column;gap:18px;">
            <!-- Grid: Route & Vehicle -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:14px;">
                <!-- Vehicle Card -->
                <div style="background:var(--card-2);border:1px solid var(--border);border-radius:12px;padding:16px;">
                    <div style="font-size:11px;font-weight:800;color:var(--text-3);text-transform:uppercase;margin-bottom:8px;">🚛 Tanker & Driver</div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                        <span style="color:var(--text-2);font-size:13px;">Truck Plate:</span>
                        <b id="vdmTruck" style="font-size:14px;color:var(--text);">—</b>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                        <span style="color:var(--text-2);font-size:13px;">Ownership:</span>
                        <span id="vdmOwnership" style="font-size:12px;font-weight:700;">—</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-2);font-size:13px;">Assigned Driver:</span>
                        <b id="vdmDriver" style="color:var(--brand);">—</b>
                    </div>
                </div>

                <!-- Route & Consignee Card -->
                <div style="background:var(--card-2);border:1px solid var(--border);border-radius:12px;padding:16px;">
                    <div style="font-size:11px;font-weight:800;color:var(--text-3);text-transform:uppercase;margin-bottom:8px;">🌍 Route & Consignee</div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                        <span style="color:var(--text-2);font-size:13px;">Date of Loading (DOL):</span>
                        <b id="vdmDol" style="color:var(--text);">—</b>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                        <span style="color:var(--text-2);font-size:13px;">Loading Depot:</span>
                        <b id="vdmFrom" style="color:var(--text);">—</b>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                        <span style="color:var(--text-2);font-size:13px;">Destination Corridor:</span>
                        <b id="vdmDestination" style="color:var(--text);">—</b>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                        <span style="color:var(--text-2);font-size:13px;">Client / Consignee:</span>
                        <b id="vdmClient" style="color:var(--brand);">—</b>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-2);font-size:13px;">Fuel Product:</span>
                        <span id="vdmProduct" style="background:var(--brand-soft);color:var(--brand);padding:2px 8px;border-radius:6px;font-weight:800;font-size:12px;">—</span>
                    </div>
                </div>
            </div>

            <!-- Cargo Volume & Reconciliation -->
            <div style="background:var(--card-2);border:1px solid var(--border);border-radius:12px;padding:16px;">
                <div style="font-size:11px;font-weight:800;color:var(--text-3);text-transform:uppercase;margin-bottom:12px;">📦 Cargo Volumes & Dipstick Reconciliation</div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:12px;">
                    <div style="background:var(--card);border:1px solid var(--border);border-radius:10px;padding:12px;text-align:center;">
                        <div style="font-size:12px;color:var(--text-3);font-weight:700;">Loaded at Depot</div>
                        <div id="vdmLoadedLitres" style="font-size:20px;font-weight:900;color:var(--brand);margin-top:4px;">— L</div>
                    </div>
                    <div style="background:var(--card);border:1px solid var(--border);border-radius:10px;padding:12px;text-align:center;">
                        <div style="font-size:12px;color:var(--text-3);font-weight:700;">Delivered / Received</div>
                        <div id="vdmDeliveredLitres" style="font-size:20px;font-weight:900;color:var(--green);margin-top:4px;">— L</div>
                    </div>
                    <div style="background:var(--card);border:1px solid var(--border);border-radius:10px;padding:12px;text-align:center;">
                        <div style="font-size:12px;color:var(--text-3);font-weight:700;">Transit Shortage / Loss</div>
                        <div id="vdmShortageLitres" style="font-size:20px;font-weight:900;color:var(--text);margin-top:4px;">0 L</div>
                    </div>
                </div>
                <div id="vdmShortageNotesBox" style="margin-top:10px;padding:10px 12px;background:var(--red-soft);border:1px solid var(--red);border-radius:8px;font-size:12.5px;color:var(--red);display:none;">
                    <b>📝 Shortage Reason / Note:</b> <span id="vdmShortageNotesText"></span>
                </div>
            </div>

            <!-- Financial Breakdown (with Owner-Catered Diesel Fuel highlighted) -->
            <div style="background:var(--card-2);border:1.5px solid var(--border-2);border-radius:12px;padding:18px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
                    <div style="font-size:12px;font-weight:800;color:var(--text-3);text-transform:uppercase;">
                        💰 Financial Breakdown & Net Profit Audit
                    </div>
                    <span style="font-size:11.5px;color:var(--amber);background:var(--amber-soft);padding:3px 8px;border-radius:6px;font-weight:700;">
                        ⛽ Diesel Fuel Cost is catered for by Owner (Deducted from Profit)
                    </span>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <!-- Revenue Side -->
                    <div style="display:flex;flex-direction:column;gap:8px;background:var(--card);border:1px solid var(--border);border-radius:10px;padding:14px;">
                        <div style="font-size:11px;font-weight:800;color:var(--green);text-transform:uppercase;">Revenue / Billings</div>
                        <div style="display:flex;justify-content:space-between;font-size:13.5px;">
                            <span style="color:var(--text-2);">Expected Transport:</span>
                            <b id="vdmTransport" style="color:var(--text);">—</b>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:13.5px;">
                            <span style="color:var(--text-2);">Final Client Payout:</span>
                            <b id="vdmFinalPayout" style="color:var(--brand);">—</b>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:13.5px;border-top:1px dashed var(--border);padding-top:6px;">
                            <span style="color:var(--text-2);">Payout Diff (Loss):</span>
                            <b id="vdmPayoutDiff" style="color:var(--text);">—</b>
                        </div>
                    </div>

                    <!-- Operational Costs (Deductions) Side -->
                    <div style="display:flex;flex-direction:column;gap:8px;background:var(--card);border:1px solid var(--border);border-radius:10px;padding:14px;">
                        <div style="font-size:11px;font-weight:800;color:var(--red);text-transform:uppercase;">Owner Expenses (Deductions)</div>
                        <div style="display:flex;justify-content:space-between;align-items:center;font-size:13.5px;">
                            <div style="display:flex;align-items:center;gap:6px;">
                                <span style="color:var(--amber);font-weight:700;">⛽ Diesel Fuel:</span>
                                <button type="button" class="btn btn-ghost btn-xs" id="vdmAddFuelStopBtn" style="padding:2px 7px;font-size:11px;color:var(--amber);border:1px solid var(--amber);" title="View or Add Cross-Border Fuel Stops">⛽ Log Stops</button>
                            </div>
                            <b id="vdmDiesel" style="color:var(--amber);">—</b>
                        </div>
                        <!-- Corridor Fuel Stops Container -->
                        <div id="vdmFuelStopsBox" style="display:none;background:var(--card-2);border:1px dashed var(--amber);border-radius:8px;padding:8px 10px;margin:4px 0;">
                            <div style="display:flex;justify-content:space-between;font-size:11.5px;font-weight:800;color:var(--amber);margin-bottom:4px;">
                                <span>Refueling Stops Along Corridor</span>
                                <span id="vdmFuelStopsCount">0 stops</span>
                            </div>
                            <div id="vdmFuelStopsList" style="display:flex;flex-direction:column;gap:4px;font-size:12px;"></div>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:13.5px;">
                            <span style="color:var(--text-2);">Mileage / Allowances:</span>
                            <b id="vdmMileage" style="color:var(--text);">—</b>
                        </div>
                    </div>
                </div>

                <!-- Net Profit Big Display Card -->
                <div style="margin-top:16px;background:linear-gradient(135deg, var(--card), var(--green-soft));border:2px solid var(--green);border-radius:10px;padding:14px 20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                    <div>
                        <div style="font-size:12px;font-weight:800;color:var(--green);text-transform:uppercase;">
                            Net Trip Balance (Yield / Profit)
                        </div>
                        <div style="font-size:12px;color:var(--text-2);margin-top:2px;">
                            = Final Payout - (Diesel Fuel + Mileage)
                        </div>
                    </div>
                    <div id="vdmBalance" style="font-size:28px;font-weight:900;color:var(--green);">
                        —
                    </div>
                </div>
            </div>

            <!-- Notes, Seals, and Custom Columns -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:14px;">
                <div style="background:var(--card-2);border:1px solid var(--border);border-radius:12px;padding:16px;">
                    <div style="font-size:11px;font-weight:800;color:var(--text-3);text-transform:uppercase;margin-bottom:6px;">🛠️ Remarks & Transit Notes</div>
                    <div style="font-size:13px;color:var(--text-2);">
                        <span style="color:var(--text-3);font-weight:700;">Notes:</span> <span id="vdmBreakdownNotes">—</span>
                    </div>
                </div>

                <div id="vdmCustomFieldsBox" style="background:var(--card-2);border:1px solid var(--border);border-radius:12px;padding:16px;display:none;">
                    <div style="font-size:11px;font-weight:800;color:var(--text-3);text-transform:uppercase;margin-bottom:8px;">📋 Custom Table Fields</div>
                    <div id="vdmCustomFieldsList" style="display:flex;flex-direction:column;gap:6px;font-size:13px;"></div>
                </div>
            </div>
        </div>

        <div style="padding:14px 24px;border-top:1px solid var(--border);background:var(--card-2);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
            <div style="display:flex;gap:8px;">
                <button type="button" id="vdmEditBtn" class="btn btn-ghost btn-sm">✏️ Edit Dispatch</button>
            </div>
            <button type="button" class="btn btn-brand" onclick="document.getElementById('viewDispatchModal').classList.remove('active')">Close</button>
        </div>
    </div>
</div>

<script>
let isSubcontractedTruck = false;
const CSRF_TOKEN = '<?= csrf_token() ?>';
const CURRENCY_SYM = '<?= app_currency_symbol() ?>';
const DRIVER_OPTIONS = <?= json_encode(array_column($drivers, 'name')) ?>;
window.FLEET_ROWS_MAP = <?= json_encode($fleetDetailsMap ?? []) ?>;

const IS_KES = ('<?= current_currency() ?>' === 'KES');
const SYS_EX_RATE = <?= exchange_rate() ?>;

function toSystemCurrency(baseUsd) {
    return IS_KES ? (Number(baseUsd || 0) * SYS_EX_RATE) : Number(baseUsd || 0);
}

function formatSystemMoney(val) {
    return CURRENCY_SYM + ' ' + Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/* =========================================================================
   PROCEDURAL DISPATCH WIZARD (STEPS 1 -> 4)
   ========================================================================= */
let currentWizardStep = 1;

window.openProceduralDispatchModal = function() {
    currentWizardStep = 1;
    const form = document.getElementById('dispatchWizardForm');
    if (form) form.reset();

    // Reset default dates and numbers
    const dDate = document.getElementById('wizardDispatchDate');
    if (dDate) dDate.value = '<?= date('Y-m-d') ?>';
    const fromLoc = document.getElementById('wizardFromLocation');
    if (fromLoc) fromLoc.value = 'Eldoret';

    // Reset transport payment inputs
    const trUsd = document.getElementById('dispatchTransportUsd');
    if (trUsd) trUsd.value = '';
    const trKes = document.getElementById('dispatchTransportKes');
    if (trKes) trKes.value = '';
    syncTransportPayment(0, 0);

    // Reset mileage inputs in dual KES/USD
    const milKesEl = document.getElementById('dispMileageKes');
    if (milKesEl) milKesEl.value = '';
    const milUsdEl = document.getElementById('dispMileageUsd');
    if (milUsdEl) milUsdEl.value = '';
    syncMileageFields(0, 0);

    // Reset departure fuel
    const fuelChk = document.getElementById('wizardEnableDepartureDiesel');
    if (fuelChk) {
        fuelChk.checked = true;
        toggleWizardDepartureDiesel(true);
    }
    const stEl = document.getElementById('wzDieselStation');
    if (stEl) stEl.value = 'Eldoret KPC Depot Shell';
    const litEl = document.getElementById('wzDieselLitres');
    if (litEl) litEl.value = '400';
    const cEl = document.getElementById('wzDieselCountry');
    if (cEl) cEl.value = 'Kenya';
    const kesEl = document.getElementById('wzDieselKesPrice');
    if (kesEl) kesEl.value = '180.00';
    const usdEl = document.getElementById('wzDieselUsdPrice');
    if (usdEl && SYS_EX_RATE > 0) usdEl.value = (180.00 / SYS_EX_RATE).toFixed(4);
    const exEl = document.getElementById('wzDieselExRate');
    if (exEl) exEl.value = '<?= exchange_rate() ?>';

    calcWizardDiesel();
    calcExpectedTransport();
    updateWizardUI();
    document.getElementById('dispatchModal').classList.add('active');
};

window.closeProceduralDispatchModal = function(force) {
    if (!force) {
        const truck = document.getElementById('dispatchTruckSelect')?.value;
        const driver = document.getElementById('dispatchDriverSelect')?.value;
        const client = document.getElementById('dispatchClientName')?.value;
        const isDirty = (currentWizardStep > 1) || Boolean(truck) || Boolean(driver) || Boolean(client);
        if (isDirty) {
            if (!confirm('Are you sure you want to exit the New Dispatch Wizard? Any unsubmitted trip details will be lost.')) {
                return;
            }
        }
    }
    document.getElementById('dispatchModal')?.classList.remove('active');
};

window.onTruckCapacityInput = function(val) {
    const loadedEl = document.getElementById('dispatchLoadedLitres');
    if (loadedEl) {
        loadedEl.value = val;
    }
    calcExpectedTransport();
};

window.goToWizardStep = function(targetStep) {
    if (targetStep > currentWizardStep) {
        for (let s = currentWizardStep; s < targetStep; s++) {
            if (!validateWizardStep(s)) return;
        }
    }
    currentWizardStep = targetStep;
    updateWizardUI();
    if (currentWizardStep === 2) {
        calcExpectedTransport();
    } else if (currentWizardStep === 3) {
        calcWizardDiesel();
    } else if (currentWizardStep === 4) {
        renderWizardReview();
    }
};

function updateWizardUI() {
    for (let i = 1; i <= 4; i++) {
        const panel = document.getElementById('wizardStep' + i);
        if (panel) panel.style.display = (i === currentWizardStep) ? 'block' : 'none';

        const pill = document.getElementById('stepPill' + i);
        if (pill) {
            pill.classList.remove('active', 'completed');
            if (i === currentWizardStep) {
                pill.classList.add('active');
            } else if (i < currentWizardStep) {
                pill.classList.add('completed');
            }
        }
    }
}

function validateWizardStep(step) {
    if (step === 1) {
        const date = document.getElementById('wizardDispatchDate');
        if (!date || !date.value) {
            alert('Please specify the Date of Loading (DOL).');
            date?.focus();
            return false;
        }
        const truck = document.getElementById('dispatchTruckSelect');
        if (!truck || !truck.value) {
            alert('Please select an assigned Tanker Truck.');
            truck?.focus();
            return false;
        }
        const origin = document.getElementById('wizardFromLocation');
        if (!origin || !origin.value.trim()) {
            alert('Please specify the Loading Depot / Origin.');
            origin?.focus();
            return false;
        }
        const driverInput = document.getElementById('dispatchDriverInput') || document.getElementById('dispatchDriverSelect');
        const driverVal = driverInput?.value?.trim();
        const otherDrv = document.getElementById('otherDriverInput')?.value?.trim();
        const effectiveDriver = (driverVal === '__other__' || !driverVal) ? otherDrv : driverVal;
        if (!effectiveDriver) {
            alert('Please provide the Driver\'s name.');
            driverInput?.focus();
            return false;
        }
    } else if (step === 2) {
        const pr = document.getElementById('dispatchProductSelect');
        if (!pr || !pr.value) {
            alert('Please choose a cargo product.');
            pr?.focus();
            return false;
        }
        const loaded = document.getElementById('dispatchLoadedLitres');
        if (!loaded || Number(loaded.value) <= 0) {
            alert('Please enter actual Loaded Litres carried (@ L20).');
            loaded?.focus();
            return false;
        }
        const transportUsd = document.getElementById('dispatchTransportUsd');
        if (!transportUsd || Number(transportUsd.value) <= 0) {
            alert('Please enter the Agreed Transport Payment in USD ($).');
            transportUsd?.focus();
            return false;
        }
        const dest = document.getElementById('wizardDestination');
        if (!dest || !dest.value.trim()) {
            alert('Please specify Destination Country / City.');
            dest?.focus();
            return false;
        }
    } else if (step === 3) {
        const mileage = document.getElementById('dispMileage');
        if (!mileage || isNaN(parseFloat(mileage.value))) {
            alert('Please enter driver mileage & en-route allowance (0.00 if none).');
            document.getElementById('dispMileageKes')?.focus();
            return false;
        }
        const fuelEnabled = document.getElementById('wizardEnableDepartureDiesel')?.checked;
        if (fuelEnabled) {
            const litres = parseFloat(document.getElementById('wzDieselLitres')?.value) || 0;
            const price = parseFloat(document.getElementById('wzDieselLocalPrice')?.value) || 0;
            const exRate = parseFloat(document.getElementById('wzDieselExRate')?.value) || 0;
            if (litres <= 0) {
                alert('Please enter departure diesel litres pumped (or uncheck departure diesel).');
                document.getElementById('wzDieselLitres')?.focus();
                return false;
            }
            if (price <= 0) {
                alert('Please enter the local diesel pump price per litre.');
                document.getElementById('wzDieselLocalPrice')?.focus();
                return false;
            }
            if (exRate <= 0) {
                alert('Please enter a valid exchange rate for multi-currency conversion.');
                document.getElementById('wzDieselExRate')?.focus();
                return false;
            }
        }
    }
    return true;
}

window.onMileageKesInput = function(kesVal) {
    const kes = parseFloat(kesVal) || 0;
    const usd = SYS_EX_RATE > 0 ? (kes / SYS_EX_RATE) : 0;
    const usdEl = document.getElementById('dispMileageUsd');
    if (usdEl) usdEl.value = usd > 0 ? usd.toFixed(2) : '';

    syncMileageFields(kes, usd);
};

window.onMileageUsdInput = function(usdVal) {
    const usd = parseFloat(usdVal) || 0;
    const kes = usd * SYS_EX_RATE;
    const kesEl = document.getElementById('dispMileageKes');
    if (kesEl) kesEl.value = kes > 0 ? Math.round(kes) : '';

    syncMileageFields(kes, usd);
};

function syncMileageFields(kes, usd) {
    const dispMil = document.getElementById('dispMileage');
    if (dispMil) {
        dispMil.value = IS_KES ? kes.toFixed(2) : usd.toFixed(2);
    }

    const badgeKes = document.getElementById('mileageBadgeKes');
    const badgeUsd = document.getElementById('mileageBadgeUsd');
    if (badgeKes) badgeKes.textContent = 'KES ' + kes.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (badgeUsd) badgeUsd.textContent = '$ ' + usd.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    calcBalance();
}

window.toggleWizardDepartureDiesel = function(enabled) {
    const fields = document.getElementById('wizardDepartureFuelInputs');
    const notice = document.getElementById('wizardDepartureFuelDisabledNotice');
    if (fields) fields.style.display = enabled ? 'block' : 'none';
    if (notice) notice.style.display = enabled ? 'none' : 'block';

    if (enabled) {
        calcWizardDiesel();
    } else {
        const dispLit = document.getElementById('dispDieselLitres');
        const dispUp = document.getElementById('dispDieselUnitPrice');
        const dispD = document.getElementById('dispDiesel');
        if (dispLit) dispLit.value = '0';
        if (dispUp) dispUp.value = '0.00';
        if (dispD) dispD.value = '0.00';

        const locEl = document.getElementById('wzDieselLocalTotalDisplay');
        const usdEl = document.getElementById('wzDieselUsdDisplay');
        const sysEl = document.getElementById('wzDieselSysDisplay');
        if (locEl) locEl.textContent = 'None';
        if (usdEl) usdEl.textContent = '$ 0.00';
        if (sysEl) sysEl.textContent = formatSystemMoney(0);

        calcBalance();
    }
};

window.onWizardDieselCountryChange = function() {
    const sel = document.getElementById('wzDieselCountry');
    if (!sel) return;
    const opt = sel.options[sel.selectedIndex];
    const rate = opt.dataset.rate || '1.0';
    const country = opt.value || 'Kenya';
    const currency = opt.dataset.currency || 'KES';
    const flag = opt.dataset.flag || '📍';

    const exInput = document.getElementById('wzDieselExRate');
    if (exInput) exInput.value = rate;

    const badge = document.getElementById('wzDieselCountryBadge');
    if (badge) badge.textContent = `📍 ${flag} ${country}`;

    const cHidden = document.getElementById('wzDieselCountryHidden');
    if (cHidden) cHidden.value = country;
    const curHidden = document.getElementById('wzDieselCurrencyHidden');
    if (curHidden) curHidden.value = currency;
    const rHidden = document.getElementById('wzDieselExRateHidden');
    if (rHidden) rHidden.value = rate;

    calcWizardDiesel();
};

window.onWizardDieselKesInput = function(val) {
    const kesVal = parseFloat(val) || 0;
    const usdEl = document.getElementById('wzDieselUsdPrice');
    if (usdEl && SYS_EX_RATE > 0) {
        usdEl.value = kesVal > 0 ? (kesVal / SYS_EX_RATE).toFixed(4) : '';
    }
    calcWizardDiesel();
};

window.onWizardDieselUsdInput = function(val) {
    const usdVal = parseFloat(val) || 0;
    const kesEl = document.getElementById('wzDieselKesPrice');
    if (kesEl && SYS_EX_RATE > 0) {
        kesEl.value = usdVal > 0 ? (usdVal * SYS_EX_RATE).toFixed(2) : '';
    }
    calcWizardDiesel();
};

window.calcWizardDiesel = function() {
    const fuelEnabled = document.getElementById('wizardEnableDepartureDiesel')?.checked;
    if (!fuelEnabled) return;

    const litres = parseFloat(document.getElementById('wzDieselLitres')?.value) || 0;
    const kesPrice = parseFloat(document.getElementById('wzDieselKesPrice')?.value) || 0;
    const usdPrice = parseFloat(document.getElementById('wzDieselUsdPrice')?.value) || (SYS_EX_RATE > 0 ? (kesPrice / SYS_EX_RATE) : 0);
    const exRate = parseFloat(document.getElementById('wzDieselExRate')?.value) || 1;

    const sel = document.getElementById('wzDieselCountry');
    const opt = sel ? sel.options[sel.selectedIndex] : null;
    const country = opt?.value || 'Kenya';
    const currCode = opt?.dataset.currency || 'KES';

    const kesTotal = litres * kesPrice;
    const usdCost = litres * usdPrice;
    const localUnitPrice = (country === 'Kenya') ? kesPrice : (usdPrice * exRate);

    const kesDisp = document.getElementById('wzDieselKesTotalDisplay');
    const usdEl = document.getElementById('wzDieselUsdDisplay');
    const sysEl = document.getElementById('wzDieselSysDisplay');

    if (kesDisp) kesDisp.textContent = 'KES ' + kesTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (usdEl) usdEl.textContent = '$ ' + usdCost.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (sysEl) sysEl.textContent = formatSystemMoney(toSystemCurrency(usdCost));

    const dispLit = document.getElementById('dispDieselLitres');
    const dispUp = document.getElementById('dispDieselUnitPrice');
    const dispD = document.getElementById('dispDiesel');
    const rHidden = document.getElementById('wzDieselExRateHidden');
    const lpHidden = document.getElementById('wzDieselLocalPriceHidden');

    if (dispLit) dispLit.value = litres;
    if (dispUp) dispUp.value = usdPrice.toFixed(4);
    if (dispD) dispD.value = usdCost.toFixed(2);
    if (rHidden) rHidden.value = exRate;
    if (lpHidden) lpHidden.value = localUnitPrice.toFixed(2);

    calcBalance();
};

function renderWizardReview() {
    const tripNum = document.getElementById('wizardTripNumber')?.value || 'Auto Ref';
    const truck = document.getElementById('dispatchTruckSelect')?.value || '—';
    const driverInput = document.getElementById('dispatchDriverInput') || document.getElementById('dispatchDriverSelect');
    const driverVal = driverInput?.value?.trim() || '—';
    const otherDrv = document.getElementById('otherDriverInput')?.value?.trim();
    const finalDriver = (driverVal === '__other__' || !driverVal || driverVal === '—') ? (otherDrv || 'Unassigned') : driverVal;

    const fromLoc = document.getElementById('wizardFromLocation')?.value || 'Eldoret';
    const dest = document.getElementById('wizardDestination')?.value || '—';
    const client = document.getElementById('dispatchClientName')?.value?.trim() || 'Spot Consignee';

    const product = document.getElementById('dispatchProductSelect')?.value || '—';
    const loaded = Number(document.getElementById('dispatchLoadedLitres')?.value || 0);
    const date = document.getElementById('wizardDispatchDate')?.value || '—';

    const revTripTruck = document.getElementById('wzRevTripTruck');
    if (revTripTruck) revTripTruck.textContent = tripNum + ' • ' + truck;
    const revDriver = document.getElementById('wzRevDriver');
    if (revDriver) revDriver.textContent = 'Driver: ' + finalDriver;

    const revRoute = document.getElementById('wzRevRoute');
    if (revRoute) revRoute.textContent = fromLoc + ' ➔ ' + dest;
    const revClient = document.getElementById('wzRevClient');
    if (revClient) revClient.textContent = 'Consignee: ' + client;

    const revCargo = document.getElementById('wzRevCargo');
    if (revCargo) revCargo.textContent = product + ' • ' + loaded.toLocaleString() + ' L (Actual @ L20)';
    const revDate = document.getElementById('wzRevDate');
    if (revDate) revDate.textContent = 'Loading: ' + date;

    // Financial calculations
    const transportAmt = parseFloat(document.getElementById('dispTransport')?.value) || 0;
    const mileageAmt = parseFloat(document.getElementById('dispMileage')?.value) || 0;
    const dieselAmt = parseFloat(document.getElementById('dispDiesel')?.value) || 0;
    const commAmt = parseFloat(document.getElementById('dispCommission')?.value) || 0;

    const revRevenue = document.getElementById('wzRevRevenue');
    if (revRevenue) revRevenue.textContent = formatSystemMoney(transportAmt);
    const revMileage = document.getElementById('wzRevMileage');
    if (revMileage) {
        const milKes = parseFloat(document.getElementById('dispMileageKes')?.value) || 0;
        const milUsd = parseFloat(document.getElementById('dispMileageUsd')?.value) || 0;
        revMileage.innerHTML = `- ${formatSystemMoney(mileageAmt)} <small style="color:var(--text-3);font-size:11.5px;">(KES ${milKes.toLocaleString()} / $${milUsd.toFixed(2)})</small>`;
    }
    const revDiesel = document.getElementById('wzRevDiesel');
    if (revDiesel) revDiesel.textContent = '- ' + formatSystemMoney(dieselAmt);

    const yieldLabel = document.getElementById('wzRevYieldLabel');
    const revBalance = document.getElementById('wzRevBalance');

    let bal = 0;
    if (isSubcontractedTruck) {
        bal = commAmt;
        if (yieldLabel) yieldLabel.textContent = 'Contract Tanker Commission:';
    } else {
        bal = transportAmt - (mileageAmt + dieselAmt);
        if (yieldLabel) yieldLabel.textContent = 'Estimated Net Yield (Company Profit):';
    }

    if (revBalance) {
        revBalance.textContent = formatSystemMoney(bal);
        revBalance.style.color = (bal >= 0) ? 'var(--green)' : 'var(--red)';
    }
}

window.onWizardFormSubmit = function(e) {
    for (let s = 1; s <= 3; s++) {
        if (!validateWizardStep(s)) {
            if (e) e.preventDefault();
            goToWizardStep(s);
            return false;
        }
    }
    return true;
};

/* =========================================================================
   DEDICATED CROSS-BORDER DIESEL FUELING MODAL & HISTORY
   ========================================================================= */
window.openDieselLogModal = function(dispatchId = null) {
    const sel = document.getElementById('dlmDispatchSelect');
    if (!sel) return;

    cancelDieselLogEdit(false);

    const dDate = document.getElementById('dlmFuelDate');
    if (dDate) dDate.value = '<?= date('Y-m-d') ?>';

    if (dispatchId) {
        let opt = sel.querySelector('option[value="' + dispatchId + '"]');
        if (!opt && window.FLEET_ROWS_MAP && window.FLEET_ROWS_MAP[dispatchId]) {
            const rowData = window.FLEET_ROWS_MAP[dispatchId];
            opt = document.createElement('option');
            opt.value = dispatchId;
            opt.textContent = `${rowData.trip_number || 'Trip'} • ${rowData.truck || ''} (${rowData.destination || ''}) — ${rowData.driver || ''}`;
            sel.appendChild(opt);
        }
        sel.value = dispatchId;
        onDlmDispatchChanged(dispatchId);
    } else if (sel.value) {
        onDlmDispatchChanged(sel.value);
    } else if (sel.options.length > 1) {
        sel.selectedIndex = 1;
        onDlmDispatchChanged(sel.value);
    }

    onDlmCountryChange();
    document.getElementById('dieselLogModal').classList.add('active');
};

window.onDlmDispatchChanged = function(dispatchId) {
    if (dispatchId) {
        loadDispatchFuelHistory(dispatchId);
    } else {
        const body = document.getElementById('dlmHistoryTableBody');
        if (body) {
            body.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-3);">Select a dispatch trip above to view its recorded fuel stops.</td></tr>';
        }
        const summ = document.getElementById('dlmHistorySummary');
        if (summ) summ.textContent = '0 stops • Total: $0.00';
    }
};

let dlmInputCurrency = 'KES';

window.setDlmPayMode = function(mode) {
    dlmInputCurrency = mode;
    const entryCur = document.getElementById('dlmEntryCurrency');
    if (entryCur) entryCur.value = mode;

    const rate = parseFloat(document.getElementById('dlmExchangeRate')?.value) || SYS_EX_RATE;
    const usdPriceInput = document.getElementById('dlmUsdPrice');
    const kesPriceInput = document.getElementById('dlmKesPrice');

    if (mode === 'USD') {
        if (usdPriceInput) usdPriceInput.focus();
    } else {
        if (kesPriceInput) kesPriceInput.focus();
    }
    calcDlmDiesel();
};

window.onDlmKesInput = function(val) {
    const kes = parseFloat(val) || 0;
    const rate = parseFloat(document.getElementById('dlmExchangeRate')?.value) || SYS_EX_RATE;
    const usd = (kes > 0 && rate > 0) ? (kes / rate) : 0;
    
    const usdInput = document.getElementById('dlmUsdPrice');
    if (usdInput && document.activeElement === document.getElementById('dlmKesPrice')) {
        usdInput.value = usd > 0 ? usd.toFixed(4) : '';
    }
    const entryCur = document.getElementById('dlmEntryCurrency');
    if (entryCur) entryCur.value = 'KES';
    calcDlmDiesel();
};

window.onDlmUsdInput = function(val) {
    const usd = parseFloat(val) || 0;
    const rate = parseFloat(document.getElementById('dlmExchangeRate')?.value) || SYS_EX_RATE;
    const kes = (usd > 0 && rate > 0) ? (usd * rate) : 0;
    
    const kesInput = document.getElementById('dlmKesPrice');
    if (kesInput && document.activeElement === document.getElementById('dlmUsdPrice')) {
        kesInput.value = kes > 0 ? kes.toFixed(2) : '';
    }
    const entryCur = document.getElementById('dlmEntryCurrency');
    if (entryCur) entryCur.value = 'USD';
    calcDlmDiesel();
};

window.onDlmRateInput = function(val) {
    const rate = parseFloat(val) || 0;
    const badge = document.getElementById('dlmRateStatusBadge');
    if (badge) {
        badge.textContent = rate > 0 ? `1 USD = ${rate.toFixed(2)} KES` : `Custom Rate`;
    }

    const entryMode = document.getElementById('dlmEntryCurrency')?.value || 'KES';
    const usdInput = document.getElementById('dlmUsdPrice');
    const kesInput = document.getElementById('dlmKesPrice');

    if (entryMode === 'USD' && usdInput && usdInput.value) {
        const usd = parseFloat(usdInput.value) || 0;
        if (kesInput && usd > 0 && rate > 0) {
            kesInput.value = (usd * rate).toFixed(2);
        }
    } else if (kesInput && kesInput.value) {
        const kes = parseFloat(kesInput.value) || 0;
        if (usdInput && kes > 0 && rate > 0) {
            usdInput.value = (kes / rate).toFixed(4);
        }
    }
    calcDlmDiesel();
};

window.onDlmCountryChange = function() {
    const sel = document.getElementById('dlmCountrySelect');
    if (!sel) return;
    const opt = sel.options[sel.selectedIndex];
    const rate = parseFloat(opt.dataset.rate) || SYS_EX_RATE;
    const country = opt.value || 'Kenya';
    const curCode = opt.dataset.currency || 'KES';

    const cCode = document.getElementById('dlmCurrencyCode');
    if (cCode) cCode.value = curCode;

    const rateCode = document.getElementById('dlmRateCurrencyCode');
    if (rateCode) rateCode.textContent = curCode;

    const badge = document.getElementById('dlmCountryReflectBadge');
    if (badge) {
        badge.textContent = `📍 Refueling in ${opt.text}`;
    }

    if (curCode === 'KES') {
        const exRate = document.getElementById('dlmExchangeRate');
        if (exRate) exRate.value = SYS_EX_RATE;
    }
    calcDlmDiesel();
};

window.resetDlmRate = function() {
    const exRate = document.getElementById('dlmExchangeRate');
    if (exRate) {
        exRate.value = SYS_EX_RATE;
        window.onDlmRateInput(SYS_EX_RATE);
    }
};

window.calcDlmDiesel = function() {
    const litres = parseFloat(document.getElementById('dlmLitres')?.value) || 0;
    const rate = parseFloat(document.getElementById('dlmExchangeRate')?.value) || SYS_EX_RATE;
    const usdPrice = parseFloat(document.getElementById('dlmUsdPrice')?.value) || 0;
    const kesPrice = parseFloat(document.getElementById('dlmKesPrice')?.value) || 0;
    const entryMode = document.getElementById('dlmEntryCurrency')?.value || 'KES';

    let totalUsd = 0;
    let totalKes = 0;

    if (entryMode === 'USD' && usdPrice > 0) {
        totalUsd = litres * usdPrice;
        totalKes = totalUsd * rate;
    } else {
        totalKes = litres * kesPrice;
        totalUsd = (rate > 0) ? (totalKes / rate) : 0;
    }

    const localPriceInput = document.getElementById('dlmLocalPrice');
    if (localPriceInput) {
        localPriceInput.value = (litres > 0 && totalKes > 0) ? (totalKes / litres).toFixed(2) : '0';
    }

    const sysCost = toSystemCurrency(totalUsd);

    const kesDisp = document.getElementById('dlmKesTotalDisplay');
    const locDisp = document.getElementById('dlmLocalTotalDisplay');
    const locLbl = document.getElementById('dlmLocalTotalLabel');
    const usdDisp = document.getElementById('dlmUsdDisplay');
    const sysDisp = document.getElementById('dlmSysDisplay');

    if (kesDisp) kesDisp.textContent = 'KES ' + totalKes.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (locLbl) locLbl.textContent = `Pump Subtotal:`;
    if (locDisp) locDisp.textContent = 'KES ' + totalKes.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (usdDisp) usdDisp.textContent = '$ ' + totalUsd.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (sysDisp) sysDisp.textContent = formatSystemMoney(sysCost);
};

window.editDieselLogAjax = function(s) {
    const editIdInput = document.getElementById('dlmEditLogId');
    if (editIdInput) editIdInput.value = s.id;

    const banner = document.getElementById('dlmEditModeBanner');
    const title = document.getElementById('dlmEditModeTitle');
    if (banner) banner.style.display = 'block';
    if (title) title.textContent = `✏️ Editing Fuel Stop #${s.id}: ${s.station_location || 'Stop'} (${s.country})`;

    const dDate = document.getElementById('dlmFuelDate');
    if (dDate) dDate.value = s.fuel_date;

    const cSel = document.getElementById('dlmCountrySelect');
    if (cSel) {
        for (let i = 0; i < cSel.options.length; i++) {
            if (cSel.options[i].value.toLowerCase() === (s.country || '').toLowerCase()) {
                cSel.selectedIndex = i;
                break;
            }
        }
    }

    const stEl = document.getElementById('dlmStationLocation');
    if (stEl) stEl.value = s.station_location || '';

    const litEl = document.getElementById('dlmLitres');
    if (litEl) litEl.value = s.litres;

    const exRate = parseFloat(s.exchange_rate) || SYS_EX_RATE;
    const exEl = document.getElementById('dlmExchangeRate');
    if (exEl) exEl.value = exRate;

    const baseUsd = parseFloat(s.base_usd_cost) || 0;
    const litres = parseFloat(s.litres) || 0;
    const usdUnitPrice = litres > 0 ? (baseUsd / litres) : 0;
    const kesUnitPrice = usdUnitPrice * exRate;

    const usdPriceEl = document.getElementById('dlmUsdPrice');
    if (usdPriceEl) usdPriceEl.value = usdUnitPrice > 0 ? usdUnitPrice.toFixed(4) : '';

    const kesPriceEl = document.getElementById('dlmKesPrice');
    if (kesPriceEl) kesPriceEl.value = kesUnitPrice > 0 ? kesUnitPrice.toFixed(2) : '';

    const subBtn = document.getElementById('dlmSubmitBtn');
    if (subBtn) {
        subBtn.textContent = '✓ Save Changes to Fuel Stop';
        subBtn.style.background = 'var(--brand)';
    }

    calcDlmDiesel();

    const modal = document.querySelector('#dieselLogModal .modal-card');
    if (modal) modal.scrollTop = 0;
};

window.cancelDieselLogEdit = function(closeModalIfNormal = false) {
    const editIdInput = document.getElementById('dlmEditLogId');
    const wasEditing = editIdInput && editIdInput.value !== '';

    if (editIdInput) editIdInput.value = '';

    const banner = document.getElementById('dlmEditModeBanner');
    if (banner) banner.style.display = 'none';

    const subBtn = document.getElementById('dlmSubmitBtn');
    if (subBtn) {
        subBtn.textContent = '⛽ Record Fuel Stop';
        subBtn.style.background = 'var(--amber)';
    }

    // Reset fields
    const litEl = document.getElementById('dlmLitres');
    if (litEl) litEl.value = '';
    const usdPriceEl = document.getElementById('dlmUsdPrice');
    if (usdPriceEl) usdPriceEl.value = '';
    const kesPriceEl = document.getElementById('dlmKesPrice');
    if (kesPriceEl) kesPriceEl.value = '';
    const stEl = document.getElementById('dlmStationLocation');
    if (stEl) stEl.value = '';

    const exEl = document.getElementById('dlmExchangeRate');
    if (exEl) exEl.value = SYS_EX_RATE;

    calcDlmDiesel();

    if (closeModalIfNormal && !wasEditing) {
        document.getElementById('dieselLogModal').classList.remove('active');
    }
};

window.handleDieselLogSubmit = async function(e) {
    if (e) e.preventDefault();
    const form = document.getElementById('dieselLogForm');
    const dispatchId = document.getElementById('dlmDispatchSelect')?.value;
    if (!dispatchId) {
        alert('Please select the dispatch trip for this refueling.');
        return false;
    }
    const litres = parseFloat(document.getElementById('dlmLitres')?.value) || 0;
    if (litres <= 0) {
        alert('Please enter a valid volume of litres pumped.');
        return false;
    }
    const usdPrice = parseFloat(document.getElementById('dlmUsdPrice')?.value) || 0;
    const kesPrice = parseFloat(document.getElementById('dlmKesPrice')?.value) || 0;
    if (usdPrice <= 0 && kesPrice <= 0) {
        alert('Please enter the price per litre in USD or KSh.');
        return false;
    }

    const editId = document.getElementById('dlmEditLogId')?.value;
    const isEdit = Boolean(editId);
    const targetUrl = isEdit ? ('<?= url("fleet/diesel/update") ?>/' + editId) : '<?= url("fleet/diesel/store") ?>';

    const btn = document.getElementById('dlmSubmitBtn');
    if (btn) {
        btn.disabled = true;
        btn.textContent = isEdit ? 'Updating Fuel Stop...' : 'Saving Fuel Stop...';
    }

    try {
        const formData = new FormData(form);
        const res = await fetch(targetUrl, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });
        const json = await res.json();
        if (json.success) {
            cancelDieselLogEdit(false);
            loadDispatchFuelHistory(dispatchId);

            const gAggs = json.global_aggregates || (json.data && json.data.global_aggregates);
            const totals = (json.data && json.data.totals) || json.totals;
            if (totals) {
                updateFleetRowTotals(dispatchId, totals, gAggs);
            }

            const fb = document.getElementById('dlmFeedbackBanner');
            if (fb) {
                fb.style.display = 'block';
                fb.textContent = isEdit ? '✓ Fuel stop updated successfully!' : '✓ Fuel stop recorded successfully!';
                setTimeout(() => { if (fb) fb.style.display = 'none'; }, 4000);
            }
        } else {
            alert('Could not save fuel record: ' + (json.error || 'Server error'));
        }
    } catch(err) {
        console.error(err);
        alert('Network error while saving fuel record.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = isEdit ? '✓ Save Changes to Fuel Stop' : '⛽ Record Fuel Stop';
        }
    }
    return false;
};

window.loadDispatchFuelHistory = async function(dispatchId) {
    const body = document.getElementById('dlmHistoryTableBody');
    const summ = document.getElementById('dlmHistorySummary');
    if (!body) return;

    body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:15px;color:var(--text-3);">Loading fuel stops...</td></tr>';

    try {
        const res = await fetch('<?= url("fleet/diesel/list") ?>/' + dispatchId);
        const json = await res.json();
        const logsList = json.logs || json.data || [];
        if (json.success && (json.logs || json.data)) {
            if (logsList.length === 0) {
                body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:18px;color:var(--text-3);">No fuel stops recorded yet for this dispatch trip.</td></tr>';
                if (summ) summ.textContent = '0 stops • Total: $0.00';
            } else {
                let html = '';
                logsList.forEach(s => {
                    const localTot = parseFloat(s.local_total_cost || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    const usdTot = parseFloat(s.base_usd_cost || 0).toFixed(2);
                    const sysTot = formatSystemMoney(toSystemCurrency(s.base_usd_cost));
                    const litres = parseFloat(s.litres) || 0;
                    const stopRate = parseFloat(s.exchange_rate) || SYS_EX_RATE;
                    const stopUsdPrice = litres > 0 ? (parseFloat(s.base_usd_cost || 0) / litres) : 0;
                    const stopKesPrice = stopUsdPrice * stopRate;

                    html += `<tr>
                        <td style="padding:8px 10px;white-space:nowrap;">${s.fuel_date}</td>
                        <td style="padding:8px 10px;">
                            <b>${s.country}</b><br>
                            <small style="color:var(--text-3);">${s.station_location || 'En-route Station'}</small>
                        </td>
                        <td style="padding:8px 10px;font-weight:700;">
                            ${Number(s.litres).toLocaleString()} L
                            <div style="font-size:10.5px;color:var(--text-3);font-weight:normal;">$${stopUsdPrice.toFixed(3)}/L • KES ${stopKesPrice.toFixed(1)}/L</div>
                        </td>
                        <td style="padding:8px 10px;font-weight:800;color:var(--amber);">
                            KES ${localTot}<br>
                            <span style="font-size:10px;color:var(--text-3);background:var(--card-2);padding:1px 5px;border-radius:4px;border:1px solid var(--border);">Rate: ${stopRate.toFixed(2)}</span>
                        </td>
                        <td style="padding:8px 10px;white-space:nowrap;">
                            <b style="color:var(--brand);">$${usdTot}</b><br>
                            <small style="color:var(--text-3);font-weight:600;">${sysTot}</small>
                        </td>
                        <td style="padding:8px 10px;font-size:11px;color:var(--text-3);">
                            ${s.receipt_status || 'Received'}
                        </td>
                        <td style="padding:8px 10px;text-align:center;">
                            <div style="display:inline-flex;gap:4px;">
                                <button type="button" class="btn btn-ghost btn-xs" style="color:var(--brand);padding:2px 7px;font-weight:700;border:1px solid var(--border);" onclick='editDieselLogAjax(${JSON.stringify(s)})' title="Edit this fuel stop">✏️ Edit</button>
                                <button type="button" class="btn btn-ghost btn-xs" style="color:var(--red);padding:2px 6px;" onclick="deleteDieselLogAjax(${s.id}, ${dispatchId})" title="Delete this fueling stop">✕</button>
                            </div>
                        </td>
                    </tr>`;
                });
                body.innerHTML = html;

                if (summ && json.dispatch) {
                    summ.textContent = `${logsList.length} stops • ${json.dispatch.total_diesel_litres.toLocaleString()} L • Total: ${json.dispatch.total_diesel_formatted}`;
                }
            }
        }
    } catch(err) {
        console.error('Error fetching fuel stops history:', err);
        body.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:15px;color:var(--red);">Error loading fuel stops history.</td></tr>';
    }
};

window.deleteDieselLogAjax = async function(logId, dispatchId) {
    if (!confirm('Are you sure you want to delete this diesel fueling stop? The trip diesel total and balance will be recalculated.')) {
        return;
    }
    try {
        const res = await fetch('<?= url("fleet/diesel/delete") ?>/' + logId, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({ _csrf_token: CSRF_TOKEN }).toString()
        });
        const json = await res.json();
        if (json.success) {
            loadDispatchFuelHistory(dispatchId);
            const gAggs = json.global_aggregates || (json.data && json.data.global_aggregates);
            const totals = (json.data && json.data.totals) || json.totals;
            if (totals) {
                updateFleetRowTotals(dispatchId, totals, gAggs);
            }
        } else {
            alert('Could not delete fuel stop: ' + (json.error || 'Server error'));
        }
    } catch(err) {
        console.error(err);
        alert('Network error while deleting fuel stop.');
    }
};

window.refreshFleetStatCards = function(aggs) {
    if (!aggs) return;
    const canView = <?= $canViewFin ? 'true' : 'false' ?>;
    if (!canView) return;

    const elTrans = document.getElementById('statTotalTransport');
    const elDiesel = document.getElementById('statTotalDiesel');
    const elMileage = document.getElementById('statTotalMileage');
    const elBal = document.getElementById('statTotalBalance');

    if (elTrans && aggs.total_transport !== undefined) {
        elTrans.textContent = formatSystemMoney(toSystemCurrency(aggs.total_transport));
    }
    if (elDiesel && aggs.total_diesel !== undefined) {
        elDiesel.textContent = formatSystemMoney(toSystemCurrency(aggs.total_diesel));
    }
    if (elMileage && aggs.total_mileage !== undefined) {
        elMileage.textContent = formatSystemMoney(toSystemCurrency(aggs.total_mileage));
    }
    if (elBal && aggs.total_balance !== undefined) {
        elBal.textContent = formatSystemMoney(toSystemCurrency(aggs.total_balance));
    }
};

window.updateFleetRowTotals = function(dispatchId, totals, globalAggregates = null) {
    if (globalAggregates) {
        refreshFleetStatCards(globalAggregates);
    }
    if (window.FLEET_ROWS_MAP && window.FLEET_ROWS_MAP[dispatchId]) {
        const d = window.FLEET_ROWS_MAP[dispatchId];
        d.diesel = (totals.diesel !== undefined) ? totals.diesel : (totals.diesel_raw ?? 0);
        d.diesel_litres = totals.diesel_litres;
        d.diesel_unit_price = totals.diesel_unit_price;
        d.diesel_formatted = totals.diesel_formatted || formatSystemMoney(d.diesel);
        d.balance = (totals.balance !== undefined) ? totals.balance : (totals.balance_raw ?? 0);
        d.balance_formatted = totals.balance_formatted || formatSystemMoney(d.balance);
    }

    const row = document.getElementById('fleet-row-' + dispatchId);
    if (row) {
        row.dataset.diesel = (totals.diesel !== undefined) ? totals.diesel : (totals.diesel_raw ?? 0);
        const cellDiesel = row.querySelector('.cell-diesel');
        if (cellDiesel) {
            const dVal = parseFloat(totals.diesel ?? totals.diesel_raw ?? 0);
            const dLit = parseFloat(totals.diesel_litres || 0);
            const dUp = parseFloat(totals.diesel_unit_price || 0);

            if (dVal > 0) {
                const usdStr = '$' + dVal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                const kesStr = 'KSh ' + (dVal * SYS_EX_RATE).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                let dHtml = `
                    <div class="val-diesel-usd" style="font-weight:900;color:var(--amber);font-size:13.5px;">${usdStr}</div>
                    <div class="val-diesel-kes" style="font-size:11.5px;color:var(--text-2);font-weight:700;margin-top:1px;">${kesStr}</div>
                `;
                if (dLit > 0) {
                    dHtml += `<div style="font-size:10.5px;color:var(--text-3);font-weight:600;margin-top:2px;">⛽ ${dLit.toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1})} L</div>`;
                }
                cellDiesel.innerHTML = dHtml;
            } else {
                cellDiesel.innerHTML = '<span class="view-val" style="color:var(--text-3);">—</span>';
            }
        }

        const cellBalance = row.querySelector('.cell-balance');
        if (cellBalance) {
            const balVal = parseFloat(totals.balance ?? totals.balance_raw ?? 0);
            cellBalance.innerHTML = `<span class="view-val" style="color:${balVal >= 0 ? 'var(--green)' : 'var(--red)'};">${formatSystemMoney(balVal)}</span>`;
        }

        row.style.transition = 'background .4s';
        row.style.background = 'var(--green-soft)';
        setTimeout(() => { row.style.background = ''; }, 1200);
    }
};

window.loadVdmFuelStops = async function(dispatchId) {
    const box = document.getElementById('vdmFuelStopsBox');
    const list = document.getElementById('vdmFuelStopsList');
    const countEl = document.getElementById('vdmFuelStopsCount');
    if (!box || !list) return;
    box.style.display = 'none';
    list.innerHTML = '';

    try {
        const res = await fetch('<?= url("fleet/diesel/list") ?>/' + dispatchId);
        const json = await res.json();
        const logsList = json.logs || json.data || [];
        if (json.success && logsList.length > 0) {
            box.style.display = 'block';
            if (countEl) countEl.textContent = logsList.length + (logsList.length === 1 ? ' stop' : ' stops');
            list.innerHTML = logsList.map(s => `
                <div style="display:flex;justify-content:space-between;align-items:center;padding:3px 0;border-bottom:1px dashed var(--border);">
                    <div>
                        <b>${s.country}</b> • <span style="color:var(--text-2);">${s.station_location || 'En-route Station'}</span> (${Number(s.litres).toLocaleString()} L)
                    </div>
                    <div style="font-weight:700;color:var(--amber);">
                        ${s.currency_code} ${parseFloat(s.local_total_cost || 0).toLocaleString(undefined, {minimumFractionDigits:2})} 
                        <span style="font-size:11px;color:var(--brand);font-weight:800;">($${parseFloat(s.base_usd_cost || 0).toFixed(2)})</span>
                    </div>
                </div>
            `).join('');
        }
    } catch(e) {
        console.error('Error loading fuel stops for view modal:', e);
    }
};

window.openViewDispatchModal = function(param) {
    let d = null;
    if (typeof param === 'object' && param !== null) {
        d = param;
    } else if (typeof param === 'number' || typeof param === 'string') {
        d = (window.FLEET_ROWS_MAP && window.FLEET_ROWS_MAP[param]) ? window.FLEET_ROWS_MAP[param] : null;
    }
    if (!d) {
        console.error('Dispatch record not found for details modal:', param);
        alert('Could not find dispatch details for ID: ' + param);
        return;
    }
    document.getElementById('vdmTripNumber').textContent = d.trip_number || '—';
    
    // Status badge
    const badge = document.getElementById('vdmStatusBadge');
    const st = (d.status || '').toLowerCase();
    badge.className = 'status ' + (st === 'delivered' ? 's-done' : (st === 'in transit' ? 's-prog' : 's-plan'));
    badge.innerHTML = '<i></i>' + (d.status || 'Pending');

    document.getElementById('vdmTruck').textContent = d.truck || '—';
    document.getElementById('vdmOwnership').textContent = d.ownership || (d.is_subcontracted ? 'Subcontracted' : 'Company Fleet');
    document.getElementById('vdmCapacity').textContent = (Number(d.truck_capacity || 0).toLocaleString()) + ' L';
    document.getElementById('vdmDriver').textContent = d.driver || '—';

    document.getElementById('vdmDol').textContent = d.dol_formatted || d.dispatch_date || '—';
    document.getElementById('vdmFrom').textContent = d.from_location || 'Eldoret Depot';
    document.getElementById('vdmDestination').textContent = d.destination || '—';
    document.getElementById('vdmClient').textContent = d.client_name || '—';
    document.getElementById('vdmProduct').textContent = d.product || '—';

    const loaded = Number(d.loaded_litres || 0);
    const delivered = (d.delivered_litres !== null && d.delivered_litres !== undefined && d.delivered_litres !== '') ? Number(d.delivered_litres) : null;
    document.getElementById('vdmLoadedLitres').textContent = loaded.toLocaleString() + ' L';
    document.getElementById('vdmDeliveredLitres').textContent = delivered !== null ? (delivered.toLocaleString() + ' L') : 'Pending Arrival';

    const shortage = delivered !== null ? (loaded - delivered) : 0;
    const shortageEl = document.getElementById('vdmShortageLitres');
    if (shortage > 0) {
        shortageEl.textContent = '-' + shortage.toLocaleString() + ' L';
        shortageEl.style.color = 'var(--red)';
    } else if (shortage < 0) {
        shortageEl.textContent = '+' + Math.abs(shortage).toLocaleString() + ' L';
        shortageEl.style.color = 'var(--green)';
    } else {
        shortageEl.textContent = '0 L';
        shortageEl.style.color = 'var(--green)';
    }

    const shortageBox = document.getElementById('vdmShortageNotesBox');
    if (d.shortage_notes) {
        document.getElementById('vdmShortageNotesText').textContent = d.shortage_notes;
        shortageBox.style.display = 'block';
    } else {
        shortageBox.style.display = 'none';
    }

    document.getElementById('vdmTransport').textContent = d.transport_formatted || '—';
    document.getElementById('vdmFinalPayout').textContent = d.final_payout_formatted || d.transport_formatted || '—';
    document.getElementById('vdmPayoutDiff').textContent = d.payout_diff_formatted || '—';

    if (document.getElementById('vdmDiesel')) document.getElementById('vdmDiesel').textContent = d.diesel_formatted || '—';
    if (document.getElementById('vdmMileage')) document.getElementById('vdmMileage').textContent = d.mileage_formatted || '—';
    if (document.getElementById('vdmExtra')) document.getElementById('vdmExtra').textContent = d.extra_formatted || '—';
    
    const balEl = document.getElementById('vdmBalance');
    if (balEl) {
        balEl.textContent = d.balance_formatted || '—';
        balEl.style.color = (Number(d.balance || 0) >= 0) ? 'var(--green)' : 'var(--red)';
    }

    if (document.getElementById('vdmBreakdownNotes')) {
        document.getElementById('vdmBreakdownNotes').textContent = d.breakdown_notes || 'No transit notes recorded.';
    }

    // Custom fields
    const cfBox = document.getElementById('vdmCustomFieldsBox');
    const cfList = document.getElementById('vdmCustomFieldsList');
    cfList.innerHTML = '';
    if (d.custom_fields && Object.keys(d.custom_fields).length > 0) {
        let hasVisible = false;
        for (const [key, val] of Object.entries(d.custom_fields)) {
            if (val !== null && val !== undefined && val !== '') {
                hasVisible = true;
                const row = document.createElement('div');
                row.style = 'display:flex;justify-content:space-between;border-bottom:1px dashed var(--border);padding-bottom:4px;';
                row.innerHTML = `<span style="color:var(--text-3);font-weight:700;">${key.replace(/_/g, ' ')}:</span> <b>${val}</b>`;
                cfList.appendChild(row);
            }
        }
        cfBox.style.display = hasVisible ? 'block' : 'none';
    } else {
        cfBox.style.display = 'none';
    }

    // Actions
    document.getElementById('vdmEditBtn').onclick = function() {
        document.getElementById('viewDispatchModal').classList.remove('active');
        startFleetInlineEdit(d.id);
    };

    if (document.getElementById('vdmAddFuelStopBtn')) {
        document.getElementById('vdmAddFuelStopBtn').onclick = function() {
            document.getElementById('viewDispatchModal').classList.remove('active');
            openDieselLogModal(d.id);
        };
    }
    loadVdmFuelStops(d.id);

    document.getElementById('viewDispatchModal').classList.add('active');
};

window.openConfirmDeliveryModal = function(data) {
    document.getElementById('confirmDeliveryForm').action = '<?= url("fleet/confirm-delivery") ?>/' + data.id;
    document.getElementById('cdmTripNumber').textContent = data.trip_number;
    document.getElementById('cdmTruckDriver').textContent = data.truck + ' • ' + (data.driver || 'Driver');
    document.getElementById('cdmDestClient').textContent = (data.destination || '—') + ' • ' + (data.client || 'Consignee');
    document.getElementById('cdmLoadedLitres').textContent = Number(data.loaded_litres).toLocaleString() + ' L';
    document.getElementById('cdmDeliveredInput').value = data.loaded_litres;
    document.getElementById('cdmShortageInput').value = 0;
    
    // Set default payout in active currency
    const isKes = ('<?= current_currency() ?>' === 'KES');
    const exRate = <?= exchange_rate() ?>;
    const basePayout = Number(data.transport_amount || 0);
    const displayedPayout = isKes ? (basePayout * exRate).toFixed(2) : basePayout.toFixed(2);
    document.getElementById('cdmPayoutInput').value = displayedPayout;

    const modal = document.getElementById('confirmDeliveryModal');
    modal.dataset.loaded = data.loaded_litres;
    modal.dataset.unitPrice = data.unit_price || (data.loaded_litres > 0 ? (data.transport_amount / data.loaded_litres) : 0);
    modal.dataset.transport = data.transport_amount;
    recalcDeliveryModal();
    modal.classList.add('active');
};

window.recalcDeliveryFromShortage = function() {
    const modal = document.getElementById('confirmDeliveryModal');
    const loaded = Number(modal.dataset.loaded || 0);
    const unitPrice = Number(modal.dataset.unitPrice || 0);
    const isKes = ('<?= current_currency() ?>' === 'KES');
    const exRate = <?= exchange_rate() ?>;
    const effUnitPrice = isKes ? (unitPrice * exRate) : unitPrice;

    const shortage = Math.max(0, Number(document.getElementById('cdmShortageInput').value || 0));
    const delivered = Math.max(0, loaded - shortage);
    document.getElementById('cdmDeliveredInput').value = delivered;

    if (effUnitPrice > 0) {
        document.getElementById('cdmPayoutInput').value = (delivered * effUnitPrice).toFixed(2);
    }
    recalcDeliveryModal();
};

window.recalcDeliveryModal = function() {
    const modal = document.getElementById('confirmDeliveryModal');
    const loaded = Number(modal.dataset.loaded || 0);
    const delivered = Number(document.getElementById('cdmDeliveredInput').value || 0);
    const unitPrice = Number(modal.dataset.unitPrice || 0);
    const isKes = ('<?= current_currency() ?>' === 'KES');
    const exRate = <?= exchange_rate() ?>;
    const effUnitPrice = isKes ? (unitPrice * exRate) : unitPrice;

    const banner = document.getElementById('cdmShortageBanner');
    const loss = loaded - delivered;

    if (loss > 0) {
        document.getElementById('cdmShortageInput').value = loss;
        if (effUnitPrice > 0) {
            document.getElementById('cdmPayoutInput').value = (delivered * effUnitPrice).toFixed(2);
        }
        banner.style.display = 'block';
        banner.style.background = 'var(--red-soft)';
        banner.style.border = '1px solid var(--red)';
        banner.style.color = 'var(--red)';
        const diffAmt = loss * effUnitPrice;
        banner.innerHTML = `⚠️ Shortage: -${loss.toLocaleString()} L. Client payout automatically reduced by ${CURRENCY_SYM} ${diffAmt.toFixed(2)}. This loss is forwarded to driver salary deductions.`;
    } else if (loss < 0) {
        document.getElementById('cdmShortageInput').value = 0;
        banner.style.display = 'block';
        banner.style.background = 'var(--green-soft)';
        banner.style.border = '1px solid var(--green)';
        banner.style.color = 'var(--green)';
        banner.textContent = '✓ Surplus: +' + Math.abs(loss).toLocaleString() + ' Litres over loaded volume.';
    } else {
        document.getElementById('cdmShortageInput').value = 0;
        banner.style.display = 'block';
        banner.style.background = 'var(--green-soft)';
        banner.style.border = '1px solid var(--green)';
        banner.style.color = 'var(--green)';
        banner.textContent = '✓ 100% Full Offload: 0 Litres loss.';
    }
};

function onDriverSelected(val) {
    const grp = document.getElementById('otherDriverGroup');
    const input = document.getElementById('otherDriverInput');
    if (val === '__other__') {
        grp.style.display = 'block';
        input.required = true;
        input.focus();
    } else {
        grp.style.display = 'none';
        input.required = false;
    }
}

window.openTripExpenseModal = function(data) {
    document.getElementById('temTripId').value = data.id;
    document.getElementById('temTripRef').textContent = data.trip_number || '—';
    document.getElementById('temTruck').textContent = data.truck || '—';

    const isKes = ('<?= current_currency() ?>' === 'KES');
    const exRate = <?= exchange_rate() ?>;
    const baseMileage = Number(data.mileage_cost || 0);
    const baseExtra = Number(data.extra_expenses || 0);
    const baseDiesel = Number(data.diesel || 0);

    document.getElementById('temMileageCost').value = isKes ? (baseMileage * exRate).toFixed(2) : baseMileage.toFixed(2);
    document.getElementById('temDiesel').value = isKes ? (baseDiesel * exRate).toFixed(2) : baseDiesel.toFixed(2);

    document.getElementById('tripExpenseModal').classList.add('active');
};

window.saveTripExpenses = async function(e) {
    e.preventDefault();
    const id = document.getElementById('temTripId').value;
    const mileage = document.getElementById('temMileageCost').value;
    const diesel = document.getElementById('temDiesel').value;
    const btn = document.getElementById('temSubmitBtn');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    try {
        const res = await fetch('<?= url("fleet/inline-update") ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                _csrf_token: CSRF_TOKEN,
                id: id,
                mileage_cost: mileage,
                diesel: diesel
            }).toString()
        });
        const json = await res.json();
        if (json.success && json.data) {
            const d = json.data;
            const row = document.getElementById('fleet-row-' + id);
            if (row) {
                row.dataset.mileageCost = d.mileage_cost;
                row.dataset.extraExpenses = d.extra_expenses;
                row.dataset.diesel = d.diesel;

                if (row.querySelector('.cell-diesel')) {
                    row.querySelector('.cell-diesel').innerHTML = `<span class="view-val">${parseFloat(d.diesel) > 0 ? d.diesel_formatted : '—'}</span>`;
                }

                let costsHtml = `<div class="val-mileage" style="color:var(--amber);font-weight:700;font-size:13.5px;">${d.mileage_formatted}</div>`;
                if (parseFloat(d.extra_expenses) > 0) {
                    costsHtml += `<div class="val-extra" style="color:var(--red);font-size:12px;font-weight:600;margin-top:1px;">+ ${d.extra_formatted} fix</div>`;
                }
                row.querySelector('.cell-costs').innerHTML = costsHtml;
                row.querySelector('.cell-balance').innerHTML = `<span class="view-val" style="color:${d.balance >= 0 ? 'var(--green)' : 'var(--red)'};">${d.balance_formatted}</span>`;

                row.style.transition = 'background .4s';
                row.style.background = 'var(--green-soft)';
                setTimeout(() => { row.style.background = ''; }, 1200);
            }
            document.getElementById('tripExpenseModal').classList.remove('active');
        } else {
            alert('Could not update expenses: ' + (json.error || 'Unknown error'));
        }
    } catch (err) {
        console.error(err);
        alert('Network error while saving trip expenses.');
    } finally {
        btn.disabled = false;
        btn.textContent = '✓ Update Trip Expenses';
    }
};

function onTruckSelected() {
    const sel = document.getElementById('dispatchTruckSelect');
    if (!sel || sel.selectedIndex < 0) return;
    const opt = sel.options[sel.selectedIndex];
    const notice = document.getElementById('truckOwnershipNotice');
    const commGroup = document.getElementById('commissionGroup');
    const commInput = document.getElementById('dispCommission');
    const balLabel = document.getElementById('balanceLabel');

    if (opt && opt.dataset.capacity) {
        const capInput = document.getElementById('dispatchTruckCapacity');
        if (capInput) capInput.value = opt.dataset.capacity;
        const loadedInput = document.getElementById('dispatchLoadedLitres');
        if (loadedInput) loadedInput.value = opt.dataset.capacity;
        isSubcontractedTruck = (opt.dataset.subcontracted === '1');

        if (isSubcontractedTruck) {
            if (notice) notice.style.display = 'block';
            if (commGroup) commGroup.style.display = 'block';
            if (commInput) commInput.value = opt.dataset.commission || '0';
            const subText = document.getElementById('subcontractedDetailsText');
            if (subText) {
                subText.textContent = 'Owner: ' + (opt.dataset.owner || 'Contract Haulier') + ' • Only agreed company commission acts as profit.';
            }
            if (balLabel) balLabel.textContent = 'Agreed Company Commission';
        } else {
            if (notice) notice.style.display = 'none';
            if (commGroup) commGroup.style.display = 'none';
            if (balLabel) balLabel.textContent = 'Estimated Net Profit (Balance)';
        }
        calcExpectedTransport();
    }
}
window.onTruckSelected = onTruckSelected;

window.onTransportUsdInput = function(usdVal) {
    const usd = parseFloat(usdVal) || 0;
    const kes = usd * SYS_EX_RATE;
    const kesInput = document.getElementById('dispatchTransportKes');
    if (kesInput) kesInput.value = kes > 0 ? Math.round(kes) : '';
    syncTransportPayment(usd, kes);
};

window.onTransportKesInput = function(kesVal) {
    const kes = parseFloat(kesVal) || 0;
    const usd = SYS_EX_RATE > 0 ? (kes / SYS_EX_RATE) : 0;
    const usdInput = document.getElementById('dispatchTransportUsd');
    if (usdInput) usdInput.value = usd > 0 ? usd.toFixed(2) : '';
    syncTransportPayment(usd, kes);
};

function syncTransportPayment(usd, kes) {
    const dispTransport = document.getElementById('dispTransport');
    if (dispTransport) {
        dispTransport.value = IS_KES ? kes.toFixed(2) : usd.toFixed(2);
    }
    const loaded = parseFloat(document.getElementById('dispatchLoadedLitres')?.value) || 0;
    const unitPriceEl = document.getElementById('dispatchUnitPrice');
    if (unitPriceEl) {
        unitPriceEl.value = (loaded > 0) ? ((IS_KES ? kes : usd) / loaded).toFixed(4) : '0.00';
    }

    const dispFinal = document.getElementById('dispFinalPayout');
    if (dispFinal) {
        dispFinal.value = IS_KES ? kes.toFixed(2) : usd.toFixed(2);
    }

    const bigDisp = document.getElementById('dispTransportBigDisplay');
    if (bigDisp) {
        bigDisp.textContent = '$ ' + usd.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    const altDisp = document.getElementById('dispTransportAltDisplay');
    if (altDisp) {
        altDisp.textContent = '≈ KES ' + kes.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});
    }
    const formulaDisp = document.getElementById('dispTransportFormulaText');
    if (formulaDisp) {
        formulaDisp.textContent = `Agreed payment for hauling ${loaded.toLocaleString()} L`;
    }

    calcBalance();
}

window.onDestinationChanged = function(destVal) {
    if (!destVal) return;
    const dList = document.getElementById('destPresets');
    if (!dList) return;
    const opt = Array.from(dList.options).find(o => o.value.toLowerCase() === destVal.trim().toLowerCase());
    if (opt && (opt.dataset.kes || opt.dataset.usd)) {
        const kes = parseFloat(opt.dataset.kes) || 0;
        const usd = parseFloat(opt.dataset.usd) || (SYS_EX_RATE > 0 ? kes / SYS_EX_RATE : 0);
        const milKes = document.getElementById('dispMileageKes');
        const milUsd = document.getElementById('dispMileageUsd');
        if (milKes) milKes.value = kes > 0 ? Math.round(kes) : '';
        if (milUsd) milUsd.value = usd > 0 ? usd.toFixed(2) : '';
        syncMileageFields(kes, usd);
    }
};

function calcExpectedTransport() {
    const usdVal = parseFloat(document.getElementById('dispatchTransportUsd')?.value) || 0;
    const kesVal = parseFloat(document.getElementById('dispatchTransportKes')?.value) || (usdVal * SYS_EX_RATE);
    syncTransportPayment(usdVal, kesVal);
}
window.calcExpectedTransport = calcExpectedTransport;

/* =========================================================================
   CORRIDOR MILEAGE RATES MODAL & AJAX CRUD
   ========================================================================= */
window.openMileageRatesModal = function() {
    resetMileageRateForm();
    document.getElementById('mileageRatesModal')?.classList.add('active');
};

window.closeMileageRatesModal = function() {
    document.getElementById('mileageRatesModal')?.classList.remove('active');
};

window.onMrKesInput = function(kesVal) {
    const kes = parseFloat(kesVal) || 0;
    const usd = SYS_EX_RATE > 0 ? (kes / SYS_EX_RATE) : 0;
    const usdEl = document.getElementById('mrAllowanceUsd');
    if (usdEl) usdEl.value = usd > 0 ? usd.toFixed(2) : '';
};

window.onMrUsdInput = function(usdVal) {
    const usd = parseFloat(usdVal) || 0;
    const kes = usd * SYS_EX_RATE;
    const kesEl = document.getElementById('mrAllowanceKes');
    if (kesEl) kesEl.value = kes > 0 ? Math.round(kes) : '';
};

window.resetMileageRateForm = function() {
    const form = document.getElementById('mileageRateForm');
    if (form) form.reset();
    const idEl = document.getElementById('mrRateId');
    if (idEl) idEl.value = '';
    const titleEl = document.getElementById('mrFormTitle');
    if (titleEl) titleEl.textContent = '➕ Add Corridor Destination Rate';
    const cancelBtn = document.getElementById('mrCancelEditBtn');
    if (cancelBtn) cancelBtn.style.display = 'none';
    const btn = document.getElementById('mrSubmitBtn');
    if (btn) btn.textContent = '💾 Save Corridor Rate';
    const org = document.getElementById('mrOrigin');
    if (org) org.value = 'Eldoret';
};

window.editMileageRate = function(rate) {
    document.getElementById('mrRateId').value = rate.id;
    document.getElementById('mrOrigin').value = rate.origin || 'Eldoret';
    document.getElementById('mrDestination').value = rate.destination || '';
    document.getElementById('mrDistanceKm').value = rate.distance_km || '';
    document.getElementById('mrAllowanceKes').value = Math.round(rate.standard_allowance_kes || 0);
    document.getElementById('mrAllowanceUsd').value = parseFloat(rate.standard_allowance_usd || 0).toFixed(2);
    document.getElementById('mrNotes').value = rate.notes || '';

    const titleEl = document.getElementById('mrFormTitle');
    if (titleEl) titleEl.textContent = `✏️ Editing Rate: ${rate.origin} ➔ ${rate.destination}`;
    const cancelBtn = document.getElementById('mrCancelEditBtn');
    if (cancelBtn) cancelBtn.style.display = 'inline-block';
    const btn = document.getElementById('mrSubmitBtn');
    if (btn) btn.textContent = '✓ Update Corridor Rate';

    document.getElementById('mrDestination')?.focus();
};

window.handleMileageRateSubmit = async function(e) {
    if (e) e.preventDefault();
    const id = document.getElementById('mrRateId')?.value;
    const isEdit = Boolean(id);
    const url = isEdit ? ('<?= url("fleet/mileage-rates/update") ?>/' + id) : '<?= url("fleet/mileage-rates/store") ?>';

    const origin = document.getElementById('mrOrigin')?.value.trim() || 'Eldoret';
    const dest = document.getElementById('mrDestination')?.value.trim();
    const km = document.getElementById('mrDistanceKm')?.value;
    const kes = document.getElementById('mrAllowanceKes')?.value;
    const usd = document.getElementById('mrAllowanceUsd')?.value;
    const notes = document.getElementById('mrNotes')?.value.trim();

    if (!dest) {
        alert('Please specify the destination corridor.');
        return false;
    }
    if (!kes || parseFloat(kes) <= 0) {
        alert('Please specify standard allowance in KES.');
        return false;
    }

    const payload = new URLSearchParams({
        _csrf_token: CSRF_TOKEN,
        origin: origin,
        destination: dest,
        distance_km: km,
        standard_allowance_kes: kes,
        standard_allowance_usd: usd,
        notes: notes
    });

    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: payload.toString()
        });
        const json = await res.json();
        if (json.success) {
            resetMileageRateForm();
            refreshMileageRatesTable(json.rates || []);
            const fb = document.getElementById('mrFeedbackBanner');
            if (fb) {
                fb.style.display = 'block';
                fb.textContent = isEdit ? '✓ Corridor rate updated successfully!' : '✓ New corridor rate saved!';
                setTimeout(() => { if (fb) fb.style.display = 'none'; }, 4000);
            }
        } else {
            alert('Error: ' + (json.error || 'Could not save rate.'));
        }
    } catch(err) {
        console.error(err);
        alert('Network error saving corridor mileage rate.');
    }
    return false;
};

window.deleteMileageRate = async function(id) {
    if (!confirm('Are you sure you want to delete this corridor mileage preset?')) return;
    try {
        const res = await fetch('<?= url("fleet/mileage-rates/delete") ?>/' + id, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ _csrf_token: CSRF_TOKEN }).toString()
        });
        const json = await res.json();
        if (json.success) {
            refreshMileageRatesTable(json.rates || []);
        } else {
            alert('Could not delete rate: ' + (json.error || 'Server error'));
        }
    } catch(err) {
        console.error(err);
        alert('Network error deleting rate.');
    }
};

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function refreshMileageRatesTable(rates) {
    const tbody = document.getElementById('mrTableBody');
    const badge = document.getElementById('mrTableCountBadge');
    const datalist = document.getElementById('destPresets');
    if (badge) badge.textContent = `${rates.length} routes`;
    if (!tbody) return;

    if (rates.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-3);">No corridor rates configured yet.</td></tr>';
        return;
    }

    tbody.innerHTML = rates.map(r => `
        <tr id="mr-row-${r.id}">
            <td style="padding:9px 12px;font-weight:700;">
                <span>${escapeHtml(r.origin)} ➔ <span style="color:var(--brand);">${escapeHtml(r.destination)}</span></span>
            </td>
            <td style="padding:9px 12px;text-align:right;color:var(--text-2);">${r.distance_km ? Number(r.distance_km).toLocaleString() + ' km' : '—'}</td>
            <td style="padding:9px 12px;text-align:right;font-weight:800;color:var(--amber);">KES ${Number(r.standard_allowance_kes).toLocaleString()}</td>
            <td style="padding:9px 12px;text-align:right;font-weight:800;color:var(--brand);">$ ${Number(r.standard_allowance_usd).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
            <td style="padding:9px 12px;color:var(--text-3);font-size:12px;">${escapeHtml(r.notes || '—')}</td>
            <td style="padding:9px 12px;text-align:center;">
                <div style="display:inline-flex;gap:4px;">
                    <button type="button" class="btn btn-ghost btn-xs" onclick='editMileageRate(${JSON.stringify(r)})' title="Edit Rate">✏️</button>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="deleteMileageRate(${r.id})" style="color:var(--red);" title="Delete Rate">🗑️</button>
                </div>
            </td>
        </tr>
    `).join('');

    if (datalist) {
        datalist.innerHTML = rates.map(r => `
            <option value="${escapeHtml(r.destination)}" data-kes="${r.standard_allowance_kes}" data-usd="${r.standard_allowance_usd}" data-km="${r.distance_km}">
                ${escapeHtml(r.destination)} (${Number(r.distance_km || 0).toLocaleString()} km • KES ${Number(r.standard_allowance_kes).toLocaleString()})
            </option>
        `).join('');
    }
}

function calcDieselExpense() {
    const litres = parseFloat(document.getElementById('dispDieselLitres')?.value) || 0;
    const unitPrice = parseFloat(document.getElementById('dispDieselUnitPrice')?.value) || 0;
    const total = litres * unitPrice;
    const dispDiesel = document.getElementById('dispDiesel');
    if (dispDiesel) {
        dispDiesel.value = total > 0 ? total.toFixed(2) : '0.00';
    }
    calcBalance();
}

function calcBalance() {
    const transport = parseFloat(document.getElementById('dispTransport')?.value) || 0;
    const finalPInput = document.getElementById('dispFinalPayout')?.value;
    const finalPayout = (finalPInput !== undefined && finalPInput !== '') ? (parseFloat(finalPInput) || 0) : transport;
    const mileage = parseFloat(document.getElementById('dispMileage')?.value) || 0;
    const diesel = parseFloat(document.getElementById('dispDiesel')?.value) || 0;
    const comm = parseFloat(document.getElementById('dispCommission')?.value) || 0;

    const loaded = parseFloat(document.getElementById('dispatchLoadedLitres')?.value) || 0;
    const shortage = Math.max(0, parseFloat(document.getElementById('dispatchShortageLitres')?.value) || 0);
    const payoutDiff = transport - finalPayout;

    let bal = 0;
    if (isSubcontractedTruck) {
        bal = comm;
    } else {
        // Owner expenses strictly Diesel + Mileage deducted from final client payout
        bal = finalPayout - (mileage + diesel);
    }

    const disp = document.getElementById('liveBalanceDisplay');
    if (disp) {
        disp.textContent = CURRENCY_SYM + ' ' + bal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        disp.style.color = bal >= 0 ? 'var(--green)' : 'var(--red)';
    }

    const notice = document.getElementById('payoutDiffNotice');
    if (notice) {
        let msg = '';
        if (payoutDiff > 0 || shortage > 0) {
            msg += `<span style="color:var(--red);font-weight:700;">Shortage Loss: ${shortage.toLocaleString()} Litres • -${CURRENCY_SYM} ${payoutDiff.toFixed(2)} deduction (Forwarded to Driver Salary)</span>`;
        } else {
            msg += `<span style="color:var(--green);font-weight:700;">Full Payout • 0 Litres Shortage</span>`;
        }
        if (diesel > 0) {
            msg += ` <span style="color:var(--amber);font-size:12px;font-weight:700;margin-left:8px;">(Includes -${CURRENCY_SYM} ${diesel.toFixed(2)} Owner Diesel)</span>`;
        }
        notice.innerHTML = msg;
    }
}

/* Time Horizon & Day-of-Week Period Filter */
let activePeriodFilter = 'all';
let activeDayFilter = 'all';

function setTimeHorizonFilter(period, btn) {
    activePeriodFilter = period;
    document.querySelectorAll('.time-filter-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    const subToolbar = document.getElementById('weekDaysSubToolbar');
    if (period === 'week') {
        subToolbar.style.display = 'inline-flex';
    } else {
        subToolbar.style.display = 'none';
        activeDayFilter = 'all';
        document.querySelectorAll('.day-filter-btn').forEach(b => b.classList.remove('active'));
        document.querySelector('.day-filter-btn[data-day="all"]')?.classList.add('active');
    }
    applyFleetFilters();
}

function setWeekDayFilter(day, btn) {
    activeDayFilter = day;
    document.querySelectorAll('.day-filter-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    applyFleetFilters();
}

function matchesPeriod(dateStr) {
    if (activePeriodFilter === 'all' || !dateStr) return true;

    const parts = dateStr.split('-');
    if (parts.length !== 3) return true;
    const y = parseInt(parts[0], 10);
    const m = parseInt(parts[1], 10) - 1;
    const d = parseInt(parts[2], 10);
    const rowDate = new Date(y, m, d);

    const today = new Date();
    const currentYear = today.getFullYear();
    const currentMonth = today.getMonth();

    if (activePeriodFilter === 'year') {
        return y === currentYear;
    }
    if (activePeriodFilter === 'month') {
        return y === currentYear && m === currentMonth;
    }
    if (activePeriodFilter === 'week') {
        const dayOfWeek = today.getDay();
        const diffToMon = (dayOfWeek === 0 ? -6 : 1) - dayOfWeek;
        const monday = new Date(today.getFullYear(), today.getMonth(), today.getDate() + diffToMon);
        monday.setHours(0, 0, 0, 0);
        const sunday = new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() + 6);
        sunday.setHours(23, 59, 59, 999);

        const inWeek = (rowDate >= monday && rowDate <= sunday);
        if (!inWeek) return false;
        if (activeDayFilter === 'all') return true;

        return rowDate.getDay() == parseInt(activeDayFilter, 10);
    }
    return true;
}

/* Compact Search, Status & Time Horizon Filter */
function applyFleetFilters() {
    const searchVal = (document.getElementById('fleetTableSearch')?.value || '').toLowerCase().trim();
    const statusVal = (document.getElementById('fleetStatusFilter')?.value || 'all').toLowerCase();
    const truckVal = (document.getElementById('fleetTruckFilter')?.value || 'all').toLowerCase().trim();
    const rows = document.querySelectorAll('#fleetTable tbody tr[id^="fleet-row-"]');

    let visibleCount = 0;
    rows.forEach(r => {
        const text = r.textContent.toLowerCase();
        const rowStatus = (r.querySelector('.cell-status')?.textContent || '').toLowerCase();
        const rowTruck = (r.dataset.truck || '').toLowerCase();
        const rowDate = r.dataset.dispatchDate || '';

        const matchesSearch = !searchVal || text.includes(searchVal);
        const matchesStatus = (statusVal === 'all') || rowStatus.includes(statusVal);
        const matchesTruck = (truckVal === 'all') || rowTruck.includes(truckVal);
        const matchesDate = matchesPeriod(rowDate);

        if (matchesSearch && matchesStatus && matchesTruck && matchesDate) {
            r.dataset.matchedFilter = 'true';
            visibleCount++;
        } else {
            r.dataset.matchedFilter = 'false';
        }
    });

    const countSpan = document.getElementById('fleetVisibleCount');
    if (countSpan) countSpan.textContent = visibleCount;

    if (window.fleetPagination) {
        window.fleetPagination.refresh();
    }
}

document.getElementById('fleetTableSearch')?.addEventListener('input', applyFleetFilters);
document.getElementById('fleetTruckFilter')?.addEventListener('change', applyFleetFilters);

/* Fleet Export Modal Helpers */
function openFleetExportModal() {
    const activeTruck = document.getElementById('fleetTruckFilter')?.value || 'all';
    const exportTruckSelect = document.getElementById('fleetExportTruckSelect');
    if (exportTruckSelect) {
        if (activeTruck !== 'all') {
            exportTruckSelect.value = activeTruck;
        } else {
            exportTruckSelect.value = '';
        }
    }
    updateExportSummaryNote();
    document.getElementById('fleetExportModal')?.classList.add('active');
}

function setExportPreset(type) {
    const now = new Date();
    const monthSelect = document.getElementById('fleetExportMonthSelect');
    const yearSelect = document.getElementById('fleetExportYearSelect');
    if (!monthSelect || !yearSelect) return;

    if (type === 'this_month') {
        monthSelect.value = (now.getMonth() + 1).toString();
        yearSelect.value = now.getFullYear().toString();
    } else if (type === 'this_year') {
        monthSelect.value = '';
        yearSelect.value = now.getFullYear().toString();
    } else if (type === 'all_time') {
        monthSelect.value = '';
        yearSelect.value = '';
    }
    updateExportSummaryNote();
}

function updateExportSummaryNote() {
    const truckSelect = document.getElementById('fleetExportTruckSelect');
    const monthSelect = document.getElementById('fleetExportMonthSelect');
    const yearSelect = document.getElementById('fleetExportYearSelect');
    const bannerText = document.getElementById('exportScopeText');
    if (!bannerText) return;

    const truckText = (truckSelect && truckSelect.value) ? truckSelect.options[truckSelect.selectedIndex].text.split('(')[0].trim() : 'All Vehicles / Tankers';
    
    let periodText = 'All Time';
    const monthVal = monthSelect?.value;
    const yearVal = yearSelect?.value;
    const monthName = (monthSelect && monthVal && monthSelect.selectedIndex >= 0) ? monthSelect.options[monthSelect.selectedIndex].text : '';

    if (monthVal && yearVal) {
        periodText = `${monthName} ${yearVal}`;
    } else if (yearVal) {
        periodText = `Full Year ${yearVal}`;
    } else if (monthVal) {
        periodText = `Every ${monthName} (All Years)`;
    }

    bannerText.textContent = `${truckText} • ${periodText}`;
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateExportSummaryNote);
} else {
    updateExportSummaryNote();
}

// Initialize Fleet Table Pagination
const fleetPagination = initTablePagination({
    tableId: 'fleetTable',
    footerId: 'fleetPagination',
    defaultPageSize: 10,
    pageSizes: [10, 25, 50, 100],
    countSpanId: 'fleetVisibleCount',
    rowSelector: '#fleetTable tbody tr[id^="fleet-row-"]'
});
window.fleetPagination = fleetPagination;

/* Inline Row Editing */
const rowOriginalHtml = {};

function startFleetInlineEdit(id) {
    const row = document.getElementById('fleet-row-' + id);
    if (!row || row.classList.contains('tr-editing')) return;

    rowOriginalHtml[id] = row.innerHTML;
    row.classList.add('tr-editing');

    // Extract current raw data safely
    const dolDate = row.dataset.dispatchDate || '';
    const driverName = row.querySelector('.cell-driver .view-val')?.textContent.trim() || '';
    const currentStatus = row.querySelector('.cell-status .view-val')?.textContent.trim() || 'Delivered';
    const currentDest = row.querySelector('.view-val-destination')?.textContent.trim() || '';
    const currentClient = row.querySelector('.view-val-client')?.textContent.trim() || '';

    const loadedText = row.querySelector('.view-val-loaded')?.textContent || '0';
    const loadedLitres = parseInt(loadedText.replace(/[^0-9]/g, ''), 10) || 0;

    const delivElem = row.querySelector('.view-val-delivered');
    const deliveredLitres = delivElem ? (parseInt(delivElem.textContent.replace(/[^0-9]/g, ''), 10) || loadedLitres) : loadedLitres;

    const transportText = row.querySelector('.cell-transport .view-val')?.textContent || '0';
    const transportRaw = parseFloat(transportText.replace(/[^0-9.]/g, '')) || 0;

    const payoutElem = row.querySelector('.cell-final-payout .view-val');
    const finalPayoutRaw = payoutElem ? (parseFloat(payoutElem.textContent.replace(/[^0-9.]/g, '')) || transportRaw) : transportRaw;
    const mileageRaw = parseFloat(row.dataset.mileageCost || '0');
    const extraRaw = parseFloat(row.dataset.extraExpenses || '0');
    const dieselRaw = parseFloat(row.dataset.diesel || '0');

    // Replace cells with inputs
    row.querySelector('.cell-dol').innerHTML = `<input type="date" class="table-inline-input inline-dol" value="${dolDate}">`;

    let driverOptionsHtml = DRIVER_OPTIONS.map(d => `<option value="${d}" ${d === driverName ? 'selected' : ''}>${d}</option>`).join('');
    row.querySelector('.cell-driver').innerHTML = `<select class="table-inline-select inline-driver">${driverOptionsHtml}</select>`;

    row.querySelector('.cell-status').innerHTML = `
        <select class="table-inline-select inline-status">
            <option value="Planned" ${currentStatus.includes('Plan') ? 'selected' : ''}>Planned</option>
            <option value="Loading" ${currentStatus.includes('Load') ? 'selected' : ''}>Loading</option>
            <option value="In Transit" ${currentStatus.includes('Transit') ? 'selected' : ''}>In Transit</option>
            <option value="Delivered" ${currentStatus.includes('Deliver') ? 'selected' : ''}>Delivered</option>
        </select>
    `;

    row.querySelector('.cell-route').innerHTML = `
        <div style="font-size:11px;color:var(--text-3);font-weight:700;">Destination:</div>
        <input list="destPresets" class="table-inline-input inline-destination" value="${currentDest}" style="margin-bottom:3px;font-size:12px;">
        <div style="font-size:11px;color:var(--text-3);font-weight:700;">Client:</div>
        <input list="customersList" class="table-inline-input inline-client" value="${currentClient}" style="font-size:12px;">
        <div style="font-size:11px;color:var(--text-3);margin-top:2px;"><b>From: Eldoret</b></div>
    `;

    row.querySelector('.cell-litres').innerHTML = `
        <div style="font-size:11px;color:var(--text-3);">Loaded:</div>
        <input type="number" class="table-inline-input inline-loaded" value="${loadedLitres}" style="margin-bottom:3px;">
        <div style="font-size:11px;color:var(--text-3);">Delivered:</div>
        <input type="number" class="table-inline-input inline-delivered" value="${deliveredLitres}">
    `;

    const shortageVal = parseInt(row.dataset.shortageLitres || '0', 10) || Math.max(0, loadedLitres - deliveredLitres);
    if (row.querySelector('.cell-shortage')) {
        row.querySelector('.cell-shortage').innerHTML = `
            <input type="number" class="table-inline-input inline-shortage" value="${shortageVal}" style="width:65px;text-align:center;">
        `;
    }

    row.querySelector('.cell-diesel').innerHTML = `
        <input type="number" step="0.01" class="table-inline-input inline-diesel" value="${dieselRaw > 0 ? dieselRaw : ''}" placeholder="${CURRENCY_SYM}" style="width:75px;">
    `;

    row.querySelector('.cell-transport').innerHTML = `
        <input type="number" step="0.01" class="table-inline-input inline-transport" value="${transportRaw}">
    `;

    row.querySelector('.cell-final-payout').innerHTML = `
        <input type="number" step="0.01" class="table-inline-input inline-final-payout" value="${finalPayoutRaw}">
    `;

    row.querySelector('.cell-costs').innerHTML = `
        <div style="font-size:10px;color:var(--text-3);font-weight:700;">Mileage:</div>
        <input type="number" step="0.01" class="table-inline-input inline-mileage" value="${mileageRaw}" style="width:85px;">
    `;

    // Toggle actions buttons
    row.querySelector('.row-normal-actions').style.display = 'none';
    row.querySelector('.row-editing-actions').style.display = 'inline-flex';
}

function cancelFleetInlineEdit(id) {
    const row = document.getElementById('fleet-row-' + id);
    if (!row || !rowOriginalHtml[id]) return;
    row.innerHTML = rowOriginalHtml[id];
    row.classList.remove('tr-editing');
    delete rowOriginalHtml[id];
}

async function saveFleetInlineEdit(id) {
    const row = document.getElementById('fleet-row-' + id);
    if (!row) return;

    const dol = row.querySelector('.inline-dol')?.value;
    const driver = row.querySelector('.inline-driver')?.value;
    const status = row.querySelector('.inline-status')?.value;
    const destination = row.querySelector('.inline-destination')?.value;
    const clientName = row.querySelector('.inline-client')?.value;
    const loadedLitres = row.querySelector('.inline-loaded')?.value;
    const deliveredLitres = row.querySelector('.inline-delivered')?.value;
    const shortageLitres = row.querySelector('.inline-shortage')?.value;
    const diesel = row.querySelector('.inline-diesel')?.value;
    const transport = row.querySelector('.inline-transport')?.value;
    const finalPayout = row.querySelector('.inline-final-payout')?.value;
    const mileageCost = row.querySelector('.inline-mileage')?.value;
    const extraExpenses = 0;

    const postData = {
        _csrf_token: CSRF_TOKEN,
        id: id,
        dispatch_date: dol,
        driver: driver,
        status: status,
        destination: destination,
        client_name: clientName,
        loaded_litres: loadedLitres,
        delivered_litres: deliveredLitres,
        shortage_litres: shortageLitres,
        diesel: diesel,
        transport_amount: transport,
        final_payout: finalPayout,
        mileage_cost: mileageCost,
        extra_expenses: extraExpenses
    };

    const isOffline = !navigator.onLine || (typeof isSimulatedOffline !== 'undefined' && isSimulatedOffline);

    if (isOffline) {
        queueOfflineAction('<?= url("fleet/inline-update") ?>', postData, () => {
            // Apply updates locally to DOM
            row.dataset.dispatchDate = dol;
            row.dataset.diesel = diesel || 0;
            row.dataset.mileageCost = mileageCost || 0;
            row.dataset.extraExpenses = extraExpenses || 0;
            row.dataset.shortageLitres = shortageLitres || 0;

            const diff = (parseFloat(transport) || 0) - (parseFloat(finalPayout) || 0);
            const lossL = Math.max(0, (parseInt(loadedLitres) || 0) - (parseInt(deliveredLitres) || 0));

            row.querySelector('.cell-dol').innerHTML = `<span class="view-val">${formatDateClient(dol)}</span><div class="offline-sync-badge">Pending Sync</div>`;
            row.querySelector('.cell-driver').innerHTML = `<span class="view-val">${driver}</span>`;
            row.querySelector('.cell-status').innerHTML = `<span class="status s-done view-val"><i></i>${status}</span>`;

            let flag = '🇰🇪';
            const dLow = (destination || '').toLowerCase();
            if (dLow.includes('congo') || dLow.includes('goma')) flag = '🇨🇩';
            else if (dLow.includes('uganda') || dLow.includes('kampala')) flag = '🇺🇬';
            else if (dLow.includes('south sudan') || dLow.includes('juba')) flag = '🇸🇸';
            else if (dLow.includes('sudan') || dLow.includes('khartoum')) flag = '🇸🇩';
            else if (dLow.includes('rwanda')) flag = '🇷🇼';
            else if (dLow.includes('tanzania')) flag = '🇹🇿';

            row.querySelector('.cell-route').innerHTML = `
                <div style="font-size:13.5px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:5px;">
                    <span style="font-size:15px;">${flag}</span>
                    <span class="view-val-destination">${destination || 'Regional'}</span>
                </div>
                <div style="font-size:12px;color:var(--brand);font-weight:700;margin-top:2px;">
                    👤 <span class="view-val-client">${clientName || 'Consignee'}</span>
                </div>
                <div style="font-size:11.5px;color:var(--text-3);margin-top:2px;">
                    <b>From: Eldoret</b>
                </div>
            `;

            row.querySelector('.cell-litres').innerHTML = `
                <div style="font-weight:800;color:var(--brand);font-size:14px;"><span class="view-val-loaded">${parseInt(loadedLitres).toLocaleString()}</span> L</div>
                <div style="font-size:12px;color:var(--text-2);">Deliv: <b class="view-val-delivered">${parseInt(deliveredLitres).toLocaleString()}</b> L</div>
            `;
            if (row.querySelector('.cell-shortage')) {
                const sNum = parseInt(shortageLitres || 0, 10);
                row.querySelector('.cell-shortage').innerHTML = sNum > 0
                    ? `<span style="background:var(--red-soft);color:var(--red);padding:3px 8px;border-radius:6px;font-weight:800;font-size:12px;display:inline-block;">-${sNum.toLocaleString()} L</span>`
                    : `<span style="background:var(--green-soft);color:var(--green);padding:3px 8px;border-radius:6px;font-weight:700;font-size:12px;display:inline-block;">0 L</span>`;
            }
            const dieselVal = parseFloat(diesel) || 0;
            row.querySelector('.cell-diesel').innerHTML = `<span class="view-val">${dieselVal > 0 ? CURRENCY_SYM + ' ' + dieselVal.toFixed(2) : '—'}</span>`;
            row.querySelector('.cell-transport').innerHTML = `<span class="view-val">${CURRENCY_SYM} ${parseFloat(transport).toFixed(2)}</span>`;
            row.querySelector('.cell-final-payout').innerHTML = `<span class="view-val">${CURRENCY_SYM} ${parseFloat(finalPayout).toFixed(2)}</span><div style="font-size:11px;color:var(--text-3);font-weight:600;">Client Paid</div>`;
            row.querySelector('.cell-diff').innerHTML = diff > 0.01 ? `<div style="color:var(--red);font-weight:900;">-${CURRENCY_SYM} ${diff.toFixed(2)}</div>` : `<span style="color:var(--green);font-weight:700;">✓ Full</span>`;

            row.querySelector('.row-normal-actions').style.display = 'inline-flex';
            row.querySelector('.row-editing-actions').style.display = 'none';
            row.classList.remove('tr-editing');
            delete rowOriginalHtml[id];
        });
        return;
    }

    try {
        const res = await fetch('<?= url("fleet/inline-update") ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams(postData).toString()
        });

        const json = await res.json();
        if (json.success && json.data) {
            const d = json.data;
            row.dataset.dispatchDate = d.dispatch_date;
            row.dataset.diesel = d.diesel;
            row.dataset.mileageCost = d.mileage_cost;
            row.dataset.extraExpenses = d.extra_expenses;
            row.dataset.shortageLitres = d.shortage_litres;

            row.querySelector('.cell-dol').innerHTML = `<span class="view-val">${d.dol_formatted}</span>`;
            row.querySelector('.cell-driver').innerHTML = `<span class="view-val">${d.driver}</span>`;

            let sClass = 's-plan';
            const sLower = d.status.toLowerCase();
            if (sLower.includes('transit')) sClass = 's-transit';
            else if (sLower.includes('load')) sClass = 's-load';
            else if (sLower.includes('deliver') || sLower.includes('complete')) sClass = 's-done';

            row.querySelector('.cell-status').innerHTML = `<span class="status ${sClass} view-val"><i></i>${d.status}</span>`;

            let flag = '🇰🇪';
            const dLow = (d.destination || destination || '').toLowerCase();
            if (dLow.includes('congo') || dLow.includes('goma')) flag = '🇨🇩';
            else if (dLow.includes('uganda') || dLow.includes('kampala')) flag = '🇺🇬';
            else if (dLow.includes('south sudan') || dLow.includes('juba')) flag = '🇸🇸';
            else if (dLow.includes('sudan') || dLow.includes('khartoum')) flag = '🇸🇩';
            else if (dLow.includes('rwanda')) flag = '🇷🇼';
            else if (dLow.includes('tanzania')) flag = '🇹🇿';

            row.querySelector('.cell-route').innerHTML = `
                <div style="font-size:13.5px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:5px;">
                    <span style="font-size:15px;">${flag}</span>
                    <span class="view-val-destination">${d.destination || destination}</span>
                </div>
                <div style="font-size:12px;color:var(--brand);font-weight:700;margin-top:2px;">
                    👤 <span class="view-val-client">${d.client_name || clientName || 'Regional Consignee'}</span>
                </div>
                <div style="font-size:11.5px;color:var(--text-3);margin-top:2px;">
                    <b>From: Eldoret</b>
                </div>
            `;

            row.querySelector('.cell-litres').innerHTML = `
                <div style="font-weight:800;color:var(--brand);font-size:14px;"><span class="view-val-loaded">${d.loaded_litres.toLocaleString()}</span> L</div>
                <div style="font-size:12px;color:var(--text-2);margin-top:2px;">Deliv: <b class="view-val-delivered">${d.delivered_litres.toLocaleString()}</b> L</div>
            `;

            if (row.querySelector('.cell-shortage')) {
                row.querySelector('.cell-shortage').innerHTML = d.shortage_litres > 0
                    ? `<span style="background:var(--red-soft);color:var(--red);padding:3px 8px;border-radius:6px;font-weight:800;font-size:12px;display:inline-block;">-${d.shortage_litres.toLocaleString()} L</span>`
                    : `<span style="background:var(--green-soft);color:var(--green);padding:3px 8px;border-radius:6px;font-weight:700;font-size:12px;display:inline-block;">0 L</span>`;
            }

            row.querySelector('.cell-diesel').innerHTML = `<span class="view-val">${d.diesel_formatted}</span>`;
            row.querySelector('.cell-transport').innerHTML = `<span class="view-val">${d.transport_formatted}</span>`;
            row.querySelector('.cell-final-payout').innerHTML = `<span class="view-val">${d.final_payout_formatted}</span><div style="font-size:11px;color:var(--text-3);font-weight:600;">Client Paid</div>`;

            row.querySelector('.cell-diff').innerHTML = d.payout_diff > 0.01 
                ? `<div style="color:var(--red);font-weight:900;font-size:14px;">-${d.payout_diff_formatted}</div><div style="font-size:11px;color:var(--red);font-weight:700;">Shortage Loss</div>`
                : `<span style="color:var(--green);font-weight:700;font-size:12px;background:var(--green-soft);padding:2px 7px;border-radius:6px;">✓ Full (0 Diff)</span>`;

            let costsHtml = `<div class="val-mileage" style="color:var(--amber);font-weight:700;font-size:13.5px;">${d.mileage_formatted}</div>`;
            row.querySelector('.cell-costs').innerHTML = costsHtml;

            row.querySelector('.cell-balance').innerHTML = `
                <span class="view-val" style="color:${d.balance >= 0 ? 'var(--green)' : 'var(--red)'};">${d.balance_formatted}</span>
            `;

            row.querySelector('.row-normal-actions').style.display = 'inline-flex';
            row.querySelector('.row-editing-actions').style.display = 'none';
            row.classList.remove('tr-editing');
            delete rowOriginalHtml[id];

            // Success pulse effect
            row.style.transition = 'background .4s';
            row.style.background = 'var(--green-soft)';
            setTimeout(() => { row.style.background = ''; }, 1200);
        } else {
            alert('Could not update dispatch: ' + (json.error || 'Server error'));
        }
    } catch (err) {
        console.error('Update failed:', err);
        alert('Network error. Update was not saved.');
    }
}

function formatDateClient(dStr) {
    if (!dStr) return '—';
    const parts = dStr.split('-');
    if (parts.length === 3) {
        const yr = parts[0].slice(-2);
        return `${parts[2]}/${parts[1]}/${yr}`;
    }
    return dStr;
}

// Inline editing for custom table columns in Fleet
document.querySelectorAll('#fleetTable .editable-cell').forEach(cell => {
    cell.addEventListener('dblclick', function() {
        if (this.querySelector('input')) return;
        const currentVal = this.innerText.trim();
        const id = this.dataset.id;
        const field = this.dataset.field;
        const type = this.dataset.type;

        const input = document.createElement('input');
        input.type = (type === 'number' || type === 'currency') ? 'number' : (type === 'date' ? 'date' : 'text');
        if (type === 'number' || type === 'currency') input.step = 'any';
        input.value = currentVal === '—' ? '' : currentVal;
        input.className = 'table-inline-input';
        input.style = 'width:100%;font-size:13px;padding:4px 6px;';

        this.innerHTML = '';
        this.appendChild(input);
        input.focus();

        const saveVal = async () => {
            const newVal = input.value.trim();
            this.innerHTML = `<span class="view-val">${newVal || '—'}</span>`;
            try {
                const res = await fetch('<?= url("fleet/inline-update") ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        _csrf_token: CSRF_TOKEN,
                        id: id,
                        field: field,
                        value: newVal
                    }).toString()
                });
                const json = await res.json();
                if (!json.success) {
                    this.innerHTML = `<span class="view-val">${currentVal}</span>`;
                    alert(json.error || 'Failed to update cell');
                }
            } catch (err) {
                this.innerHTML = `<span class="view-val">${currentVal}</span>`;
                alert('Network error while saving cell');
            }
        };

        input.addEventListener('blur', saveVal);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                input.blur();
            } else if (e.key === 'Escape') {
                input.removeEventListener('blur', saveVal);
                this.innerHTML = `<span class="view-val">${currentVal}</span>`;
            }
        });
    });
});
</script>
<?php };
require __DIR__ . '/../layouts/app.php';
?>
