<?php
    $content = function () use ($title, $telemetry, $sessions, $securityLogs) {
        $isMaint = !empty($telemetry['maint_mode']);
?>
<section class="view active" id="view-dev">
    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
        <div class="hello">
            <h1>Developer & Security Console 🛡️</h1>
            <p>Technical engine telemetry, intrusion watchdog, maintenance mode control, and active session termination.</p>
        </div>
        <div>
            <span style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:999px;background:rgba(99,102,241,0.12);border:1px solid #6366F1;color:#6366F1;font-size:12.5px;font-weight:800;">
                <span style="width:8px;height:8px;border-radius:50%;background:#6366F1;box-shadow:0 0 8px #6366F1;"></span>
                Technical Operations Mode
            </span>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($msg = flash('dev_success')): ?>
        <div style="margin-top:16px;padding:12px 18px;background:var(--green-soft);border:1.5px solid var(--green);border-radius:10px;color:var(--green);font-weight:700;font-size:14px;">
            ✓ <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <!-- System Telemetry & Status Cards -->
    <div class="kpis" style="margin-top:20px;">
        <!-- Maintenance Mode Status Card -->
        <div class="kpi" style="border-top:4px solid <?= $isMaint ? 'var(--amber)' : 'var(--green)' ?>;">
            <div class="lbl" style="color:<?= $isMaint ? 'var(--amber)' : 'var(--green)' ?>;font-weight:800;">System Availability</div>
            <div class="val" style="font-size:22px;color:<?= $isMaint ? 'var(--amber)' : 'var(--green)' ?>;">
                <?= $isMaint ? '🟠 Maintenance Mode' : '🟢 Publicly Live' ?>
            </div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">
                <?= $isMaint ? 'Non-admin visitors blocked' : 'Standard operations normal' ?>
            </div>
        </div>

        <!-- Database Engine -->
        <div class="kpi" style="border-top:4px solid var(--brand);">
            <div class="lbl" style="color:var(--brand);font-weight:800;">Database Engine</div>
            <div class="val" style="font-size:22px;color:var(--text);">
                <?= htmlspecialchars($telemetry['db_driver']) ?>
            </div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">
                Storage: <b><?= htmlspecialchars($telemetry['db_size']) ?></b>
            </div>
        </div>

        <!-- Server Environment -->
        <div class="kpi" style="border-top:4px solid #3B82F6;">
            <div class="lbl" style="color:#3B82F6;font-weight:800;">PHP Engine</div>
            <div class="val" style="font-size:22px;color:var(--text);">
                v<?= htmlspecialchars($telemetry['php_version']) ?>
            </div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">
                Memory: <b><?= htmlspecialchars($telemetry['memory_used']) ?></b>
            </div>
        </div>

        <!-- Active Sessions Count -->
        <div class="kpi" style="border-top:4px solid #8B5CF6;">
            <div class="lbl" style="color:#8B5CF6;font-weight:800;">Monitored Sessions</div>
            <div class="val" style="font-size:22px;color:var(--text);">
                <?= count($sessions) ?> Active
            </div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">
                SSL: <b><?= htmlspecialchars($telemetry['is_https']) ?></b>
            </div>
        </div>
    </div>

    <!-- Maintenance Mode Control Panel -->
    <div class="panel" style="margin-top:24px;border:1.5px solid <?= $isMaint ? 'var(--amber)' : 'var(--border)' ?>;">
        <div class="panel-head">
            <h3>⚙️ Maintenance Mode Management</h3>
            <span style="font-size:13px;color:var(--text-3);">Toggle public access during database migrations, server upgrades, or security audits</span>
        </div>
        <div style="padding:16px 20px;">
            <form method="POST" action="<?= url('dev/maintenance/toggle') ?>" style="display:flex;flex-direction:column;gap:14px;">
                <?= csrf_field() ?>
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;background:var(--card-2);padding:16px;border-radius:12px;border:1px solid var(--border);">
                    <div>
                        <b style="font-size:15px;color:var(--text);">Current System State:</b>
                        <div style="font-size:13.5px;color:<?= $isMaint ? 'var(--amber)' : 'var(--green)' ?>;font-weight:800;margin-top:3px;">
                            <?= $isMaint ? '⚠️ MAINTENANCE MODE IS ACTIVE (Public users see maintenance landing screen)' : '✓ SYSTEM IS LIVE (All staff and dispatchers have normal access)' ?>
                        </div>
                    </div>
                    <button type="submit" class="btn" style="background:<?= $isMaint ? 'var(--green)' : 'var(--amber)' ?>;color:#fff;font-weight:800;padding:10px 20px;border-radius:10px;">
                        <?= $isMaint ? '✓ Turn Maintenance Mode OFF (Go Live)' : '⚠️ Turn Maintenance Mode ON (Lock Access)' ?>
                    </button>
                </div>

                <div class="form-group">
                    <label style="font-size:13px;font-weight:700;">Maintenance Announcement Message (Displayed on Maintenance Screen)</label>
                    <input type="text" name="maintenance_message" value="<?= htmlspecialchars($telemetry['maint_msg']) ?>" style="width:100%;padding:10px 14px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:14px;">
                </div>
            </form>
        </div>
    </div>

    <!-- Active User Sessions & Kill-Switch -->
    <div class="panel" style="margin-top:24px;">
        <div class="panel-head">
            <h3>👥 Active User Sessions & Immediate Force-Logout</h3>
            <span style="font-size:13px;color:var(--text-3);">Live sessions connected to this logistics cluster. Terminate suspicious or unauthorized logins instantly.</span>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>User Account</th>
                        <th>Assigned Role</th>
                        <th>IP Address</th>
                        <th>Browser / Device Agent</th>
                        <th>Connected Since</th>
                        <th>Last Heartbeat</th>
                        <th>Session Status</th>
                        <th style="text-align:center;">Security Kill</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sessions)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center;padding:24px;color:var(--text-3);">No active sessions tracked.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sessions as $s): ?>
                            <?php $isTerminated = ($s['status'] === 'terminated'); ?>
                            <tr>
                                <td>
                                    <b><?= htmlspecialchars($s['user_name'] ?? 'Guest / Staff') ?></b>
                                    <div style="font-size:11.5px;color:var(--text-3);"><?= htmlspecialchars($s['user_email'] ?? '') ?></div>
                                </td>
                                <td>
                                    <span style="font-size:12px;font-weight:800;padding:2px 8px;border-radius:6px;background:var(--card-2);border:1px solid var(--border);color:var(--text-2);">
                                        <?= htmlspecialchars(strtoupper($s['user_role'] ?? 'STAFF')) ?>
                                    </span>
                                </td>
                                <td style="font-family:monospace;font-size:13px;color:var(--brand);">
                                    <?= htmlspecialchars($s['ip_address'] ?? '127.0.0.1') ?>
                                </td>
                                <td style="font-size:12px;color:var(--text-2);max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($s['user_agent'] ?? '') ?>">
                                    <?= htmlspecialchars($s['user_agent'] ?? 'Web Browser') ?>
                                </td>
                                <td style="font-size:12px;color:var(--text-3);">
                                    <?= htmlspecialchars($s['created_at'] ?? '—') ?>
                                </td>
                                <td style="font-size:12px;color:var(--text-2);font-weight:700;">
                                    <?= htmlspecialchars($s['last_active_at'] ?? '—') ?>
                                </td>
                                <td>
                                    <?php if ($isTerminated): ?>
                                        <span style="color:var(--red);font-weight:800;font-size:12px;background:var(--red-soft);padding:2px 8px;border-radius:6px;">
                                            ✕ Terminated
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--green);font-weight:800;font-size:12px;background:var(--green-soft);padding:2px 8px;border-radius:6px;">
                                            🟢 Active
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if (!$isTerminated): ?>
                                        <form method="POST" action="<?= url('dev/sessions/terminate/' . $s['id']) ?>" onsubmit="return confirm('Force terminate this session immediately?');" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--red);border:1px solid rgba(239,68,68,0.25);background:var(--red-soft);padding:3px 9px;font-size:11.5px;font-weight:800;" title="Terminate Session">
                                                🚨 Kill Session
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="font-size:12px;color:var(--text-3);">Revoked</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Security Watchdog & Abnormal Login Monitor -->
    <div class="panel" style="margin-top:24px;">
        <div class="panel-head">
            <h3>🚨 Intrusion & Abnormal Activity Watchdog</h3>
            <span style="font-size:13px;color:var(--text-3);">Monitored security incidents, failed authentications, and session termination events</span>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Incident Category</th>
                        <th>Action</th>
                        <th>Description & Telemetry</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($securityLogs)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center;padding:24px;color:var(--green);font-weight:700;">
                                ✓ No suspicious intrusions or abnormal security events detected.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($securityLogs as $log): ?>
                            <tr>
                                <td style="font-size:12px;color:var(--text-3);white-space:nowrap;">
                                    <?= htmlspecialchars($log['created_at']) ?>
                                </td>
                                <td style="font-weight:700;color:var(--brand);font-size:13px;">
                                    <?= htmlspecialchars($log['category']) ?>
                                </td>
                                <td>
                                    <span style="font-size:11.5px;font-weight:800;padding:2px 7px;border-radius:6px;background:var(--card-2);border:1px solid var(--border);color:var(--text);">
                                        <?= htmlspecialchars($log['action']) ?>
                                    </span>
                                </td>
                                <td style="font-size:13px;color:var(--text-2);max-width:380px;">
                                    <?= htmlspecialchars($log['description']) ?>
                                </td>
                                <td style="font-family:monospace;font-size:12px;color:var(--text-3);">
                                    <?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php };
require __DIR__ . '/../layouts/app.php';
?>
