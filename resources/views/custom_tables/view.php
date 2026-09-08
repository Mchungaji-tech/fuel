<?php
$content = function() use ($title, $tableInfo, $columns, $visibleColumns, $records, $search) {
    $tableKey = $tableInfo['table_key'];
    $tableName = $tableKey;
    $tableDisplayName = $tableInfo['display_name'];
    $redirectTo = '/custom-tables/' . $tableKey;
    $isCustomTable = true;

    $flashSuccess = flash('custom_success') ?? flash('schema_success');
    $flashError = flash('custom_error') ?? flash('schema_error');
?>
<section class="view active" id="view-custom-table-detail">
    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;margin-bottom:20px;">
        <div class="hello">
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:32px;"><?= htmlspecialchars($tableInfo['icon'] ?: '📋') ?></span>
                <div>
                    <h1 style="margin:0;font-size:24px;"><?= htmlspecialchars($tableInfo['display_name']) ?></h1>
                    <p style="margin:4px 0 0 0;font-size:13.5px;color:var(--text-3);"><?= htmlspecialchars($tableInfo['description'] ?: 'Custom dataset table.') ?></p>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <a href="<?= url('custom-tables') ?>" class="btn btn-ghost" title="Back to All Tables">⬅️ All Tables</a>

            <button class="btn btn-ghost" onclick="document.getElementById('columnManagerModal').classList.add('active')" title="Customize column labels, show/hide columns, or clear table">
                ⚙️ Columns & Schema
            </button>

            <button class="btn btn-ghost" onclick="document.getElementById('customImportModal').classList.add('active')">
                📥 Import
            </button>

            <!-- Multi-Format Export Dropdown -->
            <div style="display:inline-flex;border-radius:10px;overflow:hidden;border:1.5px solid var(--border-2);box-shadow:var(--shadow-sm);">
                <a href="<?= url('custom-tables/' . $tableKey . '/export?format=xlsx') ?>" download class="btn btn-ghost" style="border-radius:0;border:0;background:var(--card);font-weight:700;padding:8px 12px;" title="Export Excel (.xlsx)">
                    📊 .xlsx
                </a>
                <a href="<?= url('custom-tables/' . $tableKey . '/export?format=xls') ?>" download class="btn btn-ghost" style="border-radius:0;border:0;border-left:1px solid var(--border);padding:8px 10px;font-size:12.5px;" title="Export Universal Excel (.xls)">
                    📗 .xls
                </a>
                <a href="<?= url('custom-tables/' . $tableKey . '/export?format=csv') ?>" download class="btn btn-ghost" style="border-radius:0;border:0;border-left:1px solid var(--border);padding:8px 10px;font-size:12.5px;" title="Export CSV (.csv)">
                    📄 CSV
                </a>
            </div>

            <button class="btn btn-brand" onclick="document.getElementById('addRecordModal').classList.add('active')">
                ＋ Add Record
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

    <!-- Filter & Search Toolbar -->
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
        <form method="GET" action="" style="display:flex;gap:10px;align-items:center;flex:1;max-width:480px;">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search <?= htmlspecialchars($tableInfo['display_name']) ?>..." class="form-control" style="padding:9px 14px;border-radius:10px;width:100%;">
            <button type="submit" class="btn btn-ghost" style="padding:9px 16px;">Search</button>
            <?php if ($search !== ''): ?>
                <a href="<?= url('custom-tables/' . $tableKey) ?>" class="btn btn-ghost" style="padding:9px 12px;">✕ Clear</a>
            <?php endif; ?>
        </form>

        <div style="font-size:13px;color:var(--text-3);font-weight:700;">
            <b><?= count($records) ?></b> records in this dataset
        </div>
    </div>

    <!-- Data Table -->
    <div class="panel">
        <div class="table-responsive">
            <table id="customDataTable">
                <thead>
                    <tr>
                        <th style="width:50px;text-align:center;">#</th>
                        <?php foreach ($visibleColumns as $col): ?>
                            <th style="white-space:nowrap;">
                                <?= htmlspecialchars($col['display_label']) ?>
                            </th>
                        <?php endforeach; ?>
                        <th style="width:100px;text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="<?= count($visibleColumns) + 2 ?>" style="text-align:center;padding:50px;color:var(--text-3);">
                                <div style="font-size:32px;margin-bottom:10px;">📋</div>
                                No records found in this dataset. Click "＋ Add Record" or "📥 Import" to add rows.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $row): ?>
                            <tr id="row-<?= $row['id'] ?>">
                                <td style="text-align:center;color:var(--text-3);font-size:12px;font-weight:700;">
                                    <?= $row['id'] ?>
                                </td>
                                <?php foreach ($visibleColumns as $col): ?>
                                    <?php
                                        $k = $col['column_key'];
                                        $val = $row[$k] ?? '';
                                        $type = $col['data_type'];
                                    ?>
                                    <td class="editable-cell" 
                                        data-id="<?= $row['id'] ?>" 
                                        data-field="<?= htmlspecialchars($k) ?>" 
                                        data-type="<?= htmlspecialchars($type) ?>"
                                        title="Click to inline edit">
                                        <span class="cell-display">
                                            <?php if ($type === 'currency'): ?>
                                                <b><?= app_currency_symbol() ?> <?= is_numeric($val) ? number_format((float)$val, 2) : htmlspecialchars($val) ?></b>
                                            <?php elseif ($type === 'number'): ?>
                                                <b><?= is_numeric($val) ? number_format((float)$val) : htmlspecialchars($val) ?></b>
                                            <?php elseif ($type === 'date'): ?>
                                                <span style="font-family:monospace;"><?= htmlspecialchars($val) ?></span>
                                            <?php elseif ($type === 'status'): ?>
                                                <span class="status s-done" style="font-size:12px;">
                                                    <i></i><?= htmlspecialchars($val ?: 'Active') ?>
                                                </span>
                                            <?php else: ?>
                                                <?= htmlspecialchars($val ?: '—') ?>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                <?php endforeach; ?>
                                <td style="text-align:center;white-space:nowrap;">
                                    <form method="POST" action="<?= url('custom-tables/' . $tableKey . '/delete/' . $row['id']) ?>" style="display:inline;" onsubmit="return confirm('Delete record #<?= $row['id'] ?>?')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red);padding:4px 8px;font-size:12px;">
                                            🗑️
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div id="customTablePagination" class="table-pagination-footer"></div>
    </div>
</section>

<!-- Modal: Add Record -->
<div class="modal-backdrop" id="addRecordModal" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal-card" style="max-width:600px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;">
        <div class="modal-head" style="border-bottom:1px solid var(--border);padding-bottom:14px;">
            <h2>＋ Add Record to <?= htmlspecialchars($tableInfo['display_name']) ?></h2>
            <button class="close-modal" onclick="document.getElementById('addRecordModal').classList.remove('active')">✕</button>
        </div>

        <form method="POST" action="<?= url('custom-tables/' . $tableKey . '/store') ?>" style="overflow-y:auto;flex:1;padding-right:4px;">
            <?= csrf_field() ?>

            <div style="display:grid;grid-template-columns:1fr;gap:14px;margin-bottom:20px;">
                <?php foreach ($columns as $col): ?>
                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">
                            <?= htmlspecialchars($col['display_label']) ?>
                            <small style="color:var(--text-3);">(<?= htmlspecialchars($col['data_type']) ?>)</small>
                        </label>
                        <?php if ($col['data_type'] === 'date'): ?>
                            <input type="date" name="<?= htmlspecialchars($col['column_key']) ?>" value="<?= date('Y-m-d') ?>" class="form-control" style="width:100%;padding:10px;border-radius:8px;">
                        <?php elseif ($col['data_type'] === 'number' || $col['data_type'] === 'currency'): ?>
                            <input type="number" step="any" name="<?= htmlspecialchars($col['column_key']) ?>" placeholder="0.00" class="form-control" style="width:100%;padding:10px;border-radius:8px;">
                        <?php else: ?>
                            <input type="text" name="<?= htmlspecialchars($col['column_key']) ?>" placeholder="Enter <?= strtolower(htmlspecialchars($col['display_label'])) ?>..." class="form-control" style="width:100%;padding:10px;border-radius:8px;">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('addRecordModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-brand">Save Record</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Import Spreadsheet -->
<div class="modal-backdrop" id="customImportModal" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal-card" style="max-width:540px;">
        <div class="modal-head" style="border-bottom:1px solid var(--border);padding-bottom:14px;">
            <h2>📥 Import Spreadsheet to <?= htmlspecialchars($tableInfo['display_name']) ?></h2>
            <button class="close-modal" onclick="document.getElementById('customImportModal').classList.remove('active')">✕</button>
        </div>

        <p style="font-size:13.5px;color:var(--text-2);margin-top:10px;">
            Upload your Excel workbook (<b>.xlsx</b>, <b>.xls</b>) or <b>.csv</b> file. Rows will be appended to this dataset.
        </p>

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
            <a href="<?= url('custom-tables/' . $tableKey . '/template?format=xlsx') ?>" download class="btn btn-ghost btn-sm" style="font-size:12.5px;font-weight:700;">
                📊 Download .xlsx Template
            </a>
            <a href="<?= url('custom-tables/' . $tableKey . '/template?format=xls') ?>" download class="btn btn-ghost btn-sm" style="font-size:12.5px;">
                📗 Download .xls Template
            </a>
            <a href="<?= url('custom-tables/' . $tableKey . '/template?format=csv') ?>" download class="btn btn-ghost btn-sm" style="font-size:12.5px;">
                📄 Download .csv Template
            </a>
        </div>

        <form method="POST" action="<?= url('custom-tables/' . $tableKey . '/import') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="form-group" style="margin-bottom:20px;">
                <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">Select File (.xlsx, .xls, .csv) *</label>
                <input type="file" name="spreadsheet_file" accept=".xlsx,.xls,.csv" required style="padding:10px;width:100%;border-radius:8px;background:var(--card-2);border:1px solid var(--border);">
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('customImportModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-brand">Upload & Import</button>
            </div>
        </form>
    </div>
</div>

<!-- Embed Reusable Column Manager Modal -->
<?php require __DIR__ . '/../schema/column_modal.php'; ?>

<script>
// Initialize universal client-side pagination
document.addEventListener('DOMContentLoaded', () => {
    if (typeof initTablePagination === 'function') {
        initTablePagination({
            tableId: 'customDataTable',
            footerId: 'customTablePagination',
            defaultPageSize: 10,
            pageSizes: [10, 25, 50, 100],
            rowSelector: '#customDataTable tbody tr[id]'
        });
    }

    // Inline cell editing
    document.querySelectorAll('.editable-cell').forEach(cell => {
        cell.addEventListener('dblclick', function() {
            if (this.querySelector('input')) return;
            const currentVal = this.innerText.trim();
            const id = this.dataset.id;
            const field = this.dataset.field;
            const type = this.dataset.type;

            const input = document.createElement('input');
            input.type = (type === 'number' || type === 'currency') ? 'number' : (type === 'date' ? 'date' : 'text');
            if (type === 'number' || type === 'currency') input.step = 'any';
            input.value = currentVal === '—' ? '' : currentVal.replace(/[^0-9.-]/g, '');
            input.className = 'form-control';
            input.style = 'width:100%;padding:4px 8px;font-size:13px;border-radius:4px;';

            this.innerHTML = '';
            this.appendChild(input);
            input.focus();

            const saveChange = () => {
                const newVal = input.value.trim();
                const formData = new FormData();
                formData.append('id', id);
                formData.append('field', field);
                formData.append('value', newVal);
                formData.append('_csrf_token', '<?= csrf_token() ?>');
                formData.append('csrf_token', '<?= csrf_token() ?>');

                fetch('<?= url("custom-tables/{$tableKey}/inline-update") ?>', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        cell.innerHTML = `<span class="cell-display">${res.updated_value || '—'}</span>`;
                    } else {
                        alert('Failed to save: ' + (res.error || 'Server error'));
                        cell.innerHTML = `<span class="cell-display">${currentVal}</span>`;
                    }
                })
                .catch(() => {
                    cell.innerHTML = `<span class="cell-display">${currentVal}</span>`;
                });
            };

            input.addEventListener('blur', saveChange);
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') saveChange();
                if (e.key === 'Escape') cell.innerHTML = `<span class="cell-display">${currentVal}</span>`;
            });
        });
    });
});
</script>
<?php
};
return require __DIR__ . '/../layouts/app.php';
