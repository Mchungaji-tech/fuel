<?php
    $message = $message ?? 'The page you requested does not exist or has been moved.';
    $title = $title ?? 'Page Not Found (404)';
    $content = function () use ($title, $message) { ?>
        <section class="view active" style="display:flex;align-items:center;justify-content:center;min-height:60vh;">
            <div class="panel" style="text-align:center;padding:48px 32px;max-width:500px;border-radius:20px;">
                <div style="font-size:56px;font-weight:800;color:var(--brand);margin-bottom:12px;">404</div>
                <h2 style="margin:0 0 10px;font-size:20px;"><?= htmlspecialchars($title) ?></h2>
                <p style="color:var(--text-2);margin-bottom:24px;"><?= htmlspecialchars($message) ?></p>
                <a href="<?= url('dashboard') ?>" class="btn btn-brand">Return to Dashboard</a>
            </div>
        </section>
    <?php };

    require __DIR__ . '/../layouts/app.php';
?>
