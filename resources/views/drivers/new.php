<?php
    $content = function () use ($title) { ?>
        <section class="view active" id="view-driver-new">
            <div class="hello"><h1>Add New Driver 🚚</h1><p>Create a driver profile and assign their compliance details.</p></div>
            <div class="panel" style="margin-top:16px;padding:24px;">
                <form method="POST" action="<?= url('drivers/store') ?>" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;">
                    <?= csrf_field() ?>
                    <label><span>Full name</span><input type="text" name="full_name" value="" required /></label>
                    <label><span>Phone</span><input type="text" name="phone" value="" /></label>
                    <label><span>License number</span><input type="text" name="license_number" value="" /></label>
                    <label><span>License class</span><input type="text" name="license_class" value="B-Class" /></label>
                    <label><span>Truck assignment</span><input type="text" name="truck" value="Unassigned" /></label>
                    <label><span>Status</span><select name="status"><option>Active</option><option>On trip</option><option>On leave</option></select></label>
                    <div style="grid-column:1/-1;display:flex;gap:12px;justify-content:flex-end;">
                        <a href="<?= url('drivers') ?>" class="btn btn-ghost">Cancel</a>
                        <button type="submit" class="btn btn-brand">Save Driver</button>
                    </div>
                </form>
            </div>
        </section>
    <?php };

    require __DIR__ . '/../layouts/app.php';
?>
