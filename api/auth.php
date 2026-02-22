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

    $db = getDB();

    // ── Try LDAP/AD first ────────────────────────────────
    try {
        $ldapCfg = $db->query("SELECT * FROM ldap_config WHERE enabled=1 LIMIT 1")->fetch();
    } catch (Exception $e) { $ldapCfg = null; }

    if ($ldapCfg && function_exists('ldap_connect')) {
        $ldapResult = tryLdapAuth($user, $pass, $ldapCfg);
        if ($ldapResult === false) {
            // User found in LDAP but wrong password
            jsonOut(['error' => 'Invalid credentials'], 401);
        }
        if (is_array($ldapResult)) {
            // Auto-provision/update user in DB
            $existing = $db->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
            $existing->execute([$user]);
            $row = $existing->fetch();
            if ($row) {
                $db->prepare("UPDATE users SET role=?, display_name=?, email=?, ldap_dn=?, last_login=NOW() WHERE id=?")
                   ->execute([$ldapResult['role'], $ldapResult['display_name'], $ldapResult['email'], $ldapResult['dn'], $row['id']]);
                $uid = $row['id'];
            } else {
                $db->prepare("INSERT INTO users (username, password_hash, role, display_name, email, ldap_dn, last_login) VALUES (?,?,?,?,?,?,NOW())")
                   ->execute([$user, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                              $ldapResult['role'], $ldapResult['display_name'], $ldapResult['email'], $ldapResult['dn']]);
                $uid = $db->lastInsertId();
            }
            $_SESSION['uid']      = $uid;
            $_SESSION['username'] = $user;
            $_SESSION['role']     = $ldapResult['role'];
            jsonOut(['ok' => true, 'user' => ['id' => $uid, 'username' => $user, 'role' => $ldapResult['role']]]);
        }
        // null = user not found in LDAP → fall through to local auth
    }

    // ── Local DB auth ────────────────────────────────────
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$user]);
    $row = $stmt->fetch();

    if ($row && password_verify($pass, $row['password_hash'])) {
        $db->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$row['id']]);
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

// ── LDAP HELPER ──────────────────────────────────────────
/**
 * Returns:
 *   array  — user authenticated, includes role/display_name/email/dn
 *   false  — user found but password wrong
 *   null   — user not found in LDAP (fall through to local auth)
 */
function tryLdapAuth(string $username, string $pass, array $cfg): array|bool|null {
    $conn = @ldap_connect("ldap://{$cfg['host']}:{$cfg['port']}");
    if (!$conn) return null;

    ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
    ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 8);

    if (!empty($cfg['use_tls'])) {
        if (!@ldap_start_tls($conn)) return null;
    }

    // Bind with service account
    if (!@ldap_bind($conn, $cfg['bind_dn'], $cfg['bind_pass'])) return null;

    // Search for the user
    $filter  = str_replace('%s', ldap_escape($username, '', LDAP_ESCAPE_FILTER), $cfg['user_filter']);
    $sr      = @ldap_search($conn, $cfg['base_dn'], $filter, ['dn', 'displayName', 'mail', 'memberOf'], 0, 1);
    if (!$sr) return null;

    $entries = ldap_get_entries($conn, $sr);
    if (!$entries || $entries['count'] === 0) return null;  // user not in LDAP

    $userDn  = $entries[0]['dn'];

    // Verify password by binding as the user
    if (!@ldap_bind($conn, $userDn, $pass)) {
        ldap_unbind($conn);
        return false;  // user exists but wrong password
    }

    // Determine role from group membership
    $role     = 'viewer';
    $memberOf = [];
    if (isset($entries[0]['memberof'])) {
        for ($i = 0; $i < ($entries[0]['memberof']['count'] ?? 0); $i++) {
            $memberOf[] = strtolower($entries[0]['memberof'][$i] ?? '');
        }
    }
    if ($cfg['admin_group'] && in_array(strtolower($cfg['admin_group']), $memberOf)) {
        $role = 'admin';
    } elseif ($cfg['operator_group'] && in_array(strtolower($cfg['operator_group']), $memberOf)) {
        $role = 'operator';
    }

    ldap_unbind($conn);

    return [
        'dn'           => $userDn,
        'display_name' => $entries[0]['displayname'][0] ?? $username,
        'email'        => $entries[0]['mail'][0] ?? '',
        'role'         => $role,
    ];
}
