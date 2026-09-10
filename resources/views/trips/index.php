<?php
    $content = function () use ($title, $trips) {
        $totalCount = count($trips);
        $transitCount = 0;
        $loadingCount = 0;
        $deliveredCount = 0;
        $disputedCount = 0;

        foreach ($trips as $t) {
            $s = strtolower($t['status'] ?? '');
            if (str_contains($s, 'transit')) $transitCount++;
            elseif (str_contains($s, 'load')) $loadingCount++;
            elseif (str_contains($s, 'deliver')) $deliveredCount++;
            elseif (str_contains($s, 'dispute') || str_contains($s, 'hold') || str_contains($s, 'cancel')) $disputedCount++;
        }
?>
<section class="view active" id="view-trips">
    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div class="hello">
            <h1>Fuel Trips Board 🚚</h1>
            <p>Dispatch, track en-route tanker transits, customer offloading and trip statuses.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="<?= url('fleet') ?>" class="btn btn-ghost">🚚 Fleet Ledger</a>
            <a href="<?= url('fleet') ?>" class="btn btn-brand">＋ New Dispatch Trip</a>
        </div>
    </div>

    <!-- Search & Quick Filter Bar -->
    <div style="display:flex;align-items:center;flex-wrap:wrap;gap:12px;margin:20px 0 14px;">
        <!-- Search Input -->
        <div style="display:flex;align-items:center;gap:10px;background:var(--card);border:1.5px solid var(--border-2);border-radius:10px;padding:8px 14px;min-width:280px;box-shadow:var(--shadow);">
            <span style="color:var(--text-3);font-size:16px;">🔍</span>
            <input type="text" id="tripsSearchInput" placeholder="Search trip #, customer, truck, driver..." style="border:0;outline:0;background:transparent;width:100%;font-size:14px;color:var(--text);">
            <button type="button" id="tripsClearSearch" onclick="clearTripsSearch()" style="background:transparent;border:0;color:var(--text-3);font-size:14px;cursor:pointer;display:none;">✕</button>
        </div>

        <!-- Interactive Filter Chips -->
        <div class="chips" id="tripsFilterChips" style="margin:0;display:flex;gap:8px;flex-wrap:wrap;">
            <span class="chip hot" data-filter="all" onclick="filterTrips('all', this)" style="cursor:pointer;">
                All (<?= $totalCount ?>)
            </span>
            <span class="chip" data-filter="transit" onclick="filterTrips('transit', this)" style="cursor:pointer;">
                🚚 In Transit (<?= $transitCount ?>)
            </span>
            <span class="chip" data-filter="loading" onclick="filterTrips('loading', this)" style="cursor:pointer;">
                ⏳ Loading (<?= $loadingCount ?>)
            </span>
            <span class="chip" data-filter="delivered" onclick="filterTrips('delivered', this)" style="cursor:pointer;">
                ✓ Delivered (<?= $deliveredCount ?>)
            </span>
            <span class="chip" data-filter="disputed" onclick="filterTrips('disputed', this)" style="cursor:pointer;">
                ⚠️ Disputed / Hold (<?= $disputedCount ?>)
            </span>
        </div>
    </div>

    <!-- Table Panel -->
    <div class="panel" style="margin-top:16px;">
        <div class="table-responsive">
            <table id="tripsTable">
                <thead>
                    <tr>
                        <th>Trip Reference</th>
                        <th>Customer / Consignee</th>
                        <th>Tanker Truck</th>
                        <th>Assigned Driver</th>
                        <th>Trip Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="tripsTableBody">
                    <?php if (empty($trips)): ?>
                        <tr id="tripsEmptyRow">
                            <td colspan="6" style="text-align:center;padding:36px;color:var(--text-3);">
                                <div style="font-size:32px;margin-bottom:8px;">🚚</div>
                                <b>No trips recorded in the ledger yet.</b>
                                <div style="font-size:13px;margin-top:4px;">Dispatches created in Fleet Management will automatically populate this board.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($trips as $t): ?>
                            <?php
                                $s = strtolower($t['status'] ?? '');
                                $statusClass = 's-plan';
                                $category = 'other';
                                if (str_contains($s, 'transit')) {
                                    $statusClass = 's-transit';
                                    $category = 'transit';
                                } elseif (str_contains($s, 'load')) {
                                    $statusClass = 's-load';
                                    $category = 'loading';
                                } elseif (str_contains($s, 'deliver')) {
                                    $statusClass = 's-done';
                                    $category = 'delivered';
                                } elseif (str_contains($s, 'dispute') || str_contains($s, 'hold') || str_contains($s, 'cancel')) {
                                    $statusClass = 's-hold';
                                    $category = 'disputed';
                                }
                            ?>
                            <tr class="trip-row" data-status-category="<?= $category ?>" data-search-text="<?= htmlspecialchars(strtolower(($t['trip'] ?? '') . ' ' . ($t['customer'] ?? '') . ' ' . ($t['truck'] ?? '') . ' ' . ($t['driver'] ?? '') . ' ' . ($t['status'] ?? '') . ' ' . ($t['route'] ?? ''))) ?>">
                                <td>
                                    <b style="font-size:14.5px;color:var(--text);"><?= htmlspecialchars($t['trip']) ?></b>
                                    <?php if (!empty($t['date']) && $t['date'] !== '—'): ?>
                                        <div style="font-size:11.5px;color:var(--text-3);font-weight:600;margin-top:2px;">📅 <?= htmlspecialchars($t['date']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight:700;color:var(--brand);font-size:14px;"><?= htmlspecialchars($t['customer']) ?></div>
                                    <?php if (!empty($t['route'])): ?>
                                        <div style="font-size:12px;color:var(--text-2);margin-top:2px;">📍 <?= htmlspecialchars($t['route']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-weight:700;font-size:13.5px;"><?= htmlspecialchars($t['truck']) ?></span>
                                    <?php if (!empty($t['load']) && $t['load'] !== '—'): ?>
                                        <div style="font-size:11.5px;color:var(--brand);font-weight:700;margin-top:2px;">⛽ <?= htmlspecialchars($t['load']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="color:var(--text-2);"><?= htmlspecialchars($t['driver'] ?: 'Unassigned') ?></span>
                                </td>
                                <td>
                                    <span class="status <?= $statusClass ?>">
                                        <i></i><?= htmlspecialchars($t['status']) ?>
                                    </span>
                                </td>
                                <td style="text-align:right;white-space:nowrap;">
                                    <a href="<?= url('fleet') ?>" class="btn btn-sm btn-ghost" style="padding:4px 9px;font-size:12px;" title="View in Fleet Ledger">
                                        View in Ledger →
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- No Results Row when filtered -->
        <div id="tripsNoResults" style="display:none;text-align:center;padding:36px;color:var(--text-3);">
            <div style="font-size:28px;margin-bottom:6px;">🔍</div>
            <b>No trips match your active filter or search.</b>
            <div style="margin-top:8px;">
                <button type="button" class="btn btn-sm btn-ghost" onclick="resetTripsFilters()">Clear Filters & Search</button>
            </div>
        </div>

        <!-- Pagination Controls -->
        <div id="tripsPaginationContainer" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;padding:14px 18px;border-top:1px solid var(--border);background:var(--card-2);font-size:13px;color:var(--text-2);">
            <div>
                Showing <b id="tripsShowingStart">0</b> to <b id="tripsShowingEnd">0</b> of <b id="tripsTotalFiltered">0</b> trips
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <label style="display:flex;align-items:center;gap:6px;">
                    Per Page:
                    <select id="tripsPerPageSelect" onchange="changeTripsPerPage(this.value)" style="padding:4px 8px;border-radius:6px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:12.5px;">
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </label>
                <div style="display:flex;gap:4px;" id="tripsPaginationButtons"></div>
            </div>
        </div>
    </div>
</section>

<script>
let currentTripFilter = 'all';
let currentTripSearch = '';
let currentTripsPage = 1;
let tripsPerPage = 10;

function filterTrips(category, el) {
    currentTripFilter = category;
    document.querySelectorAll('#tripsFilterChips .chip').forEach(c => c.classList.remove('hot'));
    if (el) el.classList.add('hot');
    currentTripsPage = 1;
    applyTripsFilter();
}

function clearTripsSearch() {
    const input = document.getElementById('tripsSearchInput');
    input.value = '';
    currentTripSearch = '';
    document.getElementById('tripsClearSearch').style.display = 'none';
    currentTripsPage = 1;
    applyTripsFilter();
}

function resetTripsFilters() {
    clearTripsSearch();
    const allChip = document.querySelector('#tripsFilterChips .chip[data-filter="all"]');
    filterTrips('all', allChip);
}

function changeTripsPerPage(val) {
    tripsPerPage = parseInt(val, 10) || 10;
    currentTripsPage = 1;
    applyTripsFilter();
}

function applyTripsFilter() {
    const rows = Array.from(document.querySelectorAll('.trip-row'));
    if (!rows.length) {
        document.getElementById('tripsPaginationContainer').style.display = 'none';
        return;
    }

    const matchedRows = [];
    rows.forEach(r => {
        const cat = r.dataset.statusCategory || '';
        const searchContent = r.dataset.searchText || '';

        const matchesCategory = (currentTripFilter === 'all') || (cat === currentTripFilter);
        const matchesSearch = (!currentTripSearch) || searchContent.includes(currentTripSearch);

        if (matchesCategory && matchesSearch) {
            matchedRows.push(r);
        } else {
            r.style.display = 'none';
        }
    });

    const total = matchedRows.length;
    const noResults = document.getElementById('tripsNoResults');
    const table = document.getElementById('tripsTable');
    const pagContainer = document.getElementById('tripsPaginationContainer');

    if (total === 0) {
        noResults.style.display = 'block';
        table.style.display = 'none';
        pagContainer.style.display = 'none';
        return;
    }

    noResults.style.display = 'none';
    table.style.display = 'table';
    pagContainer.style.display = 'flex';

    // Pagination math
    const totalPages = Math.ceil(total / tripsPerPage) || 1;
    if (currentTripsPage > totalPages) currentTripsPage = totalPages;

    const startIndex = (currentTripsPage - 1) * tripsPerPage;
    const endIndex = Math.min(startIndex + tripsPerPage, total);

    matchedRows.forEach((r, idx) => {
        if (idx >= startIndex && idx < endIndex) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });

    document.getElementById('tripsShowingStart').textContent = (total > 0) ? (startIndex + 1) : 0;
    document.getElementById('tripsShowingEnd').textContent = endIndex;
    document.getElementById('tripsTotalFiltered').textContent = total;

    // Render pagination buttons
    const btnBox = document.getElementById('tripsPaginationButtons');
    btnBox.innerHTML = '';

    if (totalPages > 1) {
        const prevBtn = document.createElement('button');
        prevBtn.type = 'button';
        prevBtn.className = 'btn btn-sm btn-ghost';
        prevBtn.textContent = '◀';
        prevBtn.disabled = (currentTripsPage <= 1);
        prevBtn.onclick = () => { currentTripsPage--; applyTripsFilter(); };
        btnBox.appendChild(prevBtn);

        for (let p = 1; p <= totalPages; p++) {
            if (p === 1 || p === totalPages || (p >= currentTripsPage - 1 && p <= currentTripsPage + 1)) {
                const pageBtn = document.createElement('button');
                pageBtn.type = 'button';
                pageBtn.className = 'btn btn-sm ' + (p === currentTripsPage ? 'btn-brand' : 'btn-ghost');
                pageBtn.textContent = p;
                pageBtn.onclick = () => { currentTripsPage = p; applyTripsFilter(); };
                btnBox.appendChild(pageBtn);
            } else if (p === currentTripsPage - 2 || p === currentTripsPage + 2) {
                const dots = document.createElement('span');
                dots.textContent = '…';
                dots.style.padding = '0 4px';
                btnBox.appendChild(dots);
            }
        }

        const nextBtn = document.createElement('button');
        nextBtn.type = 'button';
        nextBtn.className = 'btn btn-sm btn-ghost';
        nextBtn.textContent = '▶';
        nextBtn.disabled = (currentTripsPage >= totalPages);
        nextBtn.onclick = () => { currentTripsPage++; applyTripsFilter(); };
        btnBox.appendChild(nextBtn);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('tripsSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            currentTripSearch = e.target.value.trim().toLowerCase();
            document.getElementById('tripsClearSearch').style.display = currentTripSearch ? 'inline' : 'none';
            currentTripsPage = 1;
            applyTripsFilter();
        });
    }
    applyTripsFilter();
});
</script>
<?php };
require __DIR__ . '/../layouts/app.php';
?>
