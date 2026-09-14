<?php
$content = function () use ($title, $records, $kpis, $todayKpis, $categories, $methods, $filters) {
    $totalIn = (float)($kpis['total_in'] ?? 0);
    $totalOut = (float)($kpis['total_out'] ?? 0);
    $netBalance = (float)($kpis['net_balance'] ?? ($totalIn - $totalOut));
    
    $todayIn = (float)($todayKpis['today_in'] ?? 0);
    $todayOut = (float)($todayKpis['today_out'] ?? 0);
    $todayNet = (float)($todayKpis['today_net'] ?? ($todayIn - $todayOut));

    // Build export query string with existing filters
    $filterParams = array_filter($filters, fn($v) => $v !== null && $v !== '');
    $exportXlsxUrl = url('financial/export') . '?' . http_build_query(array_merge($filterParams, ['format' => 'xlsx']));
    $exportCsvUrl = url('financial/export') . '?' . http_build_query(array_merge($filterParams, ['format' => 'csv']));
?>
<section class="view active" id="view-financial">
    <!-- Header & Action Buttons -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;margin-bottom:20px;">
        <div class="hello">
            <h1 style="display:flex;align-items:center;gap:10px;margin:0;">
                <span>Finances & Cash Flow</span>
                <span style="font-size:24px;">💵</span>
            </h1>
            <p style="margin-top:4px;color:var(--text-2);font-size:14px;">
                Track daily cash inflow (amount in), daily expenditures (amount out), running balances, and personal/office expense notes.
            </p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <a href="<?= $exportXlsxUrl ?>" class="btn btn-ghost" id="btnExportFinancialXlsx" style="display:inline-flex;align-items:center;gap:7px;border-color:var(--green);color:var(--green);font-weight:700;">
                <svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span>Export Excel (.xlsx)</span>
            </a>
            <a href="<?= $exportCsvUrl ?>" class="btn btn-ghost" id="btnExportFinancialCsv" style="display:inline-flex;align-items:center;gap:7px;border-color:var(--border-2);color:var(--text-2);font-weight:700;">
                <svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                <span>Export CSV</span>
            </a>
            <button class="btn btn-brand" onclick="openNewFinancialModal()" style="display:inline-flex;align-items:center;gap:7px;box-shadow:0 4px 14px rgba(79,70,229,.35);">
                <span style="font-size:16px;font-weight:900;">＋</span>
                <span>Record Cash Flow</span>
            </button>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="kpis" style="margin-bottom:24px;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:16px;">
        <!-- Total In -->
        <div class="kpi" style="border:1.5px solid rgba(16,185,129,.35);background:linear-gradient(135deg,var(--card),rgba(16,185,129,.05));position:relative;overflow:hidden;">
            <div style="position:absolute;top:12px;right:14px;background:rgba(16,185,129,.15);color:var(--green);padding:4px 8px;border-radius:8px;font-size:11px;font-weight:800;">
                ▲ CASH IN
            </div>
            <div class="lbl" style="color:var(--text-2);font-weight:700;font-size:13px;">Total Money In (Received)</div>
            <div class="val" id="statTotalIn" style="color:var(--green);font-size:26px;font-weight:900;margin-top:6px;">
                <?= format_money($totalIn) ?>
            </div>
            <div style="font-size:12px;color:var(--text-3);margin-top:4px;">
                Freight revenues, client advances & injections
            </div>
        </div>

        <!-- Total Out -->
        <div class="kpi" style="border:1.5px solid rgba(239,68,68,.35);background:linear-gradient(135deg,var(--card),rgba(239,68,68,.05));position:relative;overflow:hidden;">
            <div style="position:absolute;top:12px;right:14px;background:rgba(239,68,68,.15);color:var(--red);padding:4px 8px;border-radius:8px;font-size:11px;font-weight:800;">
                ▼ CASH OUT
            </div>
            <div class="lbl" style="color:var(--text-2);font-weight:700;font-size:13px;">Total Money Out (Spent)</div>
            <div class="val" id="statTotalOut" style="color:var(--red);font-size:26px;font-weight:900;margin-top:6px;">
                <?= format_money($totalOut) ?>
            </div>
            <div style="font-size:12px;color:var(--text-3);margin-top:4px;">
                Operational expenses, fuel & personal draws
            </div>
        </div>

        <!-- Net Cash Balance -->
        <div class="kpi" style="border:1.5px solid <?= $netBalance >= 0 ? 'rgba(79,70,229,.4)' : 'rgba(239,68,68,.4)' ?>;background:linear-gradient(135deg,var(--card),<?= $netBalance >= 0 ? 'rgba(79,70,229,.06)' : 'rgba(239,68,68,.06)' ?>);position:relative;overflow:hidden;">
            <div style="position:absolute;top:12px;right:14px;background:<?= $netBalance >= 0 ? 'rgba(79,70,229,.15)' : 'rgba(239,68,68,.15)' ?>;color:<?= $netBalance >= 0 ? 'var(--brand)' : 'var(--red)' ?>;padding:4px 8px;border-radius:8px;font-size:11px;font-weight:800;">
                NET BALANCE
            </div>
            <div class="lbl" style="color:var(--text-2);font-weight:700;font-size:13px;">Cumulative Net Balance</div>
            <div class="val" id="statNetBalance" style="color:<?= $netBalance >= 0 ? 'var(--brand)' : 'var(--red)' ?>;font-size:26px;font-weight:900;margin-top:6px;">
                <?= format_money($netBalance) ?>
            </div>
            <div style="font-size:12px;color:var(--text-3);margin-top:4px;">
                Net cash retained after all expenditures
            </div>
        </div>

        <!-- Today's Activity -->
        <div class="kpi" style="border:1.5px solid var(--border-2);background:var(--card);">
            <div class="lbl" style="color:var(--text-2);font-weight:700;font-size:13px;">Today's Cash Activity (<?= date('d M') ?>)</div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;">
                <div>
                    <div style="font-size:11.5px;color:var(--text-3);font-weight:600;">In: <b style="color:var(--green);" id="statTodayIn">+<?= format_money($todayIn) ?></b></div>
                    <div style="font-size:11.5px;color:var(--text-3);font-weight:600;margin-top:2px;">Out: <b style="color:var(--red);" id="statTodayOut">-<?= format_money($todayOut) ?></b></div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:11px;color:var(--text-3);font-weight:700;">Day Net:</div>
                    <div id="statTodayNet" style="font-size:16px;font-weight:900;color:<?= $todayNet >= 0 ? 'var(--green)' : 'var(--red)' ?>;">
                        <?= ($todayNet >= 0 ? '+' : '') . format_money($todayNet) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Toolbar -->
    <div class="panel" style="padding:14px 18px;margin-bottom:20px;border-radius:14px;">
        <form method="GET" action="<?= url('financial') ?>" id="finFilterForm" style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;">
            <!-- Search -->
            <div style="flex:1;min-width:220px;position:relative;">
                <input type="text" name="search" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" placeholder="Search reason, note, category, ref #…" style="width:100%;padding:8px 12px 8px 34px;border:1.5px solid var(--border-2);border-radius:9px;background:var(--card);color:var(--text);font-size:13.5px;">
                <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-3);font-size:14px;">🔍</span>
            </div>

            <!-- Category -->
            <div style="min-width:160px;">
                <select name="category" style="width:100%;padding:8px 12px;border:1.5px solid var(--border-2);border-radius:9px;background:var(--card);color:var(--text);font-size:13px;font-weight:600;">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= ($filters['category'] ?? '') === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Payment Method -->
            <div style="min-width:150px;">
                <select name="payment_method" style="width:100%;padding:8px 12px;border:1.5px solid var(--border-2);border-radius:9px;background:var(--card);color:var(--text);font-size:13px;font-weight:600;">
                    <option value="">All Methods</option>
                    <?php foreach ($methods as $m): ?>
                        <option value="<?= htmlspecialchars($m) ?>" <?= ($filters['payment_method'] ?? '') === $m ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date Range -->
            <div style="display:flex;align-items:center;gap:6px;">
                <input type="date" name="start_date" value="<?= htmlspecialchars($filters['start_date'] ?? '') ?>" title="From Date" style="padding:7px 10px;border:1.5px solid var(--border-2);border-radius:9px;background:var(--card);color:var(--text);font-size:13px;">
                <span style="color:var(--text-3);font-size:12px;font-weight:700;">to</span>
                <input type="date" name="end_date" value="<?= htmlspecialchars($filters['end_date'] ?? '') ?>" title="To Date" style="padding:7px 10px;border:1.5px solid var(--border-2);border-radius:9px;background:var(--card);color:var(--text);font-size:13px;">
            </div>

            <!-- Submit & Reset -->
            <button type="submit" class="btn btn-brand" style="padding:8px 16px;font-size:13px;">Filter</button>
            <?php if (!empty($filterParams)): ?>
                <a href="<?= url('financial') ?>" class="btn btn-ghost" style="padding:8px 14px;font-size:13px;color:var(--text-3);">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Financial Cash Flow Ledger Table with Inline Editable Columns -->
    <div class="panel" style="padding:0;overflow:hidden;border-radius:16px;">
        <div class="panel-head" style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--border);">
            <div>
                <h3 style="margin:0;font-size:16px;font-weight:800;">Daily Transactions & Cash Flow Ledger</h3>
                <small style="color:var(--text-3);font-size:12.5px;">Click "Edit" on any row to edit columns directly in the table. Changes auto-save.</small>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <span class="status s-done" style="font-size:12px;">
                    <i></i> <?= count($records) ?> Transactions Listed
                </span>
            </div>
        </div>

        <div class="table-responsive">
            <table id="financialTable" style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="width:110px;">Date</th>
                        <th style="width:140px;">Category</th>
                        <th>Expenditure Reason / Note</th>
                        <th style="width:120px;">Method</th>
                        <th style="width:110px;">Ref #</th>
                        <th style="width:130px;text-align:right;">Amount In</th>
                        <th style="width:130px;text-align:right;">Amount Out</th>
                        <th style="width:130px;text-align:right;">Day Net Balance</th>
                        <th style="width:130px;text-align:right;">Running Balance</th>
                        <th style="width:110px;text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="financialTableBody">
                    <?php if (empty($records)): ?>
                        <tr id="emptyFinRow">
                            <td colspan="10" style="text-align:center;padding:48px 20px;color:var(--text-3);">
                                <div style="font-size:36px;margin-bottom:8px;">💳</div>
                                <div style="font-weight:700;font-size:15px;color:var(--text-2);">No financial records found</div>
                                <div style="font-size:13px;margin-top:4px;">Click "Record Cash Flow" above to log income or daily expenditures.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $r): 
                            $id = (int)$r['id'];
                            $in = (float)$r['amount_in'];
                            $out = (float)$r['amount_out'];
                            $bal = (float)$r['balance'];
                            $runBal = (float)$r['running_balance'];
                        ?>
                            <tr id="finRow-<?= $id ?>" data-id="<?= $id ?>" class="fin-data-row">
                                <!-- Date -->
                                <td class="cell-date" data-field="entry_date" data-val="<?= htmlspecialchars($r['entry_date']) ?>">
                                    <span class="view-val" style="font-weight:700;color:var(--text);font-size:13.5px;"><?= htmlspecialchars($r['entry_date']) ?></span>
                                </td>

                                <!-- Category -->
                                <td class="cell-category" data-field="category" data-val="<?= htmlspecialchars($r['category']) ?>">
                                    <span class="view-val">
                                        <span class="status <?= ($r['category'] === 'Client Inflow' || $in > 0) ? 's-done' : ($r['category'] === 'Personal Drawing' ? 's-hold' : 's-plan') ?>" style="font-size:11.5px;padding:3px 8px;">
                                            <?= htmlspecialchars($r['category']) ?>
                                        </span>
                                    </span>
                                </td>

                                <!-- Reason / Expenditure Note -->
                                <td class="cell-reason" data-field="reason" data-val="<?= htmlspecialchars($r['reason'] ?? '') ?>">
                                    <span class="view-val" style="font-size:13.5px;color:var(--text);line-height:1.4;">
                                        <?= htmlspecialchars($r['reason'] ?: '—') ?>
                                    </span>
                                </td>

                                <!-- Payment Method -->
                                <td class="cell-method" data-field="payment_method" data-val="<?= htmlspecialchars($r['payment_method']) ?>">
                                    <span class="view-val" style="font-size:12.5px;font-weight:700;color:var(--text-2);">
                                        <?= htmlspecialchars($r['payment_method']) ?>
                                    </span>
                                </td>

                                <!-- Reference # -->
                                <td class="cell-ref" data-field="reference_no" data-val="<?= htmlspecialchars($r['reference_no'] ?? '') ?>">
                                    <span class="view-val" style="font-size:12px;font-family:monospace;color:var(--text-3);font-weight:600;">
                                        <?= htmlspecialchars($r['reference_no'] ?: '—') ?>
                                    </span>
                                </td>

                                <!-- Amount In -->
                                <td class="cell-in" data-field="amount_in" data-val="<?= $in ?>" style="text-align:right;">
                                    <span class="view-val" style="font-weight:800;color:<?= $in > 0 ? 'var(--green)' : 'var(--text-3)' ?>;font-size:13.5px;">
                                        <?= $in > 0 ? ('+' . format_money($in)) : '—' ?>
                                    </span>
                                </td>

                                <!-- Amount Out -->
                                <td class="cell-out" data-field="amount_out" data-val="<?= $out ?>" style="text-align:right;">
                                    <span class="view-val" style="font-weight:800;color:<?= $out > 0 ? 'var(--red)' : 'var(--text-3)' ?>;font-size:13.5px;">
                                        <?= $out > 0 ? ('-' . format_money($out)) : '—' ?>
                                    </span>
                                </td>

                                <!-- Day Net Balance -->
                                <td class="cell-bal" data-val="<?= $bal ?>" style="text-align:right;">
                                    <span class="view-val" style="font-weight:900;color:<?= $bal >= 0 ? 'var(--green)' : 'var(--red)' ?>;font-size:13.5px;">
                                        <?= ($bal >= 0 ? '+' : '') . format_money($bal) ?>
                                    </span>
                                </td>

                                <!-- Running Balance -->
                                <td class="cell-runbal" data-val="<?= $runBal ?>" style="text-align:right;">
                                    <span class="view-val" style="font-weight:800;color:<?= $runBal >= 0 ? 'var(--brand)' : 'var(--red)' ?>;font-size:13.5px;">
                                        <?= format_money($runBal) ?>
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td class="cell-actions" style="text-align:center;">
                                    <div class="view-actions" style="display:flex;gap:5px;justify-content:center;align-items:center;">
                                        <button type="button" class="btn btn-ghost" onclick="startFinInlineEdit(<?= $id ?>)" title="Edit this record inline" style="padding:4px 8px;font-size:12px;">
                                            ✏️ Edit
                                        </button>
                                        <form method="POST" action="<?= url('financial/delete/' . $id) ?>" onsubmit="return confirm('Are you sure you want to delete this financial entry?');" style="display:inline;margin:0;">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-ghost" title="Delete" style="padding:4px 7px;font-size:12px;color:var(--red);">
                                                🗑️
                                            </button>
                                        </form>
                                    </div>
                                    <div class="edit-actions" style="display:none;gap:4px;justify-content:center;align-items:center;">
                                        <button type="button" class="btn btn-brand row-save-btn" onclick="saveFinInlineEdit(<?= $id ?>)" style="padding:4px 9px;font-size:12px;">
                                            💾 Save
                                        </button>
                                        <button type="button" class="btn btn-ghost row-cancel-btn" onclick="cancelFinInlineEdit(<?= $id ?>)" style="padding:4px 7px;font-size:12px;">
                                            ✕
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Modal: Record Cash Flow / Transaction -->
<div class="modal-backdrop" id="modalNewFinancial">
    <div class="modal-card" style="max-width:560px;">
        <div class="modal-head">
            <h2 style="display:flex;align-items:center;gap:8px;">
                <span>Record Cash Flow Transaction</span>
                <span style="font-size:20px;">💵</span>
            </h2>
            <button type="button" class="close-modal" onclick="closeNewFinancialModal()">✕</button>
        </div>

        <form method="POST" action="<?= url('financial/store') ?>" id="formNewFinancial" onsubmit="return handleNewFinancialSubmit(event)">
            <?= csrf_field() ?>
            
            <div class="form-grid single" style="gap:14px;">
                <!-- Date & Category -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label for="finEntryDate">Transaction Date *</label>
                        <input type="date" id="finEntryDate" name="entry_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="finCategory">Category *</label>
                        <select id="finCategory" name="category" required>
                            <option value="Client Inflow">Client Inflow (Haulage / Payout)</option>
                            <option value="Fuel & Fleet">Fuel & Fleet Refueling</option>
                            <option value="Personal Drawing">Personal Drawing / Withdrawal</option>
                            <option value="Office Operations">Office Operations & Utilities</option>
                            <option value="Driver Allowances">Driver Transit Allowances</option>
                            <option value="Maintenance & Repairs">Maintenance & Repairs</option>
                            <option value="Staff Wages">Staff / Casual Wages</option>
                            <option value="Banking & Taxes">Banking & Taxes</option>
                            <option value="General">General / Miscellaneous</option>
                        </select>
                    </div>
                </div>

                <!-- Dual Amount In & Amount Out Inputs -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;background:var(--card-2);padding:14px;border-radius:12px;border:1px solid var(--border);">
                    <div class="form-group">
                        <label for="finAmountIn" style="color:var(--green);display:flex;align-items:center;gap:4px;">
                            <span>Amount In (Money Received)</span>
                            <span style="font-size:11px;">▲</span>
                        </label>
                        <input type="number" step="0.01" min="0" id="finAmountIn" name="amount_in" placeholder="0.00" oninput="calcModalNetBalance()" style="font-size:15px;font-weight:700;color:var(--green);">
                        <small style="color:var(--text-3);font-size:11px;">Income, client deposits, inflows</small>
                    </div>

                    <div class="form-group">
                        <label for="finAmountOut" style="color:var(--red);display:flex;align-items:center;gap:4px;">
                            <span>Amount Out (Money Spent)</span>
                            <span style="font-size:11px;">▼</span>
                        </label>
                        <input type="number" step="0.01" min="0" id="finAmountOut" name="amount_out" placeholder="0.00" oninput="calcModalNetBalance()" style="font-size:15px;font-weight:700;color:var(--red);">
                        <small style="color:var(--text-3);font-size:11px;">Expenses, drawings, costs</small>
                    </div>

                    <!-- Live Net Preview Box -->
                    <div style="grid-column:1/-1;display:flex;justify-content:space-between;align-items:center;padding-top:10px;border-top:1px dashed var(--border);margin-top:4px;">
                        <span style="font-size:12.5px;font-weight:700;color:var(--text-2);">Calculated Net Day Impact:</span>
                        <span id="finModalNetPreview" style="font-size:16px;font-weight:900;color:var(--text-3);">0.00</span>
                    </div>
                </div>

                <!-- Reason / Expenditure Note -->
                <div class="form-group">
                    <label for="finReason">Expenditure Reason / Note *</label>
                    <textarea id="finReason" name="reason" rows="2" placeholder="e.g. Director personal drawing, Office internet bill, Advance per diem for Kampala route..." required style="padding:10px 12px;border:1.5px solid var(--border-2);border-radius:10px;background:var(--card);color:var(--text);font-family:inherit;font-size:13.5px;resize:vertical;"></textarea>
                    <small style="color:var(--text-3);font-size:11.5px;">Give a clear reason or explanation for this expenditure or cash inflow.</small>
                </div>

                <!-- Payment Method & Reference -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label for="finPaymentMethod">Payment Method</label>
                        <select id="finPaymentMethod" name="payment_method">
                            <option value="Cash">Cash</option>
                            <option value="M-Pesa">M-Pesa</option>
                            <option value="Bank Transfer">Bank Transfer (EFT/RTGS)</option>
                            <option value="Card">Card</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="finRefNo">Reference / Receipt #</label>
                        <input type="text" id="finRefNo" name="reference_no" placeholder="e.g. MP-8921 or REC-041">
                    </div>
                </div>

                <!-- Buttons -->
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px;">
                    <button type="button" class="btn btn-ghost" onclick="closeNewFinancialModal()">Cancel</button>
                    <button type="submit" class="btn btn-brand" id="btnSaveFinancial">
                        <span>Save Transaction</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?= csrf_token() ?>';

function openNewFinancialModal() {
    const m = document.getElementById('modalNewFinancial');
    if (m) {
        m.classList.add('active');
        document.getElementById('finAmountIn').value = '';
        document.getElementById('finAmountOut').value = '';
        document.getElementById('finReason').value = '';
        document.getElementById('finRefNo').value = '';
        calcModalNetBalance();
    }
}

function closeNewFinancialModal() {
    const m = document.getElementById('modalNewFinancial');
    if (m) m.classList.remove('active');
}

function calcModalNetBalance() {
    const inVal = parseFloat(document.getElementById('finAmountIn')?.value || 0);
    const outVal = parseFloat(document.getElementById('finAmountOut')?.value || 0);
    const net = inVal - outVal;

    const el = document.getElementById('finModalNetPreview');
    if (!el) return;

    if (inVal === 0 && outVal === 0) {
        el.textContent = '0.00';
        el.style.color = 'var(--text-3)';
    } else if (net >= 0) {
        el.textContent = '+' + net.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        el.style.color = 'var(--green)';
    } else {
        el.textContent = net.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        el.style.color = 'var(--red)';
    }
}

async function handleNewFinancialSubmit(e) {
    e.preventDefault();
    const form = document.getElementById('formNewFinancial');
    const formData = new FormData(form);
    const btn = document.getElementById('btnSaveFinancial');
    btn.disabled = true;
    btn.innerHTML = 'Saving…';

    try {
        const res = await fetch('<?= url("financial/store") ?>', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        const data = await res.json();
        if (data.success) {
            closeNewFinancialModal();
            window.location.reload();
        } else {
            alert(data.message || 'Error saving financial record.');
            btn.disabled = false;
            btn.innerHTML = 'Save Transaction';
        }
    } catch (err) {
        // Fallback standard submit if JSON error
        form.submit();
    }
}

/* ==========================================================================
   INLINE EDITING FOR FINANCIAL TABLE COLUMNS
   Requirement: "the ensur all tables in the system are editables in columns"
   ========================================================================== */
function startFinInlineEdit(id) {
    const row = document.getElementById('finRow-' + id);
    if (!row || row.classList.contains('tr-editing')) return;

    row.classList.add('tr-editing');

    // Extract current values
    const dateVal = row.querySelector('.cell-date').getAttribute('data-val') || '';
    const catVal = row.querySelector('.cell-category').getAttribute('data-val') || 'General';
    const reasonVal = row.querySelector('.cell-reason').getAttribute('data-val') || '';
    const methodVal = row.querySelector('.cell-method').getAttribute('data-val') || 'Cash';
    const refVal = row.querySelector('.cell-ref').getAttribute('data-val') || '';
    const inVal = row.querySelector('.cell-in').getAttribute('data-val') || '0';
    const outVal = row.querySelector('.cell-out').getAttribute('data-val') || '0';

    // Replace Date
    row.querySelector('.cell-date').innerHTML = `
        <input type="date" class="table-inline-input inline-date" value="${escapeHtml(dateVal)}" style="padding:4px 6px;font-size:12.5px;">
    `;

    // Replace Category
    row.querySelector('.cell-category').innerHTML = `
        <select class="table-inline-input inline-category" style="padding:4px 6px;font-size:12px;">
            <option value="Client Inflow" ${catVal === 'Client Inflow' ? 'selected' : ''}>Client Inflow</option>
            <option value="Fuel & Fleet" ${catVal === 'Fuel & Fleet' ? 'selected' : ''}>Fuel & Fleet</option>
            <option value="Personal Drawing" ${catVal === 'Personal Drawing' ? 'selected' : ''}>Personal Drawing</option>
            <option value="Office Operations" ${catVal === 'Office Operations' ? 'selected' : ''}>Office Operations</option>
            <option value="Driver Allowances" ${catVal === 'Driver Allowances' ? 'selected' : ''}>Driver Allowances</option>
            <option value="Maintenance & Repairs" ${catVal === 'Maintenance & Repairs' ? 'selected' : ''}>Maintenance</option>
            <option value="Staff Wages" ${catVal === 'Staff Wages' ? 'selected' : ''}>Staff Wages</option>
            <option value="General" ${catVal === 'General' ? 'selected' : ''}>General</option>
        </select>
    `;

    // Replace Reason
    row.querySelector('.cell-reason').innerHTML = `
        <input type="text" class="table-inline-input inline-reason" value="${escapeHtml(reasonVal)}" style="padding:4px 8px;font-size:13px;width:100%;">
    `;

    // Replace Method
    row.querySelector('.cell-method').innerHTML = `
        <select class="table-inline-input inline-method" style="padding:4px 6px;font-size:12px;">
            <option value="Cash" ${methodVal === 'Cash' ? 'selected' : ''}>Cash</option>
            <option value="M-Pesa" ${methodVal === 'M-Pesa' ? 'selected' : ''}>M-Pesa</option>
            <option value="Bank Transfer" ${methodVal === 'Bank Transfer' ? 'selected' : ''}>Bank Transfer</option>
            <option value="Card" ${methodVal === 'Card' ? 'selected' : ''}>Card</option>
            <option value="Cheque" ${methodVal === 'Cheque' ? 'selected' : ''}>Cheque</option>
        </select>
    `;

    // Replace Ref #
    row.querySelector('.cell-ref').innerHTML = `
        <input type="text" class="table-inline-input inline-ref" value="${escapeHtml(refVal)}" style="padding:4px 6px;font-size:12px;width:100%;">
    `;

    // Replace In & Out with live recalculation
    row.querySelector('.cell-in').innerHTML = `
        <input type="number" step="0.01" min="0" class="table-inline-input inline-in" value="${inVal}" oninput="calcRowLiveNet(${id})" style="padding:4px 6px;font-size:13px;width:100px;text-align:right;color:var(--green);font-weight:700;">
    `;

    row.querySelector('.cell-out').innerHTML = `
        <input type="number" step="0.01" min="0" class="table-inline-input inline-out" value="${outVal}" oninput="calcRowLiveNet(${id})" style="padding:4px 6px;font-size:13px;width:100px;text-align:right;color:var(--red);font-weight:700;">
    `;

    // Toggle actions
    row.querySelector('.view-actions').style.display = 'none';
    row.querySelector('.edit-actions').style.display = 'flex';
}

function calcRowLiveNet(id) {
    const row = document.getElementById('finRow-' + id);
    if (!row) return;

    const inVal = parseFloat(row.querySelector('.inline-in')?.value || 0);
    const outVal = parseFloat(row.querySelector('.inline-out')?.value || 0);
    const net = inVal - outVal;

    const balCell = row.querySelector('.cell-bal');
    if (balCell) {
        const netStr = (net >= 0 ? '+' : '') + net.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        balCell.innerHTML = `<span style="font-weight:900;color:${net >= 0 ? 'var(--green)' : 'var(--red)'};font-size:13.5px;">${netStr}</span>`;
    }
}

async function saveFinInlineEdit(id) {
    const row = document.getElementById('finRow-' + id);
    if (!row) return;

    const date = row.querySelector('.inline-date')?.value || '';
    const cat = row.querySelector('.inline-category')?.value || 'General';
    const reason = row.querySelector('.inline-reason')?.value || '';
    const method = row.querySelector('.inline-method')?.value || 'Cash';
    const ref = row.querySelector('.inline-ref')?.value || '';
    const amtIn = parseFloat(row.querySelector('.inline-in')?.value || 0);
    const amtOut = parseFloat(row.querySelector('.inline-out')?.value || 0);

    const postData = {
        _csrf_token: CSRF_TOKEN,
        id: id,
        entry_date: date,
        category: cat,
        reason: reason,
        payment_method: method,
        reference_no: ref,
        amount_in: amtIn,
        amount_out: amtOut
    };

    const saveBtn = row.querySelector('.row-save-btn');
    if (saveBtn) saveBtn.innerHTML = '…';

    try {
        const res = await fetch('<?= url("financial/inline-update") ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify(postData)
        });

        const data = await res.json();
        if (data.success && data.row) {
            const r = data.row;
            row.classList.remove('tr-editing');

            // Update cell attributes & HTML
            row.querySelector('.cell-date').setAttribute('data-val', r.entry_date);
            row.querySelector('.cell-date').innerHTML = `<span class="view-val" style="font-weight:700;color:var(--text);font-size:13.5px;">${escapeHtml(r.entry_date)}</span>`;

            row.querySelector('.cell-category').setAttribute('data-val', r.category);
            const catBadgeClass = (r.category === 'Client Inflow' || r.amount_in > 0) ? 's-done' : (r.category === 'Personal Drawing' ? 's-hold' : 's-plan');
            row.querySelector('.cell-category').innerHTML = `<span class="view-val"><span class="status ${catBadgeClass}" style="font-size:11.5px;padding:3px 8px;">${escapeHtml(r.category)}</span></span>`;

            row.querySelector('.cell-reason').setAttribute('data-val', r.reason);
            row.querySelector('.cell-reason').innerHTML = `<span class="view-val" style="font-size:13.5px;color:var(--text);line-height:1.4;">${escapeHtml(r.reason || '—')}</span>`;

            row.querySelector('.cell-method').setAttribute('data-val', r.payment_method);
            row.querySelector('.cell-method').innerHTML = `<span class="view-val" style="font-size:12.5px;font-weight:700;color:var(--text-2);">${escapeHtml(r.payment_method)}</span>`;

            row.querySelector('.cell-ref').setAttribute('data-val', r.reference_no);
            row.querySelector('.cell-ref').innerHTML = `<span class="view-val" style="font-size:12px;font-family:monospace;color:var(--text-3);font-weight:600;">${escapeHtml(r.reference_no || '—')}</span>`;

            row.querySelector('.cell-in').setAttribute('data-val', r.amount_in);
            const inStr = r.amount_in > 0 ? ('+' + Number(r.amount_in).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})) : '—';
            row.querySelector('.cell-in').innerHTML = `<span class="view-val" style="font-weight:800;color:${r.amount_in > 0 ? 'var(--green)' : 'var(--text-3)'};font-size:13.5px;">${inStr}</span>`;

            row.querySelector('.cell-out').setAttribute('data-val', r.amount_out);
            const outStr = r.amount_out > 0 ? ('-' + Number(r.amount_out).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})) : '—';
            row.querySelector('.cell-out').innerHTML = `<span class="view-val" style="font-weight:800;color:${r.amount_out > 0 ? 'var(--red)' : 'var(--text-3)'};font-size:13.5px;">${outStr}</span>`;

            row.querySelector('.cell-bal').setAttribute('data-val', r.balance);
            const balStr = (r.balance >= 0 ? '+' : '') + Number(r.balance).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            row.querySelector('.cell-bal').innerHTML = `<span class="view-val" style="font-weight:900;color:${r.balance >= 0 ? 'var(--green)' : 'var(--red)'};font-size:13.5px;">${balStr}</span>`;

            // Toggle actions back
            row.querySelector('.view-actions').style.display = 'flex';
            row.querySelector('.edit-actions').style.display = 'none';

            // Flash green
            row.style.transition = 'background-color 0.4s ease';
            row.style.backgroundColor = 'rgba(16,185,129,0.15)';
            setTimeout(() => {
                row.style.backgroundColor = '';
            }, 1000);

            // Update KPI cards if returned
            if (data.kpis) {
                const elTotIn = document.getElementById('statTotalIn');
                const elTotOut = document.getElementById('statTotalOut');
                const elTotBal = document.getElementById('statNetBalance');
                if (elTotIn) elTotIn.textContent = formatSystemMoney(data.kpis.total_in);
                if (elTotOut) elTotOut.textContent = formatSystemMoney(data.kpis.total_out);
                if (elTotBal) elTotBal.textContent = formatSystemMoney(data.kpis.net_balance);
            }
        } else {
            alert(data.message || 'Could not update row.');
            if (saveBtn) saveBtn.innerHTML = '💾 Save';
        }
    } catch (err) {
        alert('Network error while updating.');
        if (saveBtn) saveBtn.innerHTML = '💾 Save';
    }
}

function cancelFinInlineEdit(id) {
    const row = document.getElementById('finRow-' + id);
    if (!row) return;

    row.classList.remove('tr-editing');
    const dateVal = row.querySelector('.cell-date').getAttribute('data-val') || '';
    const catVal = row.querySelector('.cell-category').getAttribute('data-val') || 'General';
    const reasonVal = row.querySelector('.cell-reason').getAttribute('data-val') || '';
    const methodVal = row.querySelector('.cell-method').getAttribute('data-val') || 'Cash';
    const refVal = row.querySelector('.cell-ref').getAttribute('data-val') || '';
    const inVal = parseFloat(row.querySelector('.cell-in').getAttribute('data-val') || 0);
    const outVal = parseFloat(row.querySelector('.cell-out').getAttribute('data-val') || 0);
    const balVal = parseFloat(row.querySelector('.cell-bal').getAttribute('data-val') || 0);

    row.querySelector('.cell-date').innerHTML = `<span class="view-val" style="font-weight:700;color:var(--text);font-size:13.5px;">${escapeHtml(dateVal)}</span>`;
    const catBadgeClass = (catVal === 'Client Inflow' || inVal > 0) ? 's-done' : (catVal === 'Personal Drawing' ? 's-hold' : 's-plan');
    row.querySelector('.cell-category').innerHTML = `<span class="view-val"><span class="status ${catBadgeClass}" style="font-size:11.5px;padding:3px 8px;">${escapeHtml(catVal)}</span></span>`;
    row.querySelector('.cell-reason').innerHTML = `<span class="view-val" style="font-size:13.5px;color:var(--text);line-height:1.4;">${escapeHtml(reasonVal || '—')}</span>`;
    row.querySelector('.cell-method').innerHTML = `<span class="view-val" style="font-size:12.5px;font-weight:700;color:var(--text-2);">${escapeHtml(methodVal)}</span>`;
    row.querySelector('.cell-ref').innerHTML = `<span class="view-val" style="font-size:12px;font-family:monospace;color:var(--text-3);font-weight:600;">${escapeHtml(refVal || '—')}</span>`;
    
    const inStr = inVal > 0 ? ('+' + inVal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})) : '—';
    row.querySelector('.cell-in').innerHTML = `<span class="view-val" style="font-weight:800;color:${inVal > 0 ? 'var(--green)' : 'var(--text-3)'};font-size:13.5px;">${inStr}</span>`;
    
    const outStr = outVal > 0 ? ('-' + outVal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})) : '—';
    row.querySelector('.cell-out').innerHTML = `<span class="view-val" style="font-weight:800;color:${outVal > 0 ? 'var(--red)' : 'var(--text-3)'};font-size:13.5px;">${outStr}</span>`;
    
    const balStr = (balVal >= 0 ? '+' : '') + balVal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    row.querySelector('.cell-bal').innerHTML = `<span class="view-val" style="font-weight:900;color:${balVal >= 0 ? 'var(--green)' : 'var(--red)'};font-size:13.5px;">${balStr}</span>`;

    row.querySelector('.view-actions').style.display = 'flex';
    row.querySelector('.edit-actions').style.display = 'none';
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
<?php
};

require __DIR__ . '/../layouts/app.php';
