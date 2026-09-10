<?php
    $content = function () use ($title, $drivers, $salaries, $salaryAgg, $dispatches, $search, $universalFixedUsd, $universalFixedDisplay) {
        $totalTripsAll = array_sum(array_column($drivers, 'total_trips'));
?>
<section class="view active" id="view-drivers">
    <!-- Header & Actions -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
        <div class="hello">
            <h1>Drivers & Salaries 👷</h1>
            <p>Active driver roster, universal fixed monthly salary, automated arrears tracking, and cargo shortage loss deductions.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn btn-brand" onclick="document.getElementById('salaryModal').classList.add('active')">💰 Issue Driver Monthly Salary</button>
            <a href="<?= url('drivers/new') ?>" class="btn btn-ghost">＋ Add Driver</a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="kpis" style="margin-top:20px;">
        <div class="kpi">
            <div class="lbl">Registered Drivers</div>
            <div class="val"><?= count($drivers) ?> Drivers</div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Fixed base: <?= format_money($universalFixedUsd) ?>/mo</div>
        </div>
        <div class="kpi">
            <div class="lbl">Total Trips Dispatched</div>
            <div class="val" style="color:var(--brand);"><?= $totalTripsAll ?> Trips</div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Across all regional destinations</div>
        </div>
        <div class="kpi">
            <div class="lbl">Salaries Paid Out</div>
            <div class="val" style="color:var(--green);"><?= format_money($salaryAgg['total_paid'] ?? 0) ?></div>
            <div style="font-size:13px;color:var(--green);font-weight:700;margin-top:4px;">Disbursed compensation</div>
        </div>
        <div class="kpi" style="border:1.5px solid var(--amber);">
            <div class="lbl" style="color:var(--amber);font-weight:800;">Pending Arrears (Wait)</div>
            <div class="val" style="color:var(--amber);"><?= format_money($salaryAgg['total_wait'] ?? 0) ?></div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Auto-carried into next voucher</div>
        </div>
    </div>

    <!-- Compact Close-in Search Bar -->
    <div style="display:flex;align-items:center;flex-wrap:wrap;gap:12px;margin:22px 0 14px;">
        <div style="display:flex;align-items:center;gap:10px;background:var(--card);border:1.5px solid var(--border-2);border-radius:10px;padding:6px 14px;min-width:320px;max-width:440px;box-shadow:var(--shadow);">
            <span style="color:var(--text-3);font-size:16px;">🔍</span>
            <input type="text" id="driverSearch" placeholder="Search driver name, phone, license, status…" style="border:0;outline:0;background:transparent;width:100%;font-size:14.5px;color:var(--text);">
        </div>
        <div style="font-size:13.5px;color:var(--text-2);font-weight:700;">
            Showing <span id="drvVisibleCount"><?= count($drivers) ?></span> of <?= count($drivers) ?> Drivers
        </div>
    </div>

    <!-- Simple, Clean Driver Table (Same simple feel as Trucks Page) -->
    <div class="panel">
        <div class="table-responsive">
            <table id="driversTable">
                <thead>
                    <tr>
                        <th>Driver Name</th>
                        <th>Phone Contact</th>
                        <th>License & Class</th>
                        <th>Status</th>
                        <th>Fixed Base Rate</th>
                        <th>Trips Done</th>
                        <th>Cargo Shortages</th>
                        <th>Pending Arrears</th>
                        <th style="text-align:center;min-width:180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($drivers)): ?>
                        <tr>
                            <td colspan="9" style="text-align:center;padding:32px;color:var(--text-3);">No drivers registered. Click "Add Driver".</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($drivers as $d): ?>
                            <?php
                                $statusLower = strtolower($d['status'] ?? 'active');
                                $sClass = 's-done';
                                if (str_contains($statusLower, 'trip') || str_contains($statusLower, 'transit')) $sClass = 's-transit';
                                elseif (str_contains($statusLower, 'leave') || str_contains($statusLower, 'off')) $sClass = 's-hold';

                                $paid = (float) ($d['salary_paid'] ?? 0);
                                $wait = (float) ($d['salary_wait'] ?? 0);
                                $tripsCount = (int) ($d['total_trips'] ?? 0);
                                $fixedRate = (float) ($d['fixed_salary_usd'] ?? 0);
                                $shortageLoss = (float) ($d['pending_shortages_usd'] ?? 0);
                            ?>
                            <tr class="driver-row" id="drv-row-<?= $d['id'] ?>">
                                <td>
                                    <div class="who">
                                        <div class="av" style="background:#4F46E5;font-weight:800;color:#fff;">
                                            <?= strtoupper(substr($d['name'], 0, 2)) ?>
                                        </div>
                                        <div>
                                            <b style="font-size:15px;color:var(--text);"><?= htmlspecialchars($d['name']) ?></b>
                                            <small style="display:block;color:var(--text-3);font-size:11.5px;">ID: #DRV-<?= str_pad($d['id'], 3, '0', STR_PAD_LEFT) ?></small>
                                        </div>
                                    </div>
                                </td>

                                <td style="font-weight:600;color:var(--text-2);">
                                    <?= htmlspecialchars($d['phone'] ?: '—') ?>
                                </td>

                                <td>
                                    <div style="font-weight:700;font-size:13.5px;"><?= htmlspecialchars($d['license_number'] ?: '—') ?></div>
                                    <span style="background:var(--card-2);padding:2px 6px;border-radius:4px;border:1px solid var(--border);font-size:11px;font-weight:700;color:var(--text-3);">
                                        <?= htmlspecialchars($d['license_class'] ?: 'B-Class') ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="status <?= $sClass ?>">
                                        <i></i><?= htmlspecialchars($d['status'] ?: 'Active') ?>
                                    </span>
                                </td>

                                <td>
                                    <b style="font-size:14px;color:var(--brand);"><?= format_money($fixedRate) ?></b>
                                    <small style="display:block;color:var(--text-3);font-size:11px;">/ month</small>
                                </td>

                                <td>
                                    <b style="font-size:15px;color:var(--text);"><?= $tripsCount ?></b>
                                    <span style="font-size:12px;color:var(--text-3);">trips</span>
                                </td>

                                <td>
                                    <?php if ($shortageLoss > 0): ?>
                                        <span style="display:inline-flex;align-items:center;gap:3px;color:var(--red);background:rgba(239,68,68,0.1);padding:3px 8px;border-radius:6px;border:1px solid var(--red);font-size:12px;font-weight:800;" title="Cargo shortage loss to be deducted on next salary voucher">
                                            ⚠️ -<?= format_money($shortageLoss) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--green);font-size:12px;font-weight:700;">✓ 0 L (Clear)</span>
                                    <?php endif; ?>
                                </td>

                                <td style="white-space:nowrap;">
                                    <?php if ($wait > 0): ?>
                                        <span style="background:var(--amber-soft);color:var(--amber);padding:3px 8px;border-radius:6px;font-size:12px;font-weight:800;border:1px solid var(--amber);">
                                            <?= format_money($wait) ?> Wait
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--green);font-weight:700;font-size:12px;">✓ Cleared</span>
                                    <?php endif; ?>
                                </td>

                                <td style="text-align:center;white-space:nowrap;">
                                    <div style="display:inline-flex;gap:6px;align-items:center;">
                                        <!-- Extensive Details Button -->
                                        <button type="button" class="btn btn-sm btn-ghost" onclick="openDriverDetailsModal(<?= $d['id'] ?>)" style="padding:4px 10px;font-size:12.5px;font-weight:700;" title="View extensive trip and salary history">
                                            🔍 View Details
                                        </button>
                                        <!-- Quick Pay Button -->
                                        <button type="button" class="btn btn-sm btn-ghost" onclick="openDriverSalaryModal(<?= (int)$d['id'] ?>, '<?= htmlspecialchars(addslashes($d['name'])) ?>')" style="padding:4px 8px;font-size:12px;font-weight:700;color:var(--green);" title="Issue monthly salary voucher">
                                            💰 Pay
                                        </button>
                                        <!-- Delete Button -->
                                        <form method="POST" action="<?= url('drivers/delete/' . $d['id']) ?>" style="display:inline;" onsubmit="return confirm('Remove driver <?= htmlspecialchars(addslashes($d['name'])) ?> from fleet roster?');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--red);border-color:transparent;padding:4px 7px;" title="Delete Driver">✕</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Table Pagination Footer -->
        <div class="table-pagination-footer" id="driversPagination"></div>
    </div>

    <!-- Dedicated Driver Salary & Arrears Management Ledger -->
    <div id="salaries" style="margin-top:36px;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px;">
            <div>
                <h2 style="margin:0;font-size:18px;color:var(--text);">💰 Salary Disbursements & Arrears Ledger</h2>
                <p style="margin:3px 0 0 0;font-size:13px;color:var(--text-3);">Track monthly base salaries, auto-calculated carried forward arrears, transit shortage deductions, and voucher statuses.</p>
            </div>
            <button type="button" class="btn btn-brand btn-sm" onclick="document.getElementById('salaryModal').classList.add('active')">
                ＋ Issue Salary Voucher
            </button>
        </div>

        <div class="panel">
            <div class="table-responsive">
                <table id="salariesTable">
                    <thead>
                        <tr>
                            <th style="min-width:90px;">Date</th>
                            <th>Driver Name</th>
                            <th>Type / Structure</th>
                            <th>Month / Period</th>
                            <th>Base Salary</th>
                            <th>Arrears (Carried)</th>
                            <th>Shortage Loss</th>
                            <th>Net Payable</th>
                            <th>Payment Status</th>
                            <th>Notes</th>
                            <th style="text-align:center;min-width:140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($salaries)): ?>
                            <tr>
                                <td colspan="11" style="text-align:center;padding:28px;color:var(--text-3);">No salary records issued yet. Click "Issue Salary Voucher".</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($salaries as $sal): ?>
                                <?php
                                    $isPaid = ($sal['status'] === 'Paid');
                                    $base = (float)($sal['base_salary'] ?? 0);
                                    $carried = (float)($sal['carried_forward'] ?? 0);
                                    $shortage = (float)($sal['shortage_deductions'] ?? 0);
                                    $tot = (float)$sal['amount'];
                                    if ($base == 0 && $carried == 0 && $tot > 0) {
                                        $base = $tot;
                                    }
                                ?>
                                <tr id="sal-row-<?= $sal['id'] ?>">
                                    <td style="font-weight:700;white-space:nowrap;"><?= format_date_dol($sal['payment_date']) ?></td>
                                    <td><b style="color:var(--text);font-size:14px;"><?= htmlspecialchars($sal['driver_name']) ?></b></td>
                                    <td>
                                        <span style="background:var(--card-2);border:1px solid var(--border);padding:2px 8px;border-radius:6px;font-size:11.5px;font-weight:700;color:var(--text-2);">
                                            Monthly Fixed
                                        </span>
                                    </td>
                                    <td style="font-weight:600;color:var(--brand);"><?= htmlspecialchars($sal['period_reference'] ?: '—') ?></td>
                                    <td style="font-weight:700;color:var(--text);"><?= format_money($base) ?></td>
                                    <td>
                                        <?php if ($carried > 0): ?>
                                            <span style="font-weight:800;color:var(--amber);background:var(--amber-soft);padding:2px 7px;border-radius:6px;font-size:12px;">
                                                + <?= format_money($carried) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:var(--text-3);">0.00</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($shortage > 0): ?>
                                            <span style="font-weight:800;color:var(--red);background:rgba(239,68,68,0.1);padding:2px 7px;border-radius:6px;border:1px solid var(--red);font-size:12px;">
                                                - <?= format_money($shortage) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:var(--text-3);">0.00</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-weight:900;color:<?= $isPaid ? 'var(--green)' : 'var(--amber)' ?>;font-size:14px;">
                                        <?= format_money($tot) ?>
                                    </td>
                                    <td>
                                        <form method="POST" action="<?= url('drivers/salary/toggle') ?>" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="salary_id" value="<?= $sal['id'] ?>">
                                            <button type="submit" class="status <?= $isPaid ? 's-done' : 's-hold' ?>" style="cursor:pointer;border:none;font-family:inherit;" title="Click to toggle Paid/Wait">
                                                <i></i><?= $sal['status'] ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td style="font-size:12.5px;color:var(--text-2);"><?= htmlspecialchars($sal['notes'] ?: '—') ?></td>
                                    <td style="text-align:center;white-space:nowrap;">
                                        <div style="display:inline-flex;gap:6px;align-items:center;">
                                            <button type="button" class="btn btn-sm btn-ghost" onclick="openEditSalaryModal(<?= htmlspecialchars(json_encode([
                                                'id' => $sal['id'],
                                                'driver_name' => $sal['driver_name'],
                                                'payment_type' => $sal['payment_type'],
                                                'period_reference' => $sal['period_reference'],
                                                'base_salary' => $base,
                                                'carried_forward' => $carried,
                                                'shortage_deductions' => $shortage,
                                                'payment_date' => $sal['payment_date'],
                                                'status' => $sal['status'],
                                                'notes' => $sal['notes'],
                                            ])) ?>)" style="padding:3px 8px;font-size:12px;" title="Edit Salary Voucher">
                                                ✏️ Edit
                                            </button>
                                            <form method="POST" action="<?= url('drivers/salary/delete/' . $sal['id']) ?>" style="display:inline;" onsubmit="return confirm('Delete salary record for <?= htmlspecialchars(addslashes($sal['driver_name'])) ?>?');">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--red);border-color:transparent;padding:3px 6px;" title="Delete Record">✕</button>
                                            </form>
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
</section>

<!-- Extensive Driver Details Breakdown Modal -->
<div class="modal-backdrop" id="driverDetailsModal" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal-card" style="max-width:850px;max-height:90vh;overflow-y:auto;">
        <div class="modal-head">
            <div>
                <h2 id="modalDriverName" style="margin:0;">Driver Details</h2>
                <span id="modalDriverSub" style="font-size:13px;color:var(--text-3);">Full Career & Compensation Breakdown</span>
            </div>
            <button class="close-modal" onclick="document.getElementById('driverDetailsModal').classList.remove('active')">✕</button>
        </div>

        <!-- Driver Profile Header Card -->
        <div style="background:var(--card-2);border:1px solid var(--border);border-radius:14px;padding:16px 20px;margin-top:14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
            <div>
                <div style="font-size:12px;color:var(--text-3);font-weight:700;text-transform:uppercase;">Carrier Info</div>
                <div style="font-size:14px;color:var(--text);margin-top:4px;">
                    Phone: <b id="modalDriverPhone">—</b> • 
                    License: <b id="modalDriverLicense">—</b> (<span id="modalDriverClass">B-Class</span>)
                </div>
            </div>
            <div style="display:flex;gap:16px;text-align:right;">
                <div>
                    <div style="font-size:11.5px;color:var(--text-3);font-weight:700;text-transform:uppercase;">Volume Hauled</div>
                    <div id="modalDriverVolume" style="font-size:18px;font-weight:900;color:var(--brand);">0 L</div>
                </div>
                <div>
                    <div style="font-size:11.5px;color:var(--text-3);font-weight:700;text-transform:uppercase;">Lifetime Earnings</div>
                    <div id="modalDriverTotalEarnings" style="font-size:18px;font-weight:900;color:var(--green);"><?= app_currency_symbol() ?> 0.00</div>
                </div>
            </div>
        </div>

        <!-- Section 1: Lifetime Trip Dispatches -->
        <div style="margin-top:24px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                <h3 style="margin:0;font-size:16px;">🚚 Dispatch & Trips History</h3>
                <span id="modalTripsCount" style="font-size:12px;color:var(--text-3);font-weight:700;">0 trips</span>
            </div>
            <div class="table-responsive" style="max-height:260px;overflow-y:auto;border:1px solid var(--border);border-radius:10px;">
                <table>
                    <thead>
                        <tr>
                            <th style="min-width:85px;">DOL</th>
                            <th>Trip #</th>
                            <th>Tanker Truck</th>
                            <th>Destination</th>
                            <th>Litres Loaded</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="modalTripsTableBody">
                        <tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-3);">No trips recorded for this driver.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 2: Salary & Compensation Payouts -->
        <div style="margin-top:24px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                <h3 style="margin:0;font-size:16px;">💰 Salary & Payment Disbursements</h3>
                <button type="button" class="btn btn-sm btn-brand" id="modalIssuePayBtn" onclick="">＋ Issue Payment</button>
            </div>
            <div class="table-responsive" style="max-height:260px;overflow-y:auto;border:1px solid var(--border);border-radius:10px;">
                <table>
                    <thead>
                        <tr>
                            <th style="min-width:85px;">Date</th>
                            <th>Type / Reference</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th style="text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="modalSalariesTableBody">
                        <tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-3);">No salary records found.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="margin-top:22px;display:flex;justify-content:flex-end;">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('driverDetailsModal').classList.remove('active')">Close</button>
        </div>
    </div>
</div>

<!-- Issue Salary Modal -->
<div class="modal-backdrop" id="salaryModal" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal-card" style="max-width:560px;">
        <div class="modal-head">
            <div>
                <h2 style="margin:0;">💰 Issue Driver Monthly Salary Voucher</h2>
                <span style="font-size:12.5px;color:var(--text-3);">Universal fixed base salary • Automated arrears • Transit shortage loss deductions</span>
            </div>
            <button class="close-modal" onclick="document.getElementById('salaryModal').classList.remove('active')">✕</button>
        </div>
        <form method="POST" action="<?= url('drivers/salary/store') ?>">
            <?= csrf_field() ?>
            <div class="form-grid single" style="gap:14px;">
                <div class="form-group">
                    <label>Select Driver *</label>
                    <select name="driver_id" id="salaryDriverSelect" required onchange="onDriverSalarySelected()">
                        <option value="">-- Choose Driver --</option>
                        <?php foreach ($drivers as $dr): ?>
                            <option value="<?= $dr['id'] ?>" data-name="<?= htmlspecialchars($dr['name']) ?>">
                                <?= htmlspecialchars($dr['name']) ?> (<?= htmlspecialchars($dr['status'] ?: 'Active') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="driver_name" id="salaryDriverName">
                </div>

                <div class="form-group">
                    <label>Compensation Structure *</label>
                    <input type="text" class="table-inline-input" value="Universal Fixed Monthly Salary" readonly style="background:var(--card-2);font-weight:700;color:var(--text-2);border:1px solid var(--border);border-radius:8px;padding:8px 12px;">
                    <input type="hidden" name="payment_type" value="monthly_salary">
                </div>

                <div class="form-group">
                    <label>Payroll Month / Period *</label>
                    <input type="text" name="period_reference" id="salaryPeriodRef" value="<?= date('F Y') ?>" placeholder="e.g. <?= date('F Y') ?>" required>
                    <small style="color:var(--text-3);">Monthly salary payroll cycle</small>
                </div>

                <div class="form-group">
                    <label>Fixed Monthly Base Salary [<?= app_currency_symbol() ?>] *</label>
                    <input type="number" step="0.01" name="base_salary" id="salaryBaseInput" placeholder="0.00" required oninput="calcSalaryTotal()">
                    <small style="color:var(--text-3);">Universal fixed base salary for all drivers (Default: <?= format_money($universalFixedUsd) ?>)</small>
                </div>

                <div class="form-group">
                    <label>Carried Forward Unpaid Balance (Arrears) [<?= app_currency_symbol() ?>]</label>
                    <input type="number" step="0.01" name="carried_forward" id="salaryCarriedInput" placeholder="0.00" value="0.00" oninput="calcSalaryTotal()">
                    <small style="color:var(--text-3);">Auto-calculated from unpaid past vouchers (Status = Wait)</small>
                </div>

                <div class="form-group">
                    <label style="color:var(--red);font-weight:800;">Cargo Shortage Deductions [<?= app_currency_symbol() ?>]</label>
                    <input type="number" step="0.01" name="shortage_deductions" id="salaryShortageInput" placeholder="0.00" value="0.00" oninput="calcSalaryTotal()" style="border-color:var(--red);color:var(--red);font-weight:800;">
                    <small style="color:var(--red);font-weight:700;">Transit cargo shortage loss automatically forwarded from fleet dispatches</small>
                </div>

                <!-- Shortage Alert Banner -->
                <div id="salaryShortageAlert" style="display:none;background:rgba(239,68,68,0.08);border:1.5px solid var(--red);border-radius:10px;padding:10px 14px;font-size:12.5px;color:var(--text);line-height:1.4;"></div>

                <!-- Live Total Preview -->
                <div style="background:var(--card-2);border:1.5px solid var(--border);border-radius:10px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:11.5px;color:var(--text-3);font-weight:800;text-transform:uppercase;letter-spacing:0.5px;">Net Payable Voucher</div>
                        <div id="salaryFormulaBreakdown" style="font-size:12px;color:var(--text-2);margin-top:2px;">Base + Arrears - Shortage Deductions</div>
                    </div>
                    <div id="salaryTotalDisplay" style="font-size:24px;font-weight:900;color:var(--green);">
                        <?= app_currency_symbol() ?> 0.00
                    </div>
                </div>

                <div class="form-group">
                    <label>Payment Date *</label>
                    <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label>Payment Approval Status *</label>
                    <select name="status">
                        <option value="Wait" selected>Wait (Pending Disbursement / In Review)</option>
                        <option value="Paid">Paid (Disbursed to Driver)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Notes / Voucher Reference</label>
                    <input type="text" name="notes" placeholder="e.g. Bank transfer ref / M-Pesa code / Salary balance">
                </div>
            </div>

            <div style="margin-top:22px;display:flex;justify-content:flex-end;gap:12px;">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('salaryModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-brand">Issue Salary Voucher</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Salary Modal -->
<div class="modal-backdrop" id="editSalaryModal" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal-card" style="max-width:540px;">
        <div class="modal-head">
            <h2>✏️ Edit Driver Salary Record</h2>
            <button class="close-modal" onclick="document.getElementById('editSalaryModal').classList.remove('active')">✕</button>
        </div>
        <form method="POST" action="<?= url('drivers/salary/update') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="editSalId">
            <div class="form-grid single" style="gap:14px;">
                <div class="form-group">
                    <label>Driver Name *</label>
                    <input type="text" name="driver_name" id="editSalDriverName" required>
                </div>

                <div class="form-group">
                    <label>Compensation Structure *</label>
                    <input type="text" class="table-inline-input" value="Monthly Fixed Salary" readonly style="background:var(--card-2);font-weight:700;color:var(--text-2);border:1px solid var(--border);border-radius:8px;padding:8px 12px;">
                    <input type="hidden" name="payment_type" value="monthly_salary">
                </div>

                <div class="form-group">
                    <label>Payroll Month / Period Reference</label>
                    <input type="text" name="period_reference" id="editSalPeriodRef" placeholder="e.g. May 2026">
                </div>

                <div class="form-group">
                    <label>Fixed Monthly Base Salary [<?= app_currency_symbol() ?>] *</label>
                    <input type="number" step="0.01" name="base_salary" id="editSalBase" placeholder="0.00" required oninput="calcEditSalaryTotal()">
                </div>

                <div class="form-group">
                    <label>Carried Forward Unpaid Balance (Arrears) [<?= app_currency_symbol() ?>]</label>
                    <input type="number" step="0.01" name="carried_forward" id="editSalCarried" placeholder="0.00" oninput="calcEditSalaryTotal()">
                </div>

                <div class="form-group">
                    <label style="color:var(--red);font-weight:800;">Cargo Shortage Deductions [<?= app_currency_symbol() ?>]</label>
                    <input type="number" step="0.01" name="shortage_deductions" id="editSalShortage" placeholder="0.00" oninput="calcEditSalaryTotal()" style="border-color:var(--red);color:var(--red);font-weight:800;">
                </div>

                <!-- Live Total Preview -->
                <div style="background:var(--card-2);border:1.5px solid var(--border);border-radius:10px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:12px;color:var(--text-3);font-weight:700;text-transform:uppercase;">Net Payable Amount</div>
                        <div style="font-size:12px;color:var(--text-2);">Base + Arrears - Shortages</div>
                    </div>
                    <div id="editSalTotalDisplay" style="font-size:22px;font-weight:900;color:var(--green);">
                        <?= app_currency_symbol() ?> 0.00
                    </div>
                </div>

                <div class="form-group">
                    <label>Payment Date *</label>
                    <input type="date" name="payment_date" id="editSalDate" required>
                </div>

                <div class="form-group">
                    <label>Payment Approval Status *</label>
                    <select name="status" id="editSalStatus">
                        <option value="Wait">Wait (Pending Disbursement / In Review)</option>
                        <option value="Paid">Paid (Disbursed to Driver)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Notes / Voucher Reference</label>
                    <input type="text" name="notes" id="editSalNotes" placeholder="e.g. Bank transfer ref / M-Pesa code / Trip allowance">
                </div>
            </div>

            <div style="margin-top:22px;display:flex;justify-content:flex-end;gap:12px;">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('editSalaryModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-brand">✓ Update Salary Record</button>
            </div>
        </form>
    </div>
</div>

<script>
const DRIVER_DATA = <?= json_encode($drivers) ?>;
const CURRENCY_SYM = '<?= app_currency_symbol() ?>';
const IS_KES = <?= current_currency() === 'KES' ? 'true' : 'false' ?>;
const EXCHANGE_RATE = <?= (float)exchange_rate() ?>;
const TOGGLE_SALARY_URL = '<?= url("drivers/salary/toggle") ?>';
const CSRF_TOKEN = '<?= csrf_token() ?>';

function calcSalaryTotal() {
    const base = parseFloat(document.getElementById('salaryBaseInput')?.value) || 0;
    const carried = parseFloat(document.getElementById('salaryCarriedInput')?.value) || 0;
    const shortage = parseFloat(document.getElementById('salaryShortageInput')?.value) || 0;
    const net = Math.max(0, base + carried - shortage);
    const disp = document.getElementById('salaryTotalDisplay');
    if (disp) disp.textContent = CURRENCY_SYM + ' ' + net.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const bd = document.getElementById('salaryFormulaBreakdown');
    if (bd) {
        bd.textContent = `${CURRENCY_SYM} ${base.toLocaleString('en-US',{minimumFractionDigits:2})} (Base) + ${CURRENCY_SYM} ${carried.toLocaleString('en-US',{minimumFractionDigits:2})} (Arrears) - ${CURRENCY_SYM} ${shortage.toLocaleString('en-US',{minimumFractionDigits:2})} (Shortage)`;
    }
}

function calcEditSalaryTotal() {
    const base = parseFloat(document.getElementById('editSalBase')?.value) || 0;
    const carried = parseFloat(document.getElementById('editSalCarried')?.value) || 0;
    const shortage = parseFloat(document.getElementById('editSalShortage')?.value) || 0;
    const net = Math.max(0, base + carried - shortage);
    const disp = document.getElementById('editSalTotalDisplay');
    if (disp) disp.textContent = CURRENCY_SYM + ' ' + net.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function onDriverSalarySelected() {
    const sel = document.getElementById('salaryDriverSelect');
    const drvId = sel.value;
    if (!drvId) return;
    const drv = DRIVER_DATA.find(d => d.id == drvId);
    if (!drv) return;

    document.getElementById('salaryDriverName').value = drv.name;
    document.getElementById('salaryBaseInput').value = (drv.display_fixed_salary || 0).toFixed(2);
    document.getElementById('salaryCarriedInput').value = (drv.display_arrears || 0).toFixed(2);
    document.getElementById('salaryShortageInput').value = (drv.display_shortages || 0).toFixed(2);

    const alertBox = document.getElementById('salaryShortageAlert');
    if (alertBox) {
        if (drv.display_shortages > 0 && drv.shortage_trips && drv.shortage_trips.length > 0) {
            const tripsList = drv.shortage_trips.map(t => {
                const lossDisp = (parseFloat(t.payout_difference || 0) * (IS_KES ? EXCHANGE_RATE : 1)).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
                return `<b>${t.trip_number}</b> (${t.shortage_litres} L shortage, loss: ${CURRENCY_SYM} ${lossDisp})`;
            }).join('; ');
            alertBox.innerHTML = `⚠️ <b>Cargo Shortage Loss Forwarded:</b> ${CURRENCY_SYM} ${(drv.display_shortages || 0).toLocaleString('en-US', {minimumFractionDigits:2})} automatically deducted from driver salary for transit shortage on: ${tripsList}.`;
            alertBox.style.display = 'block';
        } else {
            alertBox.style.display = 'none';
            alertBox.innerHTML = '';
        }
    }

    calcSalaryTotal();
}

window.openEditSalaryModal = function(data) {
    document.getElementById('editSalId').value = data.id;
    document.getElementById('editSalDriverName').value = data.driver_name || '';
    document.getElementById('editSalPeriodRef').value = data.period_reference || '';
    const mult = IS_KES ? EXCHANGE_RATE : 1;
    document.getElementById('editSalBase').value = (parseFloat(data.base_salary || 0) * mult).toFixed(2);
    document.getElementById('editSalCarried').value = (parseFloat(data.carried_forward || 0) * mult).toFixed(2);
    document.getElementById('editSalShortage').value = (parseFloat(data.shortage_deductions || 0) * mult).toFixed(2);
    document.getElementById('editSalDate').value = data.payment_date || '';
    document.getElementById('editSalStatus').value = data.status || 'Wait';
    document.getElementById('editSalNotes').value = data.notes || '';
    calcEditSalaryTotal();
    document.getElementById('editSalaryModal').classList.add('active');
};

// Open Driver Salary modal with pre-selected driver
function openDriverSalaryModal(driverId, driverName) {
    const sel = document.getElementById('salaryDriverSelect');
    if (sel) {
        sel.value = driverId;
        onDriverSalarySelected();
    }
    document.getElementById('salaryModal').classList.add('active');
}

function updateDriverName() {
    const sel = document.getElementById('salaryDriverSelect');
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('salaryDriverName').value = opt ? (opt.dataset.name || '') : '';
}

function onPaymentTypeChange() {
    const pType = document.getElementById('salaryPaymentType').value;
    const refGroup = document.getElementById('tripRefGroup');
    refGroup.style.display = (pType === 'per_trip') ? 'block' : 'none';
}

function formatDate(dStr) {
    if (!dStr) return '—';
    const parts = dStr.split('-');
    if (parts.length === 3) {
        const yr = parts[0].slice(-2);
        return `${parts[2]}/${parts[1]}/${yr}`;
    }
    return dStr;
}

// Open Extensive Driver Details Modal
function openDriverDetailsModal(driverId) {
    const drv = DRIVER_DATA.find(d => d.id == driverId);
    if (!drv) return;

    document.getElementById('modalDriverName').textContent = drv.name;
    document.getElementById('modalDriverSub').textContent = `Driver ID: #DRV-${String(drv.id).padStart(3, '0')} • Status: ${drv.status || 'Active'}`;
    document.getElementById('modalDriverPhone').textContent = drv.phone || '—';
    document.getElementById('modalDriverLicense').textContent = drv.license_number || '—';
    document.getElementById('modalDriverClass').textContent = drv.license_class || 'B-Class';
    document.getElementById('modalDriverVolume').textContent = (drv.total_litres || 0).toLocaleString() + ' L';
    document.getElementById('modalDriverTotalEarnings').textContent = `${CURRENCY_SYM} ${(drv.salary_paid || 0).toFixed(2)}`;

    // Set Pay Button
    const payBtn = document.getElementById('modalIssuePayBtn');
    payBtn.onclick = () => {
        document.getElementById('driverDetailsModal').classList.remove('active');
        openDriverSalaryModal(drv.id, drv.name);
    };

    // Populate Trips
    const trips = drv.trips || [];
    document.getElementById('modalTripsCount').textContent = `${trips.length} trips dispatched`;
    const tripsTbody = document.getElementById('modalTripsTableBody');
    if (trips.length === 0) {
        tripsTbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-3);">No trips recorded for ${drv.name}.</td></tr>`;
    } else {
        tripsTbody.innerHTML = trips.map(t => {
            const dol = formatDate(t.dispatch_date);
            const litres = parseInt(t.loaded_litres || 0).toLocaleString();
            return `
                <tr>
                    <td style="font-weight:700;white-space:nowrap;">${dol}</td>
                    <td><b>${t.trip_number}</b></td>
                    <td><b>${t.truck}</b></td>
                    <td>${t.destination}</td>
                    <td style="font-weight:800;color:var(--brand);">${litres} L</td>
                    <td><span class="status s-done" style="font-size:11.5px;"><i></i>${t.status}</span></td>
                </tr>
            `;
        }).join('');
    }

    // Populate Salaries
    const sals = drv.salary_history || [];
    const salTbody = document.getElementById('modalSalariesTableBody');
    if (sals.length === 0) {
        salTbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-3);">No salary records found for ${drv.name}.</td></tr>`;
    } else {
        salTbody.innerHTML = sals.map(s => {
            const isPaid = (s.status === 'Paid');
            const amt = `${CURRENCY_SYM} ${parseFloat(s.amount || 0).toFixed(2)}`;
            const dateFmt = formatDate(s.payment_date);
            return `
                <tr>
                    <td style="font-weight:700;white-space:nowrap;">${dateFmt}</td>
                    <td><b>${s.payment_type || 'per_trip'}</b> <small style="color:var(--text-3);">(${s.period_reference || 'General'})</small></td>
                    <td style="font-weight:800;color:${isPaid ? 'var(--green)' : 'var(--amber)'};">${amt}</td>
                    <td>
                        <span class="status ${isPaid ? 's-done' : 's-hold'}">
                            <i></i>${s.status}
                        </span>
                    </td>
                    <td style="font-size:12.5px;color:var(--text-2);">${s.notes || '—'}</td>
                    <td style="text-align:center;">
                        <form method="POST" action="${TOGGLE_SALARY_URL}" style="display:inline;">
                            <input type="hidden" name="_csrf_token" value="${CSRF_TOKEN}">
                            <input type="hidden" name="salary_id" value="${s.id}">
                            <button type="submit" class="btn btn-sm btn-ghost" style="padding:2px 8px;font-size:11px;" title="Toggle Paid/Wait status">
                                ${isPaid ? '↩ Mark Wait' : '✓ Mark Paid'}
                            </button>
                        </form>
                    </td>
                </tr>
            `;
        }).join('');
    }

    document.getElementById('driverDetailsModal').classList.add('active');
}

// Live Search Filter for Drivers
const drvSearch = document.getElementById('driverSearch');
if (drvSearch) {
    drvSearch.addEventListener('input', function(e) {
        const val = e.target.value.toLowerCase().trim();
        const rows = document.querySelectorAll('#driversTable tbody tr[id^="drv-row-"]');
        let visible = 0;
        rows.forEach(r => {
            const matches = !val || r.textContent.toLowerCase().includes(val);
            r.dataset.matchedFilter = matches ? 'true' : 'false';
            if (matches) visible++;
        });
        const cnt = document.getElementById('drvVisibleCount');
        if (cnt) cnt.textContent = visible;
        if (window.driversPagination) {
            window.driversPagination.refresh();
        }
    });
}

// Initialize Drivers Table Pagination
const driversPagination = initTablePagination({
    tableId: 'driversTable',
    footerId: 'driversPagination',
    defaultPageSize: 10,
    pageSizes: [10, 25, 50, 100],
    countSpanId: 'drvVisibleCount',
    rowSelector: '#driversTable tbody tr[id^="drv-row-"]'
});
window.driversPagination = driversPagination;
</script>
<?php };
require __DIR__ . '/../layouts/app.php';
?>
