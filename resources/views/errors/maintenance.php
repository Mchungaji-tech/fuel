<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Under Maintenance — Sarura Fuel') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0B0F19;
            --card: #111827;
            --card-border: #1F2937;
            --text: #F9FAFB;
            --text-muted: #9CA3AF;
            --brand: #F97316;
            --brand-gradient: linear-gradient(135deg, #F97316 0%, #EA580C 100%);
            --blue: #3B82F6;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg);
            color: var(--text);
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background-image: radial-gradient(circle at 50% 20%, rgba(249, 115, 22, 0.08) 0%, transparent 60%);
        }
        .maint-card {
            max-width: 580px;
            width: 100%;
            background: var(--card);
            border: 1.5px solid var(--card-border);
            border-radius: 24px;
            padding: 44px 36px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }
        .maint-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--brand-gradient);
        }
        .icon-box {
            width: 76px;
            height: 76px;
            border-radius: 20px;
            background: rgba(249, 115, 22, 0.12);
            color: var(--brand);
            display: grid;
            place-items: center;
            font-size: 36px;
            margin: 0 auto 24px;
            border: 1px solid rgba(249, 115, 22, 0.25);
            box-shadow: 0 10px 25px -5px rgba(249, 115, 22, 0.2);
            animation: pulse 2.5s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 999px;
            background: rgba(249, 115, 22, 0.15);
            color: var(--brand);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 16px;
        }
        .badge::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--brand);
            box-shadow: 0 0 8px var(--brand);
        }
        h1 {
            font-size: 28px;
            font-weight: 900;
            letter-spacing: -0.02em;
            margin-bottom: 14px;
            line-height: 1.25;
        }
        p {
            color: var(--text-muted);
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 28px;
        }
        .status-box {
            background: rgba(31, 41, 55, 0.6);
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 28px;
            font-size: 13.5px;
            color: #D1D5DB;
            text-align: left;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .admin-link {
            font-size: 13.5px;
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s;
        }
        .admin-link b {
            color: var(--brand);
        }
        .admin-link:hover {
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="maint-card">
        <div class="icon-box">⚙️</div>
        <div class="badge">Scheduled System Maintenance</div>
        <h1>System Upgrades in Progress</h1>
        <p><?= htmlspecialchars($message ?? 'Sarura Fuel Logistics Cloud is currently undergoing scheduled engineering maintenance to ensure optimal performance, security, and accuracy.') ?></p>
        
        <div class="status-box">
            <span style="font-size:18px;">🛡️</span>
            <div>
                <b style="color:#fff;display:block;margin-bottom:3px;">Logistics Network Standing By</b>
                Database, telemetry, and security services are being optimized. All ongoing dispatches and telemetry remain safe and tracked.
            </div>
        </div>

        <div>
            <a href="<?= url('login') ?>" class="admin-link">
                System Administrator or Engineer? <b>Sign In to Access Portal →</b>
            </a>
        </div>
    </div>
</body>
</html>
