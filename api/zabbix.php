<?php
session_start();
require_once '../config.php';
requireSession();

$action = $_GET['action'] ?? '';

// ── FULL STATUS (map + counts) ────────────────────────────
if ($action === 'status') {
    // Collect Zabbix host IDs: from client JS (nodeHostMap) + from saved DB nodes
    $clientHostIds = array_values(array_filter($_GET['hostids'] ?? []));
    $dbHostIds = [];
    try {
        $db   = getDB();
        $rows = $db->query(
            "SELECT DISTINCT zabbix_host_id FROM map_nodes WHERE zabbix_host_id IS NOT NULL AND zabbix_host_id != ''"
        )->fetchAll(\PDO::FETCH_COLUMN);
        $dbHostIds = array_values($rows);
    } catch (\Exception $e) {}
    $mapHostIds = array_values(array_unique(array_merge($clientHostIds, $dbHostIds)));

    // 1. Hosts — filtered to map nodes if any are linked
    $hostGetParams = [
        'output'           => ['hostid', 'host', 'name', 'status', 'description'],
        'selectInterfaces' => ['ip', 'main', 'type', 'available'],
        'monitored_hosts'  => 1,
    ];
    if (!empty($mapHostIds)) $hostGetParams['hostids'] = $mapHostIds;

    $hostsRaw = callZabbix('host.get', $hostGetParams);
    $hosts = (is_array($hostsRaw) && !isset($hostsRaw['_zabbix_error'])) ? $hostsRaw : [];

    // 2. Active problems — filtered to map hosts if any are linked
    $probGetParams = [
        'output'             => ['eventid', 'objectid', 'name', 'severity', 'clock', 'acknowledged'],
        'selectAcknowledges' => ['clock', 'message', 'userid'],
        'sortfield'          => 'eventid',
        'sortorder'          => 'DESC',
        'limit'              => 1000,
    ];
    if (!empty($mapHostIds)) $probGetParams['hostids'] = $mapHostIds;

    $problemsRaw = callZabbix('problem.get', $probGetParams);
    $problems = (is_array($problemsRaw) && !isset($problemsRaw['_zabbix_error'])) ? $problemsRaw : [];

    // 3. Triggers for host mapping
    $tids = array_values(array_unique(array_column($problems, 'objectid')));
    $triggerHostMap = [];
    if ($tids) {
        $triggersRaw = callZabbix('trigger.get', [
            'output'      => ['triggerid', 'priority', 'description'],
            'selectHosts' => ['hostid', 'host', 'name'],
            'triggerids'  => $tids,
        ]);
        $triggers = (is_array($triggersRaw) && !isset($triggersRaw['_zabbix_error'])) ? $triggersRaw : [];
        foreach ($triggers as $t) {
            foreach (($t['hosts'] ?? []) as $h) {
                $triggerHostMap[$t['triggerid']][] = $h['hostid'];
            }
        }
    }

    // 4. Map problems to hosts
    $hostProblems = [];
    foreach ($problems as &$p) {
        if (!is_array($p) || !isset($p['objectid'])) continue;
        $hostIds = $triggerHostMap[$p['objectid']] ?? [];
        $p['host_ids'] = $hostIds;
        foreach ($hostIds as $hid) {
            $hostProblems[$hid][] = [
                'eventid'      => $p['eventid'],
                'name'         => $p['name'],
                'severity'     => (int)$p['severity'],
                'clock'        => (int)$p['clock'],
                'acknowledged' => $p['acknowledged'],
            ];
        }
    }
    unset($p);

    // 5. Enrich hosts
    foreach ($hosts as &$h) {
        if (!is_array($h)) continue;
        $hid = $h['hostid'];
        $probs = $hostProblems[$hid] ?? [];
        $h['problems']      = $probs;
        $h['problem_count'] = count($probs);
        $worst = 0;
        foreach ($probs as $pr) $worst = max($worst, $pr['severity']);
        $h['worst_severity'] = $worst;
        // Get primary IP + availability from interfaces (Zabbix 7.x moved 'available' to interface)
        $ip = ''; $available = 0;
        foreach (($h['interfaces'] ?? []) as $iface) {
            if (is_array($iface) && ($iface['main'] ?? 0) == 1) {
                $ip        = $iface['ip'] ?? '';
                $available = (int)($iface['available'] ?? 0);
                break;
            }
        }
        $h['ip']        = $ip;
        $h['available'] = $available; // 0=unknown, 1=available, 2=unavailable
        unset($h['interfaces']);
    }
    unset($h);

    jsonOut([
        'hosts'     => $hosts,
        'problems'  => $problems,
        'counts'    => [
            'total'       => count($hosts),
            'ok'          => count(array_filter($hosts, fn($h) => $h['problem_count'] === 0 && $h['available'] == 1)),
            'with_problems' => count(array_filter($hosts, fn($h) => $h['problem_count'] > 0)),
            'unavailable' => count(array_filter($hosts, fn($h) => $h['available'] == 2)),
            'alarms'      => count($problems),
        ],
        'ts' => time(),
    ]);
}

// ── PROBLEMS PAGE (detailed) ──────────────────────────────
elseif ($action === 'problems') {
    $severity = isset($_GET['severity']) ? (int)$_GET['severity'] : -1;
    $hostid   = $_GET['hostid'] ?? null;

    // Collect host IDs: client-provided (from JS nodeHostMap) + DB saved nodes
    $clientHostIds = array_values(array_filter($_GET['hostids'] ?? []));
    $dbHostIds = [];
    try {
        $db   = getDB();
        $rows = $db->query(
            "SELECT DISTINCT zabbix_host_id FROM map_nodes WHERE zabbix_host_id IS NOT NULL AND zabbix_host_id != ''"
        )->fetchAll(\PDO::FETCH_COLUMN);
        $dbHostIds = array_values($rows);
    } catch (\Exception $e) {}
    $mapHostIds = array_values(array_unique(array_merge($clientHostIds, $dbHostIds)));

    $params = [
        'output'             => 'extend',
        'selectAcknowledges' => 'extend',
        'sortfield'          => 'eventid',
        'sortorder'          => 'DESC',
        'limit'              => 500,
    ];
    if ($severity >= 0) $params['severities'] = [$severity];
    // Specific single-host filter overrides; otherwise use map IDs
    if ($hostid) {
        $params['hostids'] = [$hostid];
    } elseif (!empty($mapHostIds)) {
        $params['hostids'] = $mapHostIds;
    }

    $problemsRaw = callZabbix('problem.get', $params);
    $problems = (is_array($problemsRaw) && !isset($problemsRaw['_zabbix_error'])) ? $problemsRaw : [];

    // Enrich with trigger + host info
    $tids = array_values(array_unique(array_column($problems, 'objectid')));
    $trigMap = [];
    if ($tids) {
        $trigsRaw = callZabbix('trigger.get', [
            'output'      => ['triggerid', 'priority', 'description'],
            'selectHosts' => ['hostid', 'host', 'name'],
            'triggerids'  => $tids,
        ]);
        $trigs = (is_array($trigsRaw) && !isset($trigsRaw['_zabbix_error'])) ? $trigsRaw : [];
        foreach ($trigs as $t) $trigMap[$t['triggerid']] = $t;
    }

    foreach ($problems as &$p) {
        if (!is_array($p) || !isset($p['objectid'])) continue;
        $t = $trigMap[$p['objectid']] ?? null;
        $p['trigger_desc'] = $t['description'] ?? $p['name'];
        $p['hosts']        = $t['hosts'] ?? [];
        $p['priority']     = (int)($t['priority'] ?? $p['severity']);
    }
    unset($p);

    jsonOut($problems);
}

// ── INTERFACE TRAFFIC ─────────────────────────────────────
elseif ($action === 'traffic') {
    $hostIds = array_values(array_filter($_GET['hosts'] ?? []));
    if (empty($hostIds)) jsonOut([]);

    $itemsRaw = callZabbix('item.get', [
        'output'                  => ['hostid', 'key_', 'lastvalue'],
        'hostids'                 => $hostIds,
        'search'                  => ['key_' => 'net.if'],
        'searchWildcardsEnabled'  => true,
        'monitored'               => true,
        'limit'                   => 500,
    ]);
    $items = (is_array($itemsRaw) && !isset($itemsRaw['_zabbix_error'])) ? $itemsRaw : [];

    $traffic = [];
    foreach ($items as $item) {
        if (!is_array($item) || isset($item['_zabbix_error'])) continue;
        $hid = $item['hostid'];
        $key = $item['key_'] ?? '';
        $val = (float)($item['lastvalue'] ?? 0);
        if (!isset($traffic[$hid])) $traffic[$hid] = ['in' => 0, 'out' => 0];
        if (str_contains($key, '.in['))  $traffic[$hid]['in']  += $val;
        if (str_contains($key, '.out[')) $traffic[$hid]['out'] += $val;
    }
    jsonOut($traffic);
}

// ── ITEM HISTORY (for panel sparklines) ───────────────────
elseif ($action === 'history') {
    requireSession();
    $hostId = trim($_GET['hostid'] ?? '');
    if (!$hostId) jsonOut(['error' => 'Missing hostid'], 400);

    // Targeted per-slot searches — avoids missing items due to large item sets
    $slotPatterns = [
        'cpu' => ['system.cpu.util', 'system.cpu.load[percpu,avg1]', 'system.cpu.load'],
        'mem' => ['vm.memory.size[pused]', 'vm.memory.utilization', 'vm.memory.size[available]'],
        'net' => ['net.if.in', 'net.if.out'],
    ];

    $now    = time();
    $from   = $now - 3600;
    $result = [];

    foreach ($slotPatterns as $slot => $patterns) {
        $item = null;
        foreach ($patterns as $pat) {
            $raw = callZabbix('item.get', [
                'output'                 => ['itemid', 'key_', 'name', 'value_type', 'units', 'lastvalue'],
                'hostids'                => [$hostId],
                'search'                 => ['key_' => $pat],
                'searchWildcardsEnabled' => true,
                'monitored'              => true,
                'limit'                  => 1,
            ]);
            if (is_array($raw) && !isset($raw['_zabbix_error']) && !empty($raw)) {
                $item = $raw[0];
                break;
            }
        }
        if (!$item) continue;

        $history = callZabbix('history.get', [
            'output'    => ['clock', 'value'],
            'history'   => (int)($item['value_type'] ?? 0),
            'itemids'   => [$item['itemid']],
            'time_from' => $from,
            'time_till' => $now,
            'limit'     => 120,
            'sortfield' => 'clock',
            'sortorder' => 'ASC',
        ]);
        $pts = [];
        if (is_array($history) && !isset($history['_zabbix_error'])) {
            foreach ($history as $h) {
                $pts[] = ['t' => (int)$h['clock'], 'v' => (float)$h['value']];
            }
        }
        $result[] = [
            'slot'      => $slot,
            'name'      => $item['name'],
            'units'     => $item['units'] ?? '',
            'lastvalue' => $item['lastvalue'] ?? '0',
            'history'   => $pts,
        ];
    }

    jsonOut($result);
}

// ── ACKNOWLEDGE ───────────────────────────────────────────
elseif ($action === 'acknowledge' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireOperator();
    $b       = json_decode(file_get_contents('php://input'), true) ?? [];
    $eventid = $b['eventid'] ?? null;
    $msg     = $b['message'] ?? 'Acknowledged via Tabadul NOC';
    if (!$eventid) jsonOut(['error' => 'eventid required'], 400);

    $result = callZabbix('event.acknowledge', [
        'eventids' => [$eventid],
        'action'   => 6,   // 4 (acknowledge) + 2 (add message)
        'message'  => $msg,
    ]);
    jsonOut(['ok' => true, 'result' => $result]);
}

// ── TEST CONNECTION ───────────────────────────────────────
elseif ($action === 'test') {
    $result = callZabbix('apiinfo.version', []);
    if ($result === null) jsonOut(['ok' => false, 'error' => 'Cannot reach Zabbix server']);
    if (isset($result['_zabbix_error'])) jsonOut(['ok' => false, 'error' => $result['_zabbix_error']]);
    jsonOut(['ok' => true, 'version' => $result]);
}


// ── SAVE CONFIG ───────────────────────────────────────────
elseif ($action === 'config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $db = getDB();
    $existing = $db->query("SELECT COUNT(*) FROM zabbix_config")->fetchColumn();
    // If token still contains asterisks (masked placeholder), don't overwrite it
    $tokenChanged = !str_contains($b['token'] ?? '', '*');
    if ($existing) {
        if ($tokenChanged) {
            $db->prepare("UPDATE zabbix_config SET url=?, token=?, refresh=?")->execute([$b['url'], $b['token'], $b['refresh'] ?? 30]);
        } else {
            $db->prepare("UPDATE zabbix_config SET url=?, refresh=?")->execute([$b['url'], $b['refresh'] ?? 30]);
        }
    } else {
        $db->prepare("INSERT INTO zabbix_config (url, token, refresh) VALUES (?,?,?)")->execute([$b['url'], $b['token'], $b['refresh'] ?? 30]);
    }
    jsonOut(['ok' => true]);
}

// ── GET CONFIG ────────────────────────────────────────────
elseif ($action === 'config') {
    $cfg = getZabbixConfig();
    // mask token
    $cfg['token_masked'] = substr($cfg['token'], 0, 8) . str_repeat('*', 32) . substr($cfg['token'], -4);
    jsonOut($cfg);
}

else {
    jsonOut(['error' => 'Unknown action'], 400);
}
