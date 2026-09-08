<?php
    $error = flash('error');
    $content = function () use ($title, $documents, $error) { ?>
        <section class="view active" id="view-documents">
            <div class="hello"><h1>Documents 📁</h1><p>Compliance records, licenses and key company documents.</p></div>

            <?php if ($error): ?>
                <div class="pill" style="margin-top:12px;background:var(--red-soft);color:var(--red);padding:8px 14px;font-weight:600;display:inline-flex;">
                    ⚠️ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="panel" style="margin-top:16px;padding:20px;">
                <form method="POST" action="<?= url('documents/upload') ?>" enctype="multipart/form-data" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;">
                    <?= csrf_field() ?>
                    <label style="flex:1 1 260px;">
                        <span>Upload file (PDF, PNG, JPG, WEBP, max 10MB)</span>
                        <input type="file" name="document" accept=".pdf,.png,.jpg,.jpeg,.webp,.docx,.xlsx" required />
                    </label>
                    <button type="submit" class="btn btn-brand">Upload document</button>
                </form>
            </div>

            <div class="panel" style="margin-top:16px">
                <table>
                    <thead>
                        <tr>
                            <th>Document</th>
                            <th>Type</th>
                            <th>File</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($documents)): ?>
                            <tr>
                                <td colspan="4" style="text-align:center;padding:32px;color:var(--text-3);">No documents uploaded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($documents as $document): ?>
                                <tr>
                                    <td><b><?= htmlspecialchars($document['name']) ?></b></td>
                                    <td><?= htmlspecialchars($document['type']) ?></td>
                                    <td><code><?= htmlspecialchars($document['file']) ?></code></td>
                                    <td><span class="pill" style="background:var(--blue-soft);color:var(--blue)"><?= htmlspecialchars($document['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php };

    require __DIR__ . '/../layouts/app.php';
?>
