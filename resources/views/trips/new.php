<?php
    $content = function () use ($title, $trucks, $drivers, $products) {
?>
<section class="view active" id="view-trip-new">
    <div class="hello">
        <h1>Create New Trip 🚛</h1>
        <p>Dispatch a tanker, assign an available driver dynamically, and register the petroleum delivery route.</p>
    </div>
    <div class="panel" style="margin-top:20px;padding:26px;">
        <form method="POST" action="<?= url('trips/store') ?>" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px;">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Trip Number *</label>
                <input type="text" name="trip_number" value="TRP-<?= date('Y') ?>-<?= rand(100, 999) ?>" required />
            </div>

            <div class="form-group">
                <label>Customer / Consignee *</label>
                <input type="text" name="customer" placeholder="e.g. KPC Depot, Safi Fuel" required />
            </div>

            <div class="form-group">
                <label>Select Tanker Truck *</label>
                <select name="truck" required onchange="const c=this.options[this.selectedIndex].dataset.capacity; if(c) document.getElementById('loadQtyInput').value = c + ' L';">
                    <option value="">-- Choose Truck --</option>
                    <?php foreach ($trucks as $trk): ?>
                        <option value="<?= htmlspecialchars($trk['plate_number']) ?>" data-capacity="<?= number_format($trk['capacity_litres']) ?>">
                            <?= htmlspecialchars($trk['plate_number']) ?> (<?= htmlspecialchars($trk['model']) ?> - <?= number_format($trk['capacity_litres']) ?> L)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Assigned Driver (Dynamic Placement) *</label>
                <select name="driver" required>
                    <option value="">-- Assign Driver to Trip --</option>
                    <?php foreach ($drivers as $drv): ?>
                        <option value="<?= htmlspecialchars($drv['name']) ?>"><?= htmlspecialchars($drv['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Route (Origin → Destination) *</label>
                <input type="text" name="route" placeholder="e.g. KPC Nairobi → Kisumu Depot" required />
            </div>

            <div class="form-group">
                <label>Load Quantity / Litres *</label>
                <input type="text" name="load_quantity" id="loadQtyInput" placeholder="e.g. 28,000 L" required />
            </div>

            <div class="form-group">
                <label>Delivery Status</label>
                <select name="status">
                    <option>Planned</option>
                    <option>Loading</option>
                    <option selected>In transit</option>
                    <option>Delivered</option>
                </select>
            </div>

            <div style="grid-column:1/-1;display:flex;gap:12px;justify-content:flex-end;margin-top:12px;">
                <a href="<?= url('trips') ?>" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-brand">Create Trip</button>
            </div>
        </form>
    </div>
</section>
<?php };
require __DIR__ . '/../layouts/app.php';
?>
