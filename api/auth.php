<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ── LOGIN ────────────────────────────────────────────────
if ($method === 'POST' && $action === 'login') {
    $b    = json_decode(file_get_contents('php://input'), true) ?? [];
    $user = trim($b['username'] ?? '');
    $pass = $b['password'] ?? '';

    if (!$user || !$pass) jsonOut(['error' => 'Username and password required'], 400);

    $stmt = getDB()->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$user]);
    $row = $stmt->fetch();

    if ($row && password_verify($pass, $row['password_hash'])) {
        $_SESSION['uid']      = $row['id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['role']     = $row['role'];
        jsonOut(['ok' => true, 'user' => ['id' => $row['id'], 'username' => $row['username'], 'role' => $row['role']]]);
    }
    jsonOut(['error' => 'Invalid credentials'], 401);
}

// ── LOGOUT ───────────────────────────────────────────────
if ($action === 'logout') {
    session_destroy();
    jsonOut(['ok' => true]);
}

// ── ME ───────────────────────────────────────────────────
if ($action === 'me') {
    if (!empty($_SESSION['uid'])) {
        $uid = $_SESSION['uid']; $uname = $_SESSION['username']; $role = $_SESSION['role'];
        session_write_close();
        jsonOut(['id' => $uid, 'username' => $uname, 'role' => $role]);
    }
    jsonOut(['error' => 'Not logged in'], 401);
}

// ── CHANGE PASSWORD ──────────────────────────────────────
if ($method === 'POST' && $action === 'password') {
    requireSession();
    $b       = json_decode(file_get_contents('php://input'), true) ?? [];
    $current = $b['current'] ?? '';
    $newPass = $b['new']     ?? '';

    if (strlen($newPass) < 4) jsonOut(['error' => 'Password too short (min 4)'], 400);

    $stmt = getDB()->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['uid']]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($current, $row['password_hash'])) {
        jsonOut(['error' => 'Current password incorrect'], 403);
    }

    $hash = password_hash($newPass, PASSWORD_DEFAULT);
    getDB()->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $_SESSION['uid']]);
    jsonOut(['ok' => true]);
}

jsonOut(['error' => 'Not found'], 404);
