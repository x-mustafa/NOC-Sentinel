<?php
session_start();
require_once '../config.php';
requireSession();

$action = $_GET['action'] ?? '';

// ── SCAN ──────────────────────────────────────────────────
if ($action === 'scan') {
    $hostsRaw = callZabbix('host.get', [
        'output'           => ['hostid', 'host', 'name', 'status'],
        'selectInterfaces' => ['ip', 'main'],
        'monitored_hosts'  => 1,
        'limit'            => 1000,
    ]);
    if (!is_array($hostsRaw) || isset($hostsRaw['_zabbix_error'])) jsonOut(['error' => 'Zabbix error'], 500);

    $subnets = [];
    foreach ($hostsRaw as $h) {
        $ip = '';
        foreach (($h['interfaces'] ?? []) as $i) {
            if (($i['main'] ?? 0) == 1) { $ip = $i['ip'] ?? ''; break; }
        }
        $parts = explode('.', $ip);
        $sub = (count($parts) === 4)
            ? $parts[0].'.'.$parts[1].'.'.$parts[2].'.0/24'
            : 'unassigned';

        if (!isset($subnets[$sub])) {
            $subnets[$sub] = ['subnet' => $sub, 'hosts' => []];
        }
        $subnets[$sub]['hosts'][] = [
            'hostid' => $h['hostid'],
            'host'   => $h['host'],
            'name'   => $h['name'],
            'ip'     => $ip,
            'type'   => detectType($h['host'], $h['name']),
        ];
    }

    // Sort hosts alphabetically within each subnet
    // Separate subnets with 2+ hosts from singletons
    $main = []; $small = [];
    foreach ($subnets as $s) {
        usort($s['hosts'], fn($a,$b) => strcmp($a['host'], $b['host']));
        if (count($s['hosts']) >= 2) $main[] = $s;
        else $small = array_merge($small, $s['hosts']);
    }
    usort($main, fn($a,$b) => count($b['hosts']) - count($a['hosts']));

    jsonOut(['subnets' => $main, 'singletons' => $small]);
}

// ── CREATE MAP ────────────────────────────────────────────
elseif ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireOperator();
    $b    = json_decode(file_get_contents('php://input'), true) ?? [];
    $name = trim($b['name'] ?? 'Auto-Discovered Map');
    $selected = $b['subnets'] ?? []; // [{subnet, label, hosts:[{hostid,host,name,ip,type}]}]
    if (empty($selected)) jsonOut(['error' => 'No subnets selected'], 400);

    $db = getDB();

    // Create new layout (is_default=0, never touches the main map)
    $db->prepare("INSERT INTO map_layouts (name, positions, is_default) VALUES (?,?,0)")
       ->execute([$name, '{}']);
    $layoutId = $db->lastInsertId();

    // Layout: subnets in a grid, hosts in a circle per subnet
    // Hub node at center of each cluster connects to every host (star topology)
    $cols  = max(1, (int)ceil(sqrt(count($selected))));
    $gap   = 800;  // px between cluster centers
    $nodes_created = 0;
    $edges = [];
    $nodeStmt = $db->prepare(
        "INSERT INTO map_nodes (id, label, ip, type, x, y, layout_id, zabbix_host_id, status)
         VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE
         label=VALUES(label),x=VALUES(x),y=VALUES(y),
         layout_id=VALUES(layout_id),zabbix_host_id=VALUES(zabbix_host_id)"
    );

    foreach ($selected as $si => $subnet) {
        $cx = ($si % $cols) * $gap;
        $cy = (int)floor($si / $cols) * $gap;
        $hosts  = $subnet['hosts'];
        $n      = count($hosts);
        $label  = trim($subnet['label'] ?? $subnet['subnet']);
        $radius = max(200, $n * 28);

        // Hub node at cluster center (represents the subnet gateway)
        $hubId = 'hub_'.$layoutId.'_'.$si;
        $nodeStmt->execute([$hubId, $label, $subnet['subnet'], 'switch', $cx, $cy, $layoutId, null, 'ok']);
        $nodes_created++;

        foreach ($hosts as $hi => $host) {
            $angle = (2 * M_PI * $hi) / max(1, $n) - M_PI / 2;
            $x = round($cx + $radius * cos($angle));
            $y = round($cy + $radius * sin($angle));
            $id = 'disc_'.$layoutId.'_'.$nodes_created;
            $nodeStmt->execute([
                $id,
                $host['name'] ?: $host['host'],
                $host['ip'],
                $host['type'],
                $x, $y,
                $layoutId,
                $host['hostid'],
                'ok',
            ]);
            // Star edge: hub → host
            $edges[] = ['from' => $hubId, 'to' => $id];
            $nodes_created++;
        }
    }

    // Store edges in the positions JSON field
    $db->prepare("UPDATE map_layouts SET positions = ? WHERE id = ?")
       ->execute([json_encode(['edges' => $edges]), $layoutId]);

    jsonOut(['ok' => true, 'layout_id' => (int)$layoutId, 'nodes_created' => $nodes_created]);
}

else {
    jsonOut(['error' => 'Unknown action'], 400);
}

// ── TYPE DETECTION ────────────────────────────────────────
function detectType(string $host, string $name): string {
    $s = strtolower($host . ' ' . $name);
    if (preg_match('/fortigate|fortinet/', $s))                       return 'firewall';
    if (preg_match('/\bpalo\b|pa-\d/i', $host.$name))                return 'palo';
    if (preg_match('/\bf5\b|bigip|loadbal/i', $s))                   return 'f5';
    if (preg_match('/\bhsm\b/i', $s))                                return 'hsm';
    if (preg_match('/switch|\bsw[-_]|\btor\b|nexus|catalyst/i', $s)) return 'switch';
    if (preg_match('/\bfw\b|firewall|\bftd\b|\bips\b/i', $s))        return 'firewall';
    if (preg_match('/database|\bdb[-_]|\bsql\b|oracle|mysql|redis/i', $s)) return 'dbserver';
    if (preg_match('/esxi|vmware|vcenter|hyper.v|\bhvn\b|\bolvm\b/i', $s)) return 'infra';
    if (preg_match('/backup|veeam|storeonce|\bsan\b|storage/i', $s)) return 'infra';
    if (preg_match('/router|ag1000|gateway|\bwan\b|\bisp\b/i', $s))  return 'wan';
    return 'server';
}
