<?php
    $content = function () use ($title, $settings) { ?>
        <section class="view active" id="view-settings">
            <div class="hello"><h1>Settings ⚙️</h1><p>Business configuration, user access and payroll controls.</p></div>

            <div class="stats-grid">
                <div class="stat-card"><small>Business</small><strong>Sarura Fuel</strong></div>
                <div class="stat-card"><small>Branches</small><strong>4 Active</strong></div>
                <div class="stat-card"><small>Sys status</small><strong>Healthy</strong></div>
                <div class="stat-card"><small>Users</small><strong>18</strong></div>
            </div>

            <div class="panel" style="margin-top:16px;padding:20px;">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;">
                    <?php foreach ($settings as $item): ?>
                        <div style="padding:16px;border:1px solid var(--border);border-radius:16px;background:var(--card-2);">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                                <b><?= htmlspecialchars($item['name']) ?></b>
                                <span class="pill" style="background:var(--green-soft);color:var(--green);">Enabled</span>
                            </div>
                            <p style="margin:10px 0 0;color:var(--text-2);">
                                <?= htmlspecialchars($item['description']) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php };

    require __DIR__ . '/../layouts/app.php';
?>
