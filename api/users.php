<?php
session_start();
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ── LIST USERS ─────────────────────────────────────────────
if ($method === 'GET' && !$action) {
    requireAdmin();
    $db = getDB();
    $rows = $db->query("SELECT id, username, display_name, email, role, ldap_dn, last_login, created_at FROM users ORDER BY id")->fetchAll();
    foreach ($rows as &$r) {
        $r['is_ldap'] = !empty($r['ldap_dn']);
        unset($r['ldap_dn']);
    }
    jsonOut($rows);
}

// ── CREATE USER ────────────────────────────────────────────
if ($method === 'POST' && $action === 'create') {
    requireAdmin();
    $b    = json_decode(file_get_contents('php://input'), true) ?? [];
    $user = trim($b['username'] ?? '');
    $pass = $b['password'] ?? '';
    $role = $b['role'] ?? 'viewer';
    $dn   = trim($b['display_name'] ?? '');
    $em   = trim($b['email'] ?? '');

    if (!$user) jsonOut(['error' => 'Username required'], 400);
    if (strlen($pass) < 4) jsonOut(['error' => 'Password min 4 chars'], 400);
    if (!in_array($role, ['admin', 'operator', 'viewer'])) jsonOut(['error' => 'Invalid role'], 400);

    $db = getDB();
    try {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO users (username, password_hash, role, display_name, email) VALUES (?,?,?,?,?)")
           ->execute([$user, $hash, $role, $dn ?: null, $em ?: null]);
        jsonOut(['ok' => true, 'id' => $db->lastInsertId()]);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate')) jsonOut(['error' => 'Username already exists'], 409);
        throw $e;
    }
}

// ── UPDATE USER ────────────────────────────────────────────
if ($method === 'POST' && $action === 'update') {
    $sess = requireAdmin();
    $b    = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($b['id'] ?? 0);
    if (!$id) jsonOut(['error' => 'id required'], 400);

    $db   = getDB();
    $sets = []; $vals = [];

    if (isset($b['role'])) {
        if (!in_array($b['role'], ['admin', 'operator', 'viewer'])) jsonOut(['error' => 'Invalid role'], 400);
        // Prevent admin from demoting themselves
        if ($id === (int)$sess['uid'] && $b['role'] !== 'admin') jsonOut(['error' => 'Cannot change your own role'], 400);
        $sets[] = 'role=?'; $vals[] = $b['role'];
    }
    if (isset($b['display_name'])) { $sets[] = 'display_name=?'; $vals[] = trim($b['display_name']) ?: null; }
    if (isset($b['email']))        { $sets[] = 'email=?';        $vals[] = trim($b['email']) ?: null; }
    if (!empty($b['password'])) {
        if (strlen($b['password']) < 4) jsonOut(['error' => 'Password min 4 chars'], 400);
        $sets[] = 'password_hash=?'; $vals[] = password_hash($b['password'], PASSWORD_DEFAULT);
    }

    if (empty($sets)) jsonOut(['error' => 'Nothing to update'], 400);
    $vals[] = $id;
    $db->prepare("UPDATE users SET " . implode(',', $sets) . " WHERE id=?")->execute($vals);
    jsonOut(['ok' => true]);
}

// ── DELETE USER ────────────────────────────────────────────
if ($method === 'DELETE') {
    $sess = requireAdmin();
    $id   = (int)($_GET['id'] ?? 0);
    if (!$id) jsonOut(['error' => 'id required'], 400);
    if ($id === (int)$sess['uid']) jsonOut(['error' => 'Cannot delete yourself'], 400);
    getDB()->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    jsonOut(['ok' => true]);
}

// ── GET LDAP CONFIG ────────────────────────────────────────
if ($method === 'GET' && $action === 'ldap_config') {
    requireAdmin();
    $db  = getDB();
    $row = $db->query("SELECT * FROM ldap_config WHERE id=1")->fetch();
    if ($row) {
        // Mask bind password
        $row['bind_pass_masked'] = $row['bind_pass'] ? str_repeat('*', 16) : '';
        $row['bind_pass'] = '';
    }
    jsonOut($row ?: []);
}

// ── SAVE LDAP CONFIG ───────────────────────────────────────
if ($method === 'POST' && $action === 'ldap_save') {
    requireAdmin();
    $b  = json_decode(file_get_contents('php://input'), true) ?? [];
    $db = getDB();

    $fields  = ['host','port','base_dn','bind_dn','user_filter','admin_group','operator_group','use_tls','enabled'];
    $sets    = array_map(fn($f) => "`$f`=?", $fields);
    $vals    = array_map(fn($f) => $b[$f] ?? '', $fields);

    // Only update bind_pass if it was changed (non-masked value provided)
    $bindPass = trim($b['bind_pass'] ?? '');
    if ($bindPass && !str_contains($bindPass, '*')) {
        $sets[] = '`bind_pass`=?';
        $vals[] = $bindPass;
    }
    $vals[] = 1; // WHERE id=1
    $db->prepare("UPDATE ldap_config SET " . implode(',', $sets) . " WHERE id=?")->execute($vals);
    jsonOut(['ok' => true]);
}

// ── TEST LDAP ──────────────────────────────────────────────
if ($method === 'POST' && $action === 'ldap_test') {
    requireAdmin();
    $b = json_decode(file_get_contents('php://input'), true) ?? [];

    if (!function_exists('ldap_connect')) jsonOut(['error' => 'PHP LDAP extension not enabled on this server'], 500);

    $host     = trim($b['host'] ?? '');
    $port     = (int)($b['port'] ?? 389);
    $bindDn   = trim($b['bind_dn'] ?? '');
    $bindPass = trim($b['bind_pass'] ?? '');
    $baseDn   = trim($b['base_dn'] ?? '');
    $useTls   = !empty($b['use_tls']);

    if (!$host) jsonOut(['error' => 'Host required'], 400);
    if (!$bindDn || !$bindPass) jsonOut(['error' => 'Bind DN and password required'], 400);

    // If password is masked, load from DB
    if (str_contains($bindPass, '*')) {
        $db       = getDB();
        $row      = $db->query("SELECT bind_pass FROM ldap_config WHERE id=1")->fetch();
        $bindPass = $row['bind_pass'] ?? '';
    }

    $conn = @ldap_connect("ldap://{$host}:{$port}");
    if (!$conn) jsonOut(['error' => 'Cannot create LDAP connection'], 500);

    ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
    ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 8);

    if ($useTls) {
        if (!@ldap_start_tls($conn)) jsonOut(['error' => 'TLS failed — check certificate'], 500);
    }

    if (!@ldap_bind($conn, $bindDn, $bindPass)) {
        $err = ldap_error($conn);
        jsonOut(['error' => "Bind failed: $err"], 401);
    }

    // Try a simple search to confirm base DN
    $sr = @ldap_search($conn, $baseDn, '(objectClass=*)', ['dn'], 0, 1);
    $entryCount = $sr ? ldap_count_entries($conn, $sr) : 0;
    ldap_unbind($conn);

    jsonOut(['ok' => true, 'message' => "Connected successfully. Base DN returned {$entryCount} entr" . ($entryCount === 1 ? 'y' : 'ies') . "."]);
}

jsonOut(['error' => 'Unknown action or method'], 400);
