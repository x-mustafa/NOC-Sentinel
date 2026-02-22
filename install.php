<?php
// ──────────────────────────────────────────────────────
//  Tabadul NOC — One-Time Installer
//  Visit: http://localhost/tabadul-noc/install.php
// ──────────────────────────────────────────────────────
$errors = [];

try {
    $pdo = new PDO('mysql:host=localhost;charset=utf8mb4', 'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `tabadul_noc` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `tabadul_noc`");

    // ── TABLES ───────────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id`            INT AUTO_INCREMENT PRIMARY KEY,
        `username`      VARCHAR(80)  NOT NULL UNIQUE,
        `password_hash` VARCHAR(255) NOT NULL,
        `role`          ENUM('admin','operator','viewer') DEFAULT 'viewer',
        `display_name`  VARCHAR(255) DEFAULT NULL,
        `email`         VARCHAR(255) DEFAULT NULL,
        `ldap_dn`       VARCHAR(500) DEFAULT NULL,
        `last_login`    TIMESTAMP NULL DEFAULT NULL,
        `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `zabbix_config` (
        `id`      INT AUTO_INCREMENT PRIMARY KEY,
        `url`     VARCHAR(500) NOT NULL DEFAULT 'http://zabbix.tabadul.iq',
        `token`   TEXT         NOT NULL,
        `refresh` INT          NOT NULL DEFAULT 30,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `map_nodes` (
        `id`             VARCHAR(120) PRIMARY KEY,
        `label`          VARCHAR(255) NOT NULL,
        `ip`             VARCHAR(100) DEFAULT '',
        `role`           VARCHAR(255) DEFAULT '',
        `type`           VARCHAR(50)  DEFAULT 'switch',
        `layer_key`      VARCHAR(20)  DEFAULT 'srv',
        `x`              FLOAT        DEFAULT 0,
        `y`              FLOAT        DEFAULT 0,
        `status`         VARCHAR(20)  DEFAULT 'ok',
        `ifaces`         TEXT,
        `info`           TEXT,
        `zabbix_host_id` VARCHAR(100) DEFAULT NULL,
        `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_zabbix (`zabbix_host_id`)
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `map_layouts` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `name`       VARCHAR(120) NOT NULL,
        `positions`  LONGTEXT     NOT NULL,
        `is_default` TINYINT(1)   DEFAULT 0,
        `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `activity_log` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `user`       VARCHAR(80),
        `action`     VARCHAR(255),
        `detail`     TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_time (`created_at`)
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `ldap_config` (
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
    ) ENGINE=InnoDB");
    $pdo->exec("INSERT IGNORE INTO `ldap_config` (id) VALUES (1)");

    // ── DEFAULT DATA ─────────────────────────────────────
    // Admin user  (admin / tabadul)
    $hash = password_hash('tabadul', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT IGNORE INTO `users` (username, password_hash, role) VALUES (?, ?, 'admin')")
        ->execute(['admin', $hash]);

    // Zabbix config
    $existing = $pdo->query("SELECT COUNT(*) FROM `zabbix_config`")->fetchColumn();
    if (!$existing) {
        $pdo->prepare("INSERT INTO `zabbix_config` (url, token, refresh) VALUES (?,?,?)")
            ->execute([
                'http://zabbix.tabadul.iq',
                '708e86b1d5967c0751084ed924cc90621ec36e7ce45127319497806ca8489848',
                30
            ]);
    }

    $msg = "✅ Installation complete!";
} catch (PDOException $e) {
    $errors[] = $e->getMessage();
    $msg = "❌ Installation failed.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Tabadul NOC — Installer</title>
<style>
  body { margin:0; background:#080c14; color:#c8d6f0; font-family:monospace; display:flex; align-items:center; justify-content:center; min-height:100vh; }
  .card { background:#0d1424; border:1px solid #1e2d4a; border-radius:12px; padding:40px; max-width:480px; width:90%; text-align:center; }
  h2 { color:#00d4ff; margin-bottom:20px; }
  .msg { font-size:18px; margin-bottom:16px; }
  .err { color:#ff1744; font-size:13px; margin:4px 0; }
  .ok  { color:#00e676; }
  a { color:#00d4ff; text-decoration:none; font-size:15px; display:inline-block; margin-top:20px; padding:10px 24px; border:1px solid #00d4ff; border-radius:8px; }
  a:hover { background:rgba(0,212,255,0.1); }
</style>
</head>
<body>
  <div class="card">
    <h2>💳 Tabadul NOC Installer</h2>
    <div class="msg <?= $errors ? '' : 'ok' ?>"><?= $msg ?></div>
    <?php foreach ($errors as $e): ?>
      <div class="err"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <?php if (!$errors): ?>
      <div style="font-size:12px;color:#4a6080;margin-top:12px">
        Database: <b style="color:#fff">tabadul_noc</b> created<br>
        Login: <b style="color:#fff">admin</b> / <b style="color:#fff">tabadul</b>
      </div>
      <a href="index.php">→ Open Application</a>
    <?php endif; ?>
  </div>
</body>
</html>
