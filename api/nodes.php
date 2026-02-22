<?php
session_start();
require_once '../config.php';
requireSession();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET ALL (optionally filtered by layout_id) ───────────
if ($method === 'GET' && empty($_GET['id'])) {
    if (!empty($_GET['layout_id'])) {
        $stmt = $db->prepare("SELECT * FROM map_nodes WHERE layout_id = ? ORDER BY created_at");
        $stmt->execute([$_GET['layout_id']]);
        $rows = $stmt->fetchAll();
    } else {
        $rows = $db->query("SELECT * FROM map_nodes ORDER BY created_at")->fetchAll();
    }
    foreach ($rows as &$r) {
        $r['ifaces'] = json_decode($r['ifaces'] ?? '[]', true) ?: [];
        $r['info']   = json_decode($r['info']   ?? '{}', true) ?: [];
        $r['x']      = (float)$r['x'];
        $r['y']      = (float)$r['y'];
    }
    jsonOut($rows);
}

// ── GET ONE ──────────────────────────────────────────────
if ($method === 'GET' && !empty($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM map_nodes WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $r = $stmt->fetch();
    if (!$r) jsonOut(['error' => 'Not found'], 404);
    $r['ifaces'] = json_decode($r['ifaces'] ?? '[]', true) ?: [];
    $r['info']   = json_decode($r['info']   ?? '{}', true) ?: [];
    jsonOut($r);
}

// ── CREATE / UPDATE (upsert) ──────────────────────────────
if ($method === 'POST') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($b['id']) || empty($b['label'])) jsonOut(['error' => 'id and label required'], 400);

    $stmt = $db->prepare("INSERT INTO map_nodes
        (id, label, ip, role, type, layer_key, x, y, status, ifaces, info, zabbix_host_id)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            label=VALUES(label), ip=VALUES(ip), role=VALUES(role), type=VALUES(type),
            layer_key=VALUES(layer_key), x=VALUES(x), y=VALUES(y), status=VALUES(status),
            ifaces=VALUES(ifaces), info=VALUES(info), zabbix_host_id=VALUES(zabbix_host_id)");

    $stmt->execute([
        $b['id'], $b['label'], $b['ip'] ?? '', $b['role'] ?? '',
        $b['type'] ?? 'switch', $b['layer_key'] ?? 'srv',
        (float)($b['x'] ?? 0), (float)($b['y'] ?? 0),
        $b['status'] ?? 'ok',
        json_encode($b['ifaces'] ?? []),
        json_encode($b['info']   ?? []),
        $b['zabbix_host_id'] ?? null,
    ]);
    jsonOut(['ok' => true, 'id' => $b['id']]);
}

// ── UPDATE POSITIONS BULK ────────────────────────────────
if ($method === 'PATCH') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    // $b = [ { id, x, y }, ... ]
    $stmt = $db->prepare("UPDATE map_nodes SET x=?, y=? WHERE id=?");
    foreach ($b as $item) {
        if (!empty($item['id'])) $stmt->execute([(float)$item['x'], (float)$item['y'], $item['id']]);
    }
    jsonOut(['ok' => true]);
}

// ── DELETE ───────────────────────────────────────────────
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!$id) jsonOut(['error' => 'id required'], 400);
    $db->prepare("DELETE FROM map_nodes WHERE id = ?")->execute([$id]);
    jsonOut(['ok' => true]);
}

jsonOut(['error' => 'Method not allowed'], 405);
