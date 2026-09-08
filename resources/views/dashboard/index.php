<?php
    $content = function () use ($title, $truckCount, $driverCount, $dispatchCount, $invoiceCount, $totalTransport, $totalProfit, $totalLitres, $expenseTotal, $salaryPaid, $recentDispatches) {
        $canViewFin = can_view_financials();
?>
<section class="view active" id="view-dashboard">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
        <div class="hello">
            <h1>Good day, Operations Team 👋</h1>
            <p>Real-time bulk fuel logistics, fleet profitability, and depot dispatches.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="<?= url('fleet') ?>" class="btn btn-ghost">🚚 Fleet Ledger</a>
            <?php if ($canViewFin): ?>
                <a href="<?= url('reports') ?>" class="btn btn-brand">📊 Monthly Assessment</a>
            <?php else: ?>
                <a href="<?= url('dev') ?>" class="btn btn-brand">🛡️ Dev Console</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- KPI Summary Metrics -->
    <div class="kpis" style="margin-top:24px;">
        <div class="kpi">
            <div class="lbl">Transport Revenue</div>
            <div class="val" style="color:var(--brand);"><?= $canViewFin ? format_money($totalTransport) : '[Restricted]' ?></div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Across <?= $dispatchCount ?> dispatches</div>
        </div>
        <div class="kpi" style="border:1.5px solid var(--green);">
            <div class="lbl" style="color:var(--green);font-weight:700;">Net Fleet Profit (Balance)</div>
            <div class="val" style="color:var(--green);"><?= $canViewFin ? format_money($totalProfit) : '[Restricted]' ?></div>
            <div style="font-size:13px;color:var(--green);margin-top:4px;font-weight:600;">Transport − Mileage − Fixes</div>
        </div>
        <div class="kpi">
            <div class="lbl">Fuel Delivered</div>
            <div class="val" style="color:var(--accent);"><?= number_format($totalLitres) ?> L</div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Total litres delivered</div>
        </div>
        <div class="kpi">
            <div class="lbl">Active Fleet Equipment</div>
            <div class="val"><?= $truckCount ?> Trucks / <?= $driverCount ?> Drivers</div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Dynamic driver assignment</div>
        </div>
    </div>

    <!-- Panels Grid: Recent Dispatches & Quick Operations -->
    <div class="panels" style="margin-top:20px;">
        <div class="panel">
            <div class="panel-head">
                <h3>Recent Fleet Dispatches</h3>
                <a href="<?= url('fleet') ?>">View all dispatches →</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th title="Date of Loading">DOL</th>
                            <th>Trip #</th>
                            <th>Truck</th>
                            <th>Destination</th>
                            <th>Product</th>
                            <th>Driver</th>
                            <th>Profit Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentDispatches)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center;padding:32px;color:var(--text-3);">No dispatches recorded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentDispatches as $rd): ?>
                                <?php
                                    $s = strtolower($rd['status']);
                                    $sClass = 's-plan';
                                    if (str_contains($s, 'transit')) $sClass = 's-transit';
                                    elseif (str_contains($s, 'deliver')) $sClass = 's-done';
                                    elseif (str_contains($s, 'load')) $sClass = 's-load';
                                ?>
                                <tr>
                                    <td style="font-weight:700;white-space:nowrap;"><?= format_date_dol($rd['dispatch_date']) ?></td>
                                    <td><b><?= htmlspecialchars($rd['trip_number']) ?></b></td>
                                    <td><?= htmlspecialchars($rd['truck']) ?></td>
                                    <td><b><?= htmlspecialchars($rd['destination']) ?></b></td>
                                    <td>
                                        <span style="background:var(--brand-soft);color:var(--brand);padding:3px 7px;border-radius:6px;font-weight:800;font-size:12px;">
                                            <?= htmlspecialchars($rd['product']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($rd['driver']) ?></td>
                                    <td style="font-weight:800;color:var(--green);white-space:nowrap;">
                                        <?= $canViewFin ? format_money($rd['balance']) : '[Restricted]' ?>
                                    </td>
                                    <td><span class="status <?= $sClass ?>"><i></i><?= htmlspecialchars($rd['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:18px;">
            <?php if ($canViewFin): ?>
                <!-- Outside Fleet Financial Overview -->
                <div class="panel" style="padding:22px;">
                    <h3 style="margin-top:0;font-size:17px;">Operational Expense Overview</h3>
                    <div style="margin-top:14px;display:flex;flex-direction:column;gap:12px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:var(--card-2);border-radius:10px;border:1px solid var(--border);">
                            <div>
                                <div style="font-size:12.5px;color:var(--text-3);font-weight:700;text-transform:uppercase;">Expenses</div>
                                <div style="font-weight:600;font-size:13.5px;color:var(--text-2);">Business purchases, spares & maintenance</div>
                            </div>
                            <b style="font-size:16px;color:var(--red);"><?= format_money($expenseTotal) ?></b>
                        </div>

                        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:var(--card-2);border-radius:10px;border:1px solid var(--border);">
                            <div>
                                <div style="font-size:12.5px;color:var(--text-3);font-weight:700;text-transform:uppercase;">Driver Salaries Disbursed</div>
                                <div style="font-weight:600;font-size:13.5px;color:var(--text-2);">Paid per trip & monthly retainers</div>
                            </div>
                            <b style="font-size:16px;color:var(--green);"><?= format_money($salaryPaid) ?></b>
                        </div>
                    </div>
                    <div style="margin-top:18px;">
                        <a href="<?= url('reports') ?>" class="btn btn-brand" style="width:100%;">View Full Monthly Assessment →</a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Technical Console Card for Developers -->
                <div class="panel" style="padding:22px;border:1.5px solid var(--brand);">
                    <h3 style="margin-top:0;font-size:17px;color:var(--brand);">🛡️ Technical Architecture</h3>
                    <p style="font-size:13px;color:var(--text-2);margin:8px 0 14px;">Financial data is shielded. You are in Developer stealth mode with operational control.</p>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <div style="display:flex;justify-content:space-between;font-size:13px;padding:8px 12px;background:var(--card-2);border-radius:8px;">
                            <span>Engine Status:</span>
                            <b style="color:var(--green);">🟢 Optimal</b>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:13px;padding:8px 12px;background:var(--card-2);border-radius:8px;">
                            <span>Security Monitoring:</span>
                            <b>Active Watchdog</b>
                        </div>
                    </div>
                    <div style="margin-top:16px;">
                        <a href="<?= url('dev') ?>" class="btn btn-brand" style="width:100%;">Open Developer Console →</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Quick Action Cards -->
            <div class="panel" style="padding:22px;">
                <h3 style="margin-top:0;font-size:17px;">Quick Dispatch Actions</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px;">
                    <a href="<?= url('fleet') ?>" class="btn btn-ghost" style="font-size:13.5px;justify-content:flex-start;">🚚 Dispatches</a>
                    <a href="<?= url('trucks') ?>" class="btn btn-ghost" style="font-size:13.5px;justify-content:flex-start;">🚛 Tankers</a>
                    <a href="<?= url('products') ?>" class="btn btn-ghost" style="font-size:13.5px;justify-content:flex-start;">⛽ Fuel Products</a>
                    <?php if ($canViewFin): ?>
                        <a href="<?= url('expenses') ?>" class="btn btn-ghost" style="font-size:13.5px;justify-content:flex-start;">💰 Expenses</a>
                    <?php else: ?>
                        <a href="<?= url('dev') ?>" class="btn btn-ghost" style="font-size:13.5px;justify-content:flex-start;">🛡️ Dev Console</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php };
require __DIR__ . '/../layouts/app.php';
?>
