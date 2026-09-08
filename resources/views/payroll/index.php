<?php
    $content = function () use ($title, $employees) { ?>
        <section class="view active" id="view-payroll">
            <div class="hello"><h1>Payroll — August 2026 💳</h1><p>24 employees · Draft · Cut-off 25 Aug</p></div>
            <div class="pay-summary" style="margin-top:16px">
                <div class="pcard"><div class="lbl">Total gross</div><div class="val">KES 1,842,000</div><div class="bar"><i style="width:100%;background:var(--brand)"></i></div><small>+6.2% vs July</small></div>
                <div class="pcard"><div class="lbl">Statutory deductions</div><div class="val">KES 512,400</div><div class="bar"><i style="width:28%;background:var(--accent)"></i></div><small>PAYE · NSSF · SHIF · Housing</small></div>
                <div class="pcard"><div class="lbl">Net pay</div><div class="val">KES 1,329,600</div><div class="bar"><i style="width:72%;background:var(--green)"></i></div><small>21 bank + 3 M-Pesa</small></div>
                <div class="pcard"><div class="lbl">Approval status</div><div class="val" style="font-size:16px;padding:6px 0">Awaiting sign-off</div><div class="bar"><i style="width:60%;background:var(--blue)"></i></div><small>HR checked · Finance pending</small></div>
            </div>
            <div class="panel">
                <div class="panel-head"><h3>Payroll register</h3>
                    <div style="display:flex;gap:8px">
                        <button class="btn btn-ghost" onclick="toast('🏦 Bank file exported (CSV)')">Export bank file</button>
                        <button class="btn btn-brand" onclick="toast('✅ Payroll approved & locked')">Approve payroll</button>
                    </div>
                </div>
                <table>
                    <thead><tr><th>Employee</th><th>Gross</th><th>PAYE</th><th>NSSF</th><th>SHIF</th><th>Housing</th><th>Net pay</th></tr></thead>
                    <tbody>
                        <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td><div class="who"><div class="av" style="background:#F97316">AW</div><div><?= htmlspecialchars($employee['name']) ?><small>EMP-001 · Finance</small></div></div></td>
                                <td><?= htmlspecialchars($employee['gross']) ?></td>
                                <td><?= htmlspecialchars($employee['paye']) ?></td>
                                <td><?= htmlspecialchars($employee['nssf']) ?></td>
                                <td><?= htmlspecialchars($employee['shif']) ?></td>
                                <td><?= htmlspecialchars($employee['housing']) ?></td>
                                <td><b><?= htmlspecialchars($employee['net']) ?></b></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php };

    require __DIR__ . '/../layouts/app.php';
?>
