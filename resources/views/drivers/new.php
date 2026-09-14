<?php
    $content = function () use ($title) { ?>
        <section class="view active" id="view-driver-new">
            <div class="hello"><h1>Add New Driver 🚚</h1><p>Create a driver profile and assign their compliance details.</p></div>
            <div class="panel" style="margin-top:16px;padding:24px;">
                <form method="POST" action="<?= url('drivers/store') ?>" style="display:flex;flex-direction:column;gap:18px;max-width:480px;">
                    <?= csrf_field() ?>
                    <label style="display:flex;flex-direction:column;gap:8px;">
                        <span style="font-weight:700;font-size:14px;color:var(--text);">Driver Full Name *</span>
                        <input type="text" name="full_name" placeholder="e.g. Samuel Mutua Mwangi" required autofocus style="padding:10px 14px;font-size:15px;border-radius:10px;border:1.5px solid var(--border-2);background:var(--card);color:var(--text);" />
                    </label>
                    <div style="display:flex;gap:12px;justify-content:flex-start;margin-top:6px;">
                        <button type="submit" class="btn btn-brand" style="padding:10px 22px;font-size:14px;">Save Driver</button>
                        <a href="<?= url('drivers') ?>" class="btn btn-ghost" style="padding:10px 18px;font-size:14px;">Cancel</a>
                    </div>
                </form>
            </div>
        </section>
    <?php };

    require __DIR__ . '/../layouts/app.php';
?>
