<?php
    $content = function () use (
        $title, $entries, $categories, $search, $categoryFilter,
        $period, $fromDate, $toDate, $totalLogs, $deletedCount,
        $fleetActionsCount, $securityActionsCount
    ) {
?>
<section class="view active" id="view-audit">
    <!-- Header & Action Bar -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
        <div class="hello">
            <h1>System Audit Trail & Compliance 🛡️</h1>
            <p>Real-time activity ledger, staff accountability monitoring, sensitive operational transactions, and deletion records.</p>
        </div>
        <div>
            <span style="font-size:12.5px;color:var(--text-3);font-weight:700;">Immutable Transaction Record</span>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="kpis" style="margin-top:20px;">
        <div class="kpi">
            <div class="lbl">Total Audited Events</div>
            <div class="val"><?= $totalLogs ?> Events</div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Recorded across all modules</div>
        </div>
        <div class="kpi">
            <div class="lbl">Fleet Dispatch Actions</div>
            <div class="val" style="color:var(--brand);"><?= $fleetActionsCount ?> Actions</div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Dispatches, route & payout logs</div>
        </div>
        <div class="kpi">
            <div class="lbl">User Security Events</div>
            <div class="val" style="color:#8B5CF6;"><?= $securityActionsCount ?> Events</div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Logins, registrations & role changes</div>
        </div>
        <div class="kpi" style="border:2px solid var(--red);background:var(--red-soft);">
            <div class="lbl" style="color:var(--red);font-weight:800;">Deleted Transactions 🗑️</div>
            <div class="val" style="color:var(--red);font-size:28px;"><?= $deletedCount ?> Removed</div>
            <div style="font-size:13px;color:var(--red);font-weight:700;margin-top:4px;">Red-flagged deletion records</div>
        </div>
    </div>

    <!-- Compact Search & Multi-Filter Toolbar -->
    <div style="display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin:24px 0 14px;">
        <!-- Search Input -->
        <div style="display:flex;align-items:center;gap:10px;background:var(--card);border:1.5px solid var(--border-2);border-radius:10px;padding:6px 14px;min-width:260px;max-width:340px;box-shadow:var(--shadow);">
            <span style="color:var(--text-3);font-size:16px;">🔍</span>
            <input type="text" id="auditSearchInput" placeholder="Search user, action, detail, IP…" style="border:0;outline:0;background:transparent;width:100%;font-size:14px;color:var(--text);">
        </div>

        <!-- Category Dropdown Filter -->
        <select id="auditCategoryFilter" onchange="applyAuditFilters()" style="padding:8px 12px;border:1.5px solid var(--border-2);border-radius:10px;background:var(--card);font-size:13.5px;font-weight:700;color:var(--text);box-shadow:var(--shadow);outline:0;">
            <option value="all">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
            <?php endforeach; ?>
        </select>

        <!-- Time Horizon Filter Buttons -->
        <div style="display:inline-flex;align-items:center;gap:4px;background:var(--card);border:1.5px solid var(--border-2);border-radius:10px;padding:3px;box-shadow:var(--shadow);">
            <button type="button" class="btn btn-sm btn-ghost audit-time-btn active" data-period="all" onclick="setAuditPeriod('all', this)" style="padding:4px 10px;font-size:12.5px;font-weight:700;">All Time</button>
            <button type="button" class="btn btn-sm btn-ghost audit-time-btn" data-period="week" onclick="setAuditPeriod('week', this)" style="padding:4px 10px;font-size:12.5px;font-weight:700;">This Week ▾</button>
            <button type="button" class="btn btn-sm btn-ghost audit-time-btn" data-period="month" onclick="setAuditPeriod('month', this)" style="padding:4px 10px;font-size:12.5px;font-weight:700;">This Month</button>
            <button type="button" class="btn btn-sm btn-ghost audit-time-btn" data-period="year" onclick="setAuditPeriod('year', this)" style="padding:4px 10px;font-size:12.5px;font-weight:700;">This Year</button>
        </div>

        <!-- Days of Week Sub-Bar (Mon-Sun) -->
        <div id="auditWeekDaysSubToolbar" style="display:none;align-items:center;gap:3px;background:var(--card-2);border:1.5px solid var(--border);border-radius:10px;padding:3px 6px;">
            <span style="font-size:11px;font-weight:800;color:var(--text-3);margin-right:2px;">DAY:</span>
            <button type="button" class="btn btn-sm btn-ghost audit-day-btn active" data-day="all" onclick="setAuditDay('all', this)" style="padding:2px 7px;font-size:11.5px;font-weight:700;">All Week</button>
            <button type="button" class="btn btn-sm btn-ghost audit-day-btn" data-day="1" onclick="setAuditDay('1', this)" style="padding:2px 6px;font-size:11.5px;font-weight:700;">Mon</button>
            <button type="button" class="btn btn-sm btn-ghost audit-day-btn" data-day="2" onclick="setAuditDay('2', this)" style="padding:2px 6px;font-size:11.5px;font-weight:700;">Tue</button>
            <button type="button" class="btn btn-sm btn-ghost audit-day-btn" data-day="3" onclick="setAuditDay('3', this)" style="padding:2px 6px;font-size:11.5px;font-weight:700;">Wed</button>
            <button type="button" class="btn btn-sm btn-ghost audit-day-btn" data-day="4" onclick="setAuditDay('4', this)" style="padding:2px 6px;font-size:11.5px;font-weight:700;">Thu</button>
            <button type="button" class="btn btn-sm btn-ghost audit-day-btn" data-day="5" onclick="setAuditDay('5', this)" style="padding:2px 6px;font-size:11.5px;font-weight:700;">Fri</button>
            <button type="button" class="btn btn-sm btn-ghost audit-day-btn" data-day="6" onclick="setAuditDay('6', this)" style="padding:2px 6px;font-size:11.5px;font-weight:700;">Sat</button>
            <button type="button" class="btn btn-sm btn-ghost audit-day-btn" data-day="0" onclick="setAuditDay('0', this)" style="padding:2px 6px;font-size:11.5px;font-weight:700;">Sun</button>
        </div>

        <div style="font-size:13px;color:var(--text-2);font-weight:700;margin-left:auto;">
            Showing <span id="auditVisibleCount"><?= count($entries) ?></span> of <?= count($entries) ?> audit logs
        </div>
    </div>

    <!-- Audit Logs Table Panel -->
    <div class="panel">
        <div class="table-responsive">
            <table id="auditTable">
                <thead>
                    <tr>
                        <th style="min-width:130px;">Timestamp</th>
                        <th>User & Role</th>
                        <th>Category</th>
                        <th>Action Performed</th>
                        <th>Transaction Description</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entries)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;padding:40px;color:var(--text-3);">No audit records found matching your filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($entries as $row): ?>
                            <?php
                                $isDeleted = !empty($row['is_deleted']) || str_contains(strtoupper($row['action']), 'DELETE');
                                $actionUpper = strtoupper($row['action']);
                                $cat = $row['category'] ?? 'General';

                                $catBadgeColor = 'var(--brand)';
                                $catBadgeBg = 'var(--brand-soft)';
                                if ($cat === 'Expenses') { $catBadgeColor = 'var(--amber)'; $catBadgeBg = 'var(--amber-soft)'; }
                                elseif ($cat === 'User Security') { $catBadgeColor = '#8B5CF6'; $catBadgeBg = 'rgba(139,92,246,0.12)'; }
                                elseif ($cat === 'Driver Salaries') { $catBadgeColor = 'var(--green)'; $catBadgeBg = 'var(--green-soft)'; }
                                elseif ($cat === 'Trucks') { $catBadgeColor = '#06B6D4'; $catBadgeBg = 'rgba(6,182,212,0.12)'; }

                                $datePart = substr($row['created_at'], 0, 10);
                                $timePart = substr($row['created_at'], 11, 5);
                                $dateFmt = format_date_dol($datePart) . ' ' . $timePart;
                            ?>
                            <tr class="audit-row <?= $isDeleted ? 'row-deleted-scheme' : '' ?>" 
                                data-date="<?= htmlspecialchars($datePart) ?>"
                                data-category="<?= htmlspecialchars(strtolower($cat)) ?>"
                                style="<?= $isDeleted ? 'background:var(--red-soft);border-left:4px solid var(--red);' : '' ?>">
                                
                                <!-- Timestamp -->
                                <td style="font-weight:700;white-space:nowrap;color:<?= $isDeleted ? 'var(--red)' : 'var(--text)' ?>;">
                                    <?= $dateFmt ?>
                                </td>

                                <!-- User -->
                                <td>
                                    <div class="who">
                                        <div class="av" style="background:<?= $isDeleted ? 'var(--red)' : 'var(--brand)' ?>;color:#fff;font-size:11px;font-weight:800;">
                                            <?= strtoupper(substr($row['user_name'], 0, 2)) ?>
                                        </div>
                                        <div>
                                            <b style="font-size:14px;color:<?= $isDeleted ? 'var(--red)' : 'var(--text)' ?>;"><?= htmlspecialchars($row['user_name']) ?></b>
                                            <div style="font-size:11px;color:var(--text-3);"><?= htmlspecialchars($row['user_email']) ?></div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Category -->
                                <td>
                                    <span style="display:inline-block;padding:2px 8px;border-radius:6px;font-size:11.5px;font-weight:800;color:<?= $catBadgeColor ?>;background:<?= $catBadgeBg ?>;border:1px solid <?= $catBadgeColor ?>;">
                                        <?= htmlspecialchars($cat) ?>
                                    </span>
                                </td>

                                <!-- Action -->
                                <td>
                                    <?php if ($isDeleted): ?>
                                        <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:6px;font-size:12px;font-weight:900;color:#fff;background:var(--red);">
                                            🗑️ <?= htmlspecialchars($row['action']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status s-done" style="font-size:12px;font-weight:700;">
                                            <i></i><?= htmlspecialchars($row['action']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Description -->
                                <td style="font-size:13px;line-height:1.45;color:<?= $isDeleted ? 'var(--red)' : 'var(--text-2)' ?>;font-weight:<?= $isDeleted ? '700' : '500' ?>;">
                                    <?= htmlspecialchars($row['description']) ?>
                                </td>

                                <!-- IP Address -->
                                <td style="font-family:monospace;font-size:12px;color:var(--text-3);white-space:nowrap;">
                                    <?= htmlspecialchars($row['ip_address'] ?: '127.0.0.1') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Table Pagination Footer -->
        <div class="table-pagination-footer" id="auditPagination"></div>
    </div>
</section>

<script>
let activeAuditPeriod = 'all';
let activeAuditDay = 'all';

function setAuditPeriod(period, btn) {
    activeAuditPeriod = period;
    document.querySelectorAll('.audit-time-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    const sub = document.getElementById('auditWeekDaysSubToolbar');
    if (period === 'week') {
        sub.style.display = 'inline-flex';
    } else {
        sub.style.display = 'none';
        activeAuditDay = 'all';
        document.querySelectorAll('.audit-day-btn').forEach(b => b.classList.remove('active'));
        document.querySelector('.audit-day-btn[data-day="all"]')?.classList.add('active');
    }
    applyAuditFilters();
}

function setAuditDay(day, btn) {
    activeAuditDay = day;
    document.querySelectorAll('.audit-day-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    applyAuditFilters();
}

function matchesAuditPeriod(dateStr) {
    if (activeAuditPeriod === 'all' || !dateStr) return true;

    const parts = dateStr.split('-');
    if (parts.length !== 3) return true;
    const y = parseInt(parts[0], 10);
    const m = parseInt(parts[1], 10) - 1;
    const d = parseInt(parts[2], 10);
    const rowDate = new Date(y, m, d);

    const today = new Date();
    const currentYear = today.getFullYear();
    const currentMonth = today.getMonth();

    if (activeAuditPeriod === 'year') {
        return y === currentYear;
    }
    if (activeAuditPeriod === 'month') {
        return y === currentYear && m === currentMonth;
    }
    if (activeAuditPeriod === 'week') {
        const dayOfWeek = today.getDay();
        const diffToMon = (dayOfWeek === 0 ? -6 : 1) - dayOfWeek;
        const monday = new Date(today.getFullYear(), today.getMonth(), today.getDate() + diffToMon);
        monday.setHours(0, 0, 0, 0);
        const sunday = new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() + 6);
        sunday.setHours(23, 59, 59, 999);

        const inWeek = (rowDate >= monday && rowDate <= sunday);
        if (!inWeek) return false;
        if (activeAuditDay === 'all') return true;

        return rowDate.getDay() == parseInt(activeAuditDay, 10);
    }
    return true;
}

function applyAuditFilters() {
    const searchVal = (document.getElementById('auditSearchInput')?.value || '').toLowerCase().trim();
    const catVal = (document.getElementById('auditCategoryFilter')?.value || 'all').toLowerCase();
    const rows = document.querySelectorAll('#auditTable tbody tr.audit-row');

    let visibleCount = 0;
    rows.forEach(r => {
        const text = r.textContent.toLowerCase();
        const rowCat = r.dataset.category || '';
        const rowDate = r.dataset.date || '';

        const matchesSearch = !searchVal || text.includes(searchVal);
        const matchesCategory = (catVal === 'all') || rowCat.includes(catVal);
        const matchesDate = matchesAuditPeriod(rowDate);

        if (matchesSearch && matchesCategory && matchesDate) {
            r.dataset.matchedFilter = 'true';
            visibleCount++;
        } else {
            r.dataset.matchedFilter = 'false';
        }
    });

    const countSpan = document.getElementById('auditVisibleCount');
    if (countSpan) countSpan.textContent = visibleCount;

    if (window.auditPagination) {
        window.auditPagination.refresh();
    }
}

document.getElementById('auditSearchInput')?.addEventListener('input', applyAuditFilters);

// Initialize Audit Table Pagination
const auditPagination = initTablePagination({
    tableId: 'auditTable',
    footerId: 'auditPagination',
    defaultPageSize: 10,
    pageSizes: [10, 25, 50, 100],
    countSpanId: 'auditVisibleCount',
    rowSelector: '#auditTable tbody tr.audit-row'
});
window.auditPagination = auditPagination;
</script>
<?php };
require __DIR__ . '/../layouts/app.php';
?>
