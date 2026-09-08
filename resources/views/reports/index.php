<?php
    $content = function () use (
        $title, $monthlyAssessments, $selectedMonth, $report, $availableMonths,
        $driverPerformance, $topDriverTrips, $topDriverVolume, $topTruckProfit,
        $topTruckTrips, $topTruckMaintenance, $shortageStats, $trucks
    ) {
        $netProfit = (float) ($report['net_profit'] ?? 0);
        $transport = (float) ($report['transport_revenue'] ?? 0);
        $mileage = (float) ($report['mileage_costs'] ?? 0);
        $extra = (float) ($report['extra_expenses'] ?? 0);
        $expenses = (float) ($report['garage_expenses'] ?? 0);
        $salaries = (float) ($report['total_salaries'] ?? 0);
        $margin = (float) ($report['margin'] ?? 0);
        $trips = (int) ($report['dispatches_count'] ?? 0);
        $litres = (int) ($report['litres_delivered'] ?? 0);

        $shortageLitres = (int) ($shortageStats['total_shortage_litres'] ?? 0);
        $shortageCost = (float) ($shortageStats['total_shortage_cost'] ?? 0);

        $mileagePct = $transport > 0 ? ($mileage / $transport) * 100 : 0;
        $expensePct = $transport > 0 ? ($expenses / $transport) * 100 : 0;
        $salaryPct = $transport > 0 ? ($salaries / $transport) * 100 : 0;
        $profitPct = $transport > 0 ? ($netProfit / $transport) * 100 : 0;
?>
<section class="view active" id="view-reports">
    <!-- Header & Month Filter Toolbar -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
        <div class="hello">
            <h1>Monthly Assessment & Reports 📊</h1>
            <p>Executive performance overview, interactive financial charts, plain-English explainers, and driver/truck analytics.</p>
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:8px;background:var(--card);border:1.5px solid var(--border-2);border-radius:10px;padding:4px 12px;box-shadow:var(--shadow);">
                <span style="font-size:14px;font-weight:700;color:var(--text-3);">Select Month:</span>
                <select onchange="window.location.href='<?= url('reports') ?>?month='+this.value" style="border:0;background:transparent;font-weight:800;font-size:14.5px;color:var(--brand);outline:0;cursor:pointer;padding:4px 0;">
                    <?php foreach ($availableMonths as $m): ?>
                        <option value="<?= htmlspecialchars($m) ?>" <?= $m === $selectedMonth ? 'selected' : '' ?>>
                            <?= date('F Y', strtotime($m . '-01')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('reportExportModal').classList.add('active')" title="Export report with filters for car, month, year, and format">📊 Export Reports</button>
            <a href="<?= url('reports/trucks') ?>" class="btn btn-ghost" title="Detailed Truck Performance Ledger">🚛 Truck Ledger</a>
        </div>
    </div>

    <!-- Main Net Operating Profit Banner -->
    <div style="background:<?= $netProfit >= 0 ? 'linear-gradient(135deg,var(--card),var(--green-soft))' : 'linear-gradient(135deg,var(--card),var(--red-soft))' ?>;border:2px solid <?= $netProfit >= 0 ? 'var(--green)' : 'var(--red)' ?>;border-radius:18px;padding:24px 28px;margin-top:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;box-shadow:var(--shadow);">
        <div>
            <div style="font-size:12.5px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:<?= $netProfit >= 0 ? 'var(--green)' : 'var(--red)' ?>;">
                NET OPERATING PROFIT FOR <?= strtoupper(date('F Y', strtotime($selectedMonth . '-01'))) ?>
            </div>
            <div style="font-size:36px;font-weight:900;color:<?= $netProfit >= 0 ? 'var(--green)' : 'var(--red)' ?>;margin-top:4px;">
                <?= format_money($netProfit) ?>
            </div>
            <div style="font-size:13.5px;color:var(--text-2);margin-top:4px;">
                Freight Revenue (<?= format_money($transport) ?>) − Trip Costs (<?= format_money($mileage + $extra) ?>) − Expenses (<?= format_money($expenses) ?>) − Salaries (<?= format_money($salaries) ?>)
            </div>
        </div>
        <div style="display:flex;gap:24px;text-align:right;">
            <div>
                <div style="font-size:12px;color:var(--text-3);font-weight:700;text-transform:uppercase;">Operating Margin</div>
                <div style="font-size:32px;font-weight:900;color:var(--brand);"><?= $margin ?>%</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--text-3);font-weight:700;text-transform:uppercase;">Volume Delivered</div>
                <div style="font-size:32px;font-weight:900;color:var(--text);"><?= number_format($litres) ?> L</div>
            </div>
        </div>
    </div>

    <!-- KPI Summary Metrics -->
    <div class="kpis" style="margin-top:20px;">
        <div class="kpi">
            <div class="lbl">Gross Transport Revenue</div>
            <div class="val" style="color:var(--brand);"><?= format_money($transport) ?></div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;"><?= $trips ?> trips dispatched</div>
        </div>
        <div class="kpi">
            <div class="lbl">En-route Mileage & Fixes</div>
            <div class="val" style="color:var(--amber);"><?= format_money($mileage + $extra) ?></div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;"><?= round($mileagePct, 1) ?>% of revenue</div>
        </div>
        <div class="kpi">
            <div class="lbl">Vehicle & Business Expenses</div>
            <div class="val" style="color:var(--red);"><?= format_money($expenses) ?></div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;"><?= round($expensePct, 1) ?>% of revenue</div>
        </div>
        <div class="kpi">
            <div class="lbl">Driver Salaries Disbursed</div>
            <div class="val" style="color:var(--brand);"><?= format_money($salaries) ?></div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;"><?= round($salaryPct, 1) ?>% of revenue</div>
        </div>
    </div>

    <!-- Plain-English Business Explainer Card -->
    <div style="margin-top:24px;background:var(--card);border:1.5px solid var(--border);border-radius:16px;padding:22px 26px;box-shadow:var(--shadow);">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
            <span style="font-size:22px;">💡</span>
            <h3 style="margin:0;font-size:17px;letter-spacing:-.01em;">Executive Monthly Explainer: <?= date('F Y', strtotime($selectedMonth . '-01')) ?></h3>
        </div>
        <div style="font-size:14.5px;color:var(--text);line-height:1.65;">
            <p style="margin:0 0 10px;">
                During <b><?= date('F Y', strtotime($selectedMonth . '-01')) ?></b>, Sarura Fuel recorded <b><?= $trips ?> trip dispatches</b>, safely delivering <b><?= number_format($litres) ?> Litres</b> of bulk petroleum products across regional corridors. Gross freight revenue billed was <b><?= format_money($transport) ?></b>, converting into a net operating balance of <b><?= format_money($netProfit) ?></b> (<b><?= $margin ?>% profit margin</b>).
            </p>
            <p style="margin:0 0 10px;color:var(--text-2);">
                <b>Cost Distribution:</b> Direct vehicle en-route mileage costs consumed <b><?= round($mileagePct, 1) ?>%</b> (<?= format_money($mileage) ?>), general vehicle repairs and business maintenance expenses accounted for <b><?= round($expensePct, 1) ?>%</b> (<?= format_money($expenses) ?>), and driver payroll compensation represented <b><?= round($salaryPct, 1) ?>%</b> (<?= format_money($salaries) ?>) of total revenues.
            </p>
            <?php if ($shortageLitres > 0): ?>
                <div style="padding:10px 14px;background:var(--red-soft);border:1px solid var(--red);border-radius:10px;color:var(--red);font-size:13.5px;font-weight:700;display:flex;align-items:center;gap:8px;">
                    <span>⚠️</span>
                    <span>Transit Fuel Variance: A net discrepancy of <b><?= number_format($shortageLitres) ?> Litres</b> was recorded at destination offloading points this month, resulting in <b><?= format_money($shortageCost) ?></b> in client invoice payout adjustments.</span>
                </div>
            <?php else: ?>
                <div style="padding:8px 14px;background:var(--green-soft);border:1px solid var(--green);border-radius:10px;color:var(--green);font-size:13px;font-weight:700;display:inline-flex;align-items:center;gap:8px;">
                    <span>✓</span>
                    <span>100% Volumetric Delivery: Zero transit shortage recorded on any deliveries during this period.</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Interactive Visual Graphs Grid -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(340px, 1fr));gap:20px;margin-top:24px;">
        <!-- Graph 1: Revenue vs Cost Breakdown Proportional Bar -->
        <div class="panel" style="padding:22px;">
            <h3 style="margin-top:0;font-size:16px;">Revenue Allocation Breakdown</h3>
            <div style="font-size:13px;color:var(--text-3);margin-bottom:16px;">Where each dollar of gross transport goes</div>

            <!-- Stacked Color Bar -->
            <div style="display:flex;height:24px;border-radius:8px;overflow:hidden;background:var(--border-2);margin-bottom:18px;">
                <div style="width:<?= max(2, min(100, $mileagePct)) ?>%;background:var(--amber);" title="Mileage Cost: <?= round($mileagePct, 1) ?>%"></div>
                <div style="width:<?= max(2, min(100, $expensePct)) ?>%;background:var(--red);" title="Expenses: <?= round($expensePct, 1) ?>%"></div>
                <div style="width:<?= max(2, min(100, $salaryPct)) ?>%;background:#6366F1;" title="Driver Salaries: <?= round($salaryPct, 1) ?>%"></div>
                <div style="width:<?= max(2, min(100, $profitPct)) ?>%;background:var(--green);" title="Net Profit: <?= round($profitPct, 1) ?>%"></div>
            </div>

            <!-- Legend Items -->
            <div style="display:flex;flex-direction:column;gap:8px;font-size:13px;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span style="width:12px;height:12px;border-radius:3px;background:var(--green);display:inline-block;"></span>
                        <span>Net Operating Profit</span>
                    </div>
                    <b><?= format_money($netProfit) ?> (<?= round($profitPct, 1) ?>%)</b>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span style="width:12px;height:12px;border-radius:3px;background:var(--amber);display:inline-block;"></span>
                        <span>Mileage & Road Costs</span>
                    </div>
                    <b><?= format_money($mileage) ?> (<?= round($mileagePct, 1) ?>%)</b>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span style="width:12px;height:12px;border-radius:3px;background:var(--red);display:inline-block;"></span>
                        <span>Workshop & Maintenance</span>
                    </div>
                    <b><?= format_money($expenses) ?> (<?= round($expensePct, 1) ?>%)</b>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span style="width:12px;height:12px;border-radius:3px;background:#6366F1;display:inline-block;"></span>
                        <span>Driver Salaries</span>
                    </div>
                    <b><?= format_money($salaries) ?> (<?= round($salaryPct, 1) ?>%)</b>
                </div>
            </div>
        </div>

        <!-- Graph 2: Recent Month Comparison Chart -->
        <div class="panel" style="padding:22px;">
            <h3 style="margin-top:0;font-size:16px;">Net Profit Trend (Recent Months)</h3>
            <div style="font-size:13px;color:var(--text-3);margin-bottom:16px;">Month-by-month profit margins</div>

            <div style="display:flex;align-items:flex-end;gap:14px;height:140px;padding-top:10px;border-bottom:1.5px solid var(--border);margin-bottom:12px;">
                <?php
                    $maxMonthProfit = 1.0;
                    foreach ($monthlyAssessments as $ma) {
                        if ($ma['net_profit'] > $maxMonthProfit) $maxMonthProfit = $ma['net_profit'];
                    }
                    $recentMonths = array_slice($monthlyAssessments, 0, 5);
                ?>
                <?php foreach (array_reverse($recentMonths) as $rm): ?>
                    <?php
                        $barH = max(10, min(120, round(($rm['net_profit'] / $maxMonthProfit) * 120)));
                        $isSel = ($rm['month'] === $selectedMonth);
                    ?>
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;">
                        <div style="font-size:10px;font-weight:800;color:<?= $isSel ? 'var(--brand)' : 'var(--text-2)' ?>;">
                            <?= format_money($rm['net_profit']) ?>
                        </div>
                        <div style="width:100%;height:<?= $barH ?>px;background:<?= $isSel ? 'var(--brand)' : 'var(--green)' ?>;border-radius:6px 6px 0 0;transition:.2s;" title="<?= date('M Y', strtotime($rm['month'] . '-01')) ?>: <?= format_money($rm['net_profit']) ?>"></div>
                        <div style="font-size:11.5px;font-weight:<?= $isSel ? '800' : '600' ?>;color:<?= $isSel ? 'var(--brand)' : 'var(--text-3)' ?>;">
                            <?= date('M', strtotime($rm['month'] . '-01')) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-3);">
                <span>Trailing Monthly Operations</span>
                <span>Active: <b><?= date('M Y', strtotime($selectedMonth . '-01')) ?></b></span>
            </div>
        </div>
    </div>

    <!-- Monthly Performance Awards: Best Driver & Top Trucks -->
    <h2 style="margin:28px 0 14px;font-size:20px;letter-spacing:-.01em;">Monthly Performance Awards & Analytics 🏆</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:16px;">
        <!-- Card 1: Best Performing Driver -->
        <div class="panel" style="padding:20px;border-top:4px solid var(--brand);box-shadow:var(--shadow);">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <span style="font-size:11px;font-weight:800;color:var(--brand);text-transform:uppercase;">Top Driver of Month</span>
                    <h3 style="margin:4px 0 2px;font-size:18px;">
                        <?= $topDriverTrips ? htmlspecialchars($topDriverTrips['driver']) : 'No Dispatches' ?>
                    </h3>
                </div>
                <span style="font-size:24px;">🌟</span>
            </div>
            <?php if ($topDriverTrips): ?>
                <div style="margin-top:10px;font-size:13.5px;color:var(--text-2);">
                    Completed <b><?= (int) $topDriverTrips['trips_count'] ?> trips</b> carrying <b><?= number_format((int) $topDriverTrips['total_litres']) ?> Litres</b>. Generated <?= format_money((float) $topDriverTrips['transport_revenue']) ?> in haulage fees.
                </div>
            <?php else: ?>
                <div style="margin-top:8px;font-size:13px;color:var(--text-3);">No driver dispatch activity in this period.</div>
            <?php endif; ?>
        </div>

        <!-- Card 2: Truck with Most Profit -->
        <div class="panel" style="padding:20px;border-top:4px solid var(--green);box-shadow:var(--shadow);">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <span style="font-size:11px;font-weight:800;color:var(--green);text-transform:uppercase;">Most Profitable Truck</span>
                    <h3 style="margin:4px 0 2px;font-size:18px;">
                        <?= $topTruckProfit ? htmlspecialchars($topTruckProfit['truck']) : 'None' ?>
                    </h3>
                </div>
                <span style="font-size:24px;">🏆</span>
            </div>
            <?php if ($topTruckProfit): ?>
                <div style="margin-top:10px;font-size:13.5px;color:var(--text-2);">
                    Delivered <b><?= format_money((float) $topTruckProfit['total_profit']) ?></b> in net return across <b><?= (int) $topTruckProfit['trips_count'] ?> trips</b> with <?= number_format((int) $topTruckProfit['total_litres']) ?> Litres delivered.
                </div>
            <?php else: ?>
                <div style="margin-top:8px;font-size:13px;color:var(--text-3);">No active trips for trucks this month.</div>
            <?php endif; ?>
        </div>

        <!-- Card 3: Truck with Most Trips -->
        <div class="panel" style="padding:20px;border-top:4px solid var(--amber);box-shadow:var(--shadow);">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <span style="font-size:11px;font-weight:800;color:var(--amber);text-transform:uppercase;">Most Utilized Truck</span>
                    <h3 style="margin:4px 0 2px;font-size:18px;">
                        <?= $topTruckTrips ? htmlspecialchars($topTruckTrips['truck']) : 'None' ?>
                    </h3>
                </div>
                <span style="font-size:24px;">🚚</span>
            </div>
            <?php if ($topTruckTrips): ?>
                <div style="margin-top:10px;font-size:13.5px;color:var(--text-2);">
                    Completed <b><?= (int) $topTruckTrips['trips_count'] ?> dispatch journeys</b>, clocking continuous carrier service throughout the month.
                </div>
            <?php else: ?>
                <div style="margin-top:8px;font-size:13px;color:var(--text-3);">No dispatch trips recorded.</div>
            <?php endif; ?>
        </div>

        <!-- Card 4: Truck with Most Maintenance -->
        <div class="panel" style="padding:20px;border-top:4px solid var(--red);box-shadow:var(--shadow);">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <span style="font-size:11px;font-weight:800;color:var(--red);text-transform:uppercase;">Highest Maintenance Truck</span>
                    <h3 style="margin:4px 0 2px;font-size:18px;">
                        <?= $topTruckMaintenance ? htmlspecialchars($topTruckMaintenance['truck']) : 'None' ?>
                    </h3>
                </div>
                <span style="font-size:24px;">🔧</span>
            </div>
            <?php if ($topTruckMaintenance && (float)$topTruckMaintenance['total_maintenance'] > 0): ?>
                <div style="margin-top:10px;font-size:13.5px;color:var(--text-2);">
                    Incurred <b><?= format_money((float) $topTruckMaintenance['total_maintenance']) ?></b> in workshop repairs & spare parts across <?= (int)$topTruckMaintenance['expense_count'] ?> visits.
                </div>
            <?php else: ?>
                <div style="margin-top:8px;font-size:13px;color:var(--green);font-weight:700;">Zero workshop repair expenses for any truck this month!</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Monthly Assessment Ledger Table -->
    <div class="panel" style="margin-top:28px;">
        <div class="panel-head">
            <h3>Monthly Assessment Comparison Ledger</h3>
            <span style="font-size:13px;color:var(--text-3);">Month-by-month financial reconciliation</span>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Trips</th>
                        <th>Litres Hauled</th>
                        <th>Transport Revenue</th>
                        <th>Trip Costs (Mileage + Fix)</th>
                        <th>Fleet Profit</th>
                        <th>Expenses</th>
                        <th>Driver Salaries</th>
                        <th>Net Monthly Profit</th>
                        <th>Margin %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthlyAssessments as $mKey => $row): ?>
                        <?php $isCurrent = ($mKey === $selectedMonth); ?>
                        <tr style="<?= $isCurrent ? 'background:var(--brand-soft);font-weight:700;' : '' ?>">
                            <td>
                                <b><?= date('M Y', strtotime($row['month'] . '-01')) ?></b>
                                <?php if ($isCurrent): ?><span style="margin-left:6px;font-size:11px;background:var(--brand);color:#fff;padding:2px 6px;border-radius:4px;">Active</span><?php endif; ?>
                            </td>
                            <td><?= (int) $row['dispatches_count'] ?></td>
                            <td><?= number_format((int) $row['litres_delivered']) ?> L</td>
                            <td style="font-weight:700;color:var(--brand);"><?= format_money($row['transport_revenue']) ?></td>
                            <td style="color:var(--text-2);"><?= format_money($row['mileage_costs'] + $row['extra_expenses']) ?></td>
                            <td style="font-weight:800;color:var(--green);"><?= format_money($row['fleet_profit']) ?></td>
                            <td style="color:var(--red);font-weight:700;"><?= format_money($row['garage_expenses']) ?></td>
                            <td style="color:var(--amber);font-weight:700;">
                                <?= format_money($row['total_salaries']) ?>
                            </td>
                            <td style="font-weight:900;font-size:15px;color:<?= (float) $row['net_profit'] >= 0 ? 'var(--green)' : 'var(--red)' ?>;">
                                <?= format_money($row['net_profit']) ?>
                            </td>
                            <td>
                                <span style="background:var(--card-2);padding:3px 8px;border-radius:6px;font-weight:800;font-size:12.5px;color:var(--brand);">
                                    <?= (float) $row['margin'] ?>%
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Report Export Filter Modal -->
<div class="modal-backdrop" id="reportExportModal" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal-card" style="max-width:500px;">
        <div class="modal-head">
            <h2>📊 Export Monthly & Vehicle Reports</h2>
            <button class="close-modal" onclick="document.getElementById('reportExportModal').classList.remove('active')">✕</button>
        </div>
        <form method="GET" action="<?= url('reports/export') ?>" target="_blank">
            <div class="form-grid single" style="gap:14px;">
                <div class="form-group">
                    <label>Vehicle / Tanker</label>
                    <select name="truck">
                        <option value="">All Vehicles / Fleet-wide</option>
                        <?php foreach (($trucks ?? []) as $trk): ?>
                            <option value="<?= htmlspecialchars($trk['plate_number']) ?>"><?= htmlspecialchars($trk['plate_number']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label>Month (Optional)</label>
                        <select name="month">
                            <option value="">All Months</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <?php $mStr = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                <option value="<?= $mStr ?>" <?= (substr($selectedMonth, 5, 2) === $mStr) ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Year (Optional)</label>
                        <select name="year">
                            <option value="">All Years</option>
                            <?php $curY = (int)date('Y'); for ($y = $curY; $y >= $curY - 4; $y--): ?>
                                <option value="<?= $y ?>" <?= (substr($selectedMonth, 0, 4) == $y) ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
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
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('reportExportModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-brand" onclick="setTimeout(() => document.getElementById('reportExportModal').classList.remove('active'), 300)">Download Report</button>
            </div>
        </form>
    </div>
</div>
<?php };
require __DIR__ . '/../layouts/app.php';
?>
