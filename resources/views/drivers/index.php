<?php
    $content = function () use ($title, $drivers, $salaries, $salaryAgg, $dispatches, $search) {
        $totalTripsAll = array_sum(array_column($drivers, 'total_trips'));
?>
<section class="view active" id="view-drivers">
    <!-- Header & Actions -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
        <div class="hello">
            <h1>Drivers & Salaries 👷</h1>
            <p>Active driver roster, quick compensation summary, and extensive career & payment breakdowns.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn btn-brand" onclick="document.getElementById('salaryModal').classList.add('active')">💰 Issue Driver Salary</button>
            <a href="<?= url('drivers/new') ?>" class="btn btn-ghost">＋ Add Driver</a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="kpis" style="margin-top:20px;">
        <div class="kpi">
            <div class="lbl">Registered Driver Fleet</div>
            <div class="val"><?= count($drivers) ?> Drivers</div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Ready for dynamic dispatch</div>
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
            <div class="lbl" style="color:var(--amber);font-weight:800;">Pending Payouts (Wait)</div>
            <div class="val" style="color:var(--amber);"><?= format_money($salaryAgg['total_wait'] ?? 0) ?></div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Awaiting clearance / payment</div>
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
                        <th>Trips Done</th>
                        <th>Total Paid</th>
                        <th>Pending Balance</th>
                        <th style="text-align:center;min-width:180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($drivers)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center;padding:32px;color:var(--text-3);">No drivers registered. Click "Add Driver".</td>
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
                                    <b style="font-size:15px;color:var(--brand);"><?= $tripsCount ?></b>
                                    <span style="font-size:12px;color:var(--text-3);">trips</span>
                                </td>

                                <td style="font-weight:800;color:var(--green);white-space:nowrap;">
                                    <?= format_money($paid) ?>
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
                                        <button type="button" class="btn btn-sm btn-ghost" onclick="openDriverSalaryModal(<?= (int)$d['id'] ?>, '<?= htmlspecialchars(addslashes($d['name'])) ?>')" style="padding:4px 8px;font-size:12px;font-weight:700;color:var(--green);" title="Issue salary payment">
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
                <p style="margin:3px 0 0 0;font-size:13px;color:var(--text-3);">Track current period base salaries, carried forward unpaid arrears, and payment statuses.</p>
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
                            <th>Period / Trip Ref</th>
                            <th>Current Salary</th>
                            <th>Carried Forward (Arrears)</th>
                            <th>Total Payable</th>
                            <th>Payment Status</th>
                            <th>Notes</th>
                            <th style="text-align:center;min-width:140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($salaries)): ?>
                            <tr>
                                <td colspan="10" style="text-align:center;padding:28px;color:var(--text-3);">No salary records issued yet. Click "Issue Salary Voucher".</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($salaries as $sal): ?>
                                <?php
                                    $isPaid = ($sal['status'] === 'Paid');
                                    $base = (float)($sal['base_salary'] ?? 0);
                                    $carried = (float)($sal['carried_forward'] ?? 0);
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
                                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $sal['payment_type'] ?? 'per_trip'))) ?>
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
    <div class="modal-card" style="max-width:540px;">
        <div class="modal-head">
            <h2>💰 Issue Driver Salary / Trip Compensation</h2>
            <button class="close-modal" onclick="document.getElementById('salaryModal').classList.remove('active')">✕</button>
        </div>
        <form method="POST" action="<?= url('drivers/salary/store') ?>">
            <?= csrf_field() ?>
            <div class="form-grid single" style="gap:14px;">
                <div class="form-group">
                    <label>Select Driver *</label>
                    <select name="driver_id" id="salaryDriverSelect" required onchange="updateDriverName()">
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
                    <label>Payment Structure Type *</label>
                    <select name="payment_type" id="salaryPaymentType" onchange="onPaymentTypeChange()">
                        <option value="per_trip" selected>Per Trip Compensation (Freight Commission / Allowance)</option>
                        <option value="monthly_retainer">Monthly Fixed Salary Retainer</option>
                        <option value="bonus">Performance / Safe Delivery Bonus</option>
                    </select>
                </div>

                <div class="form-group" id="tripRefGroup">
                    <label>Trip Reference / Dispatched Route</label>
                    <select name="period_reference" id="tripRefSelect">
                        <option value="">-- Select Trip Assignment --</option>
                        <?php foreach ($dispatches as $dsp): ?>
                            <option value="<?= htmlspecialchars($dsp['trip_number']) ?>">
                                <?= htmlspecialchars($dsp['trip_number']) ?> — <?= htmlspecialchars($dsp['destination']) ?> (<?= format_date_dol($dsp['dispatch_date']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Current Month / Period Salary [<?= app_currency_symbol() ?>] *</label>
                    <input type="number" step="0.01" name="base_salary" id="salaryBaseInput" placeholder="0.00" required oninput="calcSalaryTotal()">
                    <small style="color:var(--text-3);">Agreed monthly or trip salary for current period</small>
                </div>

                <div class="form-group">
                    <label>Carried Forward Unpaid Balance (Arrears) [<?= app_currency_symbol() ?>]</label>
                    <input type="number" step="0.01" name="carried_forward" id="salaryCarriedInput" placeholder="0.00" value="0.00" oninput="calcSalaryTotal()">
                    <small style="color:var(--text-3);">Previous months unpaid balance / arrears brought forward</small>
                </div>

                <!-- Live Total Preview -->
                <div style="background:var(--card-2);border:1.5px solid var(--border);border-radius:10px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:12px;color:var(--text-3);font-weight:700;text-transform:uppercase;">Total Payable Voucher</div>
                        <div style="font-size:12px;color:var(--text-2);">Current Salary + Carried Forward</div>
                    </div>
                    <div id="salaryTotalDisplay" style="font-size:22px;font-weight:900;color:var(--green);">
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
                    <input type="text" name="notes" placeholder="e.g. Bank transfer ref / M-Pesa code / Trip allowance">
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
                    <label>Payment Structure Type *</label>
                    <select name="payment_type" id="editSalPaymentType">
                        <option value="per_trip">Per Trip Compensation (Freight Commission / Allowance)</option>
                        <option value="monthly_retainer">Monthly Fixed Salary Retainer</option>
                        <option value="bonus">Performance / Safe Delivery Bonus</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Period / Trip Reference</label>
                    <input type="text" name="period_reference" id="editSalPeriodRef" placeholder="e.g. May 2026 or TRP-2026-001">
                </div>

                <div class="form-group">
                    <label>Current Month / Period Salary [<?= app_currency_symbol() ?>] *</label>
                    <input type="number" step="0.01" name="base_salary" id="editSalBase" placeholder="0.00" required oninput="calcEditSalaryTotal()">
                </div>

                <div class="form-group">
                    <label>Carried Forward Unpaid Balance (Arrears) [<?= app_currency_symbol() ?>]</label>
                    <input type="number" step="0.01" name="carried_forward" id="editSalCarried" placeholder="0.00" oninput="calcEditSalaryTotal()">
                </div>

                <!-- Live Total Preview -->
                <div style="background:var(--card-2);border:1.5px solid var(--border);border-radius:10px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:12px;color:var(--text-3);font-weight:700;text-transform:uppercase;">Total Payable Amount</div>
                        <div style="font-size:12px;color:var(--text-2);">Base + Carried Forward</div>
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
const TOGGLE_SALARY_URL = '<?= url("drivers/salary/toggle") ?>';
const CSRF_TOKEN = '<?= csrf_token() ?>';

function calcSalaryTotal() {
    const base = parseFloat(document.getElementById('salaryBaseInput')?.value) || 0;
    const carried = parseFloat(document.getElementById('salaryCarriedInput')?.value) || 0;
    const total = base + carried;
    const disp = document.getElementById('salaryTotalDisplay');
    if (disp) disp.textContent = CURRENCY_SYM + ' ' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function calcEditSalaryTotal() {
    const base = parseFloat(document.getElementById('editSalBase')?.value) || 0;
    const carried = parseFloat(document.getElementById('editSalCarried')?.value) || 0;
    const total = base + carried;
    const disp = document.getElementById('editSalTotalDisplay');
    if (disp) disp.textContent = CURRENCY_SYM + ' ' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

window.openEditSalaryModal = function(data) {
    document.getElementById('editSalId').value = data.id;
    document.getElementById('editSalDriverName').value = data.driver_name || '';
    document.getElementById('editSalPaymentType').value = data.payment_type || 'per_trip';
    document.getElementById('editSalPeriodRef').value = data.period_reference || '';
    document.getElementById('editSalBase').value = data.base_salary || 0;
    document.getElementById('editSalCarried').value = data.carried_forward || 0;
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
        updateDriverName();
    }
    calcSalaryTotal();
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
