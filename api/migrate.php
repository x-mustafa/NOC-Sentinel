<?php
// ─────────────────────────────────────────────────────────────
//  Tabadul NOC — DB Migration
//  Run once on existing installs to apply schema updates.
//  Visit: http://localhost/tabadul-noc/api/migrate.php
// ─────────────────────────────────────────────────────────────
session_start();
require_once '../config.php';

// Only admin can run this, or run it from CLI
$isCLI = PHP_SAPI === 'cli';
if (!$isCLI) {
    if (empty($_SESSION['uid'])) { http_response_code(401); die('Unauthorized'); }
    if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); die('Admin only'); }
}

$db = getDB();
$steps = [];

function runStep(PDO $db, string $label, string $sql): void {
    global $steps;
    try {
        $db->exec($sql);
        $steps[] = ['ok' => true, 'label' => $label];
    } catch (PDOException $e) {
        $steps[] = ['ok' => false, 'label' => $label, 'error' => $e->getMessage()];
    }
}

// ── 1. Extend users table ─────────────────────────────────────
runStep($db, 'users: add display_name',
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `display_name` VARCHAR(255) DEFAULT NULL");
runStep($db, 'users: add email',
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `email` VARCHAR(255) DEFAULT NULL");
runStep($db, 'users: add ldap_dn',
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `ldap_dn` VARCHAR(500) DEFAULT NULL");
runStep($db, 'users: add last_login',
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `last_login` TIMESTAMP NULL DEFAULT NULL");
runStep($db, 'users: update role ENUM to include operator',
    "ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin','operator','viewer') DEFAULT 'viewer'");

// ── 2. Map nodes: add layout_id (if not exists) ──────────────
runStep($db, 'map_nodes: add layout_id',
    "ALTER TABLE `map_nodes` ADD COLUMN IF NOT EXISTS `layout_id` INT DEFAULT NULL");

// ── 3. Zabbix config: add claude_key (if not exists) ─────────
runStep($db, 'zabbix_config: add claude_key',
    "ALTER TABLE `zabbix_config` ADD COLUMN IF NOT EXISTS `claude_key` TEXT DEFAULT NULL");

// ── 4. Create ldap_config table ──────────────────────────────
runStep($db, 'ldap_config: create table', "
    CREATE TABLE IF NOT EXISTS `ldap_config` (
        `id`             INT AUTO_INCREMENT PRIMARY KEY,
        `host`           VARCHAR(255) NOT NULL DEFAULT '',
        `port`           INT          NOT NULL DEFAULT 389,
        `base_dn`        VARCHAR(500) NOT NULL DEFAULT '',
        `bind_dn`        VARCHAR(500) NOT NULL DEFAULT '',
        `bind_pass`      TEXT         NOT NULL DEFAULT '',
        `user_filter`    VARCHAR(500) NOT NULL DEFAULT '(&(objectClass=user)(sAMAccountName=%s))',
        `admin_group`    VARCHAR(500) NOT NULL DEFAULT '',
        `operator_group` VARCHAR(500) NOT NULL DEFAULT '',
        `use_tls`        TINYINT(1)   NOT NULL DEFAULT 0,
        `enabled`        TINYINT(1)   NOT NULL DEFAULT 0,
        `updated_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB
");
runStep($db, 'ldap_config: seed row',
    "INSERT IGNORE INTO `ldap_config` (id) VALUES (1)");

// ── Ensure admin user is role=admin (not affected by ENUM change) ──
runStep($db, 'users: ensure admin role',
    "UPDATE `users` SET role='admin' WHERE username='admin' AND role='viewer'");

// Output
if ($isCLI) {
    foreach ($steps as $s) {
        echo ($s['ok'] ? '✓' : '✗') . ' ' . $s['label'] . ($s['ok'] ? '' : ' — ' . $s['error']) . PHP_EOL;
    }
} else {
    header('Content-Type: text/html; charset=utf-8');
    echo '<html><head><meta charset="UTF-8"><style>body{background:#080c14;color:#c8d6f0;font-family:monospace;padding:30px}
    .ok{color:#00e676}.err{color:#ff1744}.card{background:#0d1424;border:1px solid #1e2d4a;border-radius:10px;padding:24px;max-width:600px}</style></head>
    <body><div class="card"><h2 style="color:#00d4ff">🔧 NOC Sentinel — DB Migration</h2>';
    foreach ($steps as $s) {
        $cls = $s['ok'] ? 'ok' : 'err';
        $icon = $s['ok'] ? '✓' : '✗';
        echo "<div class=\"$cls\">$icon {$s['label']}" . (!$s['ok'] ? " <small>{$s['error']}</small>" : '') . '</div>';
    }
    $all = array_reduce($steps, fn($c, $s) => $c && $s['ok'], true);
    echo '<br><b style="color:' . ($all ? '#00e676' : '#ffb300') . '">' . ($all ? '✅ All migrations applied.' : '⚠ Some steps failed (may already be applied).') . '</b>';
    echo '<br><br><a href="../index.php" style="color:#00d4ff">→ Back to NOC Sentinel</a></div></body></html>';
}
