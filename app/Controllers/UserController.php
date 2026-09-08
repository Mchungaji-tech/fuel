<?php

namespace App\Controllers;

use App\Core\Database;
use PDO;

class UserController
{
    public function index(): string
    {
        if (empty($_SESSION['is_logged_in'])) {
            redirect('/login');
        }

        $pdo = Database::connection();
        // DEVELOPER STEALTH: Completely hide developer accounts from Super Admin & standard staff
        $users = $pdo->query("SELECT id, name, email, role, last_active_at, last_login_at, is_active FROM users WHERE role != 'developer' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

        // Check active sessions for each user
        foreach ($users as &$u) {
            $sCount = (int) $pdo->query("SELECT COUNT(*) FROM user_sessions WHERE user_id = {$u['id']} AND status = 'active'")->fetchColumn();
            $u['has_active_session'] = ($sCount > 0);
        }
        unset($u);

        $superAdminsCount = 0;
        $opsAdminsCount = 0;
        $auditorsCount = 0;

        foreach ($users as $u) {
            $r = strtolower($u['role'] ?? '');
            if (str_contains($r, 'super')) {
                $superAdminsCount++;
            } elseif (str_contains($r, 'audit') || str_contains($r, 'staff')) {
                $auditorsCount++;
            } else {
                $opsAdminsCount++;
            }
        }

        return view('users.index', [
            'title' => 'User Management & Access Control — Sarura Fuel',
            'users' => $users,
            'superAdminsCount' => $superAdminsCount,
            'opsAdminsCount' => $opsAdminsCount,
            'auditorsCount' => $auditorsCount,
        ]);
    }

    public function store(): void
    {
        if (empty($_SESSION['is_logged_in'])) {
            redirect('/login');
        }

        if (!is_super_admin() && !is_admin()) {
            flash('user_error', 'Access denied. Only Super Admin can register staff users.');
            redirect('/users');
        }

        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = trim($_POST['role'] ?? 'admin');

        if ($name === '' || $email === '' || $password === '') {
            flash('user_error', 'Name, email, and a secure password are required.');
            redirect('/users');
        }

        $pdo = Database::connection();

        // Check if email already registered
        $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            flash('user_error', "User account with email '{$email}' already exists.");
            redirect('/users');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $email, $hash, $role]);

        log_audit('User Security', 'REGISTER_USER', "Registered user {$name} ({$email}) with role {$role}");
        flash('user_success', "User '{$name}' registered successfully with role [{$role}].");
        redirect('/users');
    }

    public function update(string $id): void
    {
        if (empty($_SESSION['is_logged_in'])) {
            redirect('/login');
        }

        if (!is_super_admin()) {
            flash('user_error', 'Access denied. Only Super Admin can modify user profiles or reset passwords.');
            redirect('/users');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            flash('user_error', 'User record not found.');
            redirect('/users');
        }

        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $role = trim($_POST['role'] ?? $user['role']);
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirmation'] ?? '');

        if ($name === '' || $email === '') {
            flash('user_error', 'Name and work email are required.');
            redirect('/users');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('user_error', 'Please provide a valid work email address.');
            redirect('/users');
        }

        // Check unique email across other users
        $chk = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = ? AND id != ? LIMIT 1');
        $chk->execute([$email, (int) $id]);
        if ($chk->fetch()) {
            flash('user_error', "Email '{$email}' is already in use by another user account.");
            redirect('/users');
        }

        // Handle password reset
        if ($password !== '') {
            if (strlen($password) < 6) {
                flash('user_error', 'New password must be at least 6 characters long.');
                redirect('/users');
            }
            if ($password !== $passwordConfirm) {
                flash('user_error', 'Password confirmation does not match.');
                redirect('/users');
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $upd = $pdo->prepare('UPDATE users SET name = ?, email = ?, role = ?, password = ? WHERE id = ?');
            $upd->execute([$name, $email, $role, $hash, (int) $id]);
            log_audit('User Security', 'RESET_PASSWORD', "Updated profile and reset password for user {$name} ({$email})");
            flash('user_success', "User profile for '{$name}' and password successfully updated.");
        } else {
            $upd = $pdo->prepare('UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?');
            $upd->execute([$name, $email, $role, (int) $id]);
            log_audit('User Security', 'UPDATE_USER', "Updated profile for user {$name} ({$email}) to role [{$role}]");
            flash('user_success', "User profile for '{$name}' successfully updated.");
        }

        // If the current user edited their own profile, update active session
        if (!empty($_SESSION['user']['id']) && (int)$_SESSION['user']['id'] === (int)$id) {
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['role'] = $role;
        }

        redirect('/users');
    }

    public function terminate(string $id): void
    {
        if (empty($_SESSION['is_logged_in'])) {
            redirect('/login');
        }

        if (!is_super_admin()) {
            flash('user_error', 'Access denied. Only Super Admin can terminate user sessions.');
            redirect('/users');
        }

        $currentUserId = $_SESSION['user']['id'] ?? 0;
        if ((int) $id === (int) $currentUserId) {
            flash('user_error', 'You cannot force-logout your own active administrative session.');
            redirect('/users');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $upd = $pdo->prepare("UPDATE user_sessions SET status = 'terminated' WHERE user_id = ?");
            $upd->execute([(int) $id]);

            log_audit('User Security', 'TERMINATE_SESSION', "Forcefully terminated active sessions for staff user {$user['name']} ({$user['email']})");
            flash('user_success', "Active session(s) for {$user['name']} were terminated. The user has been logged out.");
        }

        redirect('/users');
    }

    public function updateRole(string $id): void
    {
        if (empty($_SESSION['is_logged_in'])) {
            redirect('/login');
        }

        if (!is_super_admin()) {
            flash('user_error', 'Access denied. Only Super Admin can modify user roles.');
            redirect('/users');
        }

        $newRole = trim($_POST['role'] ?? 'admin');
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $update = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
            $update->execute([$newRole, (int) $id]);

            log_audit('User Security', 'UPDATE_ROLE', "Updated role for {$user['name']} ({$user['email']}) to {$newRole}");
            flash('user_success', "Role for {$user['name']} updated to {$newRole}.");
        }

        redirect('/users');
    }

    public function delete(string $id): void
    {
        if (empty($_SESSION['is_logged_in'])) {
            redirect('/login');
        }

        if (!is_super_admin()) {
            flash('user_error', 'Access denied. Only Super Admin can remove user accounts.');
            redirect('/users');
        }

        $currentUserId = $_SESSION['user']['id'] ?? 0;
        if ((int) $id === (int) $currentUserId) {
            flash('user_error', 'You cannot delete your own active administrator account.');
            redirect('/users');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $del = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $del->execute([(int) $id]);

            log_audit('User Security', 'DELETE_USER', "Deleted staff user account {$user['name']} ({$user['email']})", 1);
            flash('user_success', "User account {$user['name']} has been removed.");
        }

        redirect('/users');
    }
}
