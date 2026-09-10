<?php
    $content = function () use ($title, $expenses, $aggregates, $search, $trucks, $selectedTruck, $selectedYear, $activeTruckReport, $truckReports) {
?>
<section class="view active" id="view-expenses">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
        <div class="hello">
            <h1>Expenses 💰</h1>
            <p>Business purchases, spare parts, repairs, fleet maintenance, supplies, tools, and operational expenses.</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('expenseExportModal').classList.add('active')">📊 Export Expenses</button>
            <button type="button" class="btn btn-brand" onclick="document.getElementById('expenseModal').classList.add('active')">＋ Record Expense</button>
        </div>
    </div>

    <!-- Summary Metrics -->
    <div class="kpis" style="margin-top:20px;">
        <div class="kpi">
            <div class="lbl">Total Expenses</div>
            <div class="val" style="color:var(--red);"><?= format_money($aggregates['total_amount'] ?? 0) ?></div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Business, parts, repair & yard costs</div>
        </div>
        <div class="kpi">
            <div class="lbl">Total Recorded Entries</div>
            <div class="val"><?= (int) ($aggregates['total_count'] ?? 0) ?></div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Expense transactions logged</div>
        </div>
        <div class="kpi">
            <div class="lbl">Average Expense Amount</div>
            <div class="val" style="color:var(--amber);"><?= format_money($aggregates['avg_amount'] ?? 0) ?></div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Per recorded transaction</div>
        </div>
    </div>

    <!-- Dedicated Truck Expense Report Card (Triggered when viewing a specific truck) -->
    <div id="truckExpenseReportCard" style="<?= empty($activeTruckReport) ? 'display:none;' : '' ?>margin-top:22px;background:var(--card);border:1.5px solid var(--brand);border-radius:14px;padding:22px;box-shadow:var(--shadow-md);">
        <!-- Header Row: Plate, Ownership, Status, Year Factor Dropdown, Clear Filter -->
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;border-bottom:1px solid var(--border);padding-bottom:16px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="background:var(--brand);color:#fff;font-size:24px;width:48px;height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 10px rgba(14,165,233,0.3);">
                    🚛
                </div>
                <div>
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <h2 id="reportTruckPlate" style="margin:0;font-size:20px;color:var(--text);font-weight:800;">
                            <?= htmlspecialchars($activeTruckReport['plate_number'] ?? '') ?>
                        </h2>
                        <span id="reportTruckOwnership" style="background:var(--card-2);border:1px solid var(--border);padding:2px 8px;border-radius:6px;font-size:11.5px;font-weight:700;color:var(--text-2);">
                            <?= htmlspecialchars($activeTruckReport['ownership'] ?? 'Owner') ?>
                        </span>
                        <span id="reportTruckStatus" class="status s-done" style="font-size:11.5px;">
                            <i></i><span id="reportTruckStatusText"><?= htmlspecialchars($activeTruckReport['status'] ?? 'Ready') ?></span>
                        </span>
                    </div>
                    <div style="font-size:12.5px;color:var(--text-3);margin-top:3px;">
                        Vehicle Expense Lifecycle, Annual Audit & 12-Month Breakdown Matrix
                    </div>
                </div>
            </div>

            <!-- Right: Year selector & Reset button -->
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:6px;background:var(--card-2);padding:4px 10px;border-radius:8px;border:1px solid var(--border);">
                    <label for="truckReportYearSelect" style="font-size:11.5px;font-weight:800;color:var(--text-3);letter-spacing:0.5px;">FACTOR YEAR:</label>
                    <select id="truckReportYearSelect" onchange="onTruckReportYearChange(this.value)" style="background:transparent;border:0;outline:0;font-size:13.5px;font-weight:800;color:var(--brand);cursor:pointer;">
                        <?php if (!empty($activeTruckReport)): ?>
                            <?php foreach ($activeTruckReport['available_years'] as $yr): ?>
                                <option value="<?= $yr ?>" <?= ((string)$yr === (string)$selectedYear) ? 'selected' : '' ?>><?= $yr ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="<?= date('Y') ?>"><?= date('Y') ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                <button type="button" class="btn btn-sm btn-ghost" onclick="resetTruckFilter()" style="font-size:12px;font-weight:700;" title="Clear vehicle filter and view all entries">
                    ✕ Clear Filter
                </button>
            </div>
        </div>

        <!-- Middle Row: 3 KPI Metric Badges -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:14px;margin-top:18px;">
            <div style="background:var(--card-2);border:1px solid var(--border);border-radius:10px;padding:14px 18px;border-left:3px solid var(--red);">
                <div style="font-size:11.5px;color:var(--text-3);font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">All-Time Total Expenses</div>
                <div id="reportAllTimeTotal" style="font-size:24px;font-weight:900;color:var(--red);margin-top:4px;">
                    <?= !empty($activeTruckReport) ? format_money($activeTruckReport['all_time_total']) : '0.00' ?>
                </div>
                <div id="reportAllTimeCount" style="font-size:12px;color:var(--text-3);margin-top:3px;">
                    <?= !empty($activeTruckReport) ? ((int)$activeTruckReport['all_time_count'] . ' total transactions across all years') : '' ?>
                </div>
            </div>

            <div style="background:var(--card-2);border:1px solid var(--border);border-radius:10px;padding:14px 18px;border-left:3px solid var(--brand);">
                <div style="font-size:11.5px;color:var(--text-3);font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">
                    <span id="reportYearLabel"><?= $selectedYear ?></span> Year Total Expenses
                </div>
                <div id="reportYearTotal" style="font-size:24px;font-weight:900;color:var(--brand);margin-top:4px;">
                    <?= !empty($activeTruckReport) ? format_money($activeTruckReport['yearly_total']) : '0.00' ?>
                </div>
                <div id="reportYearCount" style="font-size:12px;color:var(--text-3);margin-top:3px;">
                    <?= !empty($activeTruckReport) ? ((int)$activeTruckReport['yearly_count'] . ' transactions in ' . $selectedYear) : '' ?>
                </div>
            </div>

            <div style="background:var(--card-2);border:1px solid var(--border);border-radius:10px;padding:14px 18px;border-left:3px solid var(--amber);">
                <div style="font-size:11.5px;color:var(--text-3);font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Factored Monthly Average</div>
                <div id="reportMonthlyAvg" style="font-size:24px;font-weight:900;color:var(--amber);margin-top:4px;">
                    <?= !empty($activeTruckReport) ? format_money($activeTruckReport['yearly_total'] / 12) : '0.00' ?>
                </div>
                <div style="font-size:12px;color:var(--text-3);margin-top:3px;">
                    Averaged across 12 calendar months
                </div>
            </div>
        </div>

        <!-- Bottom Section: 12-Month Expense Matrix Grid (Jan - Dec) -->
        <div style="margin-top:20px;">
            <div style="font-size:13px;font-weight:800;color:var(--text);margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <span>📅 12-Month Expense Distribution Matrix (<span id="matrixYearLabel"><?= $selectedYear ?></span>)</span>
                <span style="font-size:11.5px;color:var(--text-3);font-weight:600;">Monthly aggregate repairs, spares, tires & maintenance</span>
            </div>
            <div id="reportMonthlyMatrixGrid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(88px, 1fr));gap:8px;">
                <?php if (!empty($activeTruckReport)): ?>
                    <?php foreach ($activeTruckReport['monthly_matrix'] as $m): ?>
                        <div style="background:var(--card-2);border:1px solid var(--border);border-radius:8px;padding:10px 8px;text-align:center;">
                            <div style="font-size:11px;font-weight:800;color:var(--text-3);text-transform:uppercase;"><?= $m['month_name'] ?></div>
                            <div style="font-size:13.5px;font-weight:900;color:<?= $m['total'] > 0 ? 'var(--red)' : 'var(--text-3)' ?>;margin-top:4px;">
                                <?= $m['total'] > 0 ? format_money($m['total']) : '—' ?>
                            </div>
                            <div style="font-size:10.5px;color:var(--text-3);margin-top:3px;">
                                <?= $m['count'] > 0 ? ($m['count'] . ' log' . ($m['count'] > 1 ? 's' : '')) : '0 logs' ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Compact Search & Time Horizon Filter Toolbar -->
    <div style="display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin:22px 0 14px;">
        <div style="display:flex;align-items:center;gap:10px;background:var(--card);border:1.5px solid var(--border-2);border-radius:10px;padding:6px 14px;min-width:260px;max-width:340px;box-shadow:var(--shadow);">
            <span style="color:var(--text-3);font-size:16px;">🔍</span>
            <input type="text" id="expenseFilter" placeholder="Search description, vendor, notes…" style="border:0;outline:0;background:transparent;width:100%;font-size:14px;color:var(--text);">
        </div>

        <!-- Vehicle Filter Dropdown -->
        <select id="expenseTruckFilter" onchange="onExpenseTruckFilterChanged()" style="background:var(--card);border:1.5px solid var(--border-2);border-radius:10px;padding:6px 12px;font-size:13px;font-weight:700;color:var(--text);box-shadow:var(--shadow);cursor:pointer;">
            <option value="all" <?= ($selectedTruck === '' || strtolower($selectedTruck) === 'all') ? 'selected' : '' ?>>All Vehicles & Targets</option>
            <option value="general" <?= (strtolower($selectedTruck) === 'general') ? 'selected' : '' ?>>General Business Only</option>
            <?php foreach ($trucks as $trk): ?>
                <?php $isSel = (strtolower($trk['plate_number']) === strtolower($selectedTruck)); ?>
                <option value="<?= htmlspecialchars(strtolower($trk['plate_number'])) ?>" <?= $isSel ? 'selected' : '' ?>>
                    <?= htmlspecialchars($trk['plate_number']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Period Filter Buttons -->
        <div style="display:inline-flex;align-items:center;gap:4px;background:var(--card);border:1.5px solid var(--border-2);border-radius:10px;padding:3px;box-shadow:var(--shadow);">
            <button type="button" class="btn btn-sm btn-ghost exp-time-btn active" data-period="all" onclick="setExpPeriodFilter('all', this)" style="padding:4px 10px;font-size:12.5px;font-weight:700;">All Time</button>
            <button type="button" class="btn btn-sm btn-ghost exp-time-btn" data-period="week" onclick="setExpPeriodFilter('week', this)" style="padding:4px 10px;font-size:12.5px;font-weight:700;">This Week ▾</button>
            <button type="button" class="btn btn-sm btn-ghost exp-time-btn" data-period="month" onclick="setExpPeriodFilter('month', this)" style="padding:4px 10px;font-size:12.5px;font-weight:700;">This Month</button>
            <button type="button" class="btn btn-sm btn-ghost exp-time-btn" data-period="year" onclick="setExpPeriodFilter('year', this)" style="padding:4px 10px;font-size:12.5px;font-weight:700;">This Year</button>
        </div>

        <!-- Days of Week Sub-Bar (Mon-Sun) -->
        <div id="expWeekDaysSubToolbar" style="display:none;align-items:center;gap:3px;background:var(--card-2);border:1.5px solid var(--border);border-radius:10px;padding:3px 6px;">
            <span style="font-size:11px;font-weight:800;color:var(--text-3);margin-right:2px;">DAY:</span>
            <button type="button" class="btn btn-sm btn-ghost exp-day-btn active" data-day="all" onclick="setExpDayFilter('all', this)" style="padding:2px 7px;font-size:11.5px;font-weight:700;">All Week</button>
            <button type="button" class="btn btn-sm btn-ghost exp-day-btn" data-day="1" onclick="setExpDayFilter('1', this)" style="padding:2px 6px;font-size:11.5px;font-weight:700;">Mon</button>
            <button type="button" class="btn btn-sm btn-ghost exp-day-btn" data-day="2" onclick="setExpDayFilter('2', this)" style="padding:2px 6px;font-size:11.5px;font-weight:700;">Tue</button>
            <button type="button" class="btn btn-sm btn-ghost exp-day-btn" data-day="3" onclick="setExpDayFilter('3', this)" style="padding:2px 6px;font-size:11.5px;font-weight:700;">Wed</button>
            <button type="button" class="btn btn-sm btn-ghost exp-day-btn" data-day="4" onclick="setExpDayFilter('4', this)" style="padding:2px 6px;font-size:11.5px;font-weight:700;">Thu</button>
            <button type="button" class="btn btn-sm btn-ghost exp-day-btn" data-day="5" onclick="setExpDayFilter('5', this)" style="padding:2px 6px;font-size:11.5px;font-weight:700;">Fri</button>
            <button type="button" class="btn btn-sm btn-ghost exp-day-btn" data-day="6" onclick="setExpDayFilter('6', this)" style="padding:2px 6px;font-size:11.5px;font-weight:700;">Sat</button>
            <button type="button" class="btn btn-sm btn-ghost exp-day-btn" data-day="0" onclick="setExpDayFilter('0', this)" style="padding:2px 6px;font-size:11.5px;font-weight:700;">Sun</button>
        </div>

        <div style="font-size:13px;color:var(--text-2);font-weight:700;margin-left:auto;">
            Showing <span id="expVisibleCount"><?= count($expenses) ?></span> of <?= count($expenses) ?> entries
        </div>
    </div>

    <!-- Expenses Table Panel with Inline Editing -->
    <div class="panel">
        <div class="table-responsive">
            <table id="expensesTable">
                <thead>
                    <tr>
                        <th style="min-width:90px;">Date</th>
                        <th>Description / Item Bought</th>
                        <th>Vehicle / Target</th>
                        <th>Vendor / Shop / Paid To</th>
                        <th>Receipt Status</th>
                        <th>Amount</th>
                        <th style="text-align:center;min-width:130px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:32px;color:var(--text-3);">No expenses recorded yet. Click "Record Expense".</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $e): ?>
                            <tr id="exp-row-<?= $e['id'] ?>" data-id="<?= $e['id'] ?>" data-expense-date="<?= htmlspecialchars($e['expense_date']) ?>">
                                <td class="cell-date" style="font-weight:700;white-space:nowrap;">
                                    <span class="view-val"><?= format_date_dol($e['expense_date']) ?></span>
                                </td>
                                <td class="cell-title">
                                    <b class="view-val" style="color:var(--text);"><?= htmlspecialchars($e['expense_title']) ?></b>
                                    <?php if (!empty($e['notes'])): ?>
                                        <div class="view-notes" style="font-size:12px;color:var(--text-3);margin-top:2px;"><?= htmlspecialchars($e['notes']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="cell-truck">
                                    <?php if (!empty($e['truck']) && strtolower($e['truck']) !== 'general business'): ?>
                                        <span class="view-val" onclick="selectTruckReport('<?= htmlspecialchars(addslashes($e['truck'])) ?>')" style="font-weight:700;color:var(--brand);background:var(--brand-soft);padding:3px 8px;border-radius:6px;font-size:13px;cursor:pointer;" title="Click to view full vehicle expense report">
                                            🔍 <?= htmlspecialchars($e['truck']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="view-val" style="font-weight:700;color:var(--text-3);background:var(--card-2);padding:3px 8px;border-radius:6px;font-size:13px;">
                                            General Business
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="cell-vendor" style="font-weight:600;color:var(--text-2);">
                                    <span class="view-val"><?= htmlspecialchars($e['garage_vendor'] ?: 'General Vendor') ?></span>
                                </td>
                                <td class="cell-receipt" style="white-space:nowrap;">
                                    <?php $isRec = ($e['receipt_status'] ?? 'Received') === 'Received'; ?>
                                    <?php if ($isRec): ?>
                                        <span class="view-val-status" style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:6px;font-size:12px;font-weight:800;color:var(--green);background:var(--green-soft);border:1px solid var(--green);">
                                            🧾 Received
                                        </span>
                                    <?php else: ?>
                                        <span class="view-val-status" style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:6px;font-size:12px;font-weight:800;color:var(--amber);background:var(--amber-soft);border:1px solid var(--amber);">
                                            ⏳ Pending
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="cell-amount" style="white-space:nowrap;font-weight:800;color:var(--red);">
                                    <span class="view-val"><?= format_money($e['amount']) ?></span>
                                </td>
                                <td class="cell-actions" style="text-align:center;white-space:nowrap;">
                                    <div class="row-normal-actions" style="display:inline-flex;gap:5px;align-items:center;">
                                        <button type="button" class="btn btn-sm btn-ghost" onclick="startExpInlineEdit(<?= $e['id'] ?>)" title="Edit expense directly on table">
                                            ✏️ Edit
                                        </button>
                                        <form method="POST" action="<?= url('expenses/delete/' . $e['id']) ?>" style="display:inline;" onsubmit="return confirm('Delete expense record?');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--red);border-color:transparent;padding:4px 7px;" title="Delete">✕</button>
                                        </form>
                                    </div>
                                    <div class="row-editing-actions" style="display:none;gap:6px;align-items:center;">
                                        <button type="button" class="row-save-btn" onclick="saveExpInlineEdit(<?= $e['id'] ?>)" title="Save Changes">✓ Save</button>
                                        <button type="button" class="row-cancel-btn" onclick="cancelExpInlineEdit(<?= $e['id'] ?>)" title="Cancel">✕</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Table Pagination Footer -->
        <div class="table-pagination-footer" id="expensesPagination"></div>
    </div>
</section>

<!-- Record Expense Modal (Free text inputs for any business purchase or repair) -->
<div class="modal-backdrop" id="expenseModal" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal-card" style="max-width:580px;">
        <div class="modal-head">
            <h2>💰 Record Business Expense</h2>
            <button class="close-modal" onclick="document.getElementById('expenseModal').classList.remove('active')">✕</button>
        </div>
        <form method="POST" action="<?= url('expenses/store') ?>">
            <?= csrf_field() ?>
            <div class="form-grid single" style="gap:14px;">
                <div class="form-group">
                    <label>Date of Expense *</label>
                    <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Expense Description / Item Bought (Free Text Input) *</label>
                    <input type="text" name="expense_title" placeholder="e.g. Brake linings, oil filters, office stationary, security lights" required>
                </div>
                <div class="form-group">
                    <label>Vehicle / Target (Live Fleet Status) *</label>
                    <select name="truck" required>
                        <option value="General Business">General Business / Yard / Office</option>
                        <?php foreach (($trucks ?? []) as $t): ?>
                            <option value="<?= htmlspecialchars($t['plate_number']) ?>">
                                <?= htmlspecialchars($t['plate_number']) ?> • <?= htmlspecialchars($t['status']) ?> (<?= htmlspecialchars($t['ownership']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div style="font-size:11.5px;color:var(--text-3);margin-top:2px;">Expenses recorded for trucks on active trips will link automatically as extra road expenses in Fleet Management.</div>
                </div>
                <div class="form-group">
                    <label>Vendor / Shop / Paid To (Free Text Input)</label>
                    <input type="text" name="garage_vendor" placeholder="e.g. Hardware shop, Simba Commercial Garage, Nairobi Supplies">
                </div>
                <div class="form-group">
                    <label>Total Amount [<?= app_currency_symbol() ?>] *</label>
                    <input type="number" step="0.01" name="amount" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label>Receipt Verification Status *</label>
                    <select name="receipt_status" required>
                        <option value="Received" selected>🧾 Received (Physical or digital receipt in hand)</option>
                        <option value="Pending">⏳ Pending / Not Received (Awaiting receipt from driver/vendor)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Additional Notes</label>
                    <input type="text" name="notes" placeholder="e.g. Parts bought for fleet servicing">
                </div>
            </div>
            <div style="margin-top:22px;display:flex;justify-content:flex-end;gap:12px;">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('expenseModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-brand">Save Expense</button>
            </div>
        </form>
    </div>
</div>

<!-- Expense Export Modal -->
<div class="modal-backdrop" id="expenseExportModal" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal-card" style="max-width:500px;">
        <div class="modal-head">
            <h2>📊 Export Business & Fleet Expenses</h2>
            <button class="close-modal" onclick="document.getElementById('expenseExportModal').classList.remove('active')">✕</button>
        </div>
        <form method="GET" action="<?= url('expenses/export') ?>" target="_blank">
            <div class="form-grid single" style="gap:14px;">
                <div class="form-group">
                    <label>Vehicle / Target</label>
                    <select name="truck">
                        <option value="">All Vehicles & General Expenses</option>
                        <option value="General Business">General Business Only</option>
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
                                <option value="<?= $m ?>"><?= date('F', mktime(0, 0, 0, $m, 10)) ?></option>
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
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('expenseExportModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-brand" onclick="setTimeout(() => document.getElementById('expenseExportModal').classList.remove('active'), 300)">Export Spreadsheet</button>
            </div>
        </form>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?= csrf_token() ?>';
const TRUCK_OPTIONS = <?= json_encode($trucks ?? []) ?>;

/* Period & Day Filter Logic for Expenses */
let activeExpPeriod = 'all';
let activeExpDay = 'all';

function setExpPeriodFilter(period, btn) {
    activeExpPeriod = period;
    document.querySelectorAll('.exp-time-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    const sub = document.getElementById('expWeekDaysSubToolbar');
    if (period === 'week') {
        sub.style.display = 'inline-flex';
    } else {
        sub.style.display = 'none';
        activeExpDay = 'all';
        document.querySelectorAll('.exp-day-btn').forEach(b => b.classList.remove('active'));
        document.querySelector('.exp-day-btn[data-day="all"]')?.classList.add('active');
    }
    applyExpenseFilters();
}

function setExpDayFilter(day, btn) {
    activeExpDay = day;
    document.querySelectorAll('.exp-day-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    applyExpenseFilters();
}

function matchesExpPeriod(dateStr) {
    if (activeExpPeriod === 'all' || !dateStr) return true;

    const parts = dateStr.split('-');
    if (parts.length !== 3) return true;
    const y = parseInt(parts[0], 10);
    const m = parseInt(parts[1], 10) - 1;
    const d = parseInt(parts[2], 10);
    const rowDate = new Date(y, m, d);

    const today = new Date();
    const currentYear = today.getFullYear();
    const currentMonth = today.getMonth();

    if (activeExpPeriod === 'year') {
        return y === currentYear;
    }
    if (activeExpPeriod === 'month') {
        return y === currentYear && m === currentMonth;
    }
    if (activeExpPeriod === 'week') {
        const dayOfWeek = today.getDay();
        const diffToMon = (dayOfWeek === 0 ? -6 : 1) - dayOfWeek;
        const monday = new Date(today.getFullYear(), today.getMonth(), today.getDate() + diffToMon);
        monday.setHours(0, 0, 0, 0);
        const sunday = new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() + 6);
        sunday.setHours(23, 59, 59, 999);

        const inWeek = (rowDate >= monday && rowDate <= sunday);
        if (!inWeek) return false;
        if (activeExpDay === 'all') return true;

        return rowDate.getDay() == parseInt(activeExpDay, 10);
    }
    return true;
}

const TRUCK_REPORTS = <?= json_encode($truckReports) ?>;
const ACTIVE_TRUCK_REPORT = <?= json_encode($activeTruckReport) ?>;
const IS_KES = <?= current_currency() === 'KES' ? 'true' : 'false' ?>;
const EXCHANGE_RATE = <?= (float)exchange_rate() ?>;
const CURRENCY_SYM = '<?= app_currency_symbol() ?>';
let currentSelectedYear = <?= (int)$selectedYear ?>;

function onExpenseTruckFilterChanged() {
    const sel = document.getElementById('expenseTruckFilter');
    const truckSel = (sel?.value || 'all').toLowerCase().trim();

    if (truckSel === 'all' || truckSel === 'general') {
        const card = document.getElementById('truckExpenseReportCard');
        if (card) card.style.display = 'none';
        applyExpenseFilters();
        if (window.history && window.history.replaceState) {
            const url = new URL(window.location.href);
            url.searchParams.delete('truck');
            window.history.replaceState({}, '', url.toString());
        }
        return;
    }

    let foundReport = null;
    for (const plate in TRUCK_REPORTS) {
        if (plate.toLowerCase() === truckSel) {
            foundReport = TRUCK_REPORTS[plate];
            break;
        }
    }

    if (foundReport) {
        renderTruckReportCard(foundReport, currentSelectedYear);
        if (window.history && window.history.replaceState) {
            const url = new URL(window.location.href);
            url.searchParams.set('truck', foundReport.plate_number);
            window.history.replaceState({}, '', url.toString());
        }
    }
    applyExpenseFilters();
}

function selectTruckReport(plate) {
    const sel = document.getElementById('expenseTruckFilter');
    if (sel) {
        sel.value = plate.toLowerCase().trim();
        onExpenseTruckFilterChanged();
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetTruckFilter() {
    const sel = document.getElementById('expenseTruckFilter');
    if (sel) {
        sel.value = 'all';
        onExpenseTruckFilterChanged();
    }
}

function onTruckReportYearChange(year) {
    currentSelectedYear = parseInt(year, 10);
    const sel = document.getElementById('expenseTruckFilter');
    const truckSel = (sel?.value || '').trim();
    if (truckSel && truckSel !== 'all' && truckSel !== 'general') {
        window.location.href = '<?= url("expenses") ?>?truck=' + encodeURIComponent(truckSel) + '&year=' + currentSelectedYear;
    }
}

function renderTruckReportCard(rep, year) {
    const card = document.getElementById('truckExpenseReportCard');
    if (!card) return;

    card.style.display = 'block';
    const plateEl = document.getElementById('reportTruckPlate');
    if (plateEl) plateEl.textContent = rep.plate_number;

    const ownEl = document.getElementById('reportTruckOwnership');
    if (ownEl) ownEl.textContent = rep.ownership || 'Owner';

    const stEl = document.getElementById('reportTruckStatusText');
    if (stEl) stEl.textContent = rep.status || 'Ready';

    const mult = IS_KES ? EXCHANGE_RATE : 1;
    const allTimeTotal = (parseFloat(rep.all_time_total) || 0) * mult;
    const yrTotal = (parseFloat(rep.yearly_total) || 0) * mult;
    const moAvg = (yrTotal / 12);

    const allTimeFmt = CURRENCY_SYM + ' ' + allTimeTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const yrFmt = CURRENCY_SYM + ' ' + yrTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const avgFmt = CURRENCY_SYM + ' ' + moAvg.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    const elAll = document.getElementById('reportAllTimeTotal');
    if (elAll) elAll.textContent = allTimeFmt;

    const elAllCnt = document.getElementById('reportAllTimeCount');
    if (elAllCnt) elAllCnt.textContent = (rep.all_time_count || 0) + ' total transactions across all years';

    const elYrLbl = document.getElementById('reportYearLabel');
    if (elYrLbl) elYrLbl.textContent = rep.selected_year || year;

    const elYrTot = document.getElementById('reportYearTotal');
    if (elYrTot) elYrTot.textContent = yrFmt;

    const elYrCnt = document.getElementById('reportYearCount');
    if (elYrCnt) elYrCnt.textContent = (rep.yearly_count || 0) + ' transactions in ' + (rep.selected_year || year);

    const elAvg = document.getElementById('reportMonthlyAvg');
    if (elAvg) elAvg.textContent = avgFmt;

    const elMatLbl = document.getElementById('matrixYearLabel');
    if (elMatLbl) elMatLbl.textContent = rep.selected_year || year;

    const yrSelect = document.getElementById('truckReportYearSelect');
    if (yrSelect && rep.available_years) {
        yrSelect.innerHTML = rep.available_years.map(y => `<option value="${y}" ${String(y) === String(rep.selected_year || year) ? 'selected' : ''}>${y}</option>`).join('');
    }

    const grid = document.getElementById('reportMonthlyMatrixGrid');
    if (grid && rep.monthly_matrix) {
        grid.innerHTML = Object.values(rep.monthly_matrix).map(m => {
            const amt = (parseFloat(m.total) || 0) * mult;
            const amtFmt = amt > 0 ? (CURRENCY_SYM + ' ' + amt.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})) : '—';
            const col = amt > 0 ? 'var(--red)' : 'var(--text-3)';
            return `
                <div style="background:var(--card-2);border:1px solid var(--border);border-radius:8px;padding:10px 8px;text-align:center;">
                    <div style="font-size:11px;font-weight:800;color:var(--text-3);text-transform:uppercase;">${m.month_name}</div>
                    <div style="font-size:13.5px;font-weight:900;color:${col};margin-top:4px;">${amtFmt}</div>
                    <div style="font-size:10.5px;color:var(--text-3);margin-top:3px;">${m.count > 0 ? (m.count + ' log' + (m.count > 1 ? 's' : '')) : '0 logs'}</div>
                </div>
            `;
        }).join('');
    }
}

function applyExpenseFilters() {
    const val = (document.getElementById('expenseFilter')?.value || '').toLowerCase().trim();
    const truckSel = (document.getElementById('expenseTruckFilter')?.value || 'all').toLowerCase().trim();
    const rows = document.querySelectorAll('#expensesTable tbody tr[id^="exp-row-"]');
    let cnt = 0;
    rows.forEach(r => {
        const text = r.textContent.toLowerCase();
        const rowDate = r.dataset.expenseDate || '';
        const rowTruck = (r.querySelector('.cell-truck .view-val')?.textContent || '').toLowerCase().trim();
        const matchesSearch = !val || text.includes(val);
        const matchesDate = matchesExpPeriod(rowDate);

        let matchesTruck = true;
        if (truckSel !== 'all') {
            if (truckSel === 'general') {
                matchesTruck = rowTruck.includes('general');
            } else {
                matchesTruck = rowTruck.includes(truckSel);
            }
        }

        if (matchesSearch && matchesDate && matchesTruck) {
            r.dataset.matchedFilter = 'true';
            cnt++;
        } else {
            r.dataset.matchedFilter = 'false';
        }
    });
    const cntSpan = document.getElementById('expVisibleCount');
    if (cntSpan) cntSpan.textContent = cnt;

    if (window.expensesPagination) {
        window.expensesPagination.refresh();
    }
}

// Live Search Filter
const expFilter = document.getElementById('expenseFilter');
if (expFilter) {
    expFilter.addEventListener('input', applyExpenseFilters);
}

// Initialize Expenses Table Pagination
const expensesPagination = initTablePagination({
    tableId: 'expensesTable',
    footerId: 'expensesPagination',
    defaultPageSize: 10,
    pageSizes: [10, 25, 50, 100],
    countSpanId: 'expVisibleCount',
    rowSelector: '#expensesTable tbody tr[id^="exp-row-"]'
});
window.expensesPagination = expensesPagination;

/* Expenses Inline Editing */
const expRowOriginal = {};

function startExpInlineEdit(id) {
    const row = document.getElementById('exp-row-' + id);
    if (!row || row.classList.contains('tr-editing')) return;

    expRowOriginal[id] = row.innerHTML;
    row.classList.add('tr-editing');

    const expDate = row.dataset.expenseDate || '';
    const title = row.querySelector('.cell-title .view-val')?.textContent.trim() || '';
    const notes = row.querySelector('.cell-title .view-notes')?.textContent.trim() || '';
    const truck = row.querySelector('.cell-truck .view-val')?.textContent.trim() || '';
    const vendor = row.querySelector('.cell-vendor .view-val')?.textContent.trim() || '';
    const isRec = row.querySelector('.cell-receipt')?.textContent.includes('Received');
    const amountRaw = parseFloat((row.querySelector('.cell-amount .view-val')?.textContent || '0').replace(/[^0-9.]/g, '')) || 0;

    row.querySelector('.cell-date').innerHTML = `<input type="date" class="table-inline-input inline-exp-date" value="${expDate}">`;
    row.querySelector('.cell-title').innerHTML = `
        <input type="text" class="table-inline-input inline-exp-title" value="${title}" style="margin-bottom:3px;">
        <input type="text" class="table-inline-input inline-exp-notes" value="${notes}" placeholder="Notes..." style="font-size:12px;">
    `;

    let truckOptionsHtml = `<option value="General Business">General Business (No Vehicle)</option>`;
    TRUCK_OPTIONS.forEach(t => {
        const isSel = (t.plate_number === truck);
        truckOptionsHtml += `<option value="${t.plate_number}" ${isSel ? 'selected' : ''}>${t.plate_number} • ${t.status}</option>`;
    });
    row.querySelector('.cell-truck').innerHTML = `<select class="table-inline-select inline-exp-truck" style="font-size:12px;">${truckOptionsHtml}</select>`;

    row.querySelector('.cell-vendor').innerHTML = `<input type="text" class="table-inline-input inline-exp-vendor" value="${vendor}">`;

    row.querySelector('.cell-receipt').innerHTML = `
        <select class="table-inline-select inline-exp-receipt-status" style="font-size:12px;">
            <option value="Received" ${isRec ? 'selected' : ''}>🧾 Received</option>
            <option value="Pending" ${!isRec ? 'selected' : ''}>⏳ Pending</option>
        </select>
    `;

    row.querySelector('.cell-amount').innerHTML = `<input type="number" step="0.01" class="table-inline-input inline-exp-amount" value="${amountRaw}">`;

    row.querySelector('.row-normal-actions').style.display = 'none';
    row.querySelector('.row-editing-actions').style.display = 'inline-flex';
}

function cancelExpInlineEdit(id) {
    const row = document.getElementById('exp-row-' + id);
    if (!row || !expRowOriginal[id]) return;
    row.innerHTML = expRowOriginal[id];
    row.classList.remove('tr-editing');
    delete expRowOriginal[id];
}

async function saveExpInlineEdit(id) {
    const row = document.getElementById('exp-row-' + id);
    if (!row) return;

    const dateVal = row.querySelector('.inline-exp-date')?.value;
    const titleVal = row.querySelector('.inline-exp-title')?.value;
    const notesVal = row.querySelector('.inline-exp-notes')?.value;
    const truckVal = row.querySelector('.inline-exp-truck')?.value;
    const vendorVal = row.querySelector('.inline-exp-vendor')?.value;
    const receiptStatusVal = row.querySelector('.inline-exp-receipt-status')?.value || 'Received';
    const amountVal = row.querySelector('.inline-exp-amount')?.value;

    const postData = {
        _csrf_token: CSRF_TOKEN,
        id: id,
        expense_date: dateVal,
        expense_title: titleVal,
        notes: notesVal,
        truck: truckVal,
        garage_vendor: vendorVal,
        receipt_status: receiptStatusVal,
        amount: amountVal
    };

    const isOffline = !navigator.onLine || (typeof isSimulatedOffline !== 'undefined' && isSimulatedOffline);
    if (isOffline) {
        queueOfflineAction('<?= url("expenses/inline-update") ?>', postData, () => {
            row.dataset.expenseDate = dateVal;
            row.querySelector('.cell-date').innerHTML = `<span class="view-val">${formatDateClient(dateVal)}</span><div class="offline-sync-badge">Pending Sync</div>`;
            row.querySelector('.cell-title').innerHTML = `<b class="view-val" style="color:var(--text);">${titleVal}</b>${notesVal ? `<div class="view-notes" style="font-size:12px;color:var(--text-3);">${notesVal}</div>` : ''}`;
            row.querySelector('.cell-truck').innerHTML = `<span class="view-val" style="font-weight:700;color:var(--brand);background:var(--brand-soft);padding:3px 8px;border-radius:6px;font-size:13px;">${truckVal || 'General'}</span>`;
            row.querySelector('.cell-vendor').innerHTML = `<span class="view-val">${vendorVal || 'General Vendor'}</span>`;

            const isRecOff = (receiptStatusVal === 'Received');
            row.querySelector('.cell-receipt').innerHTML = isRecOff
                ? `<span class="view-val-status" style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:6px;font-size:12px;font-weight:800;color:var(--green);background:var(--green-soft);border:1px solid var(--green);">🧾 Received</span>`
                : `<span class="view-val-status" style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:6px;font-size:12px;font-weight:800;color:var(--amber);background:var(--amber-soft);border:1px solid var(--amber);">⏳ Pending</span>`;

            row.querySelector('.cell-amount').innerHTML = `<span class="view-val"><?= app_currency_symbol() ?> ${parseFloat(amountVal).toFixed(2)}</span>`;

            row.querySelector('.row-normal-actions').style.display = 'inline-flex';
            row.querySelector('.row-editing-actions').style.display = 'none';
            row.classList.remove('tr-editing');
            delete expRowOriginal[id];
        });
        return;
    }

    try {
        const res = await fetch('<?= url("expenses/inline-update") ?>', {
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
            row.dataset.expenseDate = d.expense_date;
            row.querySelector('.cell-date').innerHTML = `<span class="view-val">${d.dol_formatted}</span>`;
            row.querySelector('.cell-title').innerHTML = `
                <b class="view-val" style="color:var(--text);">${d.expense_title}</b>
                ${d.notes ? `<div class="view-notes" style="font-size:12px;color:var(--text-3);margin-top:2px;">${d.notes}</div>` : ''}
            `;
            row.querySelector('.cell-truck').innerHTML = `
                <span class="view-val" style="font-weight:700;color:var(--brand);background:var(--brand-soft);padding:3px 8px;border-radius:6px;font-size:13px;">
                    ${d.truck}
                </span>
            `;
            row.querySelector('.cell-vendor').innerHTML = `<span class="view-val">${d.garage_vendor}</span>`;

            const isRecFin = (d.receipt_status === 'Received');
            row.querySelector('.cell-receipt').innerHTML = isRecFin
                ? `<span class="view-val-status" style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:6px;font-size:12px;font-weight:800;color:var(--green);background:var(--green-soft);border:1px solid var(--green);">🧾 Received</span>`
                : `<span class="view-val-status" style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:6px;font-size:12px;font-weight:800;color:var(--amber);background:var(--amber-soft);border:1px solid var(--amber);">⏳ Pending</span>`;

            row.querySelector('.cell-amount').innerHTML = `<span class="view-val">${d.amount_formatted}</span>`;

            row.querySelector('.row-normal-actions').style.display = 'inline-flex';
            row.querySelector('.row-editing-actions').style.display = 'none';
            row.classList.remove('tr-editing');
            delete expRowOriginal[id];

            row.style.transition = 'background .4s';
            row.style.background = 'var(--green-soft)';
            setTimeout(() => { row.style.background = ''; }, 1200);
        } else {
            alert('Could not update expense: ' + (json.error || 'Server error'));
        }
    } catch (err) {
        console.error('Update error:', err);
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
</script>
<?php };
require __DIR__ . '/../layouts/app.php';
?>
