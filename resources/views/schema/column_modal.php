<?php
/**
 * Reusable Column & Schema Manager Modal
 * Parameters required:
 * - $tableName: string (e.g. 'fleet_dispatches', 'trucks', 'custom_tyre_log')
 * - $tableDisplayName: string (e.g. 'Fleet Management')
 * - $redirectTo: string (URL to redirect back to)
 * - $isCustomTable: bool (true if table is a custom-created table)
 */
$allColumns = \App\Services\TableSchemaService::getTableColumns($tableName, false);
?>
<div class="modal-backdrop" id="columnManagerModal" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal-card" style="max-width:760px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;">
        <div class="modal-head" style="border-bottom:1px solid var(--border);padding-bottom:14px;margin-bottom:0;">
            <div>
                <h2 style="font-size:20px;display:flex;align-items:center;gap:8px;">
                    <span>⚙️ Manage Columns & Schema</span>
                    <span style="font-size:13px;padding:2px 8px;background:var(--brand-soft);color:var(--brand);border-radius:6px;font-weight:700;">
                        <?= htmlspecialchars($tableDisplayName) ?>
                    </span>
                </h2>
                <p style="margin:4px 0 0 0;font-size:13px;color:var(--text-3);">
                    Rename column headers, toggle column visibility (show/hide), add custom columns, or clear table data.
                </p>
            </div>
            <button class="close-modal" onclick="document.getElementById('columnManagerModal').classList.remove('active')">✕</button>
        </div>

        <!-- Tab Navigation -->
        <div style="display:flex;gap:8px;border-bottom:1px solid var(--border);padding:10px 0;margin-bottom:14px;background:var(--card-2);">
            <button type="button" class="btn btn-ghost btn-sm cm-tab-btn active" onclick="switchCmTab('cm-tab-columns', this)">
                📋 Columns & Visibility (<?= count($allColumns) ?>)
            </button>
            <button type="button" class="btn btn-ghost btn-sm cm-tab-btn" onclick="switchCmTab('cm-tab-add', this)">
                ＋ Add New Column
            </button>
            <button type="button" class="btn btn-ghost btn-sm cm-tab-btn" style="color:var(--red);" onclick="switchCmTab('cm-tab-danger', this)">
                ⚠️ Table Reset & Danger Zone
            </button>
        </div>

        <div style="overflow-y:auto;flex:1;padding-right:4px;">
            <!-- TAB 1: Columns & Visibility -->
            <div id="cm-tab-columns" class="cm-tab-pane">
                <form method="POST" action="<?= url('schema/columns/update') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="table_name" value="<?= htmlspecialchars($tableName) ?>">
                    <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($redirectTo) ?>">

                    <div style="margin-bottom:12px;font-size:13px;color:var(--text-2);">
                        Edit any column display title or uncheck to hide it from your table view and exports.
                    </div>

                    <div style="border:1px solid var(--border);border-radius:10px;overflow:hidden;">
                        <table style="width:100%;border-collapse:collapse;font-size:13.5px;">
                            <thead style="background:var(--card-2);border-bottom:1px solid var(--border);">
                                <tr>
                                    <th style="padding:8px 12px;text-align:center;width:60px;">Visible</th>
                                    <th style="padding:8px 12px;text-align:left;">Column Header (Title)</th>
                                    <th style="padding:8px 12px;text-align:left;width:120px;">Data Type</th>
                                    <th style="padding:8px 12px;text-align:left;width:90px;">Type</th>
                                    <th style="padding:8px 12px;text-align:center;width:70px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allColumns as $idx => $col): ?>
                                    <tr style="border-bottom:1px solid var(--border);background:<?= $idx % 2 === 0 ? 'transparent' : 'rgba(255,255,255,0.015)' ?>;">
                                        <td style="text-align:center;padding:8px 12px;">
                                            <input type="hidden" name="columns[<?= $idx ?>][key]" value="<?= htmlspecialchars($col['column_key']) ?>">
                                            <input type="hidden" name="columns[<?= $idx ?>][order]" value="<?= (int)$col['sort_order'] ?>">
                                            <input type="checkbox" name="columns[<?= $idx ?>][visible]" value="1" <?= !empty($col['is_visible']) ? 'checked' : '' ?> style="width:17px;height:17px;cursor:pointer;">
                                        </td>
                                        <td style="padding:8px 12px;">
                                            <input type="text" name="columns[<?= $idx ?>][label]" value="<?= htmlspecialchars($col['display_label']) ?>" class="form-control" style="font-size:13.5px;padding:6px 10px;border-radius:6px;width:100%;" required>
                                            <div style="font-size:11px;color:var(--text-3);margin-top:2px;">Key: <code><?= htmlspecialchars($col['column_key']) ?></code></div>
                                        </td>
                                        <td style="padding:8px 12px;">
                                            <span style="font-size:12px;padding:2px 6px;border-radius:4px;background:var(--card-2);border:1px solid var(--border);text-transform:capitalize;">
                                                <?= htmlspecialchars($col['data_type']) ?>
                                            </span>
                                        </td>
                                        <td style="padding:8px 12px;">
                                            <?php if (!empty($col['is_custom'])): ?>
                                                <span style="font-size:11px;font-weight:700;color:var(--brand);background:var(--brand-soft);padding:2px 6px;border-radius:4px;">Custom</span>
                                            <?php else: ?>
                                                <span style="font-size:11px;font-weight:700;color:var(--text-3);background:var(--card-2);padding:2px 6px;border-radius:4px;">System</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:center;padding:8px 12px;">
                                            <?php if (!empty($col['is_custom'])): ?>
                                                <button type="button" class="btn btn-ghost btn-sm" style="color:var(--red);padding:4px 8px;font-size:12px;" onclick="confirmDeleteCol('<?= htmlspecialchars($col['column_key']) ?>', '<?= htmlspecialchars($col['display_label']) ?>')">
                                                    🗑️
                                                </button>
                                            <?php else: ?>
                                                <span style="color:var(--text-3);font-size:12px;" title="System columns cannot be deleted, but you can hide them">🔒</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
                        <button type="button" class="btn btn-ghost" onclick="document.getElementById('columnManagerModal').classList.remove('active')">Cancel</button>
                        <button type="submit" class="btn btn-brand">💾 Save Column Changes</button>
                    </div>
                </form>
            </div>

            <!-- TAB 2: Add New Column -->
            <div id="cm-tab-add" class="cm-tab-pane" style="display:none;">
                <form method="POST" action="<?= url('schema/columns/add') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="table_name" value="<?= htmlspecialchars($tableName) ?>">
                    <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($redirectTo) ?>">

                    <div style="background:var(--card-2);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:16px;">
                        <h3 style="margin-top:0;font-size:16px;color:var(--text);">＋ Add Custom Column to <?= htmlspecialchars($tableDisplayName) ?></h3>
                        <p style="font-size:13px;color:var(--text-2);margin-bottom:16px;">
                            The new column will immediately be added to the database table, rendered in your view, editable, and included in spreadsheet exports & imports.
                        </p>

                        <div class="form-group" style="margin-bottom:14px;">
                            <label style="font-size:13px;font-weight:700;display:block;margin-bottom:6px;">Column Title (e.g. Trailer Number, Inspector, Fuel Density) *</label>
                            <input type="text" name="column_label" required placeholder="e.g. Trailer Number" class="form-control" style="width:100%;padding:10px;border-radius:8px;">
                        </div>

                        <div class="form-group" style="margin-bottom:14px;">
                            <label style="font-size:13px;font-weight:700;display:block;margin-bottom:6px;">Data Type *</label>
                            <select name="data_type" class="form-control" style="width:100%;padding:10px;border-radius:8px;background:var(--card);">
                                <option value="text">Text (General string, names, remarks)</option>
                                <option value="number">Number (Quantity, litres, mileage)</option>
                                <option value="currency">Currency (Financial amount, rates)</option>
                                <option value="date">Date (Calendar date YYYY-MM-DD)</option>
                                <option value="status">Status / Badge (Selection, category)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label style="font-size:13px;font-weight:700;display:block;margin-bottom:6px;">Default Value (Optional)</label>
                            <input type="text" name="default_value" placeholder="e.g. N/A or 0" class="form-control" style="width:100%;padding:10px;border-radius:8px;">
                        </div>
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:10px;">
                        <button type="button" class="btn btn-ghost" onclick="switchCmTab('cm-tab-columns', document.querySelector('.cm-tab-btn'))">Back to Columns</button>
                        <button type="submit" class="btn btn-brand">＋ Add Column</button>
                    </div>
                </form>
            </div>

            <!-- TAB 3: Table Reset & Danger Zone -->
            <div id="cm-tab-danger" class="cm-tab-pane" style="display:none;">
                <div style="border:1.5px solid var(--red);border-radius:12px;padding:20px;background:rgba(239,68,68,0.05);margin-bottom:20px;">
                    <h3 style="margin-top:0;color:var(--red);font-size:16px;">⚠️ Clear All Table Records (Reset Table)</h3>
                    <p style="font-size:13px;color:var(--text-2);line-height:1.5;">
                        This will permanently delete <b>all rows</b> in <code><?= htmlspecialchars($tableName) ?></code> so you can start completely empty and fresh. All column configurations and custom fields remain intact.
                    </p>

                    <form method="POST" action="<?= url('schema/table/clear') ?>" onsubmit="return confirmTableClear('<?= htmlspecialchars($tableName) ?>')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="table_name" value="<?= htmlspecialchars($tableName) ?>">
                        <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($redirectTo) ?>">

                        <div style="margin-bottom:14px;">
                            <label style="font-size:13px;font-weight:700;display:block;margin-bottom:6px;color:var(--text);">
                                Type table name <code style="color:var(--red);"><?= htmlspecialchars($tableName) ?></code> to confirm:
                            </label>
                            <input type="text" id="clearTableConfirmInput" name="confirm_table_name" required placeholder="<?= htmlspecialchars($tableName) ?>" class="form-control" style="width:100%;max-width:320px;padding:8px 12px;border-radius:8px;border-color:var(--red);">
                        </div>

                        <button type="submit" class="btn" style="background:var(--red);color:#fff;font-weight:700;">
                            🗑️ Empty All Records in <?= htmlspecialchars($tableDisplayName) ?>
                        </button>
                    </form>
                </div>

                <?php if (!empty($isCustomTable)): ?>
                    <div style="border:1.5px solid #7F1D1D;border-radius:12px;padding:20px;background:rgba(127,29,29,0.1);">
                        <h3 style="margin-top:0;color:var(--red);font-size:16px;">🚨 Delete Entire Custom Table</h3>
                        <p style="font-size:13px;color:var(--text-2);line-height:1.5;">
                            Permanently drop <code><?= htmlspecialchars($tableName) ?></code> and remove it completely from the database and menu. This action cannot be undone.
                        </p>

                        <form method="POST" action="<?= url('schema/table/delete') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="table_key" value="<?= htmlspecialchars($tableName) ?>">

                            <div style="margin-bottom:14px;">
                                <label style="font-size:13px;font-weight:700;display:block;margin-bottom:6px;color:var(--text);">
                                    Type <code style="color:var(--red);"><?= htmlspecialchars($tableName) ?></code> to delete this table:
                                </label>
                                <input type="text" name="confirm_table_key" required placeholder="<?= htmlspecialchars($tableName) ?>" class="form-control" style="width:100%;max-width:320px;padding:8px 12px;border-radius:8px;border-color:var(--red);">
                            </div>

                            <button type="submit" class="btn" style="background:#7F1D1D;color:#fff;font-weight:800;">
                                🚨 Permanently Drop & Delete Table
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form for deleting a single custom column -->
<form id="deleteColForm" method="POST" action="<?= url('schema/columns/delete') ?>" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="table_name" value="<?= htmlspecialchars($tableName) ?>">
    <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($redirectTo) ?>">
    <input type="hidden" name="column_key" id="deleteColKey" value="">
</form>

<script>
function switchCmTab(tabId, btn) {
    document.querySelectorAll('.cm-tab-pane').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.cm-tab-btn').forEach(b => b.classList.remove('active'));
    const target = document.getElementById(tabId);
    if (target) target.style.display = 'block';
    if (btn) btn.classList.add('active');
}

function confirmDeleteCol(key, label) {
    if (confirm(`Are you sure you want to permanently delete custom column "${label}" (${key}) and all its stored data?`)) {
        document.getElementById('deleteColKey').value = key;
        document.getElementById('deleteColForm').submit();
    }
}

function confirmTableClear(tableName) {
    const input = document.getElementById('clearTableConfirmInput').value.trim();
    if (input.toLowerCase() !== tableName.toLowerCase()) {
        alert(`You must type "${tableName}" exactly to confirm clearing all table records.`);
        return false;
    }
    return confirm(`CRITICAL: You are about to permanently delete all records from ${tableName}. Are you absolutely certain?`);
}
</script>
