<?php
    $content = function () use ($title) { ?>
        <section class="view active" id="view-invoice-new">
            <div class="hello"><h1>Create Invoice 🧾</h1><p>Record a customer invoice and assign payment status.</p></div>
            <div class="panel" style="margin-top:16px;padding:24px;">
                <form method="POST" action="<?= url('invoices/store') ?>" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;">
                    <?= csrf_field() ?>
                    <label><span>Invoice number</span><input type="text" name="invoice_number" value="INV-<?= rand(1000, 9999) ?>" required /></label>
                    <label><span>Client</span><input type="text" name="client" placeholder="e.g. KPC Depot" required /></label>
                    <label><span>Amount</span><input type="text" name="amount" placeholder="e.g. KES 320,000" required /></label>
                    <label><span>Due date</span><input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" /></label>
                    <label><span>Status</span><select name="status"><option>Pending</option><option>Paid</option><option>Overdue</option></select></label>
                    <div style="grid-column:1/-1;display:flex;gap:12px;justify-content:flex-end;">
                        <a href="<?= url('invoices') ?>" class="btn btn-ghost">Cancel</a>
                        <button type="submit" class="btn btn-brand">Save Invoice</button>
                    </div>
                </form>
            </div>
        </section>
    <?php };

    require __DIR__ . '/../layouts/app.php';
?>
