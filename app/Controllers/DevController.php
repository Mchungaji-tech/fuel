<?php

namespace App\Controllers;

use App\Core\Database;
use PDO;

class DevController
{
    public function index(): string
    {
        if (empty($_SESSION['is_logged_in'])) {
            redirect('/login');
        }

        // Only Super Admin and Developer have access to Dev Dashboard
        if (!is_super_admin() && !is_developer()) {
            flash('login_error', 'Access restricted to Developer and Super Administrator.');
            redirect('/dashboard');
        }

        $pdo = Database::connection();
        $isDev = is_developer();

        // Query active sessions (Developers can see all; Super Admin sees only non-dev sessions)
        if ($isDev) {
            $sessStmt = $pdo->query("SELECT s.*, u.name as user_name, u.email as user_email, u.role as user_role 
                FROM user_sessions s 
                LEFT JOIN users u ON s.user_id = u.id 
                ORDER BY s.last_active_at DESC LIMIT 50");
        } else {
            $sessStmt = $pdo->query("SELECT s.*, u.name as user_name, u.email as user_email, u.role as user_role 
                FROM user_sessions s 
                LEFT JOIN users u ON s.user_id = u.id 
                WHERE u.role != 'developer' 
                ORDER BY s.last_active_at DESC LIMIT 50");
        }
        $sessions = $sessStmt ? $sessStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        // Query abnormal / failed login security alerts from audit_logs
        $auditSecurity = $pdo->query("SELECT * FROM audit_logs 
            WHERE action LIKE '%FAIL%' OR action LIKE '%TERMINATE%' OR action LIKE '%SECURITY%' OR description LIKE '%failed%' OR description LIKE '%attack%' 
            ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

        // System telemetry
        $dbDriver = config('database.driver') ?? 'sqlite';
        $dbSize = 'N/A';
        $dbPath = config('database.sqlite.database') ?? base_path('storage/database.sqlite');
        if (file_exists($dbPath)) {
            $bytes = filesize($dbPath);
            $dbSize = number_format($bytes / 1024, 2) . ' KB';
        }

        $systemTelemetry = [
            'php_version' => PHP_VERSION,
            'os' => PHP_OS . ' (' . PHP_OS_FAMILY . ')',
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Apache/2.4 (XAMPP)',
            'db_driver' => strtoupper($dbDriver),
            'db_size' => $dbSize,
            'memory_used' => number_format(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'is_https' => is_https() ? 'Active (SSL Secured)' : 'Inactive (Plain HTTP)',
            'maint_mode' => is_maintenance_mode(),
            'maint_msg' => maintenance_message(),
        ];

        return view('dev.index', [
            'title' => 'Developer & Security Console — Sarura Fuel',
            'telemetry' => $systemTelemetry,
            'sessions' => $sessions,
            'securityLogs' => $auditSecurity,
        ]);
    }

    public function toggleMaintenance(): void
    {
        if (empty($_SESSION['is_logged_in'])) {
            redirect('/login');
        }

        if (!is_super_admin() && !is_developer()) {
            redirect('/dashboard');
        }

        $pdo = Database::connection();
        $current = is_maintenance_mode();
        $newVal = $current ? '0' : '1';
        $customMsg = trim($_POST['maintenance_message'] ?? '');

        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ?, updated_at = ? WHERE setting_key = 'maintenance_mode'");
        $stmt->execute([$newVal, date('Y-m-d H:i:s')]);

        if ($customMsg !== '') {
            $msgStmt = $pdo->prepare("UPDATE settings SET setting_value = ?, updated_at = ? WHERE setting_key = 'maintenance_message'");
            $msgStmt->execute([$customMsg, date('Y-m-d H:i:s')]);
        }

        $statusText = ($newVal === '1') ? 'ACTIVATED' : 'DEACTIVATED';
        log_audit(
            'System Security',
            'MAINTENANCE_TOGGLE',
            "Maintenance Mode {$statusText} by " . ($_SESSION['user']['name'] ?? 'System')
        );

        flash('dev_success', "Maintenance mode successfully {$statusText}.");
        redirect('/dev');
    }

    public function terminateSession(string $id): void
    {
        if (empty($_SESSION['is_logged_in'])) {
            redirect('/login');
        }

        if (!is_super_admin() && !is_developer()) {
            redirect('/dashboard');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("UPDATE user_sessions SET status = 'terminated' WHERE id = ?");
        $stmt->execute([(int) $id]);

        log_audit(
            'User Security',
            'KILL_SESSION',
            "Terminated session #{$id} by " . ($_SESSION['user']['name'] ?? 'System')
        );

        flash('dev_success', "Session #{$id} has been forcefully terminated.");
        redirect('/dev');
    }

    public function terminateAllUserSessions(string $userId): void
    {
        if (empty($_SESSION['is_logged_in'])) {
            redirect('/login');
        }

        if (!is_super_admin() && !is_developer()) {
            redirect('/dashboard');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("UPDATE user_sessions SET status = 'terminated' WHERE user_id = ?");
        $stmt->execute([(int) $userId]);

        log_audit(
            'User Security',
            'KILL_ALL_SESSIONS',
            "Terminated all active sessions for User #{$userId}"
        );

        flash('dev_success', "All active sessions for User #{$userId} have been revoked.");
        redirect('/dev');
    }
}
