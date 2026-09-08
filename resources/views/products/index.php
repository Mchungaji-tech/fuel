<?php
    $content = function () use ($title, $products, $search) {
?>
<section class="view active" id="view-products">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
        <div class="hello">
            <h1>Fuel Products Directory ⛽</h1>
            <p>Kenyan bulk petroleum specifications, abbreviations, and energy grades.</p>
        </div>
        <button class="btn btn-brand" onclick="document.getElementById('productModal').classList.add('active')">＋ Add Product</button>
    </div>

    <!-- Quick Info Cards for Key Kenya Fuel Products -->
    <div class="kpis" style="margin-top:20px;">
        <div class="kpi">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:18px;font-weight:900;color:var(--brand);background:var(--brand-soft);padding:4px 10px;border-radius:8px;">AGO</span>
                <span class="status s-done">Active</span>
            </div>
            <div class="lbl" style="margin-top:10px;">Automotive Gas Oil</div>
            <div style="font-size:13.5px;color:var(--text-2);margin-top:4px;">Low-sulphur diesel for commercial fleet & transport</div>
        </div>
        <div class="kpi">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:18px;font-weight:900;color:var(--accent);background:var(--accent-soft);padding:4px 10px;border-radius:8px;">PMS</span>
                <span class="status s-done">Active</span>
            </div>
            <div class="lbl" style="margin-top:10px;">Premium Motor Spirit</div>
            <div style="font-size:13.5px;color:var(--text-2);margin-top:4px;">Super unleaded petrol (RON 93+) for passenger vehicles</div>
        </div>
        <div class="kpi">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:18px;font-weight:900;color:var(--blue);background:var(--blue-soft);padding:4px 10px;border-radius:8px;">DPK / IK</span>
                <span class="status s-done">Active</span>
            </div>
            <div class="lbl" style="margin-top:10px;">Dual Purpose / Illuminating Kerosene</div>
            <div style="font-size:13.5px;color:var(--text-2);margin-top:4px;">Refined paraffin for lighting, heating & domestic supply</div>
        </div>
        <div class="kpi">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:18px;font-weight:900;color:#7C3AED;background:#F5F3FF;padding:4px 10px;border-radius:8px;">JET A-1</span>
                <span class="status s-done">Active</span>
            </div>
            <div class="lbl" style="margin-top:10px;">Aviation Turbine Fuel</div>
            <div style="font-size:13.5px;color:var(--text-2);margin-top:4px;">Specialized civil aviation turbine fuel grade</div>
        </div>
    </div>

    <!-- Products Table -->
    <div class="panel" style="margin-top:20px;">
        <div class="panel-head">
            <h3>Registered Fuel Products</h3>
            <input type="text" id="prodFilter" placeholder="Filter product initials, name…" style="padding:6px 12px;border:1px solid var(--border-2);border-radius:8px;background:var(--card);font-size:13.5px;">
        </div>
        <div class="table-responsive">
            <table id="productsTable">
                <thead>
                    <tr>
                        <th>Product Initials</th>
                        <th>Full Official Name</th>
                        <th>Category</th>
                        <th>Standard Unit</th>
                        <th>Status</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;padding:32px;color:var(--text-3);">No products registered yet. Click "Add Product".</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td>
                                    <b style="font-size:16px;color:var(--brand);background:var(--brand-soft);padding:4px 10px;border-radius:8px;">
                                        <?= htmlspecialchars($p['code']) ?>
                                    </b>
                                </td>
                                <td style="font-weight:600;">
                                    <?= htmlspecialchars($p['name']) ?>
                                </td>
                                <td><?= htmlspecialchars($p['category']) ?></td>
                                <td><?= htmlspecialchars($p['unit']) ?></td>
                                <td>
                                    <span class="status <?= $p['status'] === 'Active' ? 's-done' : 's-hold' ?>">
                                        <i></i><?= htmlspecialchars($p['status']) ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <form method="POST" action="<?= url('products/delete/' . $p['id']) ?>" style="display:inline;" onsubmit="return confirm('Remove this fuel product?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--red);border-color:transparent;">✕</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Add Product Modal -->
<div class="modal-backdrop" id="productModal" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal-card" style="max-width:540px;">
        <div class="modal-head">
            <h2>⛽ Add Fuel Product</h2>
            <button class="close-modal" onclick="document.getElementById('productModal').classList.remove('active')">✕</button>
        </div>
        <form method="POST" action="<?= url('products/store') ?>">
            <?= csrf_field() ?>
            <div class="form-grid single" style="gap:14px;">
                <div class="form-group">
                    <label>Product Initials / Code *</label>
                    <input type="text" name="code" placeholder="e.g. AGO, PMS, DPK, JET A-1, HFO" required style="text-transform:uppercase;">
                </div>
                <div class="form-group">
                    <label>Full Product Description *</label>
                    <input type="text" name="name" placeholder="e.g. Automotive Gas Oil (Diesel Low Sulphur)" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" value="Fuel" placeholder="e.g. Clean Fuel, Heavy Fuel, Aviation">
                </div>
                <div class="form-group">
                    <label>Measurement Unit</label>
                    <input type="text" name="unit" value="Litres" placeholder="Litres or Metric Tonnes">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="Active" selected>Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div style="margin-top:22px;display:flex;justify-content:flex-end;gap:12px;">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('productModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-brand">Save Product</button>
            </div>
        </form>
    </div>
</div>

<script>
const prodFilter = document.getElementById('prodFilter');
if (prodFilter) {
    prodFilter.addEventListener('input', function(e) {
        const val = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('#productsTable tbody tr');
        rows.forEach(r => {
            r.style.display = r.textContent.toLowerCase().includes(val) ? '' : 'none';
        });
    });
}
</script>
<?php };
require __DIR__ . '/../layouts/app.php';
?>
