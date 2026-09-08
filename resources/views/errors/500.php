<?php
    $message = $message ?? 'An unexpected internal server error occurred. Our technical operations team has been notified.';
    $title = $title ?? 'Internal Server Error (500)';
    $content = function () use ($title, $message) { ?>
        <section class="view active" style="display:flex;align-items:center;justify-content:center;min-height:60vh;">
            <div class="panel" style="text-align:center;padding:48px 32px;max-width:520px;border-radius:20px;">
                <div style="font-size:56px;font-weight:800;color:var(--red);margin-bottom:12px;">500</div>
                <h2 style="margin:0 0 10px;font-size:22px;"><?= htmlspecialchars($title) ?></h2>
                <p style="color:var(--text-2);margin-bottom:24px;line-height:1.6;"><?= htmlspecialchars($message) ?></p>
                <div style="display:flex;gap:12px;justify-content:center;">
                    <a href="<?= url('dashboard') ?>" class="btn btn-brand">Return to Dashboard</a>
                    <button onclick="window.location.reload()" class="btn btn-ghost">↻ Retry Page</button>
                </div>
            </div>
        </section>
    <?php };

    require __DIR__ . '/../layouts/app.php';
?>
