<?php
$content = function () use ($title, $records, $kpis, $todayKpis, $categories, $methods, $filters, $pagination) {
    $totalIn = (float)($kpis['total_in'] ?? 0);
    $totalOut = (float)($kpis['total_out'] ?? 0);
    $netBalance = (float)($kpis['net_balance'] ?? ($totalIn - $totalOut));
    
    $todayIn = (float)($todayKpis['today_in'] ?? 0);
    $todayOut = (float)($todayKpis['today_out'] ?? 0);
    $todayNet = (float)($todayKpis['today_net'] ?? ($todayIn - $todayOut));

    $exRate = function_exists('exchange_rate') ? (float)exchange_rate() : 128.0;
    if ($exRate <= 0) $exRate = 128.0;

    $currCode = function_exists('get_current_currency') ? get_current_currency() : 'USD';
    $currSym = function_exists('currency_symbol') ? currency_symbol() : '$';
    $isKesActive = ($currCode === 'KES');

    $currentPage = (int)($pagination['current_page'] ?? 1);
    $perPage = (int)($pagination['per_page'] ?? 20);
    $totalPages = (int)($pagination['total_pages'] ?? 1);
    $totalRecords = (int)($pagination['total_records'] ?? count($records));
    $startRecord = (int)($pagination['start_record'] ?? 1);
    $endRecord = (int)($pagination['end_record'] ?? count($records));

    // Build base filter params for URLs
    $filterParams = array_filter($filters, fn($v) => $v !== null && $v !== '');
    unset($filterParams['page']);

    $exportXlsxUrl = url('financial/export') . '?' . http_build_query(array_merge($filterParams, ['format' => 'xlsx']));
    $exportCsvUrl = url('financial/export') . '?' . http_build_query(array_merge($filterParams, ['format' => 'csv']));
?>
<section class="view active" id="view-financial">
    <!-- Header & Action Buttons -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;margin-bottom:22px;">
        <div class="hello">
            <h1 style="display:flex;align-items:center;gap:10px;margin:0;flex-wrap:wrap;">
                <span>Finances & Cash Flow</span>
                <span style="font-size:24px;">💵</span>
                <span class="status s-done" style="font-size:12px;font-weight:800;letter-spacing:0.3px;padding:3px 10px;">
                    🇰🇪 KES ⇄ 🇺🇸 USD (1 USD = <?= number_format($exRate, 2) ?> KES)
                </span>
            </h1>
            <p style="margin-top:4px;color:var(--text-2);font-size:14px;">
                Track received funds (Amount In), expenditures (Amount Out), and remaining balances separated by date.
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
            <?php if ($totalRecords > 0): ?>
            <form method="POST" action="<?= url('financial/clear-all') ?>" onsubmit="return confirm('Are you sure you want to permanently delete ALL financial transactions? This cannot be undone.');" style="display:inline;margin:0;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-ghost" title="Clear all transactions / reset ledger" style="display:inline-flex;align-items:center;gap:6px;border-color:rgba(239,68,68,.3);color:var(--red);font-weight:700;">
                    <span>🗑️ Clear All</span>
                </button>
            </form>
            <?php endif; ?>
            <button class="btn btn-brand" onclick="openNewFinancialModal()" style="display:inline-flex;align-items:center;gap:7px;box-shadow:0 4px 14px rgba(79,70,229,.35);font-weight:800;">
                <span style="font-size:16px;font-weight:900;">＋</span>
                <span>Record Cash Flow</span>
            </button>
        </div>
    </div>

    <!-- KPI Summary Cards with Proper Spacing & No Overlapping -->
    <div class="kpis" style="margin-bottom:24px;display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:16px;">
        <!-- Total In -->
        <div class="kpi" style="border:1.5px solid rgba(16,185,129,.35);background:linear-gradient(135deg,var(--card),rgba(16,185,129,.05));border-radius:14px;padding:18px 20px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:8px;">
                <div class="lbl" style="color:var(--text-2);font-weight:700;font-size:13px;margin:0;line-height:1.3;">
                    Total Money In (Received)
                </div>
                <span style="background:rgba(16,185,129,.15);color:var(--green);padding:3px 8px;border-radius:6px;font-size:11px;font-weight:800;white-space:nowrap;flex-shrink:0;">
                    ▲ CASH IN
                </span>
            </div>
            <div class="val" id="statTotalIn" style="color:var(--green);font-size:26px;font-weight:900;line-height:1.2;">
                $ <?= number_format($totalIn, 2) ?>
            </div>
            <div style="font-size:11.5px;color:var(--text-3);font-weight:600;margin-top:2px;">
                KES <?= number_format($totalIn * $exRate) ?> (dual evaluation)
            </div>
            <div style="font-size:12px;color:var(--text-3);margin-top:6px;line-height:1.3;">
                Freight revenues & client injections
            </div>
        </div>

        <!-- Total Out -->
        <div class="kpi" style="border:1.5px solid rgba(239,68,68,.35);background:linear-gradient(135deg,var(--card),rgba(239,68,68,.05));border-radius:14px;padding:18px 20px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:8px;">
                <div class="lbl" style="color:var(--text-2);font-weight:700;font-size:13px;margin:0;line-height:1.3;">
                    Total Money Out (Spent)
                </div>
                <span style="background:rgba(239,68,68,.15);color:var(--red);padding:3px 8px;border-radius:6px;font-size:11px;font-weight:800;white-space:nowrap;flex-shrink:0;">
                    ▼ CASH OUT
                </span>
            </div>
            <div class="val" id="statTotalOut" style="color:var(--red);font-size:26px;font-weight:900;line-height:1.2;">
                $ <?= number_format($totalOut, 2) ?>
            </div>
            <div style="font-size:11.5px;color:var(--text-3);font-weight:600;margin-top:2px;">
                KES <?= number_format($totalOut * $exRate) ?> (dual evaluation)
            </div>
            <div style="font-size:12px;color:var(--text-3);margin-top:6px;line-height:1.3;">
                Operational expenses, fuel & drawings
            </div>
        </div>

        <!-- Net Cash Balance -->
        <div class="kpi" style="border:1.5px solid <?= $netBalance >= 0 ? 'rgba(79,70,229,.4)' : 'rgba(239,68,68,.4)' ?>;background:linear-gradient(135deg,var(--card),<?= $netBalance >= 0 ? 'rgba(79,70,229,.06)' : 'rgba(239,68,68,.06)' ?>);border-radius:14px;padding:18px 20px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:8px;">
                <div class="lbl" style="color:var(--text-2);font-weight:700;font-size:13px;margin:0;line-height:1.3;">
                    Cumulative Net Balance
                </div>
                <span style="background:<?= $netBalance >= 0 ? 'rgba(79,70,229,.15)' : 'rgba(239,68,68,.15)' ?>;color:<?= $netBalance >= 0 ? 'var(--brand)' : 'var(--red)' ?>;padding:3px 8px;border-radius:6px;font-size:11px;font-weight:800;white-space:nowrap;flex-shrink:0;">
                    NET BALANCE
                </span>
            </div>
            <div class="val" id="statNetBalance" style="color:<?= $netBalance >= 0 ? 'var(--brand)' : 'var(--red)' ?>;font-size:26px;font-weight:900;line-height:1.2;">
                <?= ($netBalance >= 0 ? '$ ' : '-$ ') . number_format(abs($netBalance), 2) ?>
            </div>
            <div style="font-size:11.5px;color:var(--text-3);font-weight:600;margin-top:2px;">
                KES <?= number_format($netBalance * $exRate) ?> (dual evaluation)
            </div>
            <div style="font-size:12px;color:var(--text-3);margin-top:6px;line-height:1.3;">
                Net cash retained after all expenditures
            </div>
        </div>

        <!-- Today's Activity -->
        <div class="kpi" style="border:1.5px solid var(--border-2);background:var(--card);border-radius:14px;padding:18px 20px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:8px;">
                <div class="lbl" style="color:var(--text-2);font-weight:700;font-size:13px;margin:0;line-height:1.3;">
                    Today's Activity (<?= date('d M') ?>)
                </div>
                <span style="background:var(--card-2);color:var(--text-2);border:1px solid var(--border-2);padding:3px 8px;border-radius:6px;font-size:11px;font-weight:700;white-space:nowrap;flex-shrink:0;">
                    TODAY
                </span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;">
                <div>
                    <div style="font-size:12px;color:var(--text-3);font-weight:600;">In: <b style="color:var(--green);" id="statTodayIn">+$ <?= number_format($todayIn, 2) ?></b></div>
                    <div style="font-size:12px;color:var(--text-3);font-weight:600;margin-top:2px;">Out: <b style="color:var(--red);" id="statTodayOut">-$ <?= number_format($todayOut, 2) ?></b></div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:11px;color:var(--text-3);font-weight:700;">Day Net:</div>
                    <div id="statTodayNet" style="font-size:16px;font-weight:900;color:<?= $todayNet >= 0 ? 'var(--green)' : 'var(--red)' ?>;">
                        <?= ($todayNet >= 0 ? '+$ ' : '-$ ') . number_format(abs($todayNet), 2) ?>
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
                <input type="text" name="search" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" placeholder="Search how funds were used, category, method…" style="width:100%;padding:8px 12px 8px 34px;border:1.5px solid var(--border-2);border-radius:9px;background:var(--card);color:var(--text);font-size:13.5px;">
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
        <div class="panel-head" style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:10px;">
            <div>
                <h3 style="margin:0;font-size:16px;font-weight:800;display:flex;align-items:center;gap:8px;">
                    <span>Daily Transactions & Cash Flow Ledger</span>
                    <span style="font-size:11.5px;font-weight:700;color:var(--brand);background:var(--brand-soft);padding:2px 8px;border-radius:6px;">
                        Dual Evaluation: USD ($) & KES (KSh)
                    </span>
                </h3>
                <small style="color:var(--text-3);font-size:12.5px;">Click "Edit" on any row to edit columns directly in the table. Changes auto-save.</small>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <span class="status s-done" style="font-size:12px;">
                    <i></i> <?= $totalRecords ?> Total Transactions
                </span>
            </div>
        </div>

        <div class="table-responsive">
            <table id="financialTable" style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="width:120px;">Date</th>
                        <th style="width:150px;text-align:right;">Amount In (Received)</th>
                        <th style="width:150px;text-align:right;">Amount Out (Spent)</th>
                        <th style="width:155px;text-align:right;">Balance</th>
                        <th>How It Was Used / Expenditure Reason</th>
                        <th style="width:140px;">Category</th>
                        <th style="width:120px;">Method</th>
                        <th style="width:110px;text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="financialTableBody">
                    <?php if (empty($records)): ?>
                        <tr id="emptyFinRow">
                            <td colspan="8" style="text-align:center;padding:48px 20px;color:var(--text-3);">
                                <div style="font-size:36px;margin-bottom:8px;">💳</div>
                                <div style="font-weight:700;font-size:15px;color:var(--text-2);">No financial records found</div>
                                <div style="font-size:13px;margin-top:4px;">Click "Record Cash Flow" above to log received funds or expenditures.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        // Group records by entry_date to separate transactions day-by-day
                        $recordsByDate = [];
                        foreach ($records as $rec) {
                            $recordsByDate[$rec['entry_date']][] = $rec;
                        }
                        ?>
                        <?php foreach ($recordsByDate as $dateKey => $dateRecords): 
                            $dayIn = 0.0;
                            $dayOut = 0.0;
                            foreach ($dateRecords as $dr) {
                                $dayIn += (float)$dr['amount_in'];
                                $dayOut += (float)$dr['amount_out'];
                            }
                            $dayNet = $dayIn - $dayOut;
                            $dayTs = strtotime($dateKey);
                            $dayLabel = $dayTs ? date('l, d M Y', $dayTs) : $dateKey;
                        ?>
                            <!-- Date-based Group Separator -->
                            <tr class="date-group-divider" style="background:linear-gradient(90deg, var(--card-2), var(--card));border-top:2px solid var(--border-2);border-bottom:1px solid var(--border);">
                                <td colspan="8" style="padding:10px 16px;">
                                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <span style="font-size:14px;">📅</span>
                                            <b style="font-size:13.5px;color:var(--text);"><?= htmlspecialchars($dayLabel) ?></b>
                                            <span class="status s-plan" style="font-size:11px;padding:2px 7px;"><?= count($dateRecords) ?> <?= count($dateRecords) === 1 ? 'entry' : 'entries' ?></span>
                                        </div>
                                        <div style="display:flex;align-items:center;gap:14px;font-size:12px;font-weight:700;flex-wrap:wrap;">
                                            <?php if ($dayIn > 0): ?>
                                                <span style="color:var(--green);">Received: +$<?= number_format($dayIn, 2) ?> <small style="opacity:0.8;">(KES <?= number_format($dayIn * $exRate) ?>)</small></span>
                                            <?php endif; ?>
                                            <?php if ($dayOut > 0): ?>
                                                <span style="color:var(--red);">Spent: -$<?= number_format($dayOut, 2) ?> <small style="opacity:0.8;">(KES <?= number_format($dayOut * $exRate) ?>)</small></span>
                                            <?php endif; ?>
                                            <span style="color:<?= $dayNet >= 0 ? 'var(--brand)' : 'var(--red)' ?>;background:<?= $dayNet >= 0 ? 'var(--brand-soft)' : 'var(--red-soft)' ?>;padding:2px 8px;border-radius:6px;">
                                                Day Net: <?= ($dayNet >= 0 ? '+$' : '-$') . number_format(abs($dayNet), 2) ?> <small style="opacity:0.85;">(KES <?= number_format($dayNet * $exRate) ?>)</small>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <?php foreach ($dateRecords as $r): 
                                $id = (int)$r['id'];
                                $in = (float)$r['amount_in'];
                                $out = (float)$r['amount_out'];
                                $runBal = (float)$r['running_balance'];
                                $rowRate = !empty($r['exchange_rate']) ? (float)$r['exchange_rate'] : (float)$exRate;
                                if ($rowRate <= 0) $rowRate = 128.0;
                            ?>
                                <tr id="finRow-<?= $id ?>" data-id="<?= $id ?>" data-exchange-rate="<?= $rowRate ?>" class="fin-data-row">
                                    <!-- 1. Date -->
                                    <td class="cell-date" data-field="entry_date" data-val="<?= htmlspecialchars($r['entry_date']) ?>">
                                        <span class="view-val" style="font-weight:700;color:var(--text);font-size:13px;"><?= htmlspecialchars($r['entry_date']) ?></span>
                                    </td>

                                    <!-- 2. Amount In (Received) -->
                                    <td class="cell-in" data-field="amount_in" data-val="<?= $in ?>" style="text-align:right;">
                                        <div class="view-val">
                                            <?php if ($in > 0): ?>
                                                <div style="font-weight:800;color:var(--green);font-size:13.5px;">+$ <?= number_format($in, 2) ?></div>
                                                <div style="font-size:11px;color:var(--text-3);font-weight:600;">KES <?= number_format($in * $rowRate) ?></div>
                                            <?php else: ?>
                                                <span style="color:var(--text-3);font-weight:700;">—</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- 3. Amount Out (Used) -->
                                    <td class="cell-out" data-field="amount_out" data-val="<?= $out ?>" style="text-align:right;">
                                        <div class="view-val">
                                            <?php if ($out > 0): ?>
                                                <div style="font-weight:800;color:var(--red);font-size:13.5px;">-$ <?= number_format($out, 2) ?></div>
                                                <div style="font-size:11px;color:var(--text-3);font-weight:600;">KES <?= number_format($out * $rowRate) ?></div>
                                            <?php else: ?>
                                                <span style="color:var(--text-3);font-weight:700;">—</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- 4. Remaining Balance -->
                                    <td class="cell-runbal" data-field="balance" data-val="<?= $runBal ?>" style="text-align:right;">
                                        <div class="view-val">
                                            <div style="font-weight:900;color:<?= $runBal >= 0 ? 'var(--brand)' : 'var(--red)' ?>;font-size:13.5px;">
                                                <?= ($runBal >= 0 ? '+$ ' : '-$ ') . number_format(abs($runBal), 2) ?>
                                            </div>
                                            <div style="font-size:11px;color:var(--text-3);font-weight:600;">
                                                KES <?= number_format($runBal * $rowRate) ?>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 5. How It Was Used / Reason -->
                                    <td class="cell-reason" data-field="reason" data-val="<?= htmlspecialchars($r['reason'] ?? '') ?>">
                                        <span class="view-val" style="font-size:13.5px;color:var(--text);line-height:1.4;font-weight:600;">
                                            <?= htmlspecialchars($r['reason'] ?? '—') ?>
                                        </span>
                                    </td>

                                    <!-- 6. Category -->
                                    <td class="cell-category" data-field="category" data-val="<?= htmlspecialchars($r['category'] ?? 'General') ?>">
                                        <span class="view-val">
                                            <?php
                                            $catBadge = match ($r['category'] ?? 'General') {
                                                'Client Inflow' => 's-done',
                                                'Personal Drawing' => 's-hold',
                                                'Fuel & Fleet' => 's-prog',
                                                'Driver Allowances' => 's-prog',
                                                'Maintenance & Repairs' => 's-warn',
                                                'Office Operations' => 's-plan',
                                                default => 's-plan'
                                            };
                                            ?>
                                            <span class="status <?= $catBadge ?>" style="font-size:11.5px;padding:3px 8px;">
                                                <?= htmlspecialchars($r['category'] ?? 'General') ?>
                                            </span>
                                        </span>
                                    </td>

                                    <!-- 7. Payment Method -->
                                    <td class="cell-method" data-field="payment_method" data-val="<?= htmlspecialchars($r['payment_method'] ?? 'Cash') ?>">
                                        <span class="view-val" style="font-size:12.5px;font-weight:700;color:var(--text-2);">
                                            <?= htmlspecialchars($r['payment_method'] ?? 'Cash') ?>
                                        </span>
                                    </td>

                                    <!-- 8. Actions (Inline Edit & Delete) -->
                                    <td class="cell-actions" style="text-align:center;">
                                        <div class="view-actions" style="display:flex;gap:6px;justify-content:center;align-items:center;">
                                            <button type="button" class="btn btn-ghost btn-sm" onclick="startFinInlineEdit(<?= $id ?>)" title="Inline Edit Columns" style="padding:4px 8px;font-size:12px;font-weight:700;border-color:var(--border-2);color:var(--brand);">
                                                ✏️ Edit
                                            </button>
                                            <form method="POST" action="<?= url('financial/delete/' . $id) ?>" onsubmit="return confirm('Are you sure you want to delete this financial record?');" style="display:inline;margin:0;">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-ghost btn-sm" title="Delete" style="padding:4px 6px;font-size:12px;color:var(--red);border-color:rgba(239,68,68,.3);">
                                                    🗑️
                                                </button>
                                            </form>
                                        </div>
                                        <div class="edit-actions" style="display:none;gap:6px;justify-content:center;align-items:center;">
                                            <button type="button" class="btn btn-brand btn-sm row-save-btn" onclick="saveFinInlineEdit(<?= $id ?>)" style="padding:4px 10px;font-size:12px;font-weight:800;">
                                                💾 Save
                                            </button>
                                            <button type="button" class="btn btn-ghost btn-sm" onclick="cancelFinInlineEdit(<?= $id ?>)" style="padding:4px 8px;font-size:12px;">
                                                ✕
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Bar -->
        <?php if ($totalRecords > 0): ?>
            <?php
            $paginationQuery = $filterParams;
            $buildPageUrl = function($pageNum) use ($paginationQuery, $perPage) {
                $q = array_merge($paginationQuery, ['page' => $pageNum, 'per_page' => $perPage]);
                return url('financial') . '?' . http_build_query($q);
            };
            ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-top:1px solid var(--border);flex-wrap:wrap;gap:12px;background:var(--card-2);">
                <div style="font-size:13px;color:var(--text-2);display:flex;align-items:center;gap:12px;">
                    <span>Showing <b><?= $startRecord ?></b> to <b><?= $endRecord ?></b> of <b><?= $totalRecords ?></b> transactions</span>
                    
                    <span style="color:var(--border-2);">|</span>
                    
                    <label style="display:flex;align-items:center;gap:6px;font-size:12.5px;color:var(--text-3);">
                        <span>Per page:</span>
                        <select onchange="window.location.href = this.value" style="padding:3px 8px;border:1px solid var(--border-2);border-radius:6px;background:var(--card);color:var(--text);font-size:12px;font-weight:700;">
                            <?php foreach ([10, 20, 50, 100] as $size): ?>
                                <option value="<?= url('financial') . '?' . http_build_query(array_merge($filterParams, ['page' => 1, 'per_page' => $size])) ?>" <?= $perPage === $size ? 'selected' : '' ?>>
                                    <?= $size ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="<?= url('financial') . '?' . http_build_query(array_merge($filterParams, ['page' => 1, 'per_page' => 'all'])) ?>" <?= $perPage >= 5000 ? 'selected' : '' ?>>
                                All
                            </option>
                        </select>
                    </label>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div style="display:flex;align-items:center;gap:4px;">
                        <!-- First & Prev -->
                        <?php if ($currentPage > 1): ?>
                            <a href="<?= $buildPageUrl(1) ?>" class="btn btn-ghost btn-sm" style="padding:4px 8px;font-size:12px;font-weight:700;" title="First Page">«</a>
                            <a href="<?= $buildPageUrl($currentPage - 1) ?>" class="btn btn-ghost btn-sm" style="padding:4px 10px;font-size:12px;font-weight:700;" title="Previous Page">‹ Prev</a>
                        <?php else: ?>
                            <span class="btn btn-ghost btn-sm" style="padding:4px 8px;font-size:12px;opacity:0.4;cursor:not-allowed;">«</span>
                            <span class="btn btn-ghost btn-sm" style="padding:4px 10px;font-size:12px;opacity:0.4;cursor:not-allowed;">‹ Prev</span>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <?php
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);
                        if ($startPage > 1) {
                            echo '<span style="padding:0 4px;color:var(--text-3);">…</span>';
                        }
                        for ($p = $startPage; $p <= $endPage; $p++):
                        ?>
                            <?php if ($p === $currentPage): ?>
                                <span class="btn btn-brand btn-sm" style="padding:4px 10px;font-size:12px;font-weight:800;"><?= $p ?></span>
                            <?php else: ?>
                                <a href="<?= $buildPageUrl($p) ?>" class="btn btn-ghost btn-sm" style="padding:4px 10px;font-size:12px;font-weight:700;"><?= $p ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($endPage < $totalPages): ?>
                            <span style="padding:0 4px;color:var(--text-3);">…</span>
                        <?php endif; ?>

                        <!-- Next & Last -->
                        <?php if ($currentPage < $totalPages): ?>
                            <a href="<?= $buildPageUrl($currentPage + 1) ?>" class="btn btn-ghost btn-sm" style="padding:4px 10px;font-size:12px;font-weight:700;" title="Next Page">Next ›</a>
                            <a href="<?= $buildPageUrl($totalPages) ?>" class="btn btn-ghost btn-sm" style="padding:4px 8px;font-size:12px;font-weight:700;" title="Last Page">»</a>
                        <?php else: ?>
                            <span class="btn btn-ghost btn-sm" style="padding:4px 10px;font-size:12px;opacity:0.4;cursor:not-allowed;">Next ›</span>
                            <span class="btn btn-ghost btn-sm" style="padding:4px 8px;font-size:12px;opacity:0.4;cursor:not-allowed;">»</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- RECORD FINANCIAL TRANSACTION MODAL (Standard App Floating Overlay) -->
<div class="modal-backdrop" id="modalNewFinancial" onclick="handleFinBackdropClick(event)">
    <div class="modal-card" style="max-width:640px;width:100%;margin:auto;">
        <div class="modal-head" style="display:flex;justify-content:space-between;align-items:center;">
            <h2 style="margin:0;font-size:17px;font-weight:800;display:flex;align-items:center;gap:8px;">
                <span>💵 Record Financial Cash Flow</span>
                <span class="status s-done" style="font-size:11px;font-weight:800;">
                    1 USD = <?= number_format($exRate, 2) ?> KES
                </span>
            </h2>
            <button type="button" class="close-modal" onclick="closeNewFinancialModal()" style="border:none;background:none;font-size:20px;cursor:pointer;color:var(--text-2);">✕</button>
        </div>

        <form method="POST" action="<?= url('financial/store') ?>" id="formNewFinancial" onsubmit="handleNewFinancialSubmit(event)">
            <?= csrf_field() ?>
            <div style="padding:20px;display:flex;flex-direction:column;gap:16px;">
                <!-- Date & Category -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group" style="margin:0;">
                        <label for="finEntryDate" style="font-weight:700;font-size:13px;">Transaction Date *</label>
                        <input type="date" id="finEntryDate" name="entry_date" value="<?= date('Y-m-d') ?>" required onchange="fetchDaySummary(this.value)" style="width:100%;padding:9px 12px;border:1.5px solid var(--border-2);border-radius:10px;background:var(--card);color:var(--text);font-size:13.5px;font-weight:600;">
                    </div>

                    <div class="form-group" style="margin:0;">
                        <label for="finCategory" style="font-weight:700;font-size:13px;">Transaction Category *</label>
                        <select id="finCategory" name="category" required style="width:100%;padding:9px 12px;border:1.5px solid var(--border-2);border-radius:10px;background:var(--card);color:var(--text);font-size:13.5px;font-weight:600;">
                            <option value="Client Inflow">Client Inflow / Freight Revenue</option>
                            <option value="Personal Drawing">Personal Drawing / Owner Draw</option>
                            <option value="Fuel & Fleet">Fuel & Bulk Diesel</option>
                            <option value="Office Operations">Office Operations & Utilities</option>
                            <option value="Driver Allowances">Driver Transit Allowances</option>
                            <option value="Maintenance & Repairs">Maintenance & Repairs</option>
                            <option value="Staff Wages">Staff / Casual Wages</option>
                            <option value="Banking & Taxes">Banking & Taxes</option>
                            <option value="General">General / Miscellaneous</option>
                        </select>
                    </div>
                </div>

                <!-- Day Status & Existing Funds Widget (Dual Currency) -->
                <div id="daySummaryWidget" style="background:linear-gradient(135deg, var(--card-2), var(--card));border:1.5px solid var(--border-2);border-radius:12px;padding:12px 14px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:6px;">
                        <span style="font-size:12.5px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:6px;">
                            <span>📅</span>
                            <span id="daySummaryDateLabel">Selected Date Status</span>
                        </span>
                        <span id="daySummaryBadge" class="status s-plan" style="font-size:11px;padding:2px 7px;">Loading…</span>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;text-align:center;">
                        <!-- Day In -->
                        <div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.25);border-radius:8px;padding:8px 6px;">
                            <div style="font-size:10.5px;color:var(--text-3);font-weight:700;">Day Received In:</div>
                            <div id="daySummaryInUsd" style="font-size:14px;font-weight:900;color:var(--green);margin-top:2px;">+$ 0.00</div>
                            <div id="daySummaryInKes" style="font-size:10.5px;color:var(--text-3);font-weight:600;">KES 0</div>
                        </div>
                        <!-- Day Out -->
                        <div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);border-radius:8px;padding:8px 6px;">
                            <div style="font-size:10.5px;color:var(--text-3);font-weight:700;">Day Spent Out:</div>
                            <div id="daySummaryOutUsd" style="font-size:14px;font-weight:900;color:var(--red);margin-top:2px;">-$ 0.00</div>
                            <div id="daySummaryOutKes" style="font-size:10.5px;color:var(--text-3);font-weight:600;">KES 0</div>
                        </div>
                        <!-- Day Available Balance -->
                        <div style="background:rgba(79,70,229,0.08);border:1px solid rgba(79,70,229,0.25);border-radius:8px;padding:8px 6px;">
                            <div style="font-size:10.5px;color:var(--text-3);font-weight:700;">Available Day Balance:</div>
                            <div id="daySummaryBalUsd" style="font-size:14px;font-weight:900;color:var(--brand);margin-top:2px;">$ 0.00</div>
                            <div id="daySummaryBalKes" style="font-size:10.5px;color:var(--text-3);font-weight:600;">KES 0</div>
                        </div>
                    </div>
                </div>

                <!-- DUAL CURRENCY: Amount In (Received) in both USD & KES -->
                <div style="background:var(--card-2);border:1.5px solid rgba(16,185,129,.35);padding:12px 14px;border-radius:12px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <span style="color:var(--green);font-weight:800;font-size:13px;display:flex;align-items:center;gap:6px;">
                            <span>▲ Amount In (Received Funds)</span>
                            <span style="font-size:11px;background:rgba(16,185,129,0.15);padding:1px 6px;border-radius:4px;">INFLOW</span>
                        </span>
                        <small style="color:var(--text-3);font-size:11px;">Enter in either USD or KES</small>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <!-- In KES -->
                        <div class="form-group" style="margin:0;">
                            <label style="color:var(--amber);font-size:11.5px;font-weight:800;">🇰🇪 Kenya Shillings (KES / KSh)</label>
                            <div style="position:relative;margin-top:2px;">
                                <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-weight:800;color:var(--amber);font-size:12px;">KES</span>
                                <input type="number" step="1" min="0" id="finAmountInKes" name="amount_in_kes" placeholder="0" oninput="onFinInKesInput(this.value)" style="width:100%;padding:8px 10px 8px 42px;border:1.5px solid var(--amber);border-radius:8px;font-weight:800;font-size:14px;color:var(--amber);background:var(--card);">
                            </div>
                        </div>
                        <!-- In USD -->
                        <div class="form-group" style="margin:0;">
                            <label style="color:var(--green);font-size:11.5px;font-weight:800;">🇺🇸 US Dollars (USD / $)</label>
                            <div style="position:relative;margin-top:2px;">
                                <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-weight:800;color:var(--green);font-size:14px;">$</span>
                                <input type="number" step="0.01" min="0" id="finAmountInUsd" name="amount_in_usd" placeholder="0.00" oninput="onFinInUsdInput(this.value)" style="width:100%;padding:8px 10px 8px 28px;border:1.5px solid var(--green);border-radius:8px;font-weight:800;font-size:14px;color:var(--green);background:var(--card);">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DUAL CURRENCY: Amount Out (Spent) in both USD & KES -->
                <div style="background:var(--card-2);border:1.5px solid rgba(239,68,68,.35);padding:12px 14px;border-radius:12px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <span style="color:var(--red);font-weight:800;font-size:13px;display:flex;align-items:center;gap:6px;">
                            <span>▼ Amount Out (Spent Funds / Cost)</span>
                            <span style="font-size:11px;background:rgba(239,68,68,0.15);padding:1px 6px;border-radius:4px;">OUTFLOW</span>
                        </span>
                        <small style="color:var(--text-3);font-size:11px;">Enter in either USD or KES</small>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <!-- Out KES -->
                        <div class="form-group" style="margin:0;">
                            <label style="color:var(--amber);font-size:11.5px;font-weight:800;">🇰🇪 Kenya Shillings (KES / KSh)</label>
                            <div style="position:relative;margin-top:2px;">
                                <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-weight:800;color:var(--amber);font-size:12px;">KES</span>
                                <input type="number" step="1" min="0" id="finAmountOutKes" name="amount_out_kes" placeholder="0" oninput="onFinOutKesInput(this.value)" style="width:100%;padding:8px 10px 8px 42px;border:1.5px solid var(--amber);border-radius:8px;font-weight:800;font-size:14px;color:var(--amber);background:var(--card);">
                            </div>
                        </div>
                        <!-- Out USD -->
                        <div class="form-group" style="margin:0;">
                            <label style="color:var(--red);font-size:11.5px;font-weight:800;">🇺🇸 US Dollars (USD / $)</label>
                            <div style="position:relative;margin-top:2px;">
                                <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-weight:800;color:var(--red);font-size:14px;">$</span>
                                <input type="number" step="0.01" min="0" id="finAmountOutUsd" name="amount_out_usd" placeholder="0.00" oninput="onFinOutUsdInput(this.value)" style="width:100%;padding:8px 10px 8px 28px;border:1.5px solid var(--red);border-radius:8px;font-weight:800;font-size:14px;color:var(--red);background:var(--card);">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Live Net Impact & Deduction Breakdown Card -->
                <div style="background:var(--card);padding:12px 14px;border-radius:10px;border:1px dashed var(--border-2);">
                    <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px;flex-wrap:wrap;gap:6px;">
                        <span style="font-weight:700;color:var(--text-2);">This Entry Net Impact:</span>
                        <span id="finModalNetPreview" style="font-size:15px;font-weight:900;color:var(--text-3);">$ 0.00 (KES 0)</span>
                    </div>
                    <div id="finModalDeductionNote" style="font-size:12px;color:var(--text-2);margin-top:6px;font-weight:600;display:none;line-height:1.4;"></div>
                </div>

                <!-- How It Was Used / Expenditure Reason -->
                <div class="form-group" style="margin:0;">
                    <label for="finReason" style="font-weight:700;font-size:13px;">How Was It Used? / Expenditure Reason & Details *</label>
                    <textarea id="finReason" name="reason" rows="2" placeholder="e.g. Bulk diesel refill, Tanker brake repair, Kampala freight haulage payout, Director withdrawal, Yard utilities..." required style="width:100%;padding:10px 12px;border:1.5px solid var(--border-2);border-radius:10px;background:var(--card);color:var(--text);font-family:inherit;font-size:13.5px;resize:vertical;"></textarea>
                    <small style="color:var(--text-3);font-size:11.5px;">Explain clearly how the funds were used or the nature of received inflow.</small>
                </div>

                <!-- Payment Method (Receipt concept removed) -->
                <div class="form-group" style="margin:0;">
                    <label for="finPaymentMethod" style="font-weight:700;font-size:13px;">Payment Method</label>
                    <select id="finPaymentMethod" name="payment_method" style="width:100%;padding:9px 12px;border:1.5px solid var(--border-2);border-radius:10px;background:var(--card);color:var(--text);font-size:13.5px;font-weight:600;">
                        <option value="Cash">Cash</option>
                        <option value="M-Pesa">M-Pesa</option>
                        <option value="Bank Transfer">Bank Transfer (EFT/RTGS)</option>
                        <option value="Card">Card</option>
                        <option value="Cheque">Cheque</option>
                    </select>
                </div>

                <!-- Buttons -->
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:6px;">
                    <button type="button" class="btn btn-ghost" onclick="closeNewFinancialModal()">Cancel</button>
                    <button type="submit" class="btn btn-brand" id="btnSaveFinancial" style="font-weight:800;padding:10px 20px;">
                        <span>Save Transaction</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?= csrf_token() ?>';
const EX_RATE = <?= (float)$exRate ?>;

let currentDaySummary = {
    day_in: 0,
    day_in_kes: 0,
    day_out: 0,
    day_out_kes: 0,
    day_balance: 0,
    day_balance_kes: 0,
    count: 0
};

function openNewFinancialModal() {
    const m = document.getElementById('modalNewFinancial');
    if (m) {
        m.style.display = 'grid';
        m.classList.add('active');
        document.getElementById('finAmountInKes').value = '';
        document.getElementById('finAmountInUsd').value = '';
        document.getElementById('finAmountOutKes').value = '';
        document.getElementById('finAmountOutUsd').value = '';
        document.getElementById('finReason').value = '';
        const curDate = document.getElementById('finEntryDate')?.value || '<?= date("Y-m-d") ?>';
        fetchDaySummary(curDate);
        calcModalNetBalance();
    }
}

function closeNewFinancialModal() {
    const m = document.getElementById('modalNewFinancial');
    if (m) {
        m.style.display = 'none';
        m.classList.remove('active');
    }
}

function handleFinBackdropClick(e) {
    if (e.target && e.target.id === 'modalNewFinancial') {
        closeNewFinancialModal();
    }
}

/* Two-way Dual Currency Handlers for Inflow */
function onFinInKesInput(kesVal) {
    const num = parseFloat(kesVal);
    const usdInput = document.getElementById('finAmountInUsd');
    if (!isNaN(num) && num > 0) {
        usdInput.value = (num / EX_RATE).toFixed(2);
    } else {
        usdInput.value = '';
    }
    calcModalNetBalance();
}

function onFinInUsdInput(usdVal) {
    const num = parseFloat(usdVal);
    const kesInput = document.getElementById('finAmountInKes');
    if (!isNaN(num) && num > 0) {
        kesInput.value = Math.round(num * EX_RATE);
    } else {
        kesInput.value = '';
    }
    calcModalNetBalance();
}

/* Two-way Dual Currency Handlers for Outflow */
function onFinOutKesInput(kesVal) {
    const num = parseFloat(kesVal);
    const usdInput = document.getElementById('finAmountOutUsd');
    if (!isNaN(num) && num > 0) {
        usdInput.value = (num / EX_RATE).toFixed(2);
    } else {
        usdInput.value = '';
    }
    calcModalNetBalance();
}

function onFinOutUsdInput(usdVal) {
    const num = parseFloat(usdVal);
    const kesInput = document.getElementById('finAmountOutKes');
    if (!isNaN(num) && num > 0) {
        kesInput.value = Math.round(num * EX_RATE);
    } else {
        kesInput.value = '';
    }
    calcModalNetBalance();
}

async function fetchDaySummary(date) {
    if (!date) return;
    const badge = document.getElementById('daySummaryBadge');
    const label = document.getElementById('daySummaryDateLabel');
    if (badge) badge.textContent = 'Updating…';

    try {
        const res = await fetch('<?= url("financial/day-summary") ?>?date=' + encodeURIComponent(date), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        const data = await res.json();
        if (data.success) {
            currentDaySummary = data;
            if (label) label.textContent = (data.formatted_date || date) + ' Status';
            if (badge) {
                badge.textContent = data.count + (data.count === 1 ? ' entry' : ' entries');
                badge.className = data.count > 0 ? 'status s-done' : 'status s-plan';
            }
            
            const inUsd = Number(data.day_in || 0);
            const inKes = Number(data.day_in_kes || (inUsd * EX_RATE));
            const outUsd = Number(data.day_out || 0);
            const outKes = Number(data.day_out_kes || (outUsd * EX_RATE));
            const balUsd = Number(data.day_balance || (inUsd - outUsd));
            const balKes = Number(data.day_balance_kes || (balUsd * EX_RATE));

            document.getElementById('daySummaryInUsd').textContent = '+$ ' + inUsd.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('daySummaryInKes').textContent = 'KES ' + Math.round(inKes).toLocaleString('en-US');

            document.getElementById('daySummaryOutUsd').textContent = '-$ ' + outUsd.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('daySummaryOutKes').textContent = 'KES ' + Math.round(outKes).toLocaleString('en-US');

            const balUsdEl = document.getElementById('daySummaryBalUsd');
            balUsdEl.textContent = (balUsd >= 0 ? '+$ ' : '-$ ') + Math.abs(balUsd).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            balUsdEl.style.color = balUsd >= 0 ? 'var(--brand)' : 'var(--red)';
            document.getElementById('daySummaryBalKes').textContent = 'KES ' + Math.round(balKes).toLocaleString('en-US');

            calcModalNetBalance();
        }
    } catch (e) {
        if (badge) badge.textContent = 'Ready';
    }
}

function calcModalNetBalance() {
    const inUsd = parseFloat(document.getElementById('finAmountInUsd')?.value || 0);
    const outUsd = parseFloat(document.getElementById('finAmountOutUsd')?.value || 0);
    const netUsd = inUsd - outUsd;
    const netKes = Math.round(netUsd * EX_RATE);

    const el = document.getElementById('finModalNetPreview');
    const noteEl = document.getElementById('finModalDeductionNote');
    if (!el) return;

    if (inUsd === 0 && outUsd === 0) {
        el.textContent = '$ 0.00 (KES 0)';
        el.style.color = 'var(--text-3)';
        if (noteEl) noteEl.style.display = 'none';
    } else if (netUsd >= 0) {
        el.textContent = '+$ ' + netUsd.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' (KES ' + netKes.toLocaleString('en-US') + ')';
        el.style.color = 'var(--green)';
    } else {
        el.textContent = '-$ ' + Math.abs(netUsd).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' (KES ' + netKes.toLocaleString('en-US') + ')';
        el.style.color = 'var(--red)';
    }

    if (noteEl) {
        const curDayBal = Number(currentDaySummary.day_balance || 0);
        const curDayIn = Number(currentDaySummary.day_in || 0);

        if (outUsd > 0 && inUsd === 0) {
            const newBalUsd = curDayBal - outUsd;
            const newBalKes = Math.round(newBalUsd * EX_RATE);
            noteEl.style.display = 'block';
            noteEl.innerHTML = `🔻 <b>Deduction:</b> Spending <b>$${outUsd.toLocaleString('en-US', {minimumFractionDigits: 2})} (KES ${Math.round(outUsd * EX_RATE).toLocaleString()})</b> from day's available balance ($${curDayBal.toLocaleString('en-US', {minimumFractionDigits: 2})}) → New Day Balance will be <b style="color:${newBalUsd >= 0 ? 'var(--brand)' : 'var(--red)'};">${newBalUsd >= 0 ? '+$' : '-$'}${Math.abs(newBalUsd).toLocaleString('en-US', {minimumFractionDigits: 2})} (KES ${newBalKes.toLocaleString()})</b>`;
        } else if (inUsd > 0 && outUsd === 0) {
            const newInUsd = curDayIn + inUsd;
            const newInKes = Math.round(newInUsd * EX_RATE);
            noteEl.style.display = 'block';
            noteEl.innerHTML = `✨ <b>New Inflow:</b> Adding <b>+$${inUsd.toLocaleString('en-US', {minimumFractionDigits: 2})} (KES ${Math.round(inUsd * EX_RATE).toLocaleString()})</b> to day's total received ($${curDayIn.toLocaleString('en-US', {minimumFractionDigits: 2})}) → New Day Total Inflow: <b>+$${newInUsd.toLocaleString('en-US', {minimumFractionDigits: 2})} (KES ${newInKes.toLocaleString()})</b>`;
        } else if (inUsd > 0 && outUsd > 0) {
            const newBalUsd = curDayBal + netUsd;
            const newBalKes = Math.round(newBalUsd * EX_RATE);
            noteEl.style.display = 'block';
            noteEl.innerHTML = `⚖️ <b>Net Day Impact:</b> <b>${netUsd >= 0 ? '+$' : '-$'}${Math.abs(netUsd).toLocaleString('en-US', {minimumFractionDigits: 2})} (KES ${netKes.toLocaleString()})</b> → New Day Balance: <b>${newBalUsd >= 0 ? '+$' : '-$'}${Math.abs(newBalUsd).toLocaleString('en-US', {minimumFractionDigits: 2})} (KES ${newBalKes.toLocaleString()})</b>`;
        } else {
            noteEl.style.display = 'none';
        }
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
                'X-CSRF-TOKEN': CSRF_TOKEN,
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
        form.submit();
    }
}

/* ==========================================================================
   INLINE EDITING FOR FINANCIAL TABLE COLUMNS
   ========================================================================== */
function startFinInlineEdit(id) {
    const row = document.getElementById('finRow-' + id);
    if (!row || row.classList.contains('tr-editing')) return;

    row.classList.add('tr-editing');

    const dateVal = row.querySelector('.cell-date').getAttribute('data-val') || '';
    const inVal = row.querySelector('.cell-in').getAttribute('data-val') || '0';
    const outVal = row.querySelector('.cell-out').getAttribute('data-val') || '0';
    const reasonVal = row.querySelector('.cell-reason').getAttribute('data-val') || '';
    const catVal = row.querySelector('.cell-category').getAttribute('data-val') || 'General';
    const methodVal = row.querySelector('.cell-method').getAttribute('data-val') || 'Cash';

    // 1. Date
    row.querySelector('.cell-date').innerHTML = `
        <input type="date" class="table-inline-input inline-date" value="${escapeHtml(dateVal)}" style="padding:4px 6px;font-size:12.5px;">
    `;

    // 2. Amount In
    row.querySelector('.cell-in').innerHTML = `
        <input type="number" step="0.01" min="0" class="table-inline-input inline-in" value="${inVal}" oninput="calcRowLiveNet(${id})" style="padding:4px 6px;font-size:13px;width:115px;text-align:right;color:var(--green);font-weight:700;">
    `;

    // 3. Amount Out
    row.querySelector('.cell-out').innerHTML = `
        <input type="number" step="0.01" min="0" class="table-inline-input inline-out" value="${outVal}" oninput="calcRowLiveNet(${id})" style="padding:4px 6px;font-size:13px;width:115px;text-align:right;color:var(--red);font-weight:700;">
    `;

    // 4. Remaining Balance (calculated live)

    // 5. How It Was Used / Reason
    row.querySelector('.cell-reason').innerHTML = `
        <input type="text" class="table-inline-input inline-reason" value="${escapeHtml(reasonVal)}" style="padding:4px 8px;font-size:13px;width:100%;font-weight:600;">
    `;

    // 6. Category
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

    // 7. Payment Method
    row.querySelector('.cell-method').innerHTML = `
        <select class="table-inline-input inline-method" style="padding:4px 6px;font-size:12px;">
            <option value="Cash" ${methodVal === 'Cash' ? 'selected' : ''}>Cash</option>
            <option value="M-Pesa" ${methodVal === 'M-Pesa' ? 'selected' : ''}>M-Pesa</option>
            <option value="Bank Transfer" ${methodVal === 'Bank Transfer' ? 'selected' : ''}>Bank Transfer</option>
            <option value="Card" ${methodVal === 'Card' ? 'selected' : ''}>Card</option>
            <option value="Cheque" ${methodVal === 'Cheque' ? 'selected' : ''}>Cheque</option>
        </select>
    `;

    // Toggle actions
    row.querySelector('.view-actions').style.display = 'none';
    row.querySelector('.edit-actions').style.display = 'flex';
}

function calcRowLiveNet(id) {
    const row = document.getElementById('finRow-' + id);
    if (!row) return;

    const rowRate = parseFloat(row.dataset.exchangeRate) || EX_RATE;
    const inVal = parseFloat(row.querySelector('.inline-in')?.value || 0);
    const outVal = parseFloat(row.querySelector('.inline-out')?.value || 0);
    const net = inVal - outVal;

    const balCell = row.querySelector('.cell-runbal');
    if (balCell) {
        const netStr = (net >= 0 ? '+$ ' : '-$ ') + Math.abs(net).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const netKesStr = 'KES ' + Math.round(net * rowRate).toLocaleString('en-US');
        balCell.innerHTML = `
            <div style="font-weight:900;color:${net >= 0 ? 'var(--green)' : 'var(--red)'};font-size:13.5px;">${netStr}</div>
            <div style="font-size:11px;color:var(--text-3);font-weight:600;">${netKesStr}</div>
        `;
    }
}

async function saveFinInlineEdit(id) {
    const row = document.getElementById('finRow-' + id);
    if (!row) return;

    const date = row.querySelector('.inline-date')?.value || '';
    const amtIn = parseFloat(row.querySelector('.inline-in')?.value || 0);
    const amtOut = parseFloat(row.querySelector('.inline-out')?.value || 0);
    const reason = row.querySelector('.inline-reason')?.value || '';
    const cat = row.querySelector('.inline-category')?.value || 'General';
    const method = row.querySelector('.inline-method')?.value || 'Cash';
    const rowRate = parseFloat(row.dataset.exchangeRate) || EX_RATE;

    const postData = {
        _csrf_token: CSRF_TOKEN,
        id: id,
        entry_date: date,
        amount_in: amtIn,
        amount_out: amtOut,
        reason: reason,
        category: cat,
        payment_method: method,
        exchange_rate: rowRate
    };

    const saveBtn = row.querySelector('.row-save-btn');
    if (saveBtn) saveBtn.innerHTML = '…';

    try {
        const res = await fetch('<?= url("financial/inline-update") ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json'
            },
            body: JSON.stringify(postData)
        });

        const data = await res.json();
        if (data.success && data.row) {
            const r = data.row;
            row.classList.remove('tr-editing');

            // 1. Date
            row.querySelector('.cell-date').setAttribute('data-val', r.entry_date);
            row.querySelector('.cell-date').innerHTML = `<span class="view-val" style="font-weight:700;color:var(--text);font-size:13px;">${escapeHtml(r.entry_date)}</span>`;

            // 2. Amount In
            row.querySelector('.cell-in').setAttribute('data-val', r.amount_in);
            const inValNum = Number(r.amount_in);
            row.querySelector('.cell-in').innerHTML = inValNum > 0 ? `
                <div class="view-val">
                    <div style="font-weight:800;color:var(--green);font-size:13.5px;">+$ ${inValNum.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                    <div style="font-size:11px;color:var(--text-3);font-weight:600;">KES ${Math.round(inValNum * EX_RATE).toLocaleString('en-US')}</div>
                </div>` : `<span class="view-val" style="color:var(--text-3);font-weight:700;">—</span>`;

            // 3. Amount Out
            row.querySelector('.cell-out').setAttribute('data-val', r.amount_out);
            const outValNum = Number(r.amount_out);
            row.querySelector('.cell-out').innerHTML = outValNum > 0 ? `
                <div class="view-val">
                    <div style="font-weight:800;color:var(--red);font-size:13.5px;">-$ ${outValNum.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                    <div style="font-size:11px;color:var(--text-3);font-weight:600;">KES ${Math.round(outValNum * EX_RATE).toLocaleString('en-US')}</div>
                </div>` : `<span class="view-val" style="color:var(--text-3);font-weight:700;">—</span>`;

            // 4. Remaining Balance
            const balVal = Number(r.balance || (r.amount_in - r.amount_out));
            const balStr = (balVal >= 0 ? '+$ ' : '-$ ') + Math.abs(balVal).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            row.querySelector('.cell-runbal').innerHTML = `
                <div class="view-val">
                    <div style="font-weight:900;color:${balVal >= 0 ? 'var(--brand)' : 'var(--red)'};font-size:13.5px;">${balStr}</div>
                    <div style="font-size:11px;color:var(--text-3);font-weight:600;">KES ${Math.round(balVal * EX_RATE).toLocaleString('en-US')}</div>
                </div>`;

            // 5. Reason / How Used
            row.querySelector('.cell-reason').setAttribute('data-val', r.reason);
            row.querySelector('.cell-reason').innerHTML = `<span class="view-val" style="font-size:13.5px;color:var(--text);line-height:1.4;font-weight:600;">${escapeHtml(r.reason || '—')}</span>`;

            // 6. Category
            row.querySelector('.cell-category').setAttribute('data-val', r.category);
            const catBadgeClass = (r.category === 'Client Inflow' || r.amount_in > 0) ? 's-done' : (r.category === 'Personal Drawing' ? 's-hold' : 's-plan');
            row.querySelector('.cell-category').innerHTML = `<span class="view-val"><span class="status ${catBadgeClass}" style="font-size:11.5px;padding:3px 8px;">${escapeHtml(r.category)}</span></span>`;

            // 7. Payment Method
            row.querySelector('.cell-method').setAttribute('data-val', r.payment_method);
            row.querySelector('.cell-method').innerHTML = `<span class="view-val" style="font-size:12.5px;font-weight:700;color:var(--text-2);">${escapeHtml(r.payment_method)}</span>`;

            // Toggle actions back
            row.querySelector('.view-actions').style.display = 'flex';
            row.querySelector('.edit-actions').style.display = 'none';

            // Flash highlight
            row.style.transition = 'background-color 0.4s ease';
            row.style.backgroundColor = 'rgba(16,185,129,0.15)';
            setTimeout(() => { row.style.backgroundColor = ''; }, 1000);

            // Update KPI cards if returned
            if (data.kpis) {
                const elTotIn = document.getElementById('statTotalIn');
                const elTotOut = document.getElementById('statTotalOut');
                const elTotBal = document.getElementById('statNetBalance');
                if (elTotIn) elTotIn.textContent = '$ ' + Number(data.kpis.total_in).toLocaleString('en-US', {minimumFractionDigits: 2});
                if (elTotOut) elTotOut.textContent = '$ ' + Number(data.kpis.total_out).toLocaleString('en-US', {minimumFractionDigits: 2});
                if (elTotBal) {
                    const nb = Number(data.kpis.net_balance);
                    elTotBal.textContent = (nb >= 0 ? '$ ' : '-$ ') + Math.abs(nb).toLocaleString('en-US', {minimumFractionDigits: 2});
                }
            }
        } else {
            alert(data.message || 'Could not update row.');
            if (saveBtn) saveBtn.innerHTML = '💾 Save';
        }
    } catch (err) {
        console.error('Inline update error:', err);
        alert('Could not update row: ' + (err.message || 'Please check connection.'));
        if (saveBtn) saveBtn.innerHTML = '💾 Save';
    }
}

function cancelFinInlineEdit(id) {
    const row = document.getElementById('finRow-' + id);
    if (!row) return;

    row.classList.remove('tr-editing');
    const dateVal = row.querySelector('.cell-date').getAttribute('data-val') || '';
    const inVal = parseFloat(row.querySelector('.cell-in').getAttribute('data-val') || 0);
    const outVal = parseFloat(row.querySelector('.cell-out').getAttribute('data-val') || 0);
    const reasonVal = row.querySelector('.cell-reason').getAttribute('data-val') || '';
    const catVal = row.querySelector('.cell-category').getAttribute('data-val') || 'General';
    const methodVal = row.querySelector('.cell-method').getAttribute('data-val') || 'Cash';
    const balVal = inVal - outVal;

    // 1. Date
    row.querySelector('.cell-date').innerHTML = `<span class="view-val" style="font-weight:700;color:var(--text);font-size:13px;">${escapeHtml(dateVal)}</span>`;

    // 2. Amount In
    row.querySelector('.cell-in').innerHTML = inVal > 0 ? `
        <div class="view-val">
            <div style="font-weight:800;color:var(--green);font-size:13.5px;">+$ ${inVal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
            <div style="font-size:11px;color:var(--text-3);font-weight:600;">KES ${Math.round(inVal * EX_RATE).toLocaleString('en-US')}</div>
        </div>` : `<span class="view-val" style="color:var(--text-3);font-weight:700;">—</span>`;

    // 3. Amount Out
    row.querySelector('.cell-out').innerHTML = outVal > 0 ? `
        <div class="view-val">
            <div style="font-weight:800;color:var(--red);font-size:13.5px;">-$ ${outVal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
            <div style="font-size:11px;color:var(--text-3);font-weight:600;">KES ${Math.round(outVal * EX_RATE).toLocaleString('en-US')}</div>
        </div>` : `<span class="view-val" style="color:var(--text-3);font-weight:700;">—</span>`;

    // 4. Remaining Balance
    const balStr = (balVal >= 0 ? '+$ ' : '-$ ') + Math.abs(balVal).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    row.querySelector('.cell-runbal').innerHTML = `
        <div class="view-val">
            <div style="font-weight:900;color:${balVal >= 0 ? 'var(--brand)' : 'var(--red)'};font-size:13.5px;">${balStr}</div>
            <div style="font-size:11px;color:var(--text-3);font-weight:600;">KES ${Math.round(balVal * EX_RATE).toLocaleString('en-US')}</div>
        </div>`;

    // 5. Reason / How Used
    row.querySelector('.cell-reason').innerHTML = `<span class="view-val" style="font-size:13.5px;color:var(--text);line-height:1.4;font-weight:600;">${escapeHtml(reasonVal || '—')}</span>`;

    // 6. Category
    const catBadgeClass = (catVal === 'Client Inflow' || inVal > 0) ? 's-done' : (catVal === 'Personal Drawing' ? 's-hold' : 's-plan');
    row.querySelector('.cell-category').innerHTML = `<span class="view-val"><span class="status ${catBadgeClass}" style="font-size:11.5px;padding:3px 8px;">${escapeHtml(catVal)}</span></span>`;

    // 7. Payment Method
    row.querySelector('.cell-method').innerHTML = `<span class="view-val" style="font-size:12.5px;font-weight:700;color:var(--text-2);">${escapeHtml(methodVal)}</span>`;

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
