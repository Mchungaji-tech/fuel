<?php
    $content = function () use ($title, $trucks, $search) {
?>
<section class="view active" id="view-trucks">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
        <div class="hello">
            <h1>Trucks & Bulk Tankers 🚛</h1>
            <p>Fleet equipment, owner carriers, contract vehicles, and individual performance profiles.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="<?= url('reports/trucks') ?>" class="btn btn-ghost">📊 Detailed Truck Reports</a>
            <button class="btn btn-ghost" onclick="document.getElementById('columnManagerModal').classList.add('active')" title="Customize columns, show/hide, add new fields, or reset table">
                ⚙️ Columns & Schema
            </button>
            <button class="btn btn-brand" onclick="document.getElementById('truckModal').classList.add('active')">＋ Add Truck</button>
        </div>
    </div>

    <!-- Dynamic Driver Assignment Policy Banner -->
    <div style="margin-top:18px;padding:16px 20px;background:var(--brand-soft);border:1.5px solid var(--brand);border-radius:14px;display:flex;align-items:center;gap:14px;">
        <div style="font-size:24px;">💡</div>
        <div>
            <b style="color:var(--brand);font-size:15px;">Dynamic Driver Assignment Policy</b>
            <div style="color:var(--text-2);font-size:14px;margin-top:2px;">
                No specific vehicle is permanently assigned to any driver. Trucks remain in the central carrier pool, and drivers can be flexibly placed on any truck/trip during dispatching.
            </div>
        </div>
    </div>

    <!-- Compact Close-in Search Bar -->
    <div style="display:flex;align-items:center;flex-wrap:wrap;gap:12px;margin:22px 0 14px;">
        <div style="display:flex;align-items:center;gap:10px;background:var(--card);border:1.5px solid var(--border-2);border-radius:10px;padding:6px 14px;min-width:320px;max-width:440px;box-shadow:var(--shadow);">
            <span style="color:var(--text-3);font-size:16px;">🔍</span>
            <input type="text" id="truckFilter" placeholder="Search plate, model, owner, contract…" style="border:0;outline:0;background:transparent;width:100%;font-size:14.5px;color:var(--text);">
        </div>
        <div style="font-size:13.5px;color:var(--text-2);font-weight:700;">
            Showing <span id="truckVisibleCount"><?= count($trucks) ?></span> of <?= count($trucks) ?> Tankers
        </div>
    </div>

    <!-- Trucks Table Panel with Inline Editing -->
    <div class="panel">
        <div class="table-responsive">
            <table id="trucksTable">
                <thead>
                    <tr>
                        <th>Truck Plate</th>
                        <th>Owner / Contract</th>
                        <th>Tanker Capacity</th>
                        <th>Lifetime Volume</th>
                        <th>Profit / Commission</th>
                        <th>Status</th>
                        <th style="text-align:center;min-width:130px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($trucks)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:32px;color:var(--text-3);">No trucks registered. Click "Add Truck".</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($trucks as $t): ?>
                            <?php
                                $isContract = in_array(strtolower($t['ownership_type'] ?? ''), ['contract', 'subcontracted', 'sub']);
                                $statusLower = strtolower($t['status']);
                                $sClass = 's-done';
                                if (str_contains($statusLower, 'trip') || str_contains($statusLower, 'transit')) $sClass = 's-transit';
                                elseif (str_contains($statusLower, 'garage') || str_contains($statusLower, 'maint')) $sClass = 's-hold';
                                elseif (str_contains($statusLower, 'stand')) $sClass = 's-plan';
                                $totLitres = (int) ($t['stats']['total_litres'] ?? 0);
                                $totBal = (float) ($t['stats']['total_profit'] ?? 0);
                            ?>
                            <tr id="truck-row-<?= $t['id'] ?>" data-id="<?= $t['id'] ?>">
                                <!-- Plate Number -->
                                <td class="cell-plate">
                                    <a href="<?= url('trucks/view/' . $t['id']) ?>" style="text-decoration:none;" title="Click to view truck performance profile">
                                        <b class="view-val" style="font-size:16px;letter-spacing:.02em;color:var(--brand);"><?= htmlspecialchars($t['plate_number']) ?></b>
                                    </a>
                                </td>

                                <!-- Owner or Contract -->
                                <td class="cell-ownership">
                                    <?php if ($isContract): ?>
                                        <span class="view-badge" style="background:var(--amber-soft);color:var(--amber);padding:4px 9px;border-radius:6px;font-size:12px;font-weight:800;border:1px solid var(--amber);">
                                            Contract
                                        </span>
                                        <div class="view-owner" style="font-size:11.5px;color:var(--text-3);margin-top:2px;">
                                            <?= htmlspecialchars($t['owner_name'] ?: 'Contract Haulier') ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="view-badge" style="background:var(--green-soft);color:var(--green);padding:4px 9px;border-radius:6px;font-size:12px;font-weight:800;border:1px solid var(--green);">
                                            Owner
                                        </span>
                                        <div class="view-owner" style="font-size:11.5px;color:var(--text-3);margin-top:2px;">
                                            <?= htmlspecialchars($t['owner_name'] ?: 'Sarura Fuel Logistics') ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Capacity -->
                                <td class="cell-capacity">
                                    <b class="view-val" style="font-size:15px;color:var(--brand);">
                                        <?= number_format((int) $t['capacity_litres']) ?> L
                                    </b>
                                    <div class="view-comp" style="font-size:11.5px;color:var(--text-3);"><?= htmlspecialchars($t['compartments'] ?: '—') ?></div>
                                </td>

                                <!-- Lifetime Volume -->
                                <td>
                                    <b><?= number_format($totLitres) ?> L</b>
                                    <div style="font-size:11.5px;color:var(--text-3);"><?= (int)($t['stats']['trip_count'] ?? 0) ?> trips</div>
                                </td>

                                <!-- Profit / Commission -->
                                <td style="font-weight:800;color:<?= $totBal >= 0 ? 'var(--green)' : 'var(--red)' ?>;">
                                    <?= format_money($totBal) ?>
                                    <div style="font-size:11px;color:var(--text-3);font-weight:600;">
                                        <?= $isContract ? 'Commission' : 'Net Profit' ?>
                                    </div>
                                </td>

                                <!-- Status -->
                                <td class="cell-status">
                                    <span class="status <?= $sClass ?> view-val">
                                        <i></i><?= htmlspecialchars($t['status']) ?>
                                    </span>
                                </td>

                                <!-- Action Buttons -->
                                <td class="cell-actions" style="text-align:center;white-space:nowrap;">
                                    <div class="row-normal-actions" style="display:inline-flex;gap:5px;align-items:center;">
                                        <button type="button" class="btn btn-sm btn-ghost" onclick="startTruckInlineEdit(<?= $t['id'] ?>)" title="Edit truck directly on table">
                                            ✏️ Edit
                                        </button>
                                        <a href="<?= url('trucks/view/' . $t['id']) ?>" class="btn btn-sm btn-ghost" style="padding:4px 9px;font-size:12.5px;font-weight:700;" title="View individual truck ledger and reports">
                                            🔍 View
                                        </a>
                                        <form method="POST" action="<?= url('trucks/delete/' . $t['id']) ?>" style="display:inline;" onsubmit="return confirm('Remove truck <?= htmlspecialchars($t['plate_number']) ?>?');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--red);border-color:transparent;padding:4px 7px;" title="Delete">✕</button>
                                        </form>
                                    </div>
                                    <div class="row-editing-actions" style="display:none;gap:6px;align-items:center;">
                                        <button type="button" class="row-save-btn" onclick="saveTruckInlineEdit(<?= $t['id'] ?>)" title="Save Changes">✓ Save</button>
                                        <button type="button" class="row-cancel-btn" onclick="cancelTruckInlineEdit(<?= $t['id'] ?>)" title="Cancel">✕</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Table Pagination Footer -->
        <div class="table-pagination-footer" id="trucksPagination"></div>
    </div>
</section>

<!-- Add Truck Modal -->
<div class="modal-backdrop" id="truckModal" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal-card" style="max-width:540px;">
        <div class="modal-head">
            <h2>🚛 Add Fleet Tanker</h2>
            <button class="close-modal" onclick="document.getElementById('truckModal').classList.remove('active')">✕</button>
        </div>
        <form method="POST" action="<?= url('trucks/store') ?>">
            <?= csrf_field() ?>
            <div class="form-grid single" style="gap:14px;">
                <div class="form-group">
                    <label>Registration Plate Number *</label>
                    <input type="text" name="plate_number" placeholder="e.g. KAA 458Z, KCC 910L" required style="text-transform:uppercase;">
                </div>

                <div class="form-group">
                    <label>Ownership Classification (Owner / Contract) *</label>
                    <select name="ownership_type" id="newTruckOwnership" onchange="toggleOwnershipFields(this.value)">
                        <option value="Owner" selected>Owner (Company Owned Fleet)</option>
                        <option value="Contract">Contract (Contracted / Third-Party Carrier)</option>
                    </select>
                </div>

                <div class="form-group" id="ownerNameGroup" style="display:none;">
                    <label>Contract Transporter Company / Owner Name</label>
                    <input type="text" name="owner_name" placeholder="e.g. Trans-Rift Hauliers, Apex Logistics">
                </div>

                <div class="form-group" id="commissionRateGroup" style="display:none;">
                    <label>Agreed Company Commission per Trip [<?= app_currency_symbol() ?>]</label>
                    <input type="number" step="0.01" name="commission_rate" placeholder="e.g. 350.00">
                    <small style="color:var(--text-3);">Expected company profit for trips completed by this contract vehicle.</small>
                </div>

                <div class="form-group">
                    <label>Tanker Capacity (Litres) *</label>
                    <input type="number" name="capacity_litres" placeholder="e.g. 28000, 32000, 36000" required>
                </div>

                <div class="form-group">
                    <label>Compartment Setup (Optional)</label>
                    <input type="text" name="compartments" value="" placeholder="e.g. 4 comp (8k/8k/8k/8k)">
                </div>

                <div class="form-group">
                    <label>Operational Status</label>
                    <select name="status">
                        <option value="Ready" selected>Ready for Dispatch</option>
                        <option value="On Trip">On Trip</option>
                        <option value="In Garage">In Garage Maintenance</option>
                    </select>
                </div>
            </div>
            <div style="margin-top:22px;display:flex;justify-content:flex-end;gap:12px;">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('truckModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-brand">Save Truck</button>
            </div>
        </form>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?= csrf_token() ?>';

function toggleOwnershipFields(val) {
    const isContract = (val === 'Contract');
    document.getElementById('ownerNameGroup').style.display = isContract ? 'block' : 'none';
    document.getElementById('commissionRateGroup').style.display = isContract ? 'block' : 'none';
}

const truckFilter = document.getElementById('truckFilter');
if (truckFilter) {
    truckFilter.addEventListener('input', function(e) {
        const val = e.target.value.toLowerCase().trim();
        const rows = document.querySelectorAll('#trucksTable tbody tr[id^="truck-row-"]');
        let visible = 0;
        rows.forEach(r => {
            const matches = !val || r.textContent.toLowerCase().includes(val);
            r.dataset.matchedFilter = matches ? 'true' : 'false';
            if (matches) visible++;
        });
        const cnt = document.getElementById('truckVisibleCount');
        if (cnt) cnt.textContent = visible;
        if (window.trucksPagination) {
            window.trucksPagination.refresh();
        }
    });
}

// Initialize Trucks Table Pagination
const trucksPagination = initTablePagination({
    tableId: 'trucksTable',
    footerId: 'trucksPagination',
    defaultPageSize: 10,
    pageSizes: [10, 25, 50, 100],
    countSpanId: 'truckVisibleCount',
    rowSelector: '#trucksTable tbody tr[id^="truck-row-"]'
});
window.trucksPagination = trucksPagination;

/* Trucks Inline Editing */
const truckRowOriginal = {};

function startTruckInlineEdit(id) {
    const row = document.getElementById('truck-row-' + id);
    if (!row || row.classList.contains('tr-editing')) return;

    truckRowOriginal[id] = row.innerHTML;
    row.classList.add('tr-editing');

    const plate = row.querySelector('.cell-plate .view-val')?.textContent.trim() || '';
    const isContract = (row.querySelector('.cell-ownership .view-badge')?.textContent.trim() || '').toLowerCase().includes('contract');
    const ownerName = row.querySelector('.cell-ownership .view-owner')?.textContent.trim() || '';
    const cap = parseInt((row.querySelector('.cell-capacity .view-val')?.textContent || '0').replace(/[^0-9]/g, '')) || 0;
    const rawComp = row.querySelector('.cell-capacity .view-comp')?.textContent.trim() || '';
    const comp = (rawComp === '—') ? '' : rawComp;
    const currentStatus = row.querySelector('.cell-status .view-val')?.textContent.trim() || 'Ready';

    row.querySelector('.cell-plate').innerHTML = `<input type="text" class="table-inline-input inline-plate" value="${plate}" style="text-transform:uppercase;">`;
    row.querySelector('.cell-ownership').innerHTML = `
        <select class="table-inline-select inline-owner-type">
            <option value="Owner" ${!isContract ? 'selected' : ''}>Owner</option>
            <option value="Contract" ${isContract ? 'selected' : ''}>Contract</option>
        </select>
        <input type="text" class="table-inline-input inline-owner-name" value="${ownerName}" placeholder="Owner name" style="margin-top:3px;font-size:12px;">
    `;
    row.querySelector('.cell-capacity').innerHTML = `
        <input type="number" class="table-inline-input inline-cap" value="${cap}" style="margin-bottom:3px;">
        <input type="text" class="table-inline-input inline-comp" value="${comp}" placeholder="Optional compartments" style="font-size:12px;">
    `;
    row.querySelector('.cell-status').innerHTML = `
        <select class="table-inline-select inline-status">
            <option value="Ready" ${currentStatus.includes('Ready') ? 'selected' : ''}>Ready</option>
            <option value="On Trip" ${currentStatus.includes('Trip') ? 'selected' : ''}>On Trip</option>
            <option value="In Garage" ${currentStatus.includes('Garage') ? 'selected' : ''}>In Garage</option>
        </select>
    `;

    row.querySelector('.row-normal-actions').style.display = 'none';
    row.querySelector('.row-editing-actions').style.display = 'inline-flex';
}

function cancelTruckInlineEdit(id) {
    const row = document.getElementById('truck-row-' + id);
    if (!row || !truckRowOriginal[id]) return;
    row.innerHTML = truckRowOriginal[id];
    row.classList.remove('tr-editing');
    delete truckRowOriginal[id];
}

async function saveTruckInlineEdit(id) {
    const row = document.getElementById('truck-row-' + id);
    if (!row) return;

    const plate = row.querySelector('.inline-plate')?.value;
    const ownerType = row.querySelector('.inline-owner-type')?.value;
    const ownerName = row.querySelector('.inline-owner-name')?.value;
    const cap = row.querySelector('.inline-cap')?.value;
    const comp = row.querySelector('.inline-comp')?.value;
    const status = row.querySelector('.inline-status')?.value;

    const postData = {
        _csrf_token: CSRF_TOKEN,
        id: id,
        plate_number: plate,
        ownership_type: ownerType,
        owner_name: ownerName,
        capacity_litres: cap,
        compartments: comp,
        status: status
    };

    const isOffline = !navigator.onLine || (typeof isSimulatedOffline !== 'undefined' && isSimulatedOffline);
    if (isOffline) {
        queueOfflineAction('<?= url("trucks/inline-update") ?>', postData, () => {
            row.querySelector('.cell-plate').innerHTML = `<b class="view-val" style="font-size:16px;color:var(--brand);">${plate}</b><div class="offline-sync-badge">Pending Sync</div>`;
            row.querySelector('.cell-ownership').innerHTML = `
                <span class="view-badge" style="background:${ownerType === 'Contract' ? 'var(--amber-soft)' : 'var(--green-soft)'};color:${ownerType === 'Contract' ? 'var(--amber)' : 'var(--green)'};padding:4px 9px;border-radius:6px;font-size:12px;font-weight:800;">${ownerType}</span>
                <div class="view-owner" style="font-size:11.5px;color:var(--text-3);margin-top:2px;">${ownerName}</div>
            `;
            row.querySelector('.cell-capacity').innerHTML = `<b class="view-val" style="font-size:15px;color:var(--brand);">${parseInt(cap).toLocaleString()} L</b><div class="view-comp" style="font-size:11.5px;color:var(--text-3);">${comp || '—'}</div>`;
            row.querySelector('.cell-status').innerHTML = `<span class="status s-done view-val"><i></i>${status}</span>`;

            row.querySelector('.row-normal-actions').style.display = 'inline-flex';
            row.querySelector('.row-editing-actions').style.display = 'none';
            row.classList.remove('tr-editing');
            delete truckRowOriginal[id];
        });
        return;
    }

    try {
        const res = await fetch('<?= url("trucks/inline-update") ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams(postData).toString()
        });

        const json = await res.json();
        if (json.success && json.data) {
            const d = json.data;
            row.querySelector('.cell-plate').innerHTML = `
                <a href="<?= url('trucks/view/') ?>/${d.id}" style="text-decoration:none;">
                    <b class="view-val" style="font-size:16px;color:var(--brand);">${d.plate_number}</b>
                </a>
            `;

            const isC = d.ownership_type === 'Contract';
            row.querySelector('.cell-ownership').innerHTML = `
                <span class="view-badge" style="background:${isC ? 'var(--amber-soft)' : 'var(--green-soft)'};color:${isC ? 'var(--amber)' : 'var(--green)'};padding:4px 9px;border-radius:6px;font-size:12px;font-weight:800;border:1px solid ${isC ? 'var(--amber)' : 'var(--green)'};">${d.ownership_type}</span>
                <div class="view-owner" style="font-size:11.5px;color:var(--text-3);margin-top:2px;">${d.owner_name}</div>
            `;

            row.querySelector('.cell-capacity').innerHTML = `
                <b class="view-val" style="font-size:15px;color:var(--brand);">${d.capacity_formatted}</b>
                <div class="view-comp" style="font-size:11.5px;color:var(--text-3);">${d.compartments}</div>
            `;

            let sClass = 's-done';
            const sLow = d.status.toLowerCase();
            if (sLow.includes('trip')) sClass = 's-transit';
            else if (sLow.includes('garage')) sClass = 's-hold';

            row.querySelector('.cell-status').innerHTML = `<span class="status ${sClass} view-val"><i></i>${d.status}</span>`;

            row.querySelector('.row-normal-actions').style.display = 'inline-flex';
            row.querySelector('.row-editing-actions').style.display = 'none';
            row.classList.remove('tr-editing');
            delete truckRowOriginal[id];

            row.style.transition = 'background .4s';
            row.style.background = 'var(--green-soft)';
            setTimeout(() => { row.style.background = ''; }, 1200);
        } else {
            alert('Could not update truck: ' + (json.error || 'Server error'));
        }
    } catch (err) {
        console.error('Update error:', err);
        alert('Network error. Update was not saved.');
    }
}
</script>

<!-- Dynamic Column & Schema Manager Modal for Trucks -->
<?php
    $tableName = 'trucks';
    $tableDisplayName = 'Trucks & Bulk Tankers';
    $redirectTo = '/trucks';
    $isCustomTable = false;
    require __DIR__ . '/../schema/column_modal.php';
?>
<?php };
require __DIR__ . '/../layouts/app.php';
?>
