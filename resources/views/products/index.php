<?php
    $content = function () use ($title, $products, $search) {
?>
<section class="view active" id="view-products">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
        <div class="hello">
            <h1>Fuel Products Directory ⛽</h1>
            <p>Kenyan bulk petroleum specifications, transport unit pricing, and energy grades.</p>
        </div>
        <button class="btn btn-brand" onclick="document.getElementById('productModal').classList.add('active')">＋ Add Product</button>
    </div>

    <!-- Quick Info Cards for Active Bulk Kenya Fuel Grades (PMS & AGO) -->
    <div class="kpis" style="margin-top:20px;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:16px;">
        <div class="kpi" style="border:1.5px solid var(--brand);background:linear-gradient(135deg,var(--card),var(--brand-soft));">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:20px;font-weight:900;color:var(--brand);background:var(--card);padding:4px 12px;border-radius:8px;border:1px solid var(--border);">AGO</span>
                <span class="status s-done">Active Fuel</span>
            </div>
            <div class="lbl" style="margin-top:12px;font-size:16px;font-weight:800;color:var(--text);">Automotive Gas Oil (Diesel)</div>
            <div style="font-size:13px;color:var(--text-2);margin-top:4px;">Heavy low-sulphur commercial diesel for long-haul freight tankers.</div>
            <div style="margin-top:10px;font-size:14px;font-weight:800;color:var(--brand);">
                Standard Transport Payout: 
                <?php
                    $agoProd = array_values(array_filter($products, fn($p) => strtoupper($p['code']) === 'AGO'))[0] ?? null;
                    echo $agoProd ? format_money($agoProd['unit_price'] ?? 9.50) . ' / L' : 'KES 9.50 / L';
                ?>
            </div>
        </div>

        <div class="kpi" style="border:1.5px solid var(--accent);background:linear-gradient(135deg,var(--card),var(--accent-soft));">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:20px;font-weight:900;color:var(--accent);background:var(--card);padding:4px 12px;border-radius:8px;border:1px solid var(--border);">PMS</span>
                <span class="status s-done">Active Fuel</span>
            </div>
            <div class="lbl" style="margin-top:12px;font-size:16px;font-weight:800;color:var(--text);">Premium Motor Spirit (Super Petrol)</div>
            <div style="font-size:13px;color:var(--text-2);margin-top:4px;">Unleaded motor gasoline (RON 93+) for passenger vehicles and light commercial transport.</div>
            <div style="margin-top:10px;font-size:14px;font-weight:800;color:var(--accent);">
                Standard Transport Payout: 
                <?php
                    $pmsProd = array_values(array_filter($products, fn($p) => strtoupper($p['code']) === 'PMS'))[0] ?? null;
                    echo $pmsProd ? format_money($pmsProd['unit_price'] ?? 10.50) . ' / L' : 'KES 10.50 / L';
                ?>
            </div>
        </div>
    </div>

    <!-- Products Table -->
    <div class="panel" style="margin-top:24px;">
        <div class="panel-head" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
            <div>
                <h3 style="margin:0;">Registered Fuel Cargo Products</h3>
                <small style="color:var(--text-3);">Used to calculate automatic transport payout in Fleet Dispatches</small>
            </div>
            <input type="text" id="prodFilter" placeholder="Filter product initials, name…" style="padding:6px 12px;border:1.5px solid var(--border-2);border-radius:8px;background:var(--card);font-size:13.5px;min-width:240px;">
        </div>
        <div class="table-responsive">
            <table id="productsTable">
                <thead>
                    <tr>
                        <th>Product Initials</th>
                        <th>Full Official Name</th>
                        <th>Category</th>
                        <th>Standard Unit</th>
                        <th>Unit Price (Rate / L)</th>
                        <th>Status</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:32px;color:var(--text-3);">No products registered yet. Click "Add Product".</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td>
                                    <b style="font-size:16px;color:var(--brand);background:var(--brand-soft);padding:4px 10px;border-radius:8px;">
                                        <?= htmlspecialchars($p['code']) ?>
                                    </b>
                                </td>
                                <td style="font-weight:700;color:var(--text);">
                                    <?= htmlspecialchars($p['name']) ?>
                                </td>
                                <td><?= htmlspecialchars($p['category']) ?></td>
                                <td><?= htmlspecialchars($p['unit']) ?></td>
                                <td>
                                    <b style="color:var(--green);font-size:15px;background:var(--green-soft);padding:3px 8px;border-radius:6px;border:1px solid rgba(5,150,105,0.2);">
                                        <?= format_money($p['unit_price'] ?? 0) ?> / L
                                    </b>
                                </td>
                                <td>
                                    <span class="status <?= $p['status'] === 'Active' ? 's-done' : 's-hold' ?>">
                                        <i></i><?= htmlspecialchars($p['status']) ?>
                                    </span>
                                </td>
                                <td style="text-align:center;white-space:nowrap;">
                                    <div style="display:inline-flex;gap:6px;align-items:center;">
                                        <button type="button" class="btn btn-sm btn-ghost" onclick="openEditProductModal(<?= htmlspecialchars(json_encode([
                                            'id' => $p['id'],
                                            'code' => $p['code'],
                                            'name' => $p['name'],
                                            'category' => $p['category'],
                                            'unit' => $p['unit'],
                                            'unit_price' => (float)($p['unit_price'] ?? 0),
                                            'status' => $p['status'],
                                        ])) ?>)" title="Edit product and unit price">
                                            ✏️ Edit
                                        </button>
                                        <form method="POST" action="<?= url('products/delete/' . $p['id']) ?>" style="display:inline;" onsubmit="return confirm('Remove fuel product <?= htmlspecialchars(addslashes($p['code'])) ?>?');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--red);border-color:transparent;padding:4px 8px;" title="Delete Product">✕</button>
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
                    <input type="text" name="code" placeholder="e.g. PMS, AGO" required style="text-transform:uppercase;">
                </div>
                <div class="form-group">
                    <label>Full Product Description *</label>
                    <input type="text" name="name" placeholder="e.g. Automotive Gas Oil (Diesel Low Sulphur)" required>
                </div>
                <div class="form-group">
                    <label>Unit Price (Expected Transport Rate per Litre) [<?= app_currency_symbol() ?>] *</label>
                    <input type="number" step="0.01" name="unit_price" placeholder="e.g. 9.50" required>
                    <small style="color:var(--text-3);">Used to calculate automatic transport payout in fleet dispatches (Litres × Unit Price)</small>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" value="Fuel" placeholder="e.g. Clean Fuel, Heavy Fuel">
                </div>
                <div class="form-group">
                    <label>Measurement Unit</label>
                    <input type="text" name="unit" value="Litres" placeholder="Litres">
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

<!-- Edit Product Modal -->
<div class="modal-backdrop" id="editProductModal" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal-card" style="max-width:540px;">
        <div class="modal-head">
            <h2>✏️ Edit Fuel Product & Unit Price</h2>
            <button class="close-modal" onclick="document.getElementById('editProductModal').classList.remove('active')">✕</button>
        </div>
        <form method="POST" action="<?= url('products/update') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="editProdId">
            <div class="form-grid single" style="gap:14px;">
                <div class="form-group">
                    <label>Product Initials / Code *</label>
                    <input type="text" name="code" id="editProdCode" required style="text-transform:uppercase;">
                </div>
                <div class="form-group">
                    <label>Full Product Description *</label>
                    <input type="text" name="name" id="editProdName" required>
                </div>
                <div class="form-group">
                    <label>Unit Price (Expected Transport Rate per Litre) [<?= app_currency_symbol() ?>] *</label>
                    <input type="number" step="0.01" name="unit_price" id="editProdUnitPrice" required>
                    <small style="color:var(--text-3);">Used to calculate automatic transport payout in fleet dispatches (Litres × Unit Price)</small>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" id="editProdCategory" value="Fuel">
                </div>
                <div class="form-group">
                    <label>Measurement Unit</label>
                    <input type="text" name="unit" id="editProdUnit" value="Litres">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="editProdStatus">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div style="margin-top:22px;display:flex;justify-content:flex-end;gap:12px;">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('editProductModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-brand">✓ Update Product</button>
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

function openEditProductModal(prod) {
    document.getElementById('editProdId').value = prod.id;
    document.getElementById('editProdCode').value = prod.code;
    document.getElementById('editProdName').value = prod.name;
    document.getElementById('editProdUnitPrice').value = prod.unit_price || 0;
    document.getElementById('editProdCategory').value = prod.category || 'Fuel';
    document.getElementById('editProdUnit').value = prod.unit || 'Litres';
    document.getElementById('editProdStatus').value = prod.status || 'Active';
    document.getElementById('editProductModal').classList.add('active');
}
</script>
<?php };
require __DIR__ . '/../layouts/app.php';
?>
