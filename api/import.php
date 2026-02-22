<?php
session_start();
require_once '../config.php';
requireSession();

$action = $_GET['action'] ?? '';

// ── ANALYZE IMAGE/PDF via Claude ──────────────────────────
if ($action === 'analyze' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $db  = getDB();
    $cfg = $db->query("SELECT claude_key FROM zabbix_config LIMIT 1")->fetch();
    $claudeKey = $cfg['claude_key'] ?? '';
    if (!$claudeKey) jsonOut(['error' => 'Claude API key not set — go to Settings → Claude API Key'], 400);

    $file = $_FILES['map'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) jsonOut(['error' => 'File upload failed'], 400);

    $mimeType = mime_content_type($file['tmp_name']);
    $allowed  = ['image/png','image/jpeg','image/gif','image/webp','application/pdf'];
    if (!in_array($mimeType, $allowed)) jsonOut(['error' => 'Unsupported file type: '.$mimeType], 400);

    $imageData = base64_encode(file_get_contents($file['tmp_name']));

    // Build Claude content block
    if ($mimeType === 'application/pdf') {
        $mediaBlock = ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $imageData]];
    } else {
        $mediaBlock = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mimeType, 'data' => $imageData]];
    }

    $prompt = <<<PROMPT
This is a network topology diagram. Extract EVERY network device/node visible in the diagram.

For each device return these exact fields:
- name: the device label/hostname shown in the diagram
- ip: the IP address shown (empty string "" if none visible)
- type: one of: router, switch, firewall, server, load_balancer, storage, endpoint, other

Return ONLY a valid JSON array with no markdown, no explanation, no code fences.
Example: [{"name":"Core-SW-01","ip":"10.0.0.1","type":"switch"},{"name":"FW-01","ip":"192.168.1.1","type":"firewall"}]
PROMPT;

    $payload = json_encode([
        'model'      => 'claude-opus-4-6',
        'max_tokens' => 4096,
        'messages'   => [[
            'role'    => 'user',
            'content' => [
                $mediaBlock,
                ['type' => 'text', 'text' => $prompt],
            ],
        ]],
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            "x-api-key: $claudeKey",
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $resp = curl_exec($ch);
    $err  = curl_errno($ch);
    curl_close($ch);

    if ($err || !$resp) jsonOut(['error' => 'Cannot reach Claude API — check your key and network'], 500);

    $claude = json_decode($resp, true);
    if (isset($claude['error'])) jsonOut(['error' => $claude['error']['message'] ?? 'Claude error'], 500);

    $text = $claude['content'][0]['text'] ?? '';

    // Extract JSON array from Claude response (strips any markdown fences)
    if (!preg_match('/\[[\s\S]*\]/m', $text, $m)) {
        jsonOut(['error' => 'Claude did not return valid JSON. Response: '.substr($text, 0, 300)], 500);
    }
    $extracted = json_decode($m[0], true);
    if (!is_array($extracted)) jsonOut(['error' => 'Failed to parse Claude JSON'], 500);

    // ── Match each node to Zabbix by IP ──────────────────
    $results = [];
    foreach ($extracted as $node) {
        $ip   = trim($node['ip'] ?? '');
        $name = trim($node['name'] ?? 'Unknown');
        $type = $node['type'] ?? 'switch';

        $zbxHost = null;
        if ($ip && $ip !== '') {
            // Search by interface IP
            $ifaces = callZabbix('hostinterface.get', [
                'output'      => ['hostid', 'ip'],
                'search'      => ['ip' => $ip],
                'searchExact' => true,
            ]);
            if (is_array($ifaces) && !isset($ifaces['_zabbix_error']) && !empty($ifaces)) {
                $hostId = $ifaces[0]['hostid'];
                $hosts  = callZabbix('host.get', [
                    'output'   => ['hostid', 'host', 'name'],
                    'hostids'  => [$hostId],
                ]);
                $zbxHost = (is_array($hosts) && !empty($hosts)) ? $hosts[0] : null;
            }
        }

        $results[] = [
            'name'          => $name,
            'ip'            => $ip,
            'type'          => $type,
            'zabbix_host'   => $zbxHost,
            'zabbix_hostid' => $zbxHost['hostid'] ?? null,
            'matched'       => $zbxHost !== null,
        ];
    }

    jsonOut([
        'nodes'   => $results,
        'total'   => count($results),
        'matched' => count(array_filter($results, fn($r) => $r['matched'])),
        'skipped' => count(array_filter($results, fn($r) => !$r['matched'])),
    ]);
}

// ── CREATE MAP FROM CONFIRMED NODES ──────────────────────
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $b       = json_decode(file_get_contents('php://input'), true) ?? [];
    $mapName = trim($b['name'] ?? 'Imported Map');
    $nodes   = $b['nodes'] ?? [];   // only matched nodes the user confirmed

    if (empty($mapName)) jsonOut(['error' => 'Map name required'], 400);
    if (empty($nodes))   jsonOut(['error' => 'No matched nodes to create'], 400);

    $db = getDB();

    // Create layout
    $db->prepare("INSERT INTO map_layouts (name, positions, is_default) VALUES (?,?,0)")
       ->execute([$mapName, '{}']);
    $layoutId = (int)$db->lastInsertId();

    // Place nodes in a grid/circle layout
    $count = count($nodes);
    $cols  = max(1, ceil(sqrt($count * 1.6)));
    $xGap  = 220; $yGap = 160; $xOff = 150; $yOff = 120;

    $positions = [];
    foreach ($nodes as $i => $n) {
        $col = $i % $cols;
        $row = floor($i / $cols);
        $x   = $xOff + $col * $xGap;
        $y   = $yOff + $row * $yGap;
        $id  = 'map_' . $layoutId . '_' . ($i + 1);

        $db->prepare("INSERT INTO map_nodes
            (id, label, ip, type, x, y, layout_id, zabbix_host_id, status)
            VALUES (?,?,?,?,?,?,?,?,'ok')
            ON DUPLICATE KEY UPDATE label=VALUES(label),ip=VALUES(ip),type=VALUES(type),
            x=VALUES(x),y=VALUES(y),layout_id=VALUES(layout_id),zabbix_host_id=VALUES(zabbix_host_id)")
           ->execute([$id, $n['name'], $n['ip'] ?? '', $n['type'] ?? 'switch', $x, $y, $layoutId, $n['zabbix_hostid'] ?? null]);

        $positions[$id] = ['x' => $x, 'y' => $y];
    }

    // Save positions back to layout
    $db->prepare("UPDATE map_layouts SET positions=? WHERE id=?")
       ->execute([json_encode($positions), $layoutId]);

    jsonOut(['ok' => true, 'layout_id' => $layoutId, 'nodes_created' => count($nodes)]);
}

// ── SAVE CLAUDE KEY ───────────────────────────────────────
if ($action === 'savekey' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $b   = json_decode(file_get_contents('php://input'), true) ?? [];
    $key = trim($b['claude_key'] ?? '');
    if (!$key) jsonOut(['error' => 'Key required'], 400);
    $db = getDB();
    $db->prepare("UPDATE zabbix_config SET claude_key=?")->execute([$key]);
    jsonOut(['ok' => true]);
}

// ── GET CLAUDE KEY (masked) ───────────────────────────────
if ($action === 'getkey') {
    $db  = getDB();
    $cfg = $db->query("SELECT claude_key FROM zabbix_config LIMIT 1")->fetch();
    $key = $cfg['claude_key'] ?? '';
    $masked = $key ? substr($key, 0, 8) . str_repeat('*', 24) . substr($key, -4) : '';
    jsonOut(['has_key' => !empty($key), 'masked' => $masked]);
}

jsonOut(['error' => 'Unknown action'], 400);
