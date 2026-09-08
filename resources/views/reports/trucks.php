<?php
    $content = function () use ($title, $truckRows, $totalCompanyProfit, $totalSubcontractedCommission, $totalLitresAll, $totalTransportAll, $totalGarageAll) {
?>
<section class="view active" id="view-truck-reports">
    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
        <div class="hello">
            <h1>Detailed Truck Performance Report 📊</h1>
            <p>Comparative profitability, fuel volume transported, operating expenses, and net return by vehicle.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <a href="<?= url('reports') ?>" class="btn btn-ghost">📅 Monthly Assessment</a>
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('truckExportModal').classList.add('active')" title="Export truck performance matrix">📊 Export Reports</button>
            <a href="<?= url('trucks') ?>" class="btn btn-ghost">🚛 Carrier Roster</a>
            <a href="<?= url('fleet') ?>" class="btn btn-brand">🚚 Fleet Management</a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="kpis" style="margin-top:20px;">
        <div class="kpi" style="border:1.5px solid var(--green);">
            <div class="lbl" style="color:var(--green);font-weight:700;">Owner Fleet Net Profit</div>
            <div class="val" style="color:var(--green);"><?= format_money($totalCompanyProfit) ?></div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Owner tankers (less trip costs & expenses)</div>
        </div>

        <div class="kpi" style="border:1.5px solid var(--amber);">
            <div class="lbl" style="color:var(--amber);font-weight:700;">Contract Carrier Commission Yield</div>
            <div class="val" style="color:var(--amber);"><?= format_money($totalSubcontractedCommission) ?></div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Contract trucks haulage fees</div>
        </div>

        <div class="kpi">
            <div class="lbl">Total Fuel Transported</div>
            <div class="val" style="color:var(--accent);"><?= number_format($totalLitresAll) ?> L</div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Delivered across all carrier units</div>
        </div>

        <div class="kpi">
            <div class="lbl">Gross Freight Billed</div>
            <div class="val" style="color:var(--brand);"><?= format_money($totalTransportAll) ?></div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Total transport invoiced</div>
        </div>
    </div>

    <!-- Search and Filter Bar -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin:24px 0 14px;">
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button class="btn btn-sm btn-brand" onclick="filterOwnership('all', this)">All Tankers (<?= count($truckRows) ?>)</button>
            <button class="btn btn-sm btn-ghost" onclick="filterOwnership('owner', this)">🏢 Owner</button>
            <button class="btn btn-sm btn-ghost" onclick="filterOwnership('contract', this)">🤝 Contract</button>
        </div>
        <input type="text" id="reportFilter" placeholder="Filter plate, owner, carrier…" style="padding:7px 14px;border:1.5px solid var(--border-2);border-radius:10px;background:var(--card);font-size:13.5px;min-width:240px;">
    </div>

    <!-- Detailed Truck Report Table -->
    <div class="panel">
        <div class="table-responsive">
            <table id="truckReportTable">
                <thead>
                    <tr>
                        <th>Truck Plate</th>
                        <th>Ownership</th>
                        <th>Capacity</th>
                        <th>Trips</th>
                        <th>Litres Hauled</th>
                        <th>Transport Billed</th>
                        <th>Trip Costs (Fuel + Mileage + Fix)</th>
                        <th>Expenses</th>
                        <th>Net Return (Profit / Comm)</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($truckRows)): ?>
                        <tr>
                            <td colspan="10" style="text-align:center;padding:32px;color:var(--text-3);">No truck records found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($truckRows as $tr): ?>
                            <?php
                                $isContract = in_array(strtolower($tr['ownership_type'] ?? ''), ['contract', 'subcontracted', 'sub']);
                                $tripCosts = $tr['total_mileage'] + $tr['total_breakdown'] + (float)($tr['total_diesel'] ?? 0);
                                $netRet = (float) $tr['net_return'];
                            ?>
                            <tr data-ownership="<?= $isContract ? 'contract' : 'owner' ?>">
                                <td>
                                    <a href="<?= url('trucks/view/' . $tr['id']) ?>" style="text-decoration:none;">
                                        <b style="font-size:15.5px;color:var(--brand);"><?= htmlspecialchars($tr['plate_number']) ?></b>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($isContract): ?>
                                        <span style="background:var(--amber-soft);color:var(--amber);padding:3px 8px;border-radius:6px;font-size:11.5px;font-weight:800;border:1px solid var(--amber);">
                                            Contract
                                        </span>
                                        <div style="font-size:11px;color:var(--text-3);margin-top:2px;" title="<?= htmlspecialchars($tr['owner_name']) ?>">
                                            <?= htmlspecialchars(substr($tr['owner_name'], 0, 18)) ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="background:var(--green-soft);color:var(--green);padding:3px 8px;border-radius:6px;font-size:11.5px;font-weight:800;border:1px solid var(--green);">
                                            Owner
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><b><?= number_format($tr['capacity_litres']) ?> L</b></td>
                                <td style="font-weight:700;"><?= $tr['trips_count'] ?></td>
                                <td style="font-weight:800;color:var(--brand);"><?= number_format($tr['total_litres']) ?> L</td>
                                <td style="font-weight:700;"><?= format_money($tr['total_transport']) ?></td>
                                <td style="color:var(--text-2);"><?= format_money($tripCosts) ?></td>
                                <td style="color:var(--red);font-weight:600;"><?= format_money($tr['garage_expenses']) ?></td>
                                <td style="font-weight:900;font-size:15px;color:<?= $netRet >= 0 ? 'var(--green)' : 'var(--red)' ?>;">
                                    <?= format_money($netRet) ?>
                                    <div style="font-size:11px;font-weight:600;color:var(--text-3);">
                                        <?= $isContract ? 'Commission' : 'Net Company Profit' ?>
                                    </div>
                                </td>
                                <td style="text-align:center;white-space:nowrap;">
                                    <a href="<?= url('trucks/view/' . $tr['id']) ?>" class="btn btn-sm btn-ghost" style="padding:4px 10px;font-size:12px;font-weight:700;">
                                        🔍 View Truck
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<script>
function filterOwnership(type, btn) {
    document.querySelectorAll('#view-truck-reports .btn-sm').forEach(b => {
        b.classList.remove('btn-brand');
        b.classList.add('btn-ghost');
    });
    btn.classList.remove('btn-ghost');
    btn.classList.add('btn-brand');

    const rows = document.querySelectorAll('#truckReportTable tbody tr');
    rows.forEach(r => {
        if (type === 'all') {
            r.style.display = '';
        } else {
            r.style.display = (r.dataset.ownership === type) ? '' : 'none';
        }
    });
}

const repFilter = document.getElementById('reportFilter');
if (repFilter) {
    repFilter.addEventListener('input', function(e) {
        const val = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('#truckReportTable tbody tr');
        rows.forEach(r => {
            r.style.display = r.textContent.toLowerCase().includes(val) ? '' : 'none';
        });
    });
}
</script>

<!-- Truck Report Export Filter Modal -->
<div class="modal-backdrop" id="truckExportModal" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal-card" style="max-width:500px;">
        <div class="modal-head">
            <h2>📊 Export Truck Performance Report</h2>
            <button class="close-modal" onclick="document.getElementById('truckExportModal').classList.remove('active')">✕</button>
        </div>
        <form method="GET" action="<?= url('reports/trucks/export') ?>" target="_blank">
            <div class="form-grid single" style="gap:14px;">
                <div class="form-group">
                    <label>Vehicle / Tanker</label>
                    <select name="truck">
                        <option value="">All Tankers & Trucks</option>
                        <?php foreach ($truckRows as $trk): ?>
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
                                <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>"><?= date('F', mktime(0, 0, 0, $m, 10)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Year (Optional)</label>
                        <select name="year">
                            <option value="">All Years</option>
                            <?php $curY = (int)date('Y'); for ($y = $curY; $y >= $curY - 4; $y--): ?>
                                <option value="<?= $y ?>"><?= $y ?></option>
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
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('truckExportModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-brand" onclick="setTimeout(() => document.getElementById('truckExportModal').classList.remove('active'), 300)">Download Report</button>
            </div>
        </form>
    </div>
</div>
<?php };
require __DIR__ . '/../layouts/app.php';
?>
