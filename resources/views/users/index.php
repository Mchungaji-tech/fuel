<?php
    $content = function () use ($title, $users, $superAdminsCount, $opsAdminsCount, $auditorsCount) {
        $currentUser = current_user();
        $isSuper = is_super_admin();
?>
<section class="view active" id="view-users">
    <!-- Header & Action Bar -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
        <div class="hello">
            <h1>User Management & Access Control 👥</h1>
            <p>Super Admin portal to monitor staff, register new team accounts, configure role permissions, and oversee system operations.</p>
        </div>
        <div>
            <?php if ($isSuper): ?>
                <button class="btn btn-brand" onclick="document.getElementById('registerUserModal').classList.add('active')">＋ Register Staff User</button>
            <?php else: ?>
                <span style="font-size:12.5px;color:var(--text-3);font-weight:700;">Role management restricted to Super Admin</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($msg = flash('user_success')): ?>
        <div style="margin-top:16px;padding:12px 18px;background:var(--green-soft);border:1.5px solid var(--green);border-radius:10px;color:var(--green);font-weight:700;font-size:14px;">
            ✓ <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($err = flash('user_error')): ?>
        <div style="margin-top:16px;padding:12px 18px;background:var(--red-soft);border:1.5px solid var(--red);border-radius:10px;color:var(--red);font-weight:700;font-size:14px;">
            ⚠️ <?= htmlspecialchars($err) ?>
        </div>
    <?php endif; ?>

    <!-- Role Privilege Breakdown KPI Cards -->
    <div class="kpis" style="margin-top:20px;">
        <div class="kpi" style="border-top:4px solid #8B5CF6;">
            <div class="lbl" style="color:#8B5CF6;font-weight:800;">Super Admins</div>
            <div class="val"><?= $superAdminsCount ?> Accounts</div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Full master oversight, audit review & user control</div>
        </div>
        <div class="kpi" style="border-top:4px solid var(--brand);">
            <div class="lbl" style="color:var(--brand);font-weight:800;">Operations Admins</div>
            <div class="val"><?= $opsAdminsCount ?> Accounts</div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Daily fleet management, expenses, & dispatches</div>
        </div>
        <div class="kpi" style="border-top:4px solid var(--green);">
            <div class="lbl" style="color:var(--green);font-weight:800;">Staff / Auditors</div>
            <div class="val"><?= $auditorsCount ?> Accounts</div>
            <div style="font-size:13px;color:var(--text-3);margin-top:4px;">Read-only transaction monitoring & audit logging</div>
        </div>
    </div>

    <!-- Users Table Panel -->
    <div class="panel" style="margin-top:24px;">
        <div class="panel-head">
            <h3>Registered System Users & Staff Roster</h3>
            <span style="font-size:13px;color:var(--text-3);">Active access credentials and assigned role privileges</span>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>User Name</th>
                        <th>Email Address</th>
                        <th>Assigned Role</th>
                        <th>Activity / Status</th>
                        <th>System Permissions</th>
                        <th style="text-align:center;min-width:190px;">Management Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <?php
                            $roleLower = strtolower($u['role'] ?? 'admin');
                            $isUserSuper = str_contains($roleLower, 'super');
                            $isUserAuditor = str_contains($roleLower, 'audit') || str_contains($roleLower, 'staff');
                            $isSelf = ($currentUser && $currentUser['id'] == $u['id']);

                            $roleBadgeColor = 'var(--brand)';
                            $roleBadgeBg = 'var(--brand-soft)';
                            $roleLabel = 'Operations Admin';
                            $permDesc = 'Dispatches, fleet tankers, expenses, customer ledger, and driver compensation';

                            if ($isUserSuper) {
                                $roleBadgeColor = '#8B5CF6';
                                $roleBadgeBg = 'rgba(139,92,246,0.12)';
                                $roleLabel = 'Super Admin 👑';
                                $permDesc = 'Master control: user registration, role promotion, full transaction audit monitoring & fleet oversight';
                            } elseif ($isUserAuditor) {
                                $roleBadgeColor = 'var(--green)';
                                $roleBadgeBg = 'var(--green-soft)';
                                $roleLabel = 'Staff / Auditor 🔍';
                                $permDesc = 'Read-only compliance: monitoring fleet movements, reports, and real-time transaction audit trails';
                            }

                            // Activity & Online / Offline Status
                            $lastActive = $u['last_active_at'] ?? null;
                            $isOnline = false;
                            $activityLabel = 'Never logged in';
                            if ($lastActive) {
                                $diff = time() - strtotime($lastActive);
                                if ($diff <= 300) {
                                    $isOnline = true;
                                    $activityLabel = 'Active now';
                                } elseif ($diff < 3600) {
                                    $activityLabel = floor($diff / 60) . 'm ago';
                                } elseif ($diff < 86400) {
                                    $activityLabel = floor($diff / 3600) . 'h ago';
                                } else {
                                    $activityLabel = date('d/m/y', strtotime($lastActive));
                                }
                            }
                        ?>
                        <tr>
                            <td>
                                <div class="who">
                                    <div class="av" style="background:<?= $isUserSuper ? '#8B5CF6' : 'var(--brand)' ?>;color:#fff;font-weight:800;">
                                        <?= strtoupper(substr($u['name'], 0, 2)) ?>
                                    </div>
                                    <div>
                                        <b style="font-size:15px;color:var(--text);"><?= htmlspecialchars($u['name']) ?></b>
                                        <?php if ($isSelf): ?>
                                            <span style="margin-left:6px;font-size:11px;background:var(--card-2);border:1px solid var(--border);padding:1px 6px;border-radius:4px;color:var(--text-2);font-weight:700;">You</span>
                                        <?php endif; ?>
                                        <div style="font-size:11.5px;color:var(--text-3);margin-top:2px;">User ID: #USR-<?= str_pad($u['id'], 3, '0', STR_PAD_LEFT) ?></div>
                                    </div>
                                </div>
                            </td>

                            <td style="font-weight:600;color:var(--text-2);">
                                <?= htmlspecialchars($u['email']) ?>
                            </td>

                            <td>
                                <span style="display:inline-block;padding:3px 10px;border-radius:6px;font-weight:800;font-size:12px;color:<?= $roleBadgeColor ?>;background:<?= $roleBadgeBg ?>;border:1px solid <?= $roleBadgeColor ?>;">
                                    <?= $roleLabel ?>
                                </span>
                            </td>

                            <!-- Activity / Online Indicator -->
                            <td>
                                <?php if ($isOnline): ?>
                                    <span style="display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;background:var(--green-soft);border:1px solid var(--green);font-size:11.5px;font-weight:800;color:var(--green);">
                                        <span style="width:7px;height:7px;border-radius:50%;background:var(--green);box-shadow:0 0 6px var(--green);"></span>
                                        Online <small style="font-weight:600;opacity:0.85;">(Active now)</small>
                                    </span>
                                <?php else: ?>
                                    <span style="display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;background:var(--card-2);border:1px solid var(--border);font-size:11.5px;font-weight:700;color:var(--text-3);">
                                        <span style="width:7px;height:7px;border-radius:50%;background:var(--text-3);"></span>
                                        Offline <small style="color:var(--text-3);">(<?= $activityLabel ?>)</small>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td style="font-size:12.5px;color:var(--text-2);max-width:300px;line-height:1.45;">
                                <?= $permDesc ?>
                            </td>

                            <td style="text-align:center;white-space:nowrap;">
                                <?php if ($isSuper): ?>
                                    <div style="display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap;justify-content:center;">
                                        <!-- Edit Profile / Reset Password Button -->
                                        <button type="button" class="btn btn-sm btn-ghost" style="padding:4px 8px;font-size:12px;font-weight:700;" onclick="openEditUserModal(<?= htmlspecialchars(json_encode([
                                            'id' => $u['id'],
                                            'name' => $u['name'],
                                            'email' => $u['email'],
                                            'role' => $u['role'] ?? 'admin',
                                        ])) ?>)" title="Edit Name, Email, or Reset Password">
                                            ✏️ Edit
                                        </button>

                                        <!-- Force Logout Button (if active session exists and not self) -->
                                        <?php if (!empty($u['has_active_session']) && !$isSelf): ?>
                                            <form method="POST" action="<?= url('users/terminate/' . $u['id']) ?>" onsubmit="return confirm('Force terminate active session for <?= htmlspecialchars(addslashes($u['name'])) ?>? The user will be immediately logged out.');" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--red);border:1px solid rgba(239,68,68,0.3);background:var(--red-soft);padding:3px 7px;font-size:11.5px;font-weight:800;" title="Force Logout Active Session">
                                                    🚨 Logout
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Delete User -->
                                        <?php if (!$isSelf): ?>
                                            <form method="POST" action="<?= url('users/delete/' . $u['id']) ?>" onsubmit="return confirm('Permanently remove user <?= htmlspecialchars(addslashes($u['name'])) ?>?');" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--red);padding:4px 7px;" title="Delete User">✕</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="font-size:12px;color:var(--text-3);font-weight:600;">Active Account</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Register New User Modal -->
<div class="modal-backdrop" id="registerUserModal" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal-card" style="max-width:520px;">
        <div class="modal-head">
            <h2>👤 Register Staff User Account</h2>
            <button class="close-modal" onclick="document.getElementById('registerUserModal').classList.remove('active')">✕</button>
        </div>
        <form method="POST" action="<?= url('users/store') ?>">
            <?= csrf_field() ?>
            <div class="form-grid single" style="gap:14px;">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" placeholder="e.g. Samuel Kiprono" required>
                </div>

                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" placeholder="e.g. samuel@sarurafuel.co.ke" required>
                </div>

                <div class="form-group">
                    <label>Account Password *</label>
                    <input type="password" name="password" placeholder="Create a strong password" required minlength="6">
                </div>

                <div class="form-group">
                    <label>Assigned System Role *</label>
                    <select name="role" required>
                        <option value="admin" selected>Operations Admin (Manage fleet, dispatches, expenses & salaries)</option>
                        <option value="auditor">Staff / Auditor (Read-only monitoring of fleet, transactions & audits)</option>
                        <option value="super_admin">Super Admin (Full master oversight, user management & audit inspection)</option>
                    </select>
                </div>
            </div>

            <div style="margin-top:22px;display:flex;justify-content:flex-end;gap:12px;">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('registerUserModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-brand">Register User Account</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Profile & Reset Password Modal -->
<div class="modal-backdrop" id="editUserModal" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal-card" style="max-width:520px;">
        <div class="modal-head">
            <h2>✏️ Edit Staff Profile & Credentials</h2>
            <button class="close-modal" onclick="document.getElementById('editUserModal').classList.remove('active')">✕</button>
        </div>
        <form method="POST" id="editUserForm" action="">
            <?= csrf_field() ?>
            <div class="form-grid single" style="gap:14px;">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" id="eumName" required>
                </div>

                <div class="form-group">
                    <label>Work Email Address *</label>
                    <input type="email" name="email" id="eumEmail" required>
                </div>

                <div class="form-group">
                    <label>Assigned System Role *</label>
                    <select name="role" id="eumRole" required>
                        <option value="admin">Operations Admin (Manage fleet, dispatches, expenses & salaries)</option>
                        <option value="auditor">Staff / Auditor (Read-only monitoring)</option>
                        <option value="super_admin">Super Admin (Full master oversight)</option>
                    </select>
                </div>

                <div style="background:var(--card-2);border:1px solid var(--border);border-radius:10px;padding:12px;margin-top:4px;">
                    <div style="font-size:12.5px;font-weight:800;color:var(--brand);margin-bottom:6px;">🔑 PASSWORD RESET (OPTIONAL):</div>
                    <div style="font-size:12px;color:var(--text-3);margin-bottom:10px;">Leave blank to preserve user's current password. Enter a new password only if resetting.</div>
                    
                    <div class="form-group" style="margin-bottom:10px;">
                        <label style="font-size:12.5px;">New Password <small>(minimum 6 characters)</small></label>
                        <input type="password" name="password" minlength="6" placeholder="Leave blank to keep current">
                    </div>

                    <div class="form-group">
                        <label style="font-size:12.5px;">Confirm New Password</label>
                        <input type="password" name="password_confirmation" minlength="6" placeholder="Repeat new password">
                    </div>
                </div>
            </div>

            <div style="margin-top:22px;display:flex;justify-content:flex-end;gap:12px;">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('editUserModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-brand">Save User Updates</button>
            </div>
        </form>
    </div>
</div>

<script>
window.openEditUserModal = function(user) {
    document.getElementById('editUserForm').action = '<?= url("users/update") ?>/' + user.id;
    document.getElementById('eumName').value = user.name;
    document.getElementById('eumEmail').value = user.email;
    document.getElementById('eumRole').value = user.role;
    document.getElementById('editUserModal').classList.add('active');
};
</script>
<?php };
require __DIR__ . '/../layouts/app.php';
?>
