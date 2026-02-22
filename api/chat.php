<?php
session_start();
require_once '../config.php';
requireSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonOut(['error' => 'POST only'], 405);

$b = json_decode(file_get_contents('php://input'), true) ?? [];

$db = getDB();
$cfg = $db->query("SELECT claude_key FROM zabbix_config LIMIT 1")->fetch();
$claudeKey = $cfg['claude_key'] ?? '';
if (!$claudeKey) jsonOut(['error' => 'Claude API key not configured — go to Settings → Claude AI'], 400);

$messages     = $b['messages']     ?? [];
$hostContext  = $b['host_context'] ?? null;
$chatMode     = $b['mode']         ?? 'host';  // 'host' | 'network'
$networkStats = $b['network_stats'] ?? [];

// ── BUILD SYSTEM PROMPT ───────────────────────────────────
$totalHosts  = $networkStats['total']        ?? '?';
$okHosts     = $networkStats['ok']           ?? '?';
$probHosts   = $networkStats['with_problems']?? '?';
$alarmCount  = $networkStats['alarms']       ?? '?';
$unavailable = $networkStats['unavailable']  ?? '?';

$baseContext = <<<CTX
You are NOC Sentinel — the AI Network Intelligence System for Tabadul, Iraq's national payment processing infrastructure.

COMPANY: Tabadul (تبادل) — processes VISA, MasterCard, and Central Bank of Iraq (CBI) transactions for the Iraqi banking sector.

NETWORK ARCHITECTURE:
- External: VISA Network, MasterCard P14, CBI Switch, ISPs (ScopeSky, Passport-SS, Asia Local, Zain M2M)
- WAN Layer: ISP uplinks, P2P circuits
- Edge: Internet Switches, Core Switches (Cisco Catalyst 6800)
- Security: FortiGate 601E HA pair (Primary/Passive), Cisco Firepower 4150 HA (IPS/NGIPS)
- App Layer: Palo Alto PA-5250 HA pair (App-layer FW), F5 BIG-IP i7800 HA (Load Balancers)
- Servers: Payment apps, card processing, databases, HSMs
- DR: Active-Passive disaster recovery site

MONITORING PLATFORM: Zabbix 7.4.6

CURRENT NETWORK STATUS:
- Total monitored hosts: $totalHosts
- Healthy: $okHosts | With problems: $probHosts | Unreachable: $unavailable
- Active alarms: $alarmCount

YOUR MISSION:
1. Be the intelligent eyes of the NOC team
2. Help engineers understand and resolve issues quickly
3. For any host/device, ask the right questions to build a complete monitoring profile
4. Recommend specific Zabbix templates, items, triggers, and thresholds
5. Learn the network over time — ask for information you don't have
6. Proactively identify monitoring gaps and configuration improvements
7. Speak in clear, technical English. Use bullet points. Be direct and actionable.

ZABBIX KNOWLEDGE:
- Always provide exact navigation paths: Configuration → Hosts → [host] → Items
- Reference specific template names (Template Net Cisco IOS, Template OS Linux, etc.)
- Know Zabbix best practices for payment network monitoring
CTX;

if ($chatMode === 'host' && $hostContext) {
    $hn   = $hostContext['name']          ?? 'Unknown';
    $hip  = $hostContext['ip']            ?? 'Unknown';
    $htyp = $hostContext['type']          ?? 'switch';
    $hrole= $hostContext['role']          ?? '';
    $hst  = $hostContext['status']        ?? 'unknown';
    $zbid = $hostContext['zabbix_id']     ?? '';
    $probs= $hostContext['problems']      ?? [];
    $ifaces=$hostContext['ifaces']        ?? [];

    $probsText = empty($probs) ? 'None' : implode("\n  - ", array_map(fn($p)=>($p['name']??'Unknown').' (Sev:'.(int)($p['severity']??0).')', $probs));
    $ifacesText = empty($ifaces) ? 'Not configured yet' : implode(', ', $ifaces);

    $systemPrompt = $baseContext . <<<HOST

── CURRENT HOST UNDER REVIEW ──
- Device: $hn
- IP: $hip
- Type: $htyp
- Role: $hrole
- Zabbix Host ID: {$zbid}
- Current Status: $hst
- Interfaces configured: $ifacesText
- Active Problems:
  - $probsText

YOUR BEHAVIOUR FOR THIS HOST:
Start by greeting the engineer and summarizing what you know about this device.
Then immediately begin your investigation by asking:
1. What is the primary role and criticality of this device?
2. What interfaces/ports does it have and which need monitoring?
3. Are there specific services, APIs, or metrics critical to payment processing?
4. Has this device had recurring issues?
5. What Zabbix templates are currently applied?

After gathering info, provide:
- Specific Zabbix configuration recommendations
- Missing monitoring items to add
- Recommended alert thresholds for payment network criticality
- Step-by-step Zabbix navigation to implement each recommendation
HOST;
} else {
    // General network intelligence mode
    $contextFocus = $b['context_focus'] ?? 'network';
    $systemPrompt = $baseContext . "\n\nMODE: General Network Intelligence. Context: $contextFocus\n\nHelp the engineer understand the current network state, identify issues, and improve monitoring coverage. Ask probing questions to learn more about the network topology and critical paths.";
}

// ── CALL CLAUDE ───────────────────────────────────────────
$payload = json_encode([
    'model'      => 'claude-sonnet-4-6',
    'max_tokens' => 2048,
    'system'     => $systemPrompt,
    'messages'   => $messages,
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
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$resp = curl_exec($ch);
$err  = curl_errno($ch);
curl_close($ch);

if ($err || !$resp) jsonOut(['error' => 'Cannot reach Claude API'], 500);
$claude = json_decode($resp, true);
if (isset($claude['error'])) jsonOut(['error' => $claude['error']['message'] ?? 'Claude error'], 500);

$reply = $claude['content'][0]['text'] ?? '';
jsonOut(['reply' => $reply, 'usage' => $claude['usage'] ?? null]);
