<?php
    $loginError = $loginError ?? flash('login_error') ?? '';
    $content = function () use ($title, $loginError) {
?>
<section class="view active" id="view-login" style="display:flex;align-items:center;justify-content:center;min-height:85vh;width:100%;">
    <div class="panel" style="width:100%;max-width:480px;padding:34px;border-radius:22px;box-shadow:var(--shadow-lg);background:linear-gradient(180deg,var(--card),var(--card-2));border:1.5px solid var(--border);">
        <div style="text-align:center;margin-bottom:24px;">
            <div style="width:52px;height:52px;margin:0 auto 14px;border-radius:14px;display:grid;place-items:center;background:linear-gradient(135deg,#4F46E5,#7C3AED 55%,#F97316);color:#fff;font-weight:900;font-size:24px;box-shadow:0 8px 20px rgba(79,70,229,.35);">
                S
            </div>
            <span style="display:inline-flex;padding:6px 14px;border-radius:999px;background:var(--brand-soft);color:var(--brand);font-weight:800;font-size:12.5px;letter-spacing:.06em;text-transform:uppercase;">
                Operations & Fleet Portal
            </span>
            <h1 style="margin:12px 0 6px;font-size:24px;font-weight:900;">Welcome Back 👋</h1>
            <p style="margin:0;color:var(--text-2);font-size:14.5px;">Sign in to access your Sarura Fuel logistics dashboard.</p>
        </div>

        <?php if ($loginError !== ''): ?>
            <div style="margin-bottom:18px;background:var(--red-soft);border:1px solid var(--red);color:var(--red);border-radius:12px;padding:10px 14px;font-weight:700;font-size:13.5px;display:flex;align-items:center;gap:8px;">
                <span>⚠️</span>
                <div><?= htmlspecialchars($loginError) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= url('login') ?>" style="display:grid;gap:18px;">
            <?= csrf_field() ?>
            <div class="form-group">
                <label style="font-size:14px;font-weight:700;color:var(--text);">Email Address</label>
                <input type="email" name="email" value="admin@sarurafuel.co.ke" placeholder="admin@sarurafuel.co.ke" required style="width:100%;padding:12px 16px;border:1.5px solid var(--border-2);border-radius:12px;background:var(--card);color:var(--text);font-size:15px;" />
            </div>

            <div class="form-group">
                <label style="font-size:14px;font-weight:700;color:var(--text);">Password</label>
                <input type="password" name="password" value="admin123" placeholder="••••••••" required style="width:100%;padding:12px 16px;border:1.5px solid var(--border-2);border-radius:12px;background:var(--card);color:var(--text);font-size:15px;" />
            </div>

            <button type="submit" class="btn btn-brand" style="width:100%;padding:13px;font-size:16px;font-weight:800;border-radius:12px;margin-top:6px;">
                Sign In to Dashboard →
            </button>
        </form>

        <div style="margin-top:22px;padding-top:16px;border-top:1px solid var(--border);font-size:13.5px;color:var(--text-2);text-align:center;">
            Don't have an account? <a href="<?= url('register') ?>" style="color:var(--brand);font-weight:800;text-decoration:none;">Create an Account / Register →</a>
        </div>
    </div>
</section>
<?php };
require __DIR__ . '/../layouts/app.php';
?>
