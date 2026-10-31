<?php
declare(strict_types=1);

session_set_cookie_params([
    'lifetime' => 0,                 // session cookie
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']),   // HTTPS-only once you deploy
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

header('Content-Type: application/json');
header('Cache-Control: no-store');

// Where is auth.json? Prefer one level above webroot.
const AUTH_FILE = __DIR__ . '/auth.json';   // adjust if needed

function out(array $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function loadUsers(): array {
    if (!is_file(AUTH_FILE)) {
        out(['ok' => false, 'error' => 'Auth file missing'], 500);
    }
    $raw = file_get_contents(AUTH_FILE);
    $json = json_decode($raw, true);
    if (!is_array($json) || !isset($json['users']) || !is_array($json['users'])) {
        out(['ok' => false, 'error' => 'Auth file malformed'], 500);
    }
    return $json['users'];
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ---------- CHECK ----------
if ($action === 'check') {
    $user = $_SESSION['user'] ?? null;
    if ($user) {
        out(['ok' => true, 'user' => $user]);
    }
    out(['ok' => false, 'error' => 'Not authenticated'], 401);
}

// ---------- LOGIN ----------
if ($action === 'login') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        out(['ok' => false, 'error' => 'POST required'], 405);
    }
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        out(['ok' => false, 'error' => 'Missing credentials'], 400);
    }

    // Simple brute-force brake: 5 attempts per IP per 10 min
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $lockDir = sys_get_temp_dir() . '/login-lock';
    if (!is_dir($lockDir)) @mkdir($lockDir, 0700, true);
    $lockFile = $lockDir . '/' . hash('sha256', $ip);
    $now = time();
    $attempts = 0;
    if (is_file($lockFile)) {
        $data = @unserialize((string)file_get_contents($lockFile)) ?: [];
        if (($now - ($data['first'] ?? 0)) < 600) {
            $attempts = (int)($data['count'] ?? 0);
        }
    }
    if ($attempts >= 5) {
        out(['ok' => false, 'error' => 'Too many attempts. Try again later.'], 429);
    }

    $users = loadUsers();
    $hash  = $users[$username] ?? null;

    // Dummy hash so missing username and wrong password take similar time
    $dummy = '$2y$12$C6UzMDM.H6dfI/f/IKcEeO7ZL6jW5IWsFf9aB9mUqo2ZgJjKqI8Y2';

    $ok = $hash !== null
        && password_verify($password, $hash)
        && hash_equals((string)$hash, (string)$hash);   // constant-time reminder

    if (!$ok) {
        // Bump attempt counter
        if ($attempts === 0 || ($now - ($data['first'] ?? 0)) >= 600) {
            $data = ['first' => $now, 'count' => 1];
        } else {
            $data = ['first' => $data['first'], 'count' => $attempts + 1];
        }
        @file_put_contents($lockFile, serialize($data), LOCK_EX);
        password_verify($password, $dummy);              // equalise timing
        out(['ok' => false, 'error' => 'Invalid username or password'], 401);
    }

    // Success — clear attempts, rotate session id
    @unlink($lockFile);
    session_regenerate_id(true);
    $_SESSION['user'] = $username;
    out(['ok' => true, 'user' => $username]);
}

// ---------- LOGOUT ----------
if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    out(['ok' => true]);
}

out(['ok' => false, 'error' => 'Unknown action'], 400);