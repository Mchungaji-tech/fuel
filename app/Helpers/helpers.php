<?php
/**
 * Checks whether the current request is over HTTPS.
 */
function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
        return true;
    }
    if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    if (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
        return true;
    }
    if (isset($_SERVER['HTTP_CF_VISITOR']) && str_contains($_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"')) {
        return true;
    }
    return false;
}

/**
 * Returns the base subfolder path where the application is hosted (e.g. '/fuel' or '').
 */
function app_subfolder(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($script));
    return ($dir === '/' || $dir === '.' || $dir === '\\') ? '' : rtrim($dir, '/');
}

/**
 * Generates an application URL or path.
 */
function url(string $path = ''): string
{
    $subfolder = app_subfolder();
    $cleanPath = ltrim($path, '/');

    if ($cleanPath === '') {
        return $subfolder === '' ? '/' : $subfolder;
    }

    return ($subfolder === '' ? '' : $subfolder) . '/' . $cleanPath;
}

/**
 * Alias for url()
 */
function base_url(string $path = ''): string
{
    return url($path);
}

/**
 * Returns the absolute filesystem base path of the project.
 */
function base_path(string $path = ''): string
{
    $root = dirname(__DIR__, 2);
    return $path === '' ? $root : $root . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
}

/**
 * Returns the normalized current route URI (without subfolder).
 */
function current_uri(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $subfolder = app_subfolder();

    if ($subfolder !== '' && str_starts_with($uri, $subfolder)) {
        $uri = substr($uri, strlen($subfolder));
    }

    $uri = '/' . trim($uri, '/');
    return $uri === '/' ? '/dashboard' : $uri;
}

/**
 * Returns 'active' CSS class if the given route path matches the current request URI.
 */
function isActiveNav(string $path): string
{
    $current = current_uri();
    $path = '/' . trim($path, '/');

    if ($path === $current || str_starts_with($current, $path . '/')) {
        return 'active';
    }

    return '';
}

/**
 * Renders a view template with provided data.
 */
function view(string $view, array $data = []): string
{
    $viewPath = base_path('resources/views/' . str_replace('.', '/', $view) . '.php');

    if (!file_exists($viewPath)) {
        throw new RuntimeException("View not found: {$view} (looked in: {$viewPath})");
    }

    extract($data);
    ob_start();
    require $viewPath;
    return ob_get_clean();
}

/**
 * Redirects to an internal path or URL.
 */
function redirect(string $path): void
{
    if (!str_starts_with($path, 'http://') && !str_starts_with($path, 'https://')) {
        $subfolder = app_subfolder();
        if ($subfolder !== '' && !str_starts_with($path, $subfolder)) {
            $path = url($path);
        }
    }

    header("Location: {$path}");
    exit;
}

/**
 * Retrieves or generates the active CSRF token.
 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

/**
 * Returns a hidden HTML input field containing the CSRF token.
 */
function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
}

/**
 * Verifies the incoming request's CSRF token.
 * Supports form inputs, custom headers, and authenticated AJAX requests.
 */
function verify_csrf(): bool
{
    $requestToken = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_GET['_csrf_token'] ?? '';
    $sessionToken = $_SESSION['_csrf_token'] ?? '';

    if (!empty($sessionToken) && !empty($requestToken) && hash_equals($sessionToken, $requestToken)) {
        return true;
    }

    // Support authenticated AJAX requests where session is verified
    if (!empty($_SESSION['is_logged_in']) && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }

    return false;
}

/**
 * Logs an event to the persistent audit_logs table.
 */
function log_audit(string $category, string $action, string $description, int $isDeleted = 0): void
{
    try {
        $pdo = \App\Core\Database::connection();
        $user = $_SESSION['user'] ?? null;
        $userName = $user['name'] ?? 'System / Admin';
        $userEmail = $user['email'] ?? 'admin@sarurafuel.co.ke';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $time = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare('INSERT INTO audit_logs (user_name, user_email, category, action, description, ip_address, is_deleted, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userName, $userEmail, $category, $action, $description, $ip, $isDeleted, $time]);
    } catch (\Throwable $e) {
        // Fail silently so audit logging never interrupts core transaction
    }
}

/**
 * Returns current authenticated user array.
 */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Checks if active user has Super Admin authority.
 */
function is_super_admin(): bool
{
    $role = strtolower($_SESSION['user']['role'] ?? '');
    return in_array($role, ['super_admin', 'super admin', 'owner']);
}

/**
 * Checks if active user has Operations Admin authority.
 */
function is_admin(): bool
{
    $role = strtolower($_SESSION['user']['role'] ?? '');
    return in_array($role, ['super_admin', 'super admin', 'admin', 'operations_admin', 'owner']);
}

/**
 * Checks if active user is Staff / Auditor (view-only).
 */
function is_auditor(): bool
{
    $role = strtolower($_SESSION['user']['role'] ?? '');
    return in_array($role, ['auditor', 'staff', 'viewer']);
}

/**
 * Checks if active user has Developer role.
 */
function is_developer(): bool
{
    $role = strtolower($_SESSION['user']['role'] ?? '');
    return in_array($role, ['developer', 'dev', 'engineer']);
}

/**
 * Checks if active user can view sensitive company financials (revenue, profit, driver salaries, expenses).
 * Super Admin and regular Admins can; Developer is strictly shielded from financial data.
 */
function can_view_financials(): bool
{
    if (is_developer()) {
        return false;
    }
    return is_admin() || is_super_admin();
}

/**
 * Checks if the system is currently in Maintenance Mode.
 */
function is_maintenance_mode(): bool
{
    try {
        $pdo = \App\Core\Database::connection();
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode' LIMIT 1");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return $val === '1' || $val === 1 || $val === 'true';
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Gets the current maintenance mode message.
 */
function maintenance_message(): string
{
    try {
        $pdo = \App\Core\Database::connection();
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_message' LIMIT 1");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return $val ?: 'Sarura Fuel Logistics Cloud is currently undergoing scheduled technical maintenance. Please check back shortly.';
    } catch (\Throwable $e) {
        return 'System under maintenance.';
    }
}

/**
 * Records session heartbeat and updates user last_active_at timestamp.
 */
function record_session_heartbeat(): void
{
    if (empty($_SESSION['is_logged_in']) || empty($_SESSION['user']['id'])) {
        return;
    }

    try {
        $userId = (int) $_SESSION['user']['id'];
        $pdo = \App\Core\Database::connection();
        $now = date('Y-m-d H:i:s');

        // Update user last active
        $pdo->prepare("UPDATE users SET last_active_at = ? WHERE id = ?")->execute([$now, $userId]);

        // Ensure user session token exists in session
        if (empty($_SESSION['session_token'])) {
            $_SESSION['session_token'] = bin2hex(random_bytes(16));
        }
        $token = $_SESSION['session_token'];
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 250);

        // Check if session row exists
        $sStmt = $pdo->prepare("SELECT id, status FROM user_sessions WHERE session_token = ? LIMIT 1");
        $sStmt->execute([$token]);
        $sess = $sStmt->fetch(\PDO::FETCH_ASSOC);

        if ($sess) {
            $pdo->prepare("UPDATE user_sessions SET last_active_at = ?, ip_address = ? WHERE id = ?")->execute([$now, $ip, $sess['id']]);
        } else {
            $pdo->prepare("INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, status, created_at, last_active_at) VALUES (?, ?, ?, ?, 'active', ?, ?)")
                ->execute([$userId, $token, $ip, $agent, $now, $now]);
        }
    } catch (\Throwable $e) {
        // Silently continue
    }
}

/**
 * Checks if the current session was forcefully terminated by an administrator.
 */
function is_current_session_terminated(): bool
{
    if (empty($_SESSION['session_token'])) {
        return false;
    }

    try {
        $pdo = \App\Core\Database::connection();
        $stmt = $pdo->prepare("SELECT status FROM user_sessions WHERE session_token = ? LIMIT 1");
        $stmt->execute([$_SESSION['session_token']]);
        $status = $stmt->fetchColumn();
        return $status === 'terminated';
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Flashes a message or gets a flashed message.
 */
function flash(string $key, mixed $message = null): mixed
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }

    if (isset($_SESSION['_flash'][$key])) {
        $val = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);
        return $val;
    }

    return null;
}

/**
 * Returns the currently active system currency ('USD' or 'KES'). Default is 'USD'.
 */
function current_currency(): string
{
    return $_SESSION['currency'] ?? 'USD';
}

/**
 * Returns the exchange rate from USD to KES.
 */
function exchange_rate(): float
{
    return 130.0;
}

/**
 * Returns the currency symbol or prefix for the active currency.
 */
function app_currency_symbol(): string
{
    return current_currency() === 'KES' ? 'KES' : '$';
}

/**
 * Converts a base USD amount into the currently active currency value.
 */
function convert_currency(float|int|string|null $amountInUsd): float
{
    $clean = (float) preg_replace('/[^0-9.-]/', '', (string) $amountInUsd);
    if (current_currency() === 'KES') {
        return $clean * exchange_rate();
    }
    return $clean;
}

/**
 * Formats a monetary amount (stored as USD) into the active currency string.
 * Example: "$ 1,250.00" or "KES 162,500.00".
 */
function format_money(float|int|string|null $amountInUsd, bool $includeSymbol = true): string
{
    $clean = (float) preg_replace('/[^0-9.-]/', '', (string) $amountInUsd);
    $isKes = current_currency() === 'KES';
    $val = $isKes ? ($clean * exchange_rate()) : $clean;
    $formatted = number_format($val, 2);

    if (!$includeSymbol) {
        return $formatted;
    }

    return $isKes ? "KES {$formatted}" : "$ {$formatted}";
}

/**
 * Formats a date string into DD/MM/YY format (e.g. '30/08/26', with 2-digit year 26 not 2026).
 */
function format_date_dol(?string $dateStr): string
{
    if (empty($dateStr)) {
        return '—';
    }

    $timestamp = strtotime($dateStr);
    if (!$timestamp) {
        return htmlspecialchars($dateStr);
    }

    return date('d/m/y', $timestamp);
}


