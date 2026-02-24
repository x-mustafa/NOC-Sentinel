<?php
session_start();
require_once '../config.php';
requireSession();

set_time_limit(0);
ignore_user_abort(true);

// Set SSE headers FIRST — before any logic that might die()
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
while (ob_get_level()) ob_end_clean();
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');
// 2 KB padding comment — forces Apache to flush its internal buffer immediately
echo ': ' . str_repeat(' ', 2048) . "\n\n";
flush();

// ── HELPER: emit SSE error and exit ──────────────────────────────────────────
function sseError($msg) {
    echo 'event: error' . "\n" . 'data: ' . json_encode(['error' => $msg]) . "\n\n";
    flush();
    exit;
}

// ── RELEASE SESSION LOCK ──────────────────────────────────────────────────────
session_write_close();

// ── VALIDATE REQUEST ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sseError('POST only');

$b = json_decode(file_get_contents('php://input'), true) ?? [];

// ── LOAD CONFIG FROM DB ───────────────────────────────────────────────────────
try {
    $db  = getDB();
    $cfg = $db->query("SELECT * FROM zabbix_config LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    sseError('DB error: ' . $e->getMessage());
}

$employee   = $b['employee']        ?? 'aria';
$taskType   = $b['task_type']       ?? 'daily';
$customTask = $b['custom_task']     ?? '';
$netCtx     = $b['network_context'] ?? [];
$provider   = $b['provider']        ?? 'claude';   // claude|openai|gemini|grok
$modelId    = $b['model_id']        ?? '';

// ── PICK API KEY & MODEL ──────────────────────────────────────────────────────
switch ($provider) {
    case 'openai':
        $apiKey  = $cfg['openai_key']  ?? '';
        $model   = $modelId ?: 'gpt-4o';
        break;
    case 'gemini':
        $apiKey  = $cfg['gemini_key']  ?? '';
        $model   = $modelId ?: 'gemini-2.0-flash';
        break;
    case 'grok':
        $apiKey  = $cfg['grok_key']    ?? '';
        $model   = $modelId ?: 'grok-2-latest';
        break;
    default: // claude
        $provider = 'claude';
        $apiKey  = $cfg['claude_key']  ?? '';
        $model   = $modelId ?: 'claude-sonnet-4-6';
        break;
}

if (!$apiKey) sseError("$provider API key not configured — go to Settings → AI Providers");

// ── NETWORK CONTEXT ───────────────────────────────────────────────────────────
$stats      = $netCtx['stats']  ?? [];
$alarms     = $netCtx['alarms'] ?? [];
$hosts      = $netCtx['hosts']  ?? [];
$SEV        = [0=>'Info',1=>'Info',2=>'Warning',3=>'Average',4=>'High',5=>'Disaster'];

$totalHosts = $stats['total']         ?? '?';
$okHosts    = $stats['ok']            ?? '?';
$probHosts  = $stats['with_problems'] ?? '?';
$alarmCount = $stats['alarms']        ?? '?';

$ctx = "LIVE NETWORK STATUS: {$totalHosts} hosts | {$okHosts} healthy | {$probHosts} problems | {$alarmCount} alarms.";
if (!empty($alarms)) {
    $ctx .= "\nACTIVE ALARMS (" . count($alarms) . "):\n";
    foreach (array_slice($alarms, 0, 20) as $a)
        $ctx .= '  [' . ($SEV[$a['severity']??0]??'?') . '] ' . ($a['name']??'?') . "\n";
}
if (!empty($hosts)) {
    $ctx .= "\nHOSTS WITH PROBLEMS:\n";
    foreach (array_slice($hosts, 0, 15) as $h)
        $ctx .= '  - ' . ($h['host']??'?') . ': ' . ($h['problems']??0) . " problem(s)\n";
}

// ── EMPLOYEE PERSONAS ─────────────────────────────────────────────────────────
$personas = [
    'aria' =>
        "You are ARIA (Automated Response Intelligence Agent), NOC Analyst AI for Tabadul — Iraq's national payment processing infrastructure.\n" .
        "PERSONALITY: Precise, calm, methodical. Short punchy technical statements. Shift-handover reporting style. NOC jargon.\n" .
        "EXPERTISE: Zabbix alarm triage, incident lifecycle (detect->diagnose->resolve->RCA), SLA/uptime tracking, alert fatigue management, alarm correlation.\n" .
        "CONTEXT: Tabadul monitors VISA, MasterCard, CBI Switch, 4 ISP uplinks. 99.99% uptime SLA. Zabbix 7.4.6, 150+ hosts.\n" .
        "FORMAT: Use -- section headers. Use > bullets. Reference real alarm/host names from live data. No preamble, start directly.",

    'nexus' =>
        "You are NEXUS (Network Excellence Unified Infrastructure Specialist), Infrastructure Engineer AI for Tabadul.\n" .
        "PERSONALITY: Systems thinker, automation obsessed, data-driven. Identifies bottlenecks and SPOFs proactively.\n" .
        "EXPERTISE: Cisco Catalyst 6800 VSS, Nexus switching, FortiGate 601E HA, Cisco Firepower 4150, F5 BIG-IP i7800, BGP/OSPF, ISP uplinks, Ansible/Python automation, capacity planning.\n" .
        "CONTEXT: Core SW: Cisco Catalyst 6800 VSS. Security: FortiGate HA + Firepower. App: PA-5250 HA + F5. 4 ISPs: ScopeSky, Passport-SS, Asia Local, Zain M2M.\n" .
        "FORMAT: -- headers. > bullets. Specific device names. CLI hints where relevant. No preamble.",

    'cipher' =>
        "You are CIPHER (Cyber Intelligence Proactive Hardening Expert), Security Analyst AI for Tabadul.\n" .
        "PERSONALITY: Paranoid by design, evidence-based, defense-in-depth advocate. Clear severity/urgency communication.\n" .
        "EXPERTISE: NGFW rules (PA-5250, FortiGate 601E), IPS/IDS tuning (Firepower 4150), PCI-DSS for payment networks, threat hunting, anomaly detection, firewall policy optimization.\n" .
        "CONTEXT: Full PCI-DSS scope. Multi-layer security: FortiGate -> Firepower -> PA-5250. HSMs for key management. External connectivity: VISA, MasterCard, CBI networks.\n" .
        "FORMAT: -- headers with [CRITICAL]/[HIGH]/[MEDIUM] tags. > bullets. Specific devices. No preamble.",

    'vega' =>
        "You are VEGA (Vigilant Engineering Gap Analysis), Site Reliability Engineer AI for Tabadul.\n" .
        "PERSONALITY: Error-budget obsessed, runbook-for-everything mindset, toil-reduction champion. Post-incident focused.\n" .
        "EXPERTISE: SLO/SLI definition for payment systems, runbook/playbook development, chaos engineering, monitoring gap analysis, Zabbix template optimization, DR testing, BCP documentation.\n" .
        "CONTEXT: Active-passive DR site. SLA 99.99% for payment flows. Critical path: ISP -> Core SW -> Payment FW -> App FW -> Servers. Zabbix 7.4.6.\n" .
        "FORMAT: -- headers. > bullets. SLO metrics where relevant. Reference live data for gap analysis. No preamble.",
];

// ── TASK PROMPTS ──────────────────────────────────────────────────────────────
$tasks = [
    'daily' => [
        'aria'   => "Perform your morning NOC shift check. Review alarm state, flag critical/overdue issues, and deliver your shift handover briefing. Reference real alarm and host names from live data.",
        'nexus'  => "Perform your daily infrastructure health check. Review device performance, capacity concerns, and list your top 3 infrastructure actions for today. Reference specific devices from live data.",
        'cipher' => "Perform your daily security posture review. Check alarm patterns, assess FortiGate/Firepower/PA-5250 status, and deliver your threat assessment for today.",
        'vega'   => "Perform your daily reliability review. Estimate error budget status, identify monitoring coverage gaps from live data, flag recurring alarm patterns, and give your reliability report.",
    ],
    'research' => [
        'aria'   => "Write a technical report on best practices for NOC alarm management in payment processing networks. Cover correlation, fatigue management, escalation, and shift handover. Actionable for Tabadul's Zabbix environment.",
        'nexus'  => "Write a deep-dive on optimizing Cisco Catalyst 6800 VSS and FortiGate HA for payment network resilience. Include specific CLI commands and automation snippets.",
        'cipher' => "Write a PCI-DSS compliance review for Tabadul's architecture with specific hardening steps for PA-5250, FortiGate 601E, and Cisco Firepower 4150.",
        'vega'   => "Document a complete SRE runbook template for Tabadul's payment infrastructure. Include SLOs, SLIs, alert thresholds, incident procedures, escalation matrix, and post-mortem template.",
    ],
    'improvement' => [
        'aria'   => "Analyze current network state and propose 5 concrete NOC operations improvements. For each: implementation steps, expected impact, effort (Low/Medium/High), priority. Use live alarm data.",
        'nexus'  => "Propose 5 high-impact infrastructure automation improvements to reduce toil and improve resilience. Include Ansible/Python snippets for each.",
        'cipher' => "Propose 5 critical security improvements with implementation steps for PA-5250, FortiGate 601E, or Firepower 4150. Include risk level and effort estimate.",
        'vega'   => "Propose 5 monitoring improvements to reduce MTTR. Include Zabbix template recommendations, trigger expressions, and a mini-runbook stub for each.",
    ],
    'custom' => [
        'aria'   => $customTask, 'nexus' => $customTask,
        'cipher' => $customTask, 'vega'  => $customTask,
    ],
];

$persona   = $personas[$employee]        ?? $personas['aria'];
$prompt    = $tasks[$taskType][$employee] ?? $tasks['daily'][$employee];
if ($taskType === 'custom' && $customTask) $prompt = $customTask;

// ── PROCESS ATTACHMENTS ───────────────────────────────────────────────────────
$attachments    = $b['attachments'] ?? [];
$imageAtt       = [];   // vision-capable attachments
$docContext     = '';   // extracted text from documents

foreach ($attachments as $att) {
    $attName = $att['name'] ?? 'file';
    $attType = $att['type'] ?? 'application/octet-stream';
    $attData = $att['data'] ?? ''; // base64
    if (!$attData) continue;

    if (str_starts_with($attType, 'image/')) {
        // Keep for vision API
        $imageAtt[] = ['name' => $attName, 'type' => $attType, 'data' => $attData];
    } else {
        // Extract text and add to context
        $raw  = base64_decode($attData);
        $text = _extractDocText($attName, $attType, $raw);
        if ($text) {
            $docContext .= "\n\n=== ATTACHED FILE: {$attName} ===\n" . mb_substr($text, 0, 6000) . "\n=== END: {$attName} ===\n";
        }
    }
}

$sysPrompt = $persona . "\n\n---- LIVE CONTEXT ----\n" . $ctx . $docContext . "\nRespond as " . strtoupper($employee) . " to the following:";
$userMsg   = $prompt;
if (!empty($imageAtt)) $userMsg .= "\n\n[" . count($imageAtt) . " image(s) attached — analyze them as part of this task]";

// ── CALL AI API ───────────────────────────────────────────────────────────────
if ($provider === 'claude') {
    _streamClaude($apiKey, $model, $sysPrompt, $userMsg, $imageAtt);
} elseif ($provider === 'openai') {
    _streamOpenAI($apiKey, $model, $sysPrompt, $userMsg, $imageAtt);
} elseif ($provider === 'gemini') {
    _streamGemini($apiKey, $model, $sysPrompt, $userMsg, $imageAtt);
} elseif ($provider === 'grok') {
    _streamGrok($apiKey, $model, $sysPrompt, $userMsg, $imageAtt);
}
exit;

// ── DOCUMENT TEXT EXTRACTION ──────────────────────────────────────────────────
function _extractDocText($name, $type, $raw) {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if (in_array($ext, ['txt','md','csv','log','json','xml','yaml','yml'])) {
        return mb_substr($raw, 0, 12000);
    }
    if (in_array($ext, ['docx','pptx','xlsx'])) {
        return _extractZipXml($raw, $ext);
    }
    if (in_array($ext, ['doc','ppt'])) {
        // Legacy binary — try to pull readable strings
        return _extractBinaryStrings($raw);
    }
    if ($ext === 'pdf') {
        return _extractPdfText($raw);
    }
    // Fallback: try as UTF-8 text
    $text = @mb_convert_encoding($raw, 'UTF-8', 'auto');
    return preg_replace('/[^\x20-\x7E\n\r\t]/u', '', $text ?: '');
}

function _extractZipXml($raw, $ext) {
    $tmp = tempnam(sys_get_temp_dir(), 'noc_doc_');
    file_put_contents($tmp, $raw);
    $zip = new ZipArchive();
    if ($zip->open($tmp) !== true) { @unlink($tmp); return '[Could not open file]'; }
    $text = '';
    try {
        if ($ext === 'docx') {
            $xml = $zip->getFromName('word/document.xml') ?: '';
            $xml = str_replace(['</w:p>','</w:tr>'], ["\n","\n"], $xml);
            $text = strip_tags($xml);
        } elseif ($ext === 'pptx') {
            for ($i = 1; $i <= 100; $i++) {
                $xml = $zip->getFromName("ppt/slides/slide{$i}.xml");
                if ($xml === false) break;
                $xml = str_replace('</a:p>', "\n", $xml);
                $text .= "-- Slide {$i} --\n" . strip_tags($xml) . "\n";
            }
        } elseif ($ext === 'xlsx') {
            // Get shared strings
            $ss  = $zip->getFromName('xl/sharedStrings.xml') ?: '';
            preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $ss, $m);
            $text = implode("\t", $m[1]);
        }
    } finally {
        $zip->close();
        @unlink($tmp);
    }
    return trim(preg_replace('/[ \t]{2,}/', ' ', $text));
}

function _extractPdfText($raw) {
    $text = '';
    // Extract text objects between BT...ET
    preg_match_all('/BT\b(.+?)\bET/s', $raw, $blocks);
    foreach ($blocks[1] as $blk) {
        // Tj operator: (text) Tj
        preg_match_all('/\(([^)\\\\]*(?:\\\\.[^)\\\\]*)*)\)\s*Tj/s', $blk, $tj);
        foreach ($tj[1] as $t) $text .= _pdfUnescape($t) . ' ';
        // TJ operator: [(text) n (text)] TJ
        preg_match_all('/\[([^\]]*)\]\s*TJ/s', $blk, $tjArr);
        foreach ($tjArr[1] as $arr) {
            preg_match_all('/\(([^)\\\\]*(?:\\\\.[^)\\\\]*)*)\)/s', $arr, $parts);
            foreach ($parts[1] as $t) $text .= _pdfUnescape($t);
            $text .= ' ';
        }
        // Td/TD/T* often mean newline
        if (preg_match('/T[dD*]/', $blk)) $text .= "\n";
    }
    $text = trim(preg_replace('/\s{3,}/', "\n", $text));
    return $text ?: '[PDF text could not be extracted — may be image-based]';
}

function _pdfUnescape($s) {
    return strtr($s, ['\\n'=>"\n",'\\r'=>"\r",'\\t'=>"\t",'\\'.'\\'=>'\\','\\'.'('=>'(','\\)'=>')']);
}

function _extractBinaryStrings($raw) {
    preg_match_all('/[\x20-\x7E]{5,}/', $raw, $m);
    return implode(' ', $m[0]);
}

// ── STREAMING HELPERS ─────────────────────────────────────────────────────────
function _doStream($ch) {
    curl_exec($ch);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    curl_close($ch);
    if ($errno) sseError("Curl error $errno: $err");
    echo "event: done\ndata: {}\n\n"; flush();
}

function _curlBase($url, $payload, $headers) {
    $buf = '';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_WRITEFUNCTION  => function($ch, $data) use (&$buf) {
            $buf .= $data;
            while (($pos = strpos($buf, "\n")) !== false) {
                $line = rtrim(substr($buf, 0, $pos));
                $buf  = substr($buf, $pos + 1);
                if (strpos($line, 'data: ') !== 0) continue;
                yield $line;
            }
            return strlen($data);
        },
    ]);
    return [$ch, &$buf];
}

function _streamClaude($key, $model, $sys, $msg, $images=[]) {
    // Build user content — multi-part if images present
    $userContent = [];
    foreach ($images as $img) {
        $userContent[] = ['type'=>'image','source'=>['type'=>'base64','media_type'=>$img['type'],'data'=>$img['data']]];
    }
    $userContent[] = ['type'=>'text','text'=>$msg];
    $msgContent = count($userContent) === 1 ? $msg : $userContent;

    $payload = json_encode([
        'model'    => $model, 'max_tokens' => 1400, 'stream' => true,
        'system'   => $sys,
        'messages' => [['role'=>'user','content'=>$msgContent]],
    ]);
    $buf = '';
    $ch  = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', "x-api-key: $key", 'anthropic-version: 2023-06-01'],
        CURLOPT_TIMEOUT => 120, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$buf) {
            $buf .= $data;
            while (($pos = strpos($buf, "\n")) !== false) {
                $line = rtrim(substr($buf, 0, $pos));
                $buf  = substr($buf, $pos + 1);
                if (strpos($line, 'data: ') !== 0) continue;
                $json = substr($line, 6);
                if ($json === '[DONE]') { echo "event: done\ndata: {}\n\n"; flush(); continue; }
                $ev = json_decode($json, true);
                if (!is_array($ev)) continue;
                if (($ev['type']??'') === 'content_block_delta') {
                    $t = $ev['delta']['text'] ?? '';
                    if ($t !== '') { echo 'data: ' . json_encode(['t'=>$t], JSON_UNESCAPED_UNICODE) . "\n\n"; flush(); }
                } elseif (($ev['type']??'') === 'message_stop') {
                    echo "event: done\ndata: {}\n\n"; flush();
                }
            }
            return strlen($data);
        },
    ]);
    curl_exec($ch);
    $errno = curl_errno($ch); $errmsg = curl_error($ch); curl_close($ch);
    if ($errno) sseError("Claude curl error $errno: $errmsg");
    echo "event: done\ndata: {}\n\n"; flush();
}

function _streamOpenAI($key, $model, $sys, $msg, $images=[]) {
    $userContent = [];
    foreach ($images as $img) {
        $userContent[] = ['type'=>'image_url','image_url'=>['url'=>'data:'.$img['type'].';base64,'.$img['data']]];
    }
    $userContent[] = ['type'=>'text','text'=>$msg];
    $msgContent = count($userContent) === 1 ? $msg : $userContent;

    $payload = json_encode([
        'model'    => $model, 'max_tokens' => 1400, 'stream' => true,
        'messages' => [['role'=>'system','content'=>$sys],['role'=>'user','content'=>$msgContent]],
    ]);
    $buf = '';
    $ch  = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', "Authorization: Bearer $key"],
        CURLOPT_TIMEOUT => 120, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$buf) {
            $buf .= $data;
            while (($pos = strpos($buf, "\n")) !== false) {
                $line = rtrim(substr($buf, 0, $pos));
                $buf  = substr($buf, $pos + 1);
                if (strpos($line, 'data: ') !== 0) continue;
                $json = substr($line, 6);
                if ($json === '[DONE]') { echo "event: done\ndata: {}\n\n"; flush(); continue; }
                $ev = json_decode($json, true);
                if (!is_array($ev)) continue;
                $t = $ev['choices'][0]['delta']['content'] ?? '';
                if ($t !== '') { echo 'data: ' . json_encode(['t'=>$t], JSON_UNESCAPED_UNICODE) . "\n\n"; flush(); }
                if (($ev['choices'][0]['finish_reason']??'') === 'stop') { echo "event: done\ndata: {}\n\n"; flush(); }
            }
            return strlen($data);
        },
    ]);
    curl_exec($ch);
    $errno = curl_errno($ch); $errmsg = curl_error($ch); curl_close($ch);
    if ($errno) sseError("OpenAI curl error $errno: $errmsg");
    echo "event: done\ndata: {}\n\n"; flush();
}

function _streamGemini($key, $model, $sys, $msg, $images=[]) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:streamGenerateContent?key={$key}&alt=sse";
    $parts = [];
    foreach ($images as $img) {
        $parts[] = ['inlineData'=>['mimeType'=>$img['type'],'data'=>$img['data']]];
    }
    $parts[] = ['text'=>$msg];
    $payload = json_encode([
        'system_instruction' => ['parts' => [['text' => $sys]]],
        'contents'           => [['role'=>'user','parts'=>$parts]],
        'generationConfig'   => ['maxOutputTokens' => 1400],
    ]);
    $buf = '';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 120, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$buf) {
            $buf .= $data;
            while (($pos = strpos($buf, "\n")) !== false) {
                $line = rtrim(substr($buf, 0, $pos));
                $buf  = substr($buf, $pos + 1);
                if (strpos($line, 'data: ') !== 0) continue;
                $ev = json_decode(substr($line, 6), true);
                if (!is_array($ev)) continue;
                $t = $ev['candidates'][0]['content']['parts'][0]['text'] ?? '';
                if ($t !== '') { echo 'data: ' . json_encode(['t'=>$t], JSON_UNESCAPED_UNICODE) . "\n\n"; flush(); }
                if (($ev['candidates'][0]['finishReason']??'') === 'STOP') { echo "event: done\ndata: {}\n\n"; flush(); }
            }
            return strlen($data);
        },
    ]);
    curl_exec($ch);
    $errno = curl_errno($ch); $errmsg = curl_error($ch); curl_close($ch);
    if ($errno) sseError("Gemini curl error $errno: $errmsg");
    echo "event: done\ndata: {}\n\n"; flush();
}

function _streamGrok($key, $model, $sys, $msg, $images=[]) {
    // xAI Grok uses OpenAI-compatible API
    $userContent = [];
    foreach ($images as $img) {
        $userContent[] = ['type'=>'image_url','image_url'=>['url'=>'data:'.$img['type'].';base64,'.$img['data']]];
    }
    $userContent[] = ['type'=>'text','text'=>$msg];
    $msgContent = count($userContent) === 1 ? $msg : $userContent;

    $payload = json_encode([
        'model'    => $model, 'max_tokens' => 1400, 'stream' => true,
        'messages' => [['role'=>'system','content'=>$sys],['role'=>'user','content'=>$msgContent]],
    ]);
    $buf = '';
    $ch  = curl_init('https://api.x.ai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', "Authorization: Bearer $key"],
        CURLOPT_TIMEOUT => 120, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$buf) {
            $buf .= $data;
            while (($pos = strpos($buf, "\n")) !== false) {
                $line = rtrim(substr($buf, 0, $pos));
                $buf  = substr($buf, $pos + 1);
                if (strpos($line, 'data: ') !== 0) continue;
                $json = substr($line, 6);
                if ($json === '[DONE]') { echo "event: done\ndata: {}\n\n"; flush(); continue; }
                $ev = json_decode($json, true);
                if (!is_array($ev)) continue;
                $t = $ev['choices'][0]['delta']['content'] ?? '';
                if ($t !== '') { echo 'data: ' . json_encode(['t'=>$t], JSON_UNESCAPED_UNICODE) . "\n\n"; flush(); }
                if (($ev['choices'][0]['finish_reason']??'') === 'stop') { echo "event: done\ndata: {}\n\n"; flush(); }
            }
            return strlen($data);
        },
    ]);
    curl_exec($ch);
    $errno = curl_errno($ch); $errmsg = curl_error($ch); curl_close($ch);
    if ($errno) sseError("Grok curl error $errno: $errmsg");
    echo "event: done\ndata: {}\n\n"; flush();
}
