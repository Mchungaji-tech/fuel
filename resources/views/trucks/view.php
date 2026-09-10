<?php
    $content = function () use ($title, $truck, $dispatches, $expenses, $totalTrips, $totalLitres, $totalTransport, $totalMileage, $totalBreakdown, $totalBalance, $totalGarageExpenses, $isSubcontracted) {
        $isContract = in_array(strtolower($truck['ownership_type'] ?? ''), ['contract', 'subcontracted', 'sub']);
?>
<section class="view active" id="view-truck-single">
    <!-- Breadcrumbs & Navigation -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:18px;">
        <div style="display:flex;align-items:center;gap:10px;">
            <a href="<?= url('trucks') ?>" class="btn btn-ghost btn-sm" style="font-size:13.5px;">← Back to All Trucks</a>
            <span style="color:var(--text-3);">/</span>
            <span style="font-weight:700;color:var(--text);font-size:14px;">Tanker <?= htmlspecialchars($truck['plate_number']) ?></span>
        </div>
        <div style="display:flex;gap:10px;">
            <a href="<?= url('reports/trucks') ?>" class="btn btn-ghost btn-sm">📊 Detailed Truck Reports</a>
            <a href="<?= url('fleet') ?>" class="btn btn-brand btn-sm">🚚 Fleet Management</a>
        </div>
    </div>

    <!-- Truck Master Profile Banner -->
    <div style="background:linear-gradient(135deg,var(--card),var(--card-2));border:1.5px solid var(--border);border-radius:18px;padding:24px 26px;box-shadow:var(--shadow);margin-bottom:24px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;">
            <div>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <h1 style="margin:0;font-size:30px;font-weight:900;letter-spacing:-.02em;"><?= htmlspecialchars($truck['plate_number']) ?></h1>
                    <span style="font-size:15px;background:var(--brand-soft);color:var(--brand);padding:4px 12px;border-radius:8px;font-weight:800;">
                        <?= number_format((int) $truck['capacity_litres']) ?> Litres
                    </span>
                    <?php if ($isContract): ?>
                        <span style="background:var(--amber-soft);color:var(--amber);padding:5px 12px;border-radius:8px;font-weight:800;font-size:13px;border:1px solid var(--amber);">
                            Contract Tanker
                        </span>
                    <?php else: ?>
                        <span style="background:var(--green-soft);color:var(--green);padding:5px 12px;border-radius:8px;font-weight:800;font-size:13px;border:1px solid var(--green);">
                            Owner Fleet
                        </span>
                    <?php endif; ?>
                </div>
                <div style="color:var(--text-2);font-size:14.5px;margin-top:6px;">
                    Compartments: <b><?= htmlspecialchars($truck['compartments']) ?></b> • 
                    Owner / Carrier: <b><?= htmlspecialchars($truck['owner_name'] ?? ($isContract ? 'Contract Transporter' : 'Sarura Fuel Logistics')) ?></b>
                </div>
            </div>

            <!-- Commission Info for Contract Trucks -->
            <?php if ($isContract): ?>
                <div style="text-align:right;background:var(--card);padding:14px 20px;border-radius:12px;border:1px solid var(--border);">
                    <div style="font-size:12px;color:var(--text-3);font-weight:700;text-transform:uppercase;">Agreed Company Commission</div>
                    <div style="font-size:24px;font-weight:900;color:var(--amber);margin-top:2px;">
                        <?= format_money((float) ($truck['commission_rate'] ?? 0)) ?> <span style="font-size:13px;font-weight:600;color:var(--text-3);">/ trip</span>
                    </div>
                    <div style="font-size:12px;color:var(--text-2);margin-top:2px;">
                        Fixed fee return on haulage
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Lifetime Truck Performance KPI Cards -->
    <div class="kpis">
        <div class="kpi">
            <div class="lbl">Total Trips Dispatched</div>
            <div class="val"><?= $totalTrips ?> Trips</div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Dispatches recorded</div>
        </div>

        <div class="kpi">
            <div class="lbl">Total Litres Hauled</div>
            <div class="val" style="color:var(--brand);"><?= number_format($totalLitres) ?> L</div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Delivered volume</div>
        </div>

        <div class="kpi">
            <div class="lbl">Lifetime Transport Billed</div>
            <div class="val" style="color:var(--brand);"><?= format_money($totalTransport) ?></div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Gross freight revenue</div>
        </div>

        <div class="kpi" style="border:2px solid <?= $isContract ? 'var(--amber)' : 'var(--green)' ?>;">
            <div class="lbl" style="font-weight:800;color:<?= $isContract ? 'var(--amber)' : 'var(--green)' ?>;">
                <?= $isContract ? 'Total Commission Earned' : 'Net Fleet Profit (Balance)' ?>
            </div>
            <div class="val" style="color:<?= $isContract ? 'var(--amber)' : 'var(--green)' ?>;font-size:26px;">
                <?= format_money($totalBalance) ?>
            </div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">
                <?= $isContract ? 'From contracted haulage fees' : 'Transport less mileage and road fixes' ?>
            </div>
        </div>
    </div>

    <!-- Section 1: Dispatches History for this Truck with Search Bar -->
    <div class="panel" style="margin-top:24px;">
        <div class="panel-head" style="flex-wrap:wrap;gap:12px;">
            <div>
                <h3 style="margin:0;">Dispatches & Haulage History (<?= htmlspecialchars($truck['plate_number']) ?>)</h3>
                <span style="font-size:12.5px;color:var(--text-3);"><span id="truckDispVisibleCount"><?= count($dispatches) ?></span> of <?= count($dispatches) ?> records</span>
            </div>
            <!-- Dedicated Dispatches Search Bar -->
            <div style="display:flex;align-items:center;gap:8px;background:var(--card-2);border:1.5px solid var(--border-2);border-radius:9px;padding:4px 12px;min-width:280px;box-shadow:var(--shadow);">
                <span style="color:var(--text-3);font-size:15px;">🔍</span>
                <input type="text" id="truckDispatchSearch" placeholder="Search dispatches (DOL, trip #, driver, route, product)…" style="border:0;outline:0;background:transparent;width:100%;font-size:13.5px;color:var(--text);">
            </div>
        </div>
        <div class="table-responsive">
            <table id="truckDispatchesTable">
                <thead>
                    <tr>
                        <th title="Date of Loading">DOL</th>
                        <th>Trip #</th>
                        <th>Driver</th>
                        <th>Litres (Load/Deliv)</th>
                        <th>Route</th>
                        <th>Product</th>
                        <th>Expected Transport</th>
                        <th>Final Client Payout</th>
                        <th>Payout Diff</th>
                        <th>Trip Costs (Fuel + Mileage)</th>
                        <th><?= $isContract ? 'Commission' : 'Net Balance' ?></th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dispatches)): ?>
                        <tr>
                            <td colspan="12" style="text-align:center;padding:32px;color:var(--text-3);">
                                No dispatches recorded yet for truck <?= htmlspecialchars($truck['plate_number']) ?>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dispatches as $d): ?>
                            <?php
                                $loaded = (int) $d['loaded_litres'];
                                $delivered = (int) ($d['delivered_litres'] ?? $loaded);
                                $litresLoss = $loaded - $delivered;
                                $trans = (float) $d['transport_amount'];
                                $finalP = (float) ($d['final_payout'] ?? $trans);
                                $diff = (float) ($d['payout_difference'] ?? ($trans - $finalP));
                                $tripCosts = (float)$d['mileage_cost'] + (float)($d['diesel'] ?? 0) + (float)($d['extra_expenses'] ?? 0);
                            ?>
                            <tr class="disp-row">
                                <td style="font-weight:700;white-space:nowrap;"><?= format_date_dol($d['dispatch_date']) ?></td>
                                <td><b><?= htmlspecialchars($d['trip_number']) ?></b></td>
                                <td style="font-weight:600;"><?= htmlspecialchars($d['driver'] ?: 'Unassigned') ?></td>
                                <td style="white-space:nowrap;">
                                    <b style="color:var(--brand);"><?= number_format($loaded) ?> L</b>
                                    <div style="font-size:11.5px;color:var(--text-2);">Deliv: <?= number_format($delivered) ?> L</div>
                                    <?php if ($litresLoss > 0): ?>
                                        <small style="color:var(--red);font-weight:800;">-<?= number_format($litresLoss) ?> L loss</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size:12px;color:var(--text-3);"><?= htmlspecialchars($d['from_location']) ?></div>
                                    <div style="font-weight:700;font-size:13px;">→ <?= htmlspecialchars($d['destination']) ?></div>
                                </td>
                                <td>
                                    <span style="background:var(--brand-soft);color:var(--brand);padding:3px 7px;border-radius:6px;font-weight:800;font-size:12px;">
                                        <?= htmlspecialchars($d['product']) ?>
                                    </span>
                                </td>
                                <td style="white-space:nowrap;font-weight:700;"><?= format_money($trans) ?></td>
                                <td style="white-space:nowrap;font-weight:800;color:var(--brand);"><?= format_money($finalP) ?></td>
                                <td style="white-space:nowrap;">
                                    <?php if ($diff > 0.01): ?>
                                        <span style="color:var(--red);font-weight:800;">-<?= format_money($diff) ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--green);font-weight:700;font-size:12px;">✓ Full</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color:var(--amber);white-space:nowrap;font-weight:600;">
                                    <?= format_money($tripCosts) ?>
                                    <?php if ((float)($d['diesel'] ?? 0) > 0): ?>
                                        <div style="font-size:11px;color:var(--text-3);">Fuel: <?= format_money($d['diesel']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight:800;white-space:nowrap;color:<?= (float)$d['balance'] >= 0 ? 'var(--green)' : 'var(--red)' ?>;">
                                    <?= format_money($d['balance']) ?>
                                </td>
                                <td style="text-align:center;white-space:nowrap;">
                                    <a href="<?= url('fleet?search=' . urlencode($d['trip_number'])) ?>" class="btn btn-sm btn-ghost" style="padding:4px 8px;font-size:12px;font-weight:700;" title="View in Fleet Ledger">👁️ View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section 2: Truck Expenses with Search Bar -->
    <div class="panel" style="margin-top:24px;">
        <div class="panel-head" style="flex-wrap:wrap;gap:12px;">
            <div>
                <h3 style="margin:0;">Truck Expenses 💰 (<?= htmlspecialchars($truck['plate_number']) ?>)</h3>
                <span style="font-size:12.5px;color:var(--text-3);">
                    Total: <b style="color:var(--red);"><?= format_money($totalGarageExpenses) ?></b> • 
                    <span id="truckExpVisibleCount"><?= count($expenses) ?></span> of <?= count($expenses) ?> entries
                </span>
            </div>
            <!-- Dedicated Expenses Search Bar -->
            <div style="display:flex;align-items:center;gap:8px;background:var(--card-2);border:1.5px solid var(--border-2);border-radius:9px;padding:4px 12px;min-width:280px;box-shadow:var(--shadow);">
                <span style="color:var(--text-3);font-size:15px;">🔍</span>
                <input type="text" id="truckExpenseSearch" placeholder="Search expenses (date, description, vendor, receipt #)…" style="border:0;outline:0;background:transparent;width:100%;font-size:13.5px;color:var(--text);">
            </div>
        </div>
        <div class="table-responsive">
            <table id="truckExpensesTable">
                <thead>
                    <tr>
                        <th style="min-width:90px;">Date</th>
                        <th>Expense Description</th>
                        <th>Vendor / Workshop</th>
                        <th>Receipt #</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center;padding:32px;color:var(--text-3);">
                                No expenses recorded for truck <?= htmlspecialchars($truck['plate_number']) ?>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $e): ?>
                            <tr class="exp-row">
                                <td style="font-weight:700;white-space:nowrap;"><?= format_date_dol($e['expense_date']) ?></td>
                                <td>
                                    <b style="color:var(--text);"><?= htmlspecialchars($e['expense_title']) ?></b>
                                    <?php if (!empty($e['notes'])): ?>
                                        <div style="font-size:12px;color:var(--text-3);margin-top:2px;"><?= htmlspecialchars($e['notes']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight:600;color:var(--text-2);"><?= htmlspecialchars($e['garage_vendor'] ?: 'In-house / External') ?></td>
                                <td style="font-family:monospace;font-size:13px;"><?= htmlspecialchars($e['receipt_number'] ?: '—') ?></td>
                                <td style="font-weight:800;color:var(--red);white-space:nowrap;"><?= format_money($e['amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<script>
// Search Dispatches Table
const dispSearch = document.getElementById('truckDispatchSearch');
if (dispSearch) {
    dispSearch.addEventListener('input', function(e) {
        const val = e.target.value.toLowerCase().trim();
        const rows = document.querySelectorAll('#truckDispatchesTable tbody tr.disp-row');
        let cnt = 0;
        rows.forEach(r => {
            const matches = !val || r.textContent.toLowerCase().includes(val);
            r.style.display = matches ? '' : 'none';
            if (matches) cnt++;
        });
        const cntSpan = document.getElementById('truckDispVisibleCount');
        if (cntSpan) cntSpan.textContent = cnt;
    });
}

// Search Expenses Table
const expSearch = document.getElementById('truckExpenseSearch');
if (expSearch) {
    expSearch.addEventListener('input', function(e) {
        const val = e.target.value.toLowerCase().trim();
        const rows = document.querySelectorAll('#truckExpensesTable tbody tr.exp-row');
        let cnt = 0;
        rows.forEach(r => {
            const matches = !val || r.textContent.toLowerCase().includes(val);
            r.style.display = matches ? '' : 'none';
            if (matches) cnt++;
        });
        const cntSpan = document.getElementById('truckExpVisibleCount');
        if (cntSpan) cntSpan.textContent = cnt;
    });
}
</script>
<?php };
require __DIR__ . '/../layouts/app.php';
?>
