<?php
$content = function() use ($title, $customTables, $systemTables) {
    $flashSuccess = flash('schema_success') ?? flash('custom_success');
    $flashError = flash('schema_error') ?? flash('custom_error');
?>
<section class="view active" id="view-custom-tables">
    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;margin-bottom:20px;">
        <div>
            <h1>Custom Tables & Datasets Hub 📑</h1>
            <p>Create new business tables from scratch, customize columns, manage schemas, or reset table data.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn btn-brand" onclick="document.getElementById('createTableModal').classList.add('active')">
                ＋ Create New Custom Table
            </button>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if ($flashSuccess): ?>
        <div style="background:var(--green-soft);border:1px solid var(--green);color:var(--green);padding:14px 18px;border-radius:12px;margin-bottom:20px;font-weight:600;">
            ✓ <?= htmlspecialchars($flashSuccess) ?>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div style="background:var(--red-soft);border:1px solid var(--red);color:var(--red);padding:14px 18px;border-radius:12px;margin-bottom:20px;font-weight:600;">
            ⚠️ <?= htmlspecialchars($flashError) ?>
        </div>
    <?php endif; ?>

    <!-- User Custom Tables Section -->
    <div style="margin-bottom:32px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
            <h2 style="font-size:18px;margin:0;display:flex;align-items:center;gap:8px;">
                <span>User-Created Custom Tables</span>
                <span style="font-size:12.5px;background:var(--brand-soft);color:var(--brand);padding:2px 8px;border-radius:6px;font-weight:800;">
                    <?= count($customTables) ?>
                </span>
            </h2>
        </div>

        <?php if (empty($customTables)): ?>
            <div style="background:var(--card);border:1.5px dashed var(--border-2);border-radius:16px;padding:40px;text-align:center;">
                <div style="font-size:42px;margin-bottom:12px;">📑</div>
                <h3 style="margin:0 0 6px 0;font-size:17px;color:var(--text);">No Custom Tables Created Yet</h3>
                <p style="color:var(--text-3);font-size:14px;max-width:500px;margin:0 auto 18px auto;">
                    You can create any new table with custom columns (e.g., Tyre Inventory, Border Permits, Station Dips, Equipment, Supplier Invoices).
                </p>
                <button class="btn btn-brand" onclick="document.getElementById('createTableModal').classList.add('active')">
                    ＋ Create Your First Custom Table
                </button>
            </div>
        <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:16px;">
                <?php foreach ($customTables as $ct): ?>
                    <div style="background:var(--card);border:1.5px solid var(--border);border-radius:16px;padding:20px;display:flex;flex-direction:column;justify-content:space-between;box-shadow:var(--shadow-sm);transition:all .2s ease;" onmouseover="this.style.borderColor='var(--brand)'" onmouseout="this.style.borderColor='var(--border)'">
                        <div>
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
                                <span style="font-size:28px;"><?= htmlspecialchars($ct['icon'] ?: '📋') ?></span>
                                <span style="font-size:11.5px;padding:3px 8px;border-radius:6px;background:var(--card-2);border:1px solid var(--border);color:var(--text-3);font-family:monospace;">
                                    <?= htmlspecialchars($ct['table_key']) ?>
                                </span>
                            </div>
                            <h3 style="margin:0 0 6px 0;font-size:17px;color:var(--text);">
                                <?= htmlspecialchars($ct['display_name']) ?>
                            </h3>
                            <p style="margin:0 0 14px 0;font-size:13px;color:var(--text-3);line-height:1.4;">
                                <?= htmlspecialchars($ct['description'] ?: 'Custom dataset table.') ?>
                            </p>
                        </div>

                        <div>
                            <div style="display:flex;gap:16px;font-size:12.5px;color:var(--text-2);padding:10px 0;border-top:1px solid var(--border);margin-bottom:12px;">
                                <div><b><?= $ct['row_count'] ?></b> records</div>
                                <div><b><?= $ct['col_count'] ?></b> columns</div>
                                <div style="color:var(--text-3);margin-left:auto;">Created <?= substr($ct['created_at'], 0, 10) ?></div>
                            </div>
                            <div style="display:flex;gap:8px;">
                                <a href="<?= url('custom-tables/' . $ct['table_key']) ?>" class="btn btn-brand btn-sm" style="flex:1;text-align:center;font-size:13px;">
                                    Open Table ➔
                                </a>
                                <a href="<?= url('custom-tables/' . $ct['table_key'] . '/export?format=xlsx') ?>" download class="btn btn-ghost btn-sm" title="Export Excel">
                                    📊
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Core System Tables Section -->
    <div>
        <div style="margin-bottom:14px;">
            <h2 style="font-size:18px;margin:0;display:flex;align-items:center;gap:8px;">
                <span>Core System Logistics Tables</span>
                <span style="font-size:12px;background:var(--card-2);color:var(--text-3);padding:2px 8px;border-radius:6px;">
                    Manageable Schemas & Columns
                </span>
            </h2>
            <p style="margin:4px 0 0 0;font-size:13px;color:var(--text-3);">
                You can rename headers, hide/show columns, or add new custom attributes to any built-in table.
            </p>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(300px, 1fr));gap:16px;">
            <?php foreach ($systemTables as $st): ?>
                <div style="background:var(--card);border:1px solid var(--border);border-radius:14px;padding:18px;display:flex;flex-direction:column;justify-content:space-between;">
                    <div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                            <span style="font-size:24px;"><?= $st['icon'] ?></span>
                            <span style="font-size:11.5px;color:var(--text-3);font-family:monospace;">
                                <?= htmlspecialchars($st['table_key']) ?>
                            </span>
                        </div>
                        <h3 style="margin:0 0 6px 0;font-size:16px;color:var(--text);">
                            <?= htmlspecialchars($st['display_name']) ?>
                        </h3>
                        <div style="font-size:12.5px;color:var(--text-3);margin-bottom:12px;">
                            <b><?= $st['row_count'] ?></b> rows · <b><?= $st['col_count'] ?></b> columns
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <a href="<?= url($st['url']) ?>" class="btn btn-ghost btn-sm" style="flex:1;text-align:center;">
                            View Table
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Modal: Create New Custom Table -->
<div class="modal-backdrop" id="createTableModal" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal-card" style="max-width:640px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;">
        <div class="modal-head" style="border-bottom:1px solid var(--border);padding-bottom:14px;">
            <h2>＋ Create New Custom Table</h2>
            <button class="close-modal" onclick="document.getElementById('createTableModal').classList.remove('active')">✕</button>
        </div>

        <form method="POST" action="<?= url('schema/table/create') ?>" style="overflow-y:auto;flex:1;padding-right:4px;">
            <?= csrf_field() ?>

            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">Table Display Name *</label>
                <input type="text" name="display_name" required placeholder="e.g. Tyre Inventory & Serial Numbers" class="form-control" style="width:100%;padding:10px;border-radius:8px;">
            </div>

            <div style="display:grid;grid-template-columns:80px 1fr;gap:12px;margin-bottom:16px;">
                <div>
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">Icon</label>
                    <input type="text" name="icon" value="📋" class="form-control" style="width:100%;padding:10px;text-align:center;font-size:18px;border-radius:8px;">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">Short Description</label>
                    <input type="text" name="description" placeholder="e.g. Log tire positions, pressure, and replacement dates" class="form-control" style="width:100%;padding:10px;border-radius:8px;">
                </div>
            </div>

            <!-- Initial Columns Builder -->
            <div style="border:1px solid var(--border);border-radius:12px;padding:16px;background:var(--card-2);margin-bottom:20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <b style="font-size:14px;color:var(--text);">Define Table Columns</b>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="addColRow()" style="font-size:12px;">
                        ＋ Add Column
                    </button>
                </div>

                <div id="colRowsContainer" style="display:flex;flex-direction:column;gap:10px;">
                    <!-- Default Initial Column Rows -->
                    <div class="col-row" style="display:grid;grid-template-columns:1fr 140px 36px;gap:8px;align-items:center;">
                        <input type="text" name="col_labels[]" value="Item / Serial Reference" placeholder="Column Title" required class="form-control" style="padding:8px 10px;border-radius:6px;font-size:13px;">
                        <select name="col_types[]" class="form-control" style="padding:8px;border-radius:6px;font-size:13px;background:var(--card);">
                            <option value="text" selected>Text</option>
                            <option value="number">Number</option>
                            <option value="currency">Currency</option>
                            <option value="date">Date</option>
                            <option value="status">Status</option>
                        </select>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="this.parentElement.remove()" style="color:var(--red);padding:6px;">✕</button>
                    </div>

                    <div class="col-row" style="display:grid;grid-template-columns:1fr 140px 36px;gap:8px;align-items:center;">
                        <input type="text" name="col_labels[]" value="Date Logged" placeholder="Column Title" required class="form-control" style="padding:8px 10px;border-radius:6px;font-size:13px;">
                        <select name="col_types[]" class="form-control" style="padding:8px;border-radius:6px;font-size:13px;background:var(--card);">
                            <option value="text">Text</option>
                            <option value="number">Number</option>
                            <option value="currency">Currency</option>
                            <option value="date" selected>Date</option>
                            <option value="status">Status</option>
                        </select>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="this.parentElement.remove()" style="color:var(--red);padding:6px;">✕</button>
                    </div>

                    <div class="col-row" style="display:grid;grid-template-columns:1fr 140px 36px;gap:8px;align-items:center;">
                        <input type="text" name="col_labels[]" value="Category / Type" placeholder="Column Title" required class="form-control" style="padding:8px 10px;border-radius:6px;font-size:13px;">
                        <select name="col_types[]" class="form-control" style="padding:8px;border-radius:6px;font-size:13px;background:var(--card);">
                            <option value="text">Text</option>
                            <option value="number">Number</option>
                            <option value="currency">Currency</option>
                            <option value="date">Date</option>
                            <option value="status" selected>Status</option>
                        </select>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="this.parentElement.remove()" style="color:var(--red);padding:6px;">✕</button>
                    </div>

                    <div class="col-row" style="display:grid;grid-template-columns:1fr 140px 36px;gap:8px;align-items:center;">
                        <input type="text" name="col_labels[]" value="Amount / Quantity" placeholder="Column Title" required class="form-control" style="padding:8px 10px;border-radius:6px;font-size:13px;">
                        <select name="col_types[]" class="form-control" style="padding:8px;border-radius:6px;font-size:13px;background:var(--card);">
                            <option value="text">Text</option>
                            <option value="number" selected>Number</option>
                            <option value="currency">Currency</option>
                            <option value="date">Date</option>
                            <option value="status">Status</option>
                        </select>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="this.parentElement.remove()" style="color:var(--red);padding:6px;">✕</button>
                    </div>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('createTableModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-brand">🚀 Create Table & Schema</button>
            </div>
        </form>
    </div>
</div>

<script>
function addColRow() {
    const container = document.getElementById('colRowsContainer');
    const div = document.createElement('div');
    div.className = 'col-row';
    div.style = 'display:grid;grid-template-columns:1fr 140px 36px;gap:8px;align-items:center;';
    div.innerHTML = `
        <input type="text" name="col_labels[]" placeholder="Column Title" required class="form-control" style="padding:8px 10px;border-radius:6px;font-size:13px;">
        <select name="col_types[]" class="form-control" style="padding:8px;border-radius:6px;font-size:13px;background:var(--card);">
            <option value="text" selected>Text</option>
            <option value="number">Number</option>
            <option value="currency">Currency</option>
            <option value="date">Date</option>
            <option value="status">Status</option>
        </select>
        <button type="button" class="btn btn-ghost btn-sm" onclick="this.parentElement.remove()" style="color:var(--red);padding:6px;">✕</button>
    `;
    container.appendChild(div);
}
</script>
<?php
};
return require __DIR__ . '/../layouts/app.php';
