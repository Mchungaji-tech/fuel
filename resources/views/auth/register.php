<?php
    $registerError = $registerError ?? flash('register_error') ?? '';
    $registerSuccess = $registerSuccess ?? flash('register_success') ?? '';
    $content = function () use ($title, $registerError, $registerSuccess) {
?>
<section class="view active" id="view-register" style="display:flex;align-items:center;justify-content:center;min-height:85vh;width:100%;padding:20px 0;">
    <div class="panel" style="width:100%;max-width:500px;padding:36px;border-radius:22px;box-shadow:var(--shadow-lg);background:linear-gradient(180deg,var(--card),var(--card-2));border:1.5px solid var(--border);">
        <div style="text-align:center;margin-bottom:24px;">
            <div style="width:52px;height:52px;margin:0 auto 14px;border-radius:14px;display:grid;place-items:center;background:linear-gradient(135deg,#4F46E5,#7C3AED 55%,#F97316);color:#fff;font-weight:900;font-size:24px;box-shadow:0 8px 20px rgba(79,70,229,.35);">
                S
            </div>
            <span style="display:inline-flex;padding:6px 14px;border-radius:999px;background:var(--brand-soft);color:var(--brand);font-weight:800;font-size:12.5px;letter-spacing:.06em;text-transform:uppercase;">
                New Account Registration
            </span>
            <h1 style="margin:12px 0 6px;font-size:24px;font-weight:900;">Create Your Account 🚀</h1>
            <p style="margin:0;color:var(--text-2);font-size:14px;">Set up administrative access for Sarura Fuel Logistics Cloud.</p>
        </div>

        <?php if ($registerError !== ''): ?>
            <div style="margin-bottom:18px;background:var(--red-soft);border:1px solid var(--red);color:var(--red);border-radius:12px;padding:12px 14px;font-weight:700;font-size:13.5px;display:flex;align-items:center;gap:8px;">
                <span>⚠️</span>
                <div><?= htmlspecialchars($registerError) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($registerSuccess !== ''): ?>
            <div style="margin-bottom:18px;background:var(--green-soft);border:1px solid var(--green);color:var(--green);border-radius:12px;padding:12px 14px;font-weight:700;font-size:13.5px;display:flex;align-items:center;gap:8px;">
                <span>✓</span>
                <div><?= htmlspecialchars($registerSuccess) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= url('register') ?>" style="display:grid;gap:16px;">
            <?= csrf_field() ?>
            
            <div class="form-group">
                <label style="font-size:13.5px;font-weight:700;color:var(--text);">Full Name *</label>
                <input type="text" name="name" placeholder="e.g. Alex Kiprop" required autofocus style="width:100%;padding:11px 15px;border:1.5px solid var(--border-2);border-radius:11px;background:var(--card);color:var(--text);font-size:14.5px;" />
            </div>

            <div class="form-group">
                <label style="font-size:13.5px;font-weight:700;color:var(--text);">Work Email Address *</label>
                <input type="email" name="email" placeholder="e.g. alex@sarurafuel.co.ke" required style="width:100%;padding:11px 15px;border:1.5px solid var(--border-2);border-radius:11px;background:var(--card);color:var(--text);font-size:14.5px;" />
            </div>

            <div class="form-group">
                <label style="font-size:13.5px;font-weight:700;color:var(--text);">Password * <small style="color:var(--text-3);font-weight:500;">(minimum 6 characters)</small></label>
                <input type="password" name="password" placeholder="••••••••" minlength="6" required style="width:100%;padding:11px 15px;border:1.5px solid var(--border-2);border-radius:11px;background:var(--card);color:var(--text);font-size:14.5px;" />
            </div>

            <div class="form-group">
                <label style="font-size:13.5px;font-weight:700;color:var(--text);">Confirm Password *</label>
                <input type="password" name="password_confirmation" placeholder="••••••••" minlength="6" required style="width:100%;padding:11px 15px;border:1.5px solid var(--border-2);border-radius:11px;background:var(--card);color:var(--text);font-size:14.5px;" />
            </div>

            <div style="background:var(--card-2);padding:10px 14px;border-radius:10px;border:1px solid var(--border);font-size:12.5px;color:var(--text-2);">
                🛡️ <b>Security Notice:</b> The first account registered on a fresh system will automatically be designated as <b>Super Admin</b> with full administrative privileges.
            </div>

            <button type="submit" class="btn btn-brand" style="width:100%;padding:13px;font-size:15.5px;font-weight:800;border-radius:12px;margin-top:6px;">
                Complete Registration & Sign In →
            </button>
        </form>

        <div style="margin-top:22px;padding-top:16px;border-top:1px solid var(--border);font-size:13.5px;color:var(--text-2);text-align:center;">
            Already have an account? <a href="<?= url('login') ?>" style="color:var(--brand);font-weight:800;text-decoration:none;">Sign In Here →</a>
        </div>
    </div>
</section>
<?php };
require __DIR__ . '/../layouts/app.php';
?>
