<?php
    $content = function () use ($title, $leads) { ?>
        <section class="view active" id="view-leads">
            <div class="hello"><h1>Leads & CRM 🤝</h1><p>Pipeline activity, conversion opportunities and follow-ups.</p></div>
            <div class="chips">
                <span class="chip hot">Qualified (11)</span>
                <span class="chip">Proposal (4)</span>
                <span class="chip">New (9)</span>
            </div>
            <div class="panel" style="margin-top:16px">
                <table>
                    <thead>
                        <tr>
                            <th>Lead</th>
                            <th>Source</th>
                            <th>Stage</th>
                            <th>Owner</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $lead): ?>
                            <tr>
                                <td><?= htmlspecialchars($lead['name']) ?></td>
                                <td><?= htmlspecialchars($lead['source']) ?></td>
                                <td><span class="pill" style="background:var(--blue-soft);color:var(--blue)"><?= htmlspecialchars($lead['stage']) ?></span></td>
                                <td><?= htmlspecialchars($lead['owner']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php };

    require __DIR__ . '/../layouts/app.php';
?>
