<?php
/**
 * Sarura Fuel Logistics - cPanel Pre-Flight & Health Diagnostic Tool
 * Access via: https://yourdomain.com/fuel/cpanel_test.php
 */

require_once __DIR__ . '/bootstrap/app.php';

use App\Core\Database;

$action = $_GET['action'] ?? '';
$initResult = null;

// Handle manual schema initialization if requested
if ($action === 'init_db') {
    try {
        $pdo = Database::getMysqlConnection();
        if ($pdo) {
            Database::initializeSchema($pdo);
            $initResult = ['success' => true, 'message' => 'Successfully initialized all tables and seed records in MySQL!'];
        }
    } catch (\Throwable $e) {
        $initResult = ['success' => false, 'message' => 'Schema initialization failed: ' . $e->getMessage()];
    }
}

// 1. Diagnostics Checks
$phpVersion = PHP_VERSION;
$phpPass = version_compare($phpVersion, '8.0.0', '>=');

$requiredExts = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'session', 'fileinfo'];
$extResults = [];
foreach ($requiredExts as $ext) {
    $extResults[$ext] = extension_loaded($ext);
}
$sqliteLoaded = extension_loaded('pdo_sqlite');

// 2. Storage Writable Check
$storagePath = __DIR__ . '/storage';
$storageWritable = is_writable($storagePath);
$uploadsPath = __DIR__ . '/storage/uploads';
$uploadsWritable = is_dir($uploadsPath) && is_writable($uploadsPath);

// 3. MySQL Connection Check
$config = config('database.mysql', []);
$dbHost = $config['host'] ?? 'localhost';
$dbName = $config['database'] ?? 'tektxbzg_fuel';
$dbUser = $config['username'] ?? 'tektxbzg_fuel';

$mysqlStatus = false;
$mysqlError = null;
$tableCount = 0;

try {
    $mysqlPdo = Database::getMysqlConnection();
    if ($mysqlPdo instanceof PDO) {
        $mysqlStatus = true;
        $tables = $mysqlPdo->query("SHOW TABLES LIKE '%'")->fetchAll(PDO::FETCH_COLUMN);
        $tableCount = count($tables);
    }
} catch (\Throwable $e) {
    $mysqlError = $e->getMessage();
}

// 4. HTTPS and Subfolder Check
$isHttps = is_https();
$subfolder = app_subfolder();
$activeDriver = Database::getActiveDriver();
$isFallback = Database::isOfflineFallback();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>cPanel Diagnostic & Verification — Sarura Fuel</title>
<style>
  :root {
    --brand: #4F46E5;
    --green: #059669;
    --green-soft: #D1FAE5;
    --red: #DC2626;
    --red-soft: #FEE2E2;
    --amber: #D97706;
    --amber-soft: #FEF3C7;
    --card: #FFFFFF;
    --bg: #F8FAFC;
    --border: #E2E8F0;
    --text: #0F172A;
    --text-2: #64748B;
  }
  body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: var(--bg);
    color: var(--text);
    padding: 30px 16px;
    margin: 0;
  }
  .container {
    max-width: 760px;
    margin: 0 auto;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.05);
  }
  h1 { font-size: 22px; font-weight: 800; margin: 0 0 6px; display: flex; align-items: center; gap: 10px; }
  p.subtitle { color: var(--text-2); font-size: 14px; margin: 0 0 24px; }
  .grid { display: grid; gap: 14px; margin-bottom: 24px; }
  .item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 18px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: #FFF;
  }
  .item-title { font-weight: 700; font-size: 14.5px; }
  .item-desc { font-size: 12.5px; color: var(--text-2); margin-top: 2px; }
  .badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 99px;
    font-size: 12.5px;
    font-weight: 700;
  }
  .badge-pass { background: var(--green-soft); color: var(--green); }
  .badge-fail { background: var(--red-soft); color: var(--red); }
  .badge-warn { background: var(--amber-soft); color: var(--amber); }
  .btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--brand);
    color: #FFF;
    font-weight: 700;
    font-size: 14px;
    padding: 10px 18px;
    border-radius: 9px;
    text-decoration: none;
    border: 0;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(79,70,229,0.25);
  }
  .btn:hover { opacity: 0.92; }
  .notice {
    padding: 14px 18px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 600;
    margin-bottom: 20px;
  }
  .notice-success { background: var(--green-soft); color: var(--green); border: 1px solid var(--green); }
  .notice-error { background: var(--red-soft); color: var(--red); border: 1px solid var(--red); }
  .notice-info { background: #EEF2FF; color: var(--brand); border: 1px solid #C7D2FE; }
</style>
</head>
<body>

<div class="container">
  <h1>🛡️ Sarura Fuel — cPanel Diagnostics</h1>
  <p class="subtitle">Verifying environment, HTTPS security, MySQL database, and storage permissions.</p>

  <?php if ($initResult): ?>
    <div class="notice <?= $initResult['success'] ? 'notice-success' : 'notice-error' ?>">
      <?= htmlspecialchars($initResult['message']) ?>
    </div>
  <?php endif; ?>

  <div class="grid">
    <!-- PHP Version -->
    <div class="item">
      <div>
        <div class="item-title">PHP Runtime Version</div>
        <div class="item-desc">Current: PHP <?= htmlspecialchars($phpVersion) ?> (Requires &ge; 8.0)</div>
      </div>
      <span class="badge <?= $phpPass ? 'badge-pass' : 'badge-fail' ?>">
        <?= $phpPass ? '✓ PHP 8+ OK' : '✕ Outdated PHP' ?>
      </span>
    </div>

    <!-- Required Extensions -->
    <div class="item">
      <div>
        <div class="item-title">PHP Extensions (PDO MySQL, mbstring, json, etc.)</div>
        <div class="item-desc">Checking pdo_mysql, pdo_sqlite, mbstring, json, session</div>
      </div>
      <?php $allExtsPass = !in_array(false, $extResults, true); ?>
      <span class="badge <?= $allExtsPass ? 'badge-pass' : 'badge-warn' ?>">
        <?= $allExtsPass ? '✓ All Extensions Loaded' : '⚠️ Missing Extensions' ?>
      </span>
    </div>

    <!-- HTTPS Detection -->
    <div class="item">
      <div>
        <div class="item-title">HTTPS & SSL Protocol</div>
        <div class="item-desc">Detects standard HTTPS, Cloudflare headers, and reverse proxies</div>
      </div>
      <span class="badge <?= $isHttps ? 'badge-pass' : 'badge-warn' ?>">
        <?= $isHttps ? '🔒 Secure HTTPS Active' : '🔓 HTTP (Non-SSL)' ?>
      </span>
    </div>

    <!-- Subfolder Routing -->
    <div class="item">
      <div>
        <div class="item-title">Application Routing Base</div>
        <div class="item-desc">Detected Subfolder: <code><?= htmlspecialchars($subfolder ?: '/') ?></code></div>
      </div>
      <span class="badge badge-pass">✓ Routing Ready</span>
    </div>

    <!-- Storage Writable -->
    <div class="item">
      <div>
        <div class="item-title">Storage & Uploads Writable</div>
        <div class="item-desc">Path: <code>/storage</code> &amp; <code>/storage/uploads</code> (chmod 755/775)</div>
      </div>
      <span class="badge <?= ($storageWritable && $uploadsWritable) ? 'badge-pass' : 'badge-warn' ?>">
        <?= ($storageWritable && $uploadsWritable) ? '✓ Storage Writable' : '⚠️ Permission Warning' ?>
      </span>
    </div>

    <!-- MySQL Database Connection -->
    <div class="item">
      <div>
        <div class="item-title">MySQL Database Connection</div>
        <div class="item-desc">
          Database: <code><?= htmlspecialchars($dbName) ?></code> | User: <code><?= htmlspecialchars($dbUser) ?></code> | Host: <code><?= htmlspecialchars($dbHost) ?></code>
          <?php if ($mysqlError): ?>
            <div style="color:var(--red);margin-top:4px;font-size:12px;">Error: <?= htmlspecialchars($mysqlError) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <span class="badge <?= $mysqlStatus ? 'badge-pass' : 'badge-fail' ?>">
        <?= $mysqlStatus ? "✓ Connected ({$tableCount} Tables)" : '✕ MySQL Offline' ?>
      </span>
    </div>

    <!-- Active Driver Status -->
    <div class="item">
      <div>
        <div class="item-title">Active Database Driver</div>
        <div class="item-desc">Current driver in use by application</div>
      </div>
      <span class="badge <?= $activeDriver === 'mysql' ? 'badge-pass' : 'badge-warn' ?>">
        <?= $activeDriver === 'mysql' ? '🟢 Online (MySQL)' : '🟡 Offline Fallback (SQLite)' ?>
      </span>
    </div>
  </div>

  <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:20px;">
    <?php if ($mysqlStatus && $tableCount === 0): ?>
      <a href="?action=init_db" class="btn">⚡ Initialize Database Tables Now</a>
    <?php endif; ?>
    <a href="<?= url('login') ?>" class="btn" style="background:#0F172A;">Go to Application Login &rarr;</a>
  </div>

  <div class="notice notice-info" style="margin-top:24px;">
    💡 <b>Security Tip:</b> Once you have verified your deployment and database connection on cPanel, you can safely remove or restrict access to <code>cpanel_test.php</code>.
  </div>
</div>

</body>
</html>
