<?php
    $content = function () use ($title, $customers) {
        $totalClients = count($customers);
        $totalVol = array_sum(array_column($customers, 'total_volume'));
        $totalTrips = array_sum(array_column($customers, 'trip_count'));
?>
    <section class="view active" id="view-customers">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
            <div class="hello">
                <h1>Customers & Consignee Accounts 👥</h1>
                <p>Regional fuel clients, petrol stations, offloading depots, and order volume history.</p>
            </div>
            <div>
                <span style="font-size:12.5px;color:var(--text-3);font-weight:700;">Auto-synchronized with Fleet Dispatches</span>
            </div>
        </div>

        <div class="kpis" style="margin-top:20px;">
            <div class="kpi">
                <div class="lbl">Registered Clients</div>
                <div class="val"><?= $totalClients ?> Accounts</div>
                <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Across regional transit destinations</div>
            </div>
            <div class="kpi">
                <div class="lbl">Total Trips Dispatched</div>
                <div class="val" style="color:var(--brand);"><?= number_format($totalTrips) ?> Deliveries</div>
                <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Completed or in-transit shipments</div>
            </div>
            <div class="kpi">
                <div class="lbl">Bulk Fuel Hauled</div>
                <div class="val" style="color:var(--green);"><?= number_format($totalVol) ?> L</div>
                <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Cumulative volume delivered</div>
            </div>
        </div>

        <!-- Compact Search Bar -->
        <div style="display:flex;align-items:center;flex-wrap:wrap;gap:12px;margin:22px 0 14px;">
            <div style="display:flex;align-items:center;gap:10px;background:var(--card);border:1.5px solid var(--border-2);border-radius:10px;padding:6px 14px;min-width:320px;max-width:440px;box-shadow:var(--shadow);">
                <span style="color:var(--text-3);font-size:16px;">🔍</span>
                <input type="text" id="customerSearch" placeholder="Search customer, station, destination…" style="border:0;outline:0;background:transparent;width:100%;font-size:14.5px;color:var(--text);">
            </div>
            <div style="font-size:13.5px;color:var(--text-2);font-weight:700;">
                Showing <span id="custVisibleCount"><?= count($customers) ?></span> of <?= count($customers) ?> Clients
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h3>Client & Consignee Directory</h3>
                <span style="font-size:13px;color:var(--text-3);">Clients captured from Eldoret dispatches</span>
            </div>
            <div class="table-responsive">
                <table id="customersTable">
                    <thead>
                        <tr>
                            <th>Customer / Station Name</th>
                            <th>Regional Destination</th>
                            <th style="text-align:center;">Dispatched Trips</th>
                            <th style="text-align:right;">Total Volume Hauled</th>
                            <th>Last Dispatch (DOL)</th>
                            <th style="text-align:center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center;padding:40px;color:var(--text-3);">No customer accounts recorded yet. Dispatches will automatically populate here.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($customers as $c): ?>
                                <?php
                                    $dest = $c['country'] ?: 'Regional Destination';
                                    $vol = (int) ($c['total_volume'] ?: $c['total_litres'] ?: 0);
                                    $trips = (int) ($c['trip_count'] ?: $c['orders'] ?: 0);
                                    $lastDate = $c['last_trip_date'] ?: $c['last_dispatch_date'] ?: '';
                                    $dateFmt = $lastDate ? format_date_dol($lastDate) : '—';
                                ?>
                                <tr id="cust-row-<?= $c['id'] ?>">
                                    <td>
                                        <div class="who">
                                            <div class="av" style="background:var(--brand);color:#fff;font-weight:800;">
                                                <?= strtoupper(substr($c['name'], 0, 2)) ?>
                                            </div>
                                            <div>
                                                <b style="font-size:15px;color:var(--text);"><?= htmlspecialchars($c['name']) ?></b>
                                                <div style="font-size:11.5px;color:var(--text-3);margin-top:2px;">Consignee Account</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="font-weight:600;color:var(--text-2);">
                                        <?= htmlspecialchars($dest) ?>
                                    </td>
                                    <td style="text-align:center;font-weight:800;color:var(--text);">
                                        <span class="pill" style="background:var(--card-2);font-size:13px;padding:3px 10px;"><?= $trips ?> trips</span>
                                    </td>
                                    <td style="text-align:right;font-weight:800;color:var(--green);font-size:14.5px;">
                                        <?= number_format($vol) ?> L
                                    </td>
                                    <td style="font-weight:700;color:var(--text-2);white-space:nowrap;">
                                        <?= $dateFmt ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="status s-done"><i></i>Active</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Table Pagination Footer -->
            <div class="table-pagination-footer" id="customersPagination"></div>
        </div>
    </section>

<script>
// Search Filter for Customers
const custSearch = document.getElementById('customerSearch');
if (custSearch) {
    custSearch.addEventListener('input', function(e) {
        const val = e.target.value.toLowerCase().trim();
        const rows = document.querySelectorAll('#customersTable tbody tr[id^="cust-row-"]');
        let visible = 0;
        rows.forEach(r => {
            const matches = !val || r.textContent.toLowerCase().includes(val);
            r.dataset.matchedFilter = matches ? 'true' : 'false';
            if (matches) visible++;
        });
        const cnt = document.getElementById('custVisibleCount');
        if (cnt) cnt.textContent = visible;
        if (window.customersPagination) {
            window.customersPagination.refresh();
        }
    });
}

// Initialize Customers Table Pagination
const customersPagination = initTablePagination({
    tableId: 'customersTable',
    footerId: 'customersPagination',
    defaultPageSize: 10,
    pageSizes: [10, 25, 50, 100],
    countSpanId: 'custVisibleCount',
    rowSelector: '#customersTable tbody tr[id^="cust-row-"]'
});
window.customersPagination = customersPagination;
</script>
<?php };

require __DIR__ . '/../layouts/app.php';
?>
