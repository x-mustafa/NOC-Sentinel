<?php
session_start();
require_once '../config.php';
requireSession();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET ALL ──────────────────────────────────────────────
if ($method === 'GET' && empty($_GET['id'])) {
    $rows = $db->query("SELECT id, name, is_default, created_at FROM map_layouts ORDER BY is_default DESC, created_at DESC")->fetchAll();
    jsonOut($rows);
}

// ── SAVE NEW LAYOUT ───────────────────────────────────────
if ($method === 'POST') {
    requireOperator();
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($b['name'])) jsonOut(['error' => 'Name required'], 400);

    if (!empty($b['is_default'])) {
        $db->exec("UPDATE map_layouts SET is_default = 0");
    }

    $stmt = $db->prepare("INSERT INTO map_layouts (name, positions, is_default) VALUES (?,?,?)");
    $stmt->execute([
        $b['name'],
        json_encode($b['positions'] ?? []),
        empty($b['is_default']) ? 0 : 1,
    ]);
    jsonOut(['ok' => true, 'id' => $db->lastInsertId()]);
}

// ── LOAD LAYOUT (get positions + nodes) ───────────────────
if ($method === 'GET' && !empty($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM map_layouts WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $row = $stmt->fetch();
    if (!$row) jsonOut(['error' => 'Not found'], 404);
    $row['positions'] = json_decode($row['positions'], true);
    // Include nodes that belong to this layout
    $nstmt = $db->prepare("SELECT * FROM map_nodes WHERE layout_id = ? ORDER BY created_at");
    $nstmt->execute([$row['id']]);
    $nodes = $nstmt->fetchAll();
    foreach ($nodes as &$n) {
        $n['ifaces'] = json_decode($n['ifaces'] ?? '[]', true) ?: [];
        $n['info']   = json_decode($n['info']   ?? '{}', true) ?: [];
        $n['x']      = (float)$n['x'];
        $n['y']      = (float)$n['y'];
    }
    $row['nodes'] = $nodes;
    jsonOut($row);
}

// ── DELETE ───────────────────────────────────────────────
if ($method === 'DELETE') {
    requireAdmin();
    $id = $_GET['id'] ?? null;
    if (!$id) jsonOut(['error' => 'id required'], 400);
    // Also delete nodes belonging to this layout
    $db->prepare("DELETE FROM map_nodes WHERE layout_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM map_layouts WHERE id = ?")->execute([$id]);
    jsonOut(['ok' => true]);
}

jsonOut(['error' => 'Method not allowed'], 405);
