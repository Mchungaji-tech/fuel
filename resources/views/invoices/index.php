<?php
    $content = function () use ($title, $invoices) { ?>
        <section class="view active" id="view-invoices">
            <div class="hello"><h1>Invoices 💰</h1><p>Customer billing, payment status and collections overview.</p></div>

            <div class="panel" style="margin-top:16px;padding:20px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div>
                    <strong>Collections overview</strong>
                    <div style="color:var(--text-2);font-size:12px;">KES 1.42M pending receipts</div>
                </div>
                <a class="btn btn-brand" href="<?= url('invoices/new') ?>">＋ New invoice</a>
            </div>

            <div class="panel" style="margin-top:16px">
                <table>
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Client</th>
                            <th>Amount</th>
                            <th>Due</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="5" style="text-align:center;padding:32px;color:var(--text-3);">No invoices recorded. Click "New invoice" to create one.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td><b><?= htmlspecialchars($invoice['id']) ?></b></td>
                                    <td><?= htmlspecialchars($invoice['client']) ?></td>
                                    <td><?= htmlspecialchars($invoice['amount']) ?></td>
                                    <td><?= htmlspecialchars($invoice['due']) ?></td>
                                    <td>
                                        <?php
                                            $st = strtolower($invoice['status']);
                                            $pillStyle = 'background:var(--amber-soft);color:var(--amber)';
                                            if (str_contains($st, 'paid')) $pillStyle = 'background:var(--green-soft);color:var(--green)';
                                            elseif (str_contains($st, 'overdue')) $pillStyle = 'background:var(--red-soft);color:var(--red)';
                                        ?>
                                        <span class="pill" style="<?= $pillStyle ?>"><?= htmlspecialchars($invoice['status']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php };

    require __DIR__ . '/../layouts/app.php';
?>
