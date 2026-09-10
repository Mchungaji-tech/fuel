<?php

namespace App\Controllers;

use App\Core\Database;

class AuthController
{
    public function login(): string
    {
        if (!empty($_SESSION['is_logged_in'])) {
            redirect('/dashboard');
        }

        $loginError = flash('login_error') ?? '';
        return view('auth.login', [
            'title' => 'Login — Sarura Fuel',
            'loginError' => $loginError,
        ]);
    }

    public function registerForm(): string
    {
        if (!empty($_SESSION['is_logged_in'])) {
            redirect('/dashboard');
        }

        $allowReg = filter_var($_ENV['ALLOW_REGISTRATION'] ?? getenv('ALLOW_REGISTRATION') ?: false, FILTER_VALIDATE_BOOLEAN);
        $pdo = Database::connection();
        $totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($totalUsers > 0 && !$allowReg) {
            flash('login_error', 'Public self-registration is disabled. Please contact your system administrator for staff access.');
            redirect('/login');
        }

        $regError = flash('register_error') ?? '';
        $regSuccess = flash('register_success') ?? '';
        return view('auth.register', [
            'title' => 'Register Account — Sarura Fuel',
            'registerError' => $regError,
            'registerSuccess' => $regSuccess,
        ]);
    }

    public function register(): void
    {
        $allowReg = filter_var($_ENV['ALLOW_REGISTRATION'] ?? getenv('ALLOW_REGISTRATION') ?: false, FILTER_VALIDATE_BOOLEAN);
        $pdo = Database::connection();
        $totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($totalUsers > 0 && !$allowReg) {
            flash('login_error', 'Public self-registration is disabled. Please contact your system administrator for staff access.');
            redirect('/login');
        }

        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirmation'] ?? '');

        if ($name === '' || $email === '' || $password === '') {
            flash('register_error', 'All fields are required.');
            redirect('/register');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('register_error', 'Please provide a valid email address.');
            redirect('/register');
        }

        if (strlen($password) < 6) {
            flash('register_error', 'Password must be at least 6 characters long.');
            redirect('/register');
        }

        if ($password !== $passwordConfirm) {
            flash('register_error', 'Passwords do not match.');
            redirect('/register');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE LOWER(email) = ?');
        $stmt->execute([$email]);
        if ((int) $stmt->fetchColumn() > 0) {
            flash('register_error', 'An account with this email address already exists.');
            redirect('/register');
        }

        // If first user, make super_admin
        $totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $role = ($totalUsers === 0) ? 'super_admin' : 'admin';

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $ins = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
        $ins->execute([$name, $email, $passwordHash, $role]);
        $newUserId = (int) $pdo->lastInsertId();

        log_audit(
            'User Security',
            'USER_REGISTER',
            "New user {$name} ({$email}) registered with role {$role}",
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        );

        // Auto-login newly registered user
        session_regenerate_id(true);
        $_SESSION['is_logged_in'] = true;
        $_SESSION['user_id'] = $newUserId;
        $_SESSION['user'] = [
            'id' => $newUserId,
            'name' => $name,
            'email' => $email,
            'role' => $role,
        ];

        redirect('/dashboard');
    }

    public function authenticate(): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            flash('login_error', 'Email and password are required.');
            redirect('/login');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['is_logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'] ?? 'admin',
            ];
            redirect('/dashboard');
        }

        flash('login_error', 'Invalid email or password credentials.');
        redirect('/login');
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        session_destroy();
        redirect('/login');
    }
}
