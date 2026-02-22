<?php
session_start();
require_once 'config.php';
$loggedIn = !empty($_SESSION['uid']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tabadul NOC Sentinel</title>
<script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#080c14; --surface:#0d1424; --surface2:#111d35; --surface3:#162040;
  --border:#1e2d4a; --border2:#243452;
  --text:#c8d6f0; --muted:#4a6080; --bright:#fff;
  --cyan:#00d4ff; --green:#00e676; --yellow:#ffb300; --orange:#ff6d00;
  --red:#ff1744; --purple:#bb86fc;
  --sev5:#FF1744; --sev4:#FF6D00; --sev3:#FFB300; --sev2:#78909C; --sev1:#00B0FF; --sev0:#9E9E9E;
  --sidebar:220px;
}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:'Space Grotesk','Inter',sans-serif;height:100vh;display:flex;overflow:hidden;font-size:13px;line-height:1.5}
h1,h2,h3,h4{font-family:'Space Grotesk',sans-serif;letter-spacing:-0.3px}
.mono{font-family:'JetBrains Mono',monospace}
a{color:inherit;text-decoration:none}
button{font-family:'Inter',sans-serif;cursor:pointer}
input,select,textarea{font-family:'JetBrains Mono',monospace}

/* ── SIDEBAR ── */
#sidebar{
  width:var(--sidebar);background:var(--surface);border-right:1px solid var(--border);
  display:flex;flex-direction:column;flex-shrink:0;z-index:10;
  transition:width 0.2s;
}
.sidebar-logo{
  padding:18px 16px 14px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:10px;
}
.logo-icon{width:34px;height:34px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.logo-icon img{width:34px;height:34px;object-fit:contain}
.logo-text{font-size:14px;font-weight:700;color:#fff;letter-spacing:1px}
.logo-sub{font-size:9px;color:var(--muted);font-family:'JetBrains Mono',monospace;letter-spacing:1px}

nav{flex:1;padding:10px 0;overflow:hidden}
.nav-item{
  display:flex;align-items:center;gap:12px;padding:10px 16px;
  cursor:pointer;transition:all 0.15s;border-left:3px solid transparent;
  position:relative;white-space:nowrap;
}
.nav-item:hover{background:var(--surface2);color:#fff}
.nav-item.active{background:rgba(0,212,255,0.08);border-left-color:var(--cyan);color:var(--cyan)}
.nav-icon{font-size:16px;flex-shrink:0;width:20px;text-align:center}
.nav-label{font-size:13px;font-weight:500}
.nav-badge{
  background:var(--red);color:#fff;font-size:10px;font-weight:700;
  padding:1px 6px;border-radius:10px;min-width:18px;text-align:center;
  font-family:'JetBrains Mono',monospace;margin-left:auto;
}
.nav-badge.hidden{display:none}

.sidebar-bottom{padding:12px 0;border-top:1px solid var(--border)}
.nav-item.logout{color:var(--muted)}
.nav-item.logout:hover{color:var(--red)}

/* ── MAIN ── */
#main-area{flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0}

/* ── HEADER ── */
#topbar{
  background:var(--surface);border-bottom:1px solid var(--border);
  padding:0 20px;height:52px;display:flex;align-items:center;gap:20px;flex-shrink:0;
}
.topbar-title{font-size:15px;font-weight:700;color:#fff;flex:1}
.stat-chip{
  display:flex;align-items:center;gap:6px;padding:4px 10px;
  border-radius:6px;border:1px solid var(--border);font-size:11px;
  font-family:'JetBrains Mono',monospace;
}
.stat-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.stat-num{color:#fff;font-weight:700}
.stat-lbl{color:var(--muted)}
.refresh-info{font-size:10px;color:var(--muted);font-family:'JetBrains Mono',monospace}
.live-pill{
  display:flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;
  background:rgba(0,230,118,0.1);border:1px solid rgba(0,230,118,0.3);
  font-size:10px;font-weight:700;color:var(--green);font-family:'JetBrains Mono',monospace;
}
.live-dot{width:6px;height:6px;border-radius:50%;background:var(--green);animation:pulse 1.5s infinite}

/* ── PAGES ── */
.page{flex:1;display:none;flex-direction:column;overflow:hidden}
.page.active{display:flex}

/* ── MAP PAGE ── */
#page-map{position:relative}
#map-toolbar{
  background:var(--surface);border-bottom:1px solid var(--border);
  padding:6px 16px;display:flex;align-items:center;gap:8px;flex-shrink:0;flex-wrap:wrap;
}
.tb-btn{
  background:none;border:1px solid var(--border);border-radius:5px;
  padding:4px 10px;color:var(--muted);font-size:10px;font-family:'JetBrains Mono',monospace;
  transition:all 0.15s;
}
.tb-btn:hover{border-color:var(--cyan);color:var(--cyan)}
.tb-btn.active{background:rgba(0,212,255,0.1);border-color:var(--cyan);color:var(--cyan)}
.tb-btn.add{border-color:rgba(0,230,118,0.4);color:var(--green)}
.tb-btn.add:hover{background:rgba(0,230,118,0.08)}
.tb-sep{width:1px;height:18px;background:var(--border);margin:0 2px}
.tb-search{
  display:flex;align-items:center;gap:6px;background:var(--surface2);
  border:1px solid var(--border);border-radius:5px;padding:3px 8px;
}
.tb-search input{background:none;border:none;outline:none;color:#fff;font-size:11px;width:130px}
.tb-search input::placeholder{color:var(--muted)}

#map-wrap{flex:1;position:relative;overflow:hidden}
#vis-network{width:100%;height:100%}

/* ── MAP LAYER RAIL ── */
#layer-rail{
  height:30px;background:var(--surface);border-bottom:1px solid var(--border);
  display:flex;align-items:stretch;flex-shrink:0;
}
.lr-cell{
  flex:1;font-size:8px;font-family:'JetBrains Mono',monospace;text-transform:uppercase;
  letter-spacing:0.8px;color:var(--muted);display:flex;align-items:center;justify-content:center;
  border-top:2px solid transparent;border-right:1px solid var(--border);white-space:nowrap;padding:0 4px;
}
.lr-cell:last-child{border-right:none}
.lr-ext{border-top-color:#f1c40f;color:#f1c40faa}
.lr-wan{border-top-color:#3498db88}
.lr-inet,.lr-core{border-top-color:#3498db;color:#90caf9aa}
.lr-fw,.lr-fp{border-top-color:#e74c3c;color:#ef9a9aaa}
.lr-tor{border-top-color:#3498db;color:#90caf9aa}
.lr-app{border-top-color:#e67e22;color:#ffb74daa}
.lr-srv{border-top-color:#2ecc71;color:#a5d6a7aa}
.lr-hsm{border-top-color:#9b59b6;color:#ce93d8aa}

/* ── DETAIL PANEL ── */
#detail-panel{
  position:absolute;top:0;right:0;bottom:0;width:300px;
  background:var(--surface);border-left:1px solid var(--border);
  display:flex;flex-direction:column;
  transform:translateX(100%);transition:transform 0.22s ease;z-index:5;
}
#detail-panel.open{transform:translateX(0)}
.dp-header{
  padding:14px 16px 10px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;flex-shrink:0;
}
.dp-title{font-size:11px;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:0.5px}
.dp-close{background:none;border:none;color:var(--muted);font-size:18px;line-height:1;padding:0}
.dp-close:hover{color:#fff}
.dp-body{flex:1;overflow-y:auto;padding:14px 16px}
.dp-footer{padding:10px 16px;border-top:1px solid var(--border);display:flex;gap:8px}

/* ── ZOOM CONTROLS ── */
#zoom-ctrl{
  position:absolute;bottom:16px;left:50%;transform:translateX(-50%);
  display:flex;gap:4px;z-index:4;
}
.z-btn{background:var(--surface);border:1px solid var(--border);color:var(--text);
  padding:5px 10px;border-radius:4px;cursor:pointer;font-size:12px;transition:all 0.15s;}
.z-btn:hover{border-color:var(--cyan);color:var(--cyan)}

/* ── ALARM INDICATOR OVERLAY ── */
#alarm-overlay{position:absolute;inset:0;pointer-events:none;z-index:3;overflow:hidden}
.alarm-dot{
  position:absolute;width:14px;height:14px;border-radius:50%;
  margin:-7px 0 0 -7px;pointer-events:none;
  animation:alarmPulse 1.2s ease-in-out infinite;
}
@keyframes alarmPulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.8);opacity:0.4}}

/* ── INFO COMPONENTS ── */
.info-section{margin-bottom:14px}
.info-sec-title{font-size:9px;text-transform:uppercase;letter-spacing:1.5px;color:var(--muted);font-family:'JetBrains Mono',monospace;margin-bottom:6px}
.info-row{display:flex;justify-content:space-between;align-items:flex-start;padding:5px 0;border-bottom:1px solid var(--border);gap:8px}
.info-row:last-child{border:none}
.info-key{font-size:11px;color:var(--muted);font-family:'JetBrains Mono',monospace;flex-shrink:0}
.info-val{font-size:11px;color:#fff;font-family:'JetBrains Mono',monospace;text-align:right;word-break:break-all}
.iface-tag{display:inline-block;background:var(--surface2);border:1px solid var(--border);border-radius:3px;padding:1px 6px;font-size:9px;font-family:'JetBrains Mono',monospace;color:var(--cyan);margin:2px}
.sev-pill{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;font-family:'JetBrains Mono',monospace}
.pill-ok   {background:rgba(0,230,118,0.12);color:var(--green);border:1px solid rgba(0,230,118,0.3)}
.pill-warn {background:rgba(255,179,0,0.12);color:var(--yellow);border:1px solid rgba(255,179,0,0.3)}
.pill-crit {background:rgba(255,23,68,0.12);color:var(--red);border:1px solid rgba(255,23,68,0.3)}
.pill-info {background:rgba(0,212,255,0.1);color:var(--cyan);border:1px solid rgba(0,212,255,0.3)}
.pill-down {background:rgba(255,23,68,0.2);color:var(--red);border:1px solid rgba(255,23,68,0.5)}

/* ── EDIT FIELDS ── */
.ef{margin-bottom:10px}
.ef label{font-size:9px;text-transform:uppercase;letter-spacing:1.5px;color:var(--muted);font-family:'JetBrains Mono',monospace;margin-bottom:4px;display:block}
.ef input,.ef select,.ef textarea{
  width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:6px;
  padding:7px 10px;color:#fff;font-size:11px;font-family:'JetBrains Mono',monospace;
  outline:none;transition:border-color 0.15s;
}
.ef input:focus,.ef select:focus,.ef textarea:focus{border-color:var(--cyan)}
.ef textarea{resize:vertical;min-height:56px}
.ef select option{background:var(--surface2)}
.ef-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}

/* ── ALARMS PAGE ── */
#page-alarms{flex-direction:column}
.page-header{
  background:var(--surface);border-bottom:1px solid var(--border);
  padding:14px 20px;display:flex;align-items:center;gap:12px;flex-shrink:0;flex-wrap:wrap;
}
.page-title{font-size:16px;font-weight:700;color:#fff;flex:1}
.filter-btn{
  background:none;border:1px solid var(--border2);border-radius:5px;
  padding:4px 12px;color:var(--muted);font-size:11px;font-family:'JetBrains Mono',monospace;
  cursor:pointer;transition:all 0.15s;
}
.filter-btn:hover{border-color:var(--cyan);color:var(--cyan)}
.filter-btn.active{background:rgba(0,212,255,0.08);border-color:var(--cyan);color:var(--cyan)}
.filter-btn.f-dis{border-color:var(--sev5)44;color:var(--sev5)}
.filter-btn.f-high{border-color:var(--sev4)44;color:var(--sev4)}
.filter-btn.f-avg{border-color:var(--sev3)44;color:var(--sev3)}

.alarms-table-wrap{flex:1;overflow-y:auto}
table.alarms-tbl{width:100%;border-collapse:collapse}
.alarms-tbl th{
  position:sticky;top:0;background:#0a0f1e;padding:9px 14px;
  text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;
  letter-spacing:1px;color:var(--muted);font-family:'JetBrains Mono',monospace;
  border-bottom:1px solid var(--border);z-index:1;
}
.alarms-tbl td{
  padding:10px 14px;border-bottom:1px solid var(--border)55;
  font-size:12px;vertical-align:middle;
}
.alarms-tbl tr:hover td{background:rgba(255,255,255,0.02)}
.sev-bar{width:4px;height:100%;border-radius:2px;min-height:36px;display:inline-block}
.sev-icon{font-size:14px;display:inline-block;width:20px;text-align:center}
.alarm-host{color:var(--cyan);font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:600}
.alarm-name{color:var(--text);font-size:12px;line-height:1.4}
.duration{color:var(--muted);font-family:'JetBrains Mono',monospace;font-size:10px}
.ack-btn{
  background:none;border:1px solid var(--border);border-radius:4px;
  padding:3px 8px;color:var(--muted);font-size:10px;font-family:'JetBrains Mono',monospace;
  cursor:pointer;transition:all 0.15s;white-space:nowrap;
}
.ack-btn:hover{border-color:var(--green);color:var(--green)}
.ack-btn.acked{color:var(--green);border-color:var(--green)44;background:rgba(0,230,118,0.06)}
.empty-state{padding:60px;text-align:center;color:var(--muted);font-size:13px}

/* ── HOSTS PAGE ── */
#page-hosts{flex-direction:column}
.hosts-grid{flex:1;padding:16px;display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;overflow-y:auto;align-content:start}
.host-card{
  background:var(--surface);border:1px solid var(--border);border-radius:10px;
  padding:14px;cursor:pointer;transition:all 0.15s;position:relative;overflow:hidden;
}
.host-card:hover{border-color:var(--cyan)55;transform:translateY(-1px)}
.host-card.has-problems{border-color:var(--sev4)44}
.host-card.has-disaster{border-color:var(--sev5);box-shadow:0 0 12px var(--sev5)33}
.host-card.unavailable{border-color:var(--red)55;opacity:0.8}
.hc-top-stripe{position:absolute;top:0;left:0;right:0;height:3px}
.hc-name{font-size:13px;font-weight:700;color:#fff;margin-bottom:3px;font-family:'JetBrains Mono',monospace;word-break:break-all}
.hc-ip{font-size:10px;color:var(--cyan);font-family:'JetBrains Mono',monospace;margin-bottom:10px}
.hc-bottom{display:flex;align-items:center;justify-content:space-between}
.problem-badge{
  background:var(--red);color:#fff;font-size:10px;font-weight:700;
  padding:2px 8px;border-radius:12px;font-family:'JetBrains Mono',monospace;
}
.problem-badge.warn{background:var(--orange)}
.problem-badge.avg{background:var(--yellow);color:#000}

/* ── SETTINGS PAGE ── */
#page-settings{flex-direction:column;overflow-y:auto}
.settings-wrap{max-width:680px;padding:24px;display:flex;flex-direction:column;gap:24px}
.settings-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:20px}
.settings-card h3{font-size:13px;font-weight:700;color:#fff;margin-bottom:16px;text-transform:uppercase;letter-spacing:0.5px}
.layouts-list{display:flex;flex-direction:column;gap:8px;margin-top:10px}
.layout-row{display:flex;align-items:center;gap:10px;padding:8px 12px;background:var(--surface2);border-radius:7px;border:1px solid var(--border)}
.layout-name{flex:1;font-size:12px;font-family:'JetBrains Mono',monospace;color:#fff}
.layout-date{font-size:10px;color:var(--muted);font-family:'JetBrains Mono',monospace}

/* ── MODAL OVERLAY ── */
.modal-overlay{
  position:fixed;inset:0;z-index:200;
  background:rgba(8,12,20,0.88);backdrop-filter:blur(5px);
  display:none;align-items:center;justify-content:center;
}
.modal-overlay.open{display:flex}
.modal-card{
  background:var(--surface);border:1px solid var(--border2);border-radius:14px;
  padding:28px;width:440px;max-height:90vh;overflow-y:auto;
  box-shadow:0 24px 80px rgba(0,0,0,0.6);
}
.modal-card h3{font-size:15px;font-weight:700;color:#fff;margin-bottom:20px}
.modal-btns{display:flex;gap:8px;justify-content:flex-end;margin-top:20px}

/* ── BUTTONS ── */
.btn{padding:8px 16px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;border:none;transition:all 0.15s;font-family:'JetBrains Mono',monospace}
.btn-primary{background:var(--cyan);color:#000}
.btn-primary:hover{background:#33ddff}
.btn-success{background:var(--green);color:#000}
.btn-success:hover{filter:brightness(1.1)}
.btn-ghost{background:none;border:1px solid var(--border);color:var(--muted)}
.btn-ghost:hover{border-color:var(--cyan);color:var(--cyan)}
.btn-danger{background:rgba(255,23,68,0.12);border:1px solid rgba(255,23,68,0.3);color:#ff5252}
.btn-danger:hover{background:rgba(255,23,68,0.22)}

/* ── LOGIN OVERLAY ── */
#login-overlay{
  position:fixed;inset:0;z-index:9999;
  background:rgba(8,12,20,0.96);backdrop-filter:blur(12px);
  display:flex;align-items:center;justify-content:center;
}
.login-card{
  background:var(--surface);border:1px solid var(--border2);border-radius:16px;
  padding:44px 40px;width:360px;text-align:center;
  box-shadow:0 32px 80px rgba(0,0,0,0.7);
}
.login-logo{width:90px;height:90px;margin:0 auto 20px;display:flex;align-items:center;justify-content:center;}
.login-logo img{width:90px;height:90px;object-fit:contain;filter:drop-shadow(0 0 18px rgba(0,212,255,0.35))}
.login-title{font-size:22px;font-weight:700;color:#fff;letter-spacing:2px;margin-bottom:4px}
.login-sub{font-size:10px;color:var(--muted);font-family:'JetBrains Mono',monospace;margin-bottom:28px;text-transform:uppercase;letter-spacing:2px}
.login-form{display:flex;flex-direction:column;gap:12px}
.login-form input{
  background:var(--surface2);border:1px solid var(--border);border-radius:8px;
  padding:11px 14px;color:#fff;font-size:13px;outline:none;transition:border-color 0.15s;
}
.login-form input:focus{border-color:var(--cyan)}
.login-form input::placeholder{color:var(--muted)}
.login-err{color:var(--red);font-size:11px;font-family:'JetBrains Mono',monospace;min-height:14px}
.login-btn{
  background:linear-gradient(135deg,#0077ff,#00d4ff);border:none;border-radius:8px;
  padding:12px;color:#fff;font-size:13px;font-weight:700;cursor:pointer;
  letter-spacing:0.5px;transition:opacity 0.15s;
}
.login-btn:hover{opacity:0.88}

/* ── TOOLTIP ── */
#vis-tip{
  position:fixed;background:rgba(13,20,36,0.97);border:1px solid var(--border2);
  border-radius:7px;padding:9px 13px;font-size:11px;pointer-events:none;
  backdrop-filter:blur(8px);z-index:50;display:none;max-width:240px;
}
#vis-tip .t-name{font-weight:700;color:#fff;margin-bottom:3px}
#vis-tip .t-ip{font-family:'JetBrains Mono',monospace;color:var(--cyan);font-size:10px}
#vis-tip .t-role{color:var(--muted);font-size:10px;margin-top:2px}
#vis-tip .t-alarm{color:var(--red);font-size:10px;margin-top:4px;font-weight:600}

/* ── LUCIDE ICONS ── */
.nav-icon{display:flex;align-items:center;justify-content:center;width:20px}
.nav-icon svg{stroke:currentColor}

/* ── INTEL PAGE ── */
.intel-ctx-btn{background:var(--surface2);border:1px solid var(--border);border-radius:20px;padding:4px 12px;color:var(--muted);font-size:10px;font-family:'JetBrains Mono',monospace;cursor:pointer;display:inline-flex;align-items:center;gap:5px;white-space:nowrap;transition:all 0.15s;flex-shrink:0}
.intel-ctx-btn:hover{border-color:var(--cyan);color:var(--cyan)}
.intel-ctx-btn.active{background:rgba(0,212,255,0.1);border-color:var(--cyan);color:var(--cyan)}
.ai-msg{display:flex;flex-direction:column;gap:3px;max-width:92%}
.ai-msg.user{align-self:flex-end}
.ai-msg.assistant{align-self:flex-start}
.ai-bubble{padding:9px 13px;border-radius:10px;font-size:12px;line-height:1.6;white-space:pre-wrap;word-break:break-word}
.ai-bubble.user{background:linear-gradient(135deg,#3b82f6,#6366f1);color:#fff;border-radius:10px 10px 2px 10px}
.ai-bubble.assistant{background:var(--surface2);border:1px solid var(--border);color:var(--text);border-radius:10px 10px 10px 2px}
.ai-bubble.thinking{color:var(--muted);font-style:italic;font-size:11px}
.ai-sender{font-size:9px;color:var(--muted);font-family:'JetBrains Mono',monospace;padding:0 4px}
.ai-badge{display:inline-flex;align-items:center;gap:4px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:4px;padding:1px 6px;font-size:9px;color:#fff;font-family:'JetBrains Mono',monospace;font-weight:600;margin-bottom:4px}

/* ── MAP LIST (sidebar) ── */
.map-section{padding:10px 0 4px;border-top:1px solid var(--border);margin-top:4px;flex-shrink:0}
.map-section-hdr{display:flex;align-items:center;justify-content:space-between;padding:0 14px 6px;font-size:9px;font-family:'JetBrains Mono',monospace;color:var(--muted);text-transform:uppercase;letter-spacing:1px}
.map-import-btn{background:rgba(0,230,118,0.15);border:1px solid rgba(0,230,118,0.3);border-radius:4px;color:var(--green);font-size:9px;font-family:'JetBrains Mono',monospace;padding:2px 7px;cursor:pointer;transition:all 0.15s}
.map-import-btn:hover{background:rgba(0,230,118,0.25)}
.map-list-item{display:flex;align-items:center;gap:8px;padding:7px 14px;cursor:pointer;font-size:11px;color:var(--muted);transition:all 0.15s;border-left:3px solid transparent}
.map-list-item:hover{background:var(--surface2);color:#fff}
.map-list-item.active-map{border-left-color:var(--green);color:var(--green);background:rgba(0,230,118,0.06)}
.map-list-item .map-del{margin-left:auto;color:var(--muted);font-size:10px;padding:1px 4px;border-radius:3px;opacity:0;transition:opacity 0.15s}
.map-list-item:hover .map-del{opacity:1}
.map-list-item .map-del:hover{color:var(--red)}

/* ── IMPORT MODAL ── */
.import-steps{display:flex;gap:0;margin-bottom:20px}
.import-step{flex:1;text-align:center;font-size:9px;font-family:'JetBrains Mono',monospace;color:var(--muted);padding:6px 4px;border-bottom:2px solid var(--border)}
.import-step.active{color:var(--cyan);border-bottom-color:var(--cyan)}
.import-step.done{color:var(--green);border-bottom-color:var(--green)}
.drop-zone{border:2px dashed var(--border2);border-radius:10px;padding:32px 20px;text-align:center;cursor:pointer;transition:all 0.2s;position:relative}
.drop-zone:hover,.drop-zone.drag-over{border-color:var(--cyan);background:rgba(0,212,255,0.04)}
.drop-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer}
.drop-zone-icon{font-size:32px;margin-bottom:10px}
.drop-zone-lbl{font-size:12px;color:var(--muted)}
.drop-zone-lbl b{color:var(--cyan)}
.preview-table{width:100%;border-collapse:collapse;font-size:11px;margin-top:12px;max-height:300px;overflow-y:auto;display:block}
.preview-table th{background:var(--surface2);padding:6px 10px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:9px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;position:sticky;top:0}
.preview-table td{padding:6px 10px;border-bottom:1px solid var(--border)}
.preview-table tr.matched td{color:#fff}
.preview-table tr.unmatched td{color:var(--muted);text-decoration:line-through}
.match-pill{font-size:9px;padding:2px 6px;border-radius:4px;font-family:'JetBrains Mono',monospace;font-weight:700}
.match-ok{background:rgba(0,230,118,0.15);color:var(--green)}
.match-no{background:rgba(255,23,68,0.12);color:var(--red)}
.import-summary{display:flex;gap:12px;margin:12px 0;font-size:11px;font-family:'JetBrains Mono',monospace}
.sum-chip{padding:4px 10px;border-radius:5px;border:1px solid var(--border)}

/* ── MISC ── */
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--border2);border-radius:2px}
.badge-blink{animation:badgePulse 1s infinite}
@keyframes badgePulse{0%,100%{transform:scale(1)}50%{transform:scale(1.15)}}
@keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
</style>
</head>
<body>

<!-- ══ LOGIN ══ -->
<div id="login-overlay" <?= $loggedIn ? 'style="display:none"' : '' ?>>
  <div class="login-card">
    <div class="login-logo"><img src="logo.png" alt="Tabadul"></div>
    <div class="login-title">TABADUL</div>
    <div class="login-sub">NOC Command Center</div>
    <div class="login-form">
      <input id="l-user" type="text" placeholder="Username" autocomplete="username" />
      <input id="l-pass" type="password" placeholder="Password" autocomplete="current-password"
             onkeydown="if(event.key==='Enter') doLogin()" />
      <div id="l-err" class="login-err"></div>
      <button class="login-btn" onclick="doLogin()">Sign In →</button>
    </div>
  </div>
</div>

<!-- ══ SIDEBAR ══ -->
<div id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon"><img src="logo.png" alt="Tabadul"></div>
    <div>
      <div class="logo-text">TABADUL</div>
      <div class="logo-sub">NOC SENTINEL</div>
    </div>
  </div>
  <nav>
    <div class="nav-item active" data-page="map" onclick="navigate('map')">
      <span class="nav-icon"><i data-lucide="network" style="width:16px;height:16px"></i></span>
      <span class="nav-label">Network Map</span>
    </div>
    <div class="nav-item" data-page="alarms" onclick="navigate('alarms')">
      <span class="nav-icon"><i data-lucide="bell" style="width:16px;height:16px"></i></span>
      <span class="nav-label">Active Alarms</span>
      <span class="nav-badge hidden" id="alarm-badge">0</span>
    </div>
    <div class="nav-item" data-page="hosts" onclick="navigate('hosts')">
      <span class="nav-icon"><i data-lucide="server" style="width:16px;height:16px"></i></span>
      <span class="nav-label">Hosts</span>
    </div>
    <div class="nav-item" data-page="intel" onclick="navigate('intel')">
      <span class="nav-icon"><i data-lucide="brain-circuit" style="width:16px;height:16px"></i></span>
      <span class="nav-label">AI Intelligence</span>
    </div>
    <div class="nav-item" data-page="settings" onclick="navigate('settings')">
      <span class="nav-icon"><i data-lucide="settings-2" style="width:16px;height:16px"></i></span>
      <span class="nav-label">Settings</span>
    </div>

    <!-- MAP LIST -->
    <div class="map-section">
      <div class="map-section-hdr">
        <span>Maps</span>
        <button class="map-import-btn" onclick="openImportModal()">+ Import</button>
      </div>
      <div id="map-list">
        <div class="map-list-item active-map" data-mapid="0" onclick="switchMap(0,'Network Map')">
          <span>🗺️</span><span>Network Map</span>
        </div>
      </div>
    </div>
  </nav>
  <div class="sidebar-bottom">
    <div class="nav-item logout" onclick="doLogout()">
      <span class="nav-icon"><i data-lucide="log-out" style="width:15px;height:15px"></i></span>
      <span class="nav-label">Logout</span>
    </div>
    <div style="padding:10px 14px 8px;border-top:1px solid var(--border);margin-top:2px">
      <div style="font-size:9px;color:var(--muted);font-family:'JetBrains Mono',monospace;line-height:1.7">
        Made with <span style="color:#e74c3c">&#9829;</span> by<br>
        <span style="color:var(--text);font-weight:600">Mustafa Raad</span><br>
        NOC &amp; Automation Team
      </div>
    </div>
  </div>
</div>

<!-- ══ MAIN ══ -->
<div id="main-area">

  <!-- TOP BAR -->
  <div id="topbar">
    <div class="topbar-title" id="page-title">Network Map</div>
    <div class="stat-chip"><div class="stat-dot" style="background:#3498db"></div><span class="stat-num" id="sc-total">—</span><span class="stat-lbl">hosts</span></div>
    <div class="stat-chip"><div class="stat-dot" style="background:var(--green)"></div><span class="stat-num" id="sc-ok">—</span><span class="stat-lbl">ok</span></div>
    <div class="stat-chip"><div class="stat-dot" style="background:var(--red)"></div><span class="stat-num" id="sc-prob">—</span><span class="stat-lbl">problems</span></div>
    <div class="stat-chip"><div class="stat-dot" style="background:var(--yellow)"></div><span class="stat-num" id="sc-alarms">—</span><span class="stat-lbl">alarms</span></div>
    <div class="refresh-info" id="refresh-info">Connecting...</div>
    <div class="live-pill"><div class="live-dot"></div>LIVE</div>
  </div>

  <!-- ══ MAP PAGE ══ -->
  <div class="page active" id="page-map">
    <!-- Layer rail -->
    <div id="layer-rail">
      <div class="lr-cell lr-ext">External</div>
      <div class="lr-cell lr-wan">WAN/ISP</div>
      <div class="lr-cell lr-inet">Internet SW</div>
      <div class="lr-cell lr-core">Core SW</div>
      <div class="lr-cell lr-fw">Payment FW</div>
      <div class="lr-cell lr-fp">IPS/Firepower</div>
      <div class="lr-cell lr-tor">NX-OS TOR</div>
      <div class="lr-cell lr-app">App FW / LB</div>
      <div class="lr-cell lr-srv">Servers</div>
      <div class="lr-cell lr-hsm">HSM/Infra</div>
    </div>
    <!-- Map toolbar -->
    <div id="map-toolbar">
      <span style="font-size:10px;color:var(--muted);font-family:'JetBrains Mono',monospace;text-transform:uppercase;letter-spacing:1px">Layers:</span>
      <button class="tb-btn active" onclick="filterLayer('all',this)">ALL</button>
      <button class="tb-btn" onclick="filterLayer('external',this)">External</button>
      <button class="tb-btn" onclick="filterLayer('switching',this)">Switching</button>
      <button class="tb-btn" onclick="filterLayer('firewall',this)">Firewalls</button>
      <button class="tb-btn" onclick="filterLayer('loadbalancer',this)">LB/FW</button>
      <button class="tb-btn" onclick="filterLayer('servers',this)">Servers</button>
      <button class="tb-btn" onclick="filterLayer('hsm',this)">HSM</button>
      <div class="tb-sep"></div>
      <div class="tb-search">
        <span style="color:var(--muted);font-size:11px">🔍</span>
        <input id="map-search" placeholder="Search..." oninput="searchNode(this.value)">
      </div>
      <div class="tb-sep"></div>
      <button class="tb-btn" onclick="visNetwork.fit()">⊞ Fit</button>
      <button class="tb-btn" onclick="saveLayoutPrompt()">💾 Save Layout</button>
      <button class="tb-btn" onclick="resetLayout()">↺ Reset</button>
      <button class="tb-btn add" onclick="openAddModal()">＋ Add Node</button>
    </div>
    <!-- Canvas -->
    <div id="map-wrap">
      <div id="vis-network"></div>
      <div id="alarm-overlay"></div>
      <div id="zoom-ctrl">
        <button class="z-btn" onclick="visNetwork.moveTo({scale:visNetwork.getScale()*1.3})">＋</button>
        <button class="z-btn" onclick="visNetwork.fit()">◎</button>
        <button class="z-btn" onclick="visNetwork.moveTo({scale:visNetwork.getScale()*0.77})">－</button>
      </div>
    </div>
    <!-- Detail panel -->
    <div id="detail-panel">
      <div class="dp-header">
        <span class="dp-title" id="dp-title">Device Details</span>
        <button class="dp-close" onclick="closePanel()">×</button>
      </div>
      <div class="dp-body" id="dp-body"></div>
      <div class="dp-footer" id="dp-footer" style="display:none"></div>
    </div>
  </div>

  <!-- ══ ALARMS PAGE ══ -->
  <div class="page" id="page-alarms">
    <div class="page-header">
      <div class="page-title">🔔 Active Alarms</div>
      <button class="filter-btn active" onclick="filterAlarms(-1,this)">All</button>
      <button class="filter-btn f-dis"  onclick="filterAlarms(5,this)">💀 Disaster</button>
      <button class="filter-btn f-high" onclick="filterAlarms(4,this)">🔴 High</button>
      <button class="filter-btn f-avg"  onclick="filterAlarms(3,this)">🟠 Average</button>
      <button class="filter-btn"       onclick="filterAlarms(2,this)">🟡 Warning</button>
      <button class="filter-btn"       onclick="filterAlarms(1,this)">🔵 Info</button>
      <div class="tb-sep"></div>
      <button class="filter-btn" onclick="filterAlarms('unack',this)">🔕 Unacknowledged</button>
      <button class="tb-btn" onclick="refreshAlarms()" style="margin-left:auto">↺ Refresh</button>
    </div>
    <div class="alarms-table-wrap">
      <table class="alarms-tbl">
        <thead>
          <tr>
            <th style="width:6px;padding:9px 4px"></th>
            <th>Severity</th>
            <th>Host</th>
            <th>Problem</th>
            <th>Duration</th>
            <th>Since</th>
            <th>Ack</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="alarms-tbody">
          <tr><td colspan="8" class="empty-state">Loading alarms...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ══ HOSTS PAGE ══ -->
  <div class="page" id="page-hosts">
    <div class="page-header">
      <div class="page-title">🖥️ Monitored Hosts</div>
      <div class="tb-search" style="width:200px">
        <span style="color:var(--muted);font-size:11px">🔍</span>
        <input id="host-search" placeholder="Search host..." oninput="filterHosts(this.value)" style="width:160px">
      </div>
      <button class="filter-btn active" id="hf-all"   onclick="hostFilter('all',this)">All</button>
      <button class="filter-btn"        id="hf-prob"  onclick="hostFilter('problems',this)">⚠ Problems</button>
      <button class="filter-btn"        id="hf-ok"    onclick="hostFilter('ok',this)">✓ OK</button>
      <button class="filter-btn"        id="hf-down"  onclick="hostFilter('down',this)">✗ Down</button>
    </div>
    <div class="hosts-grid" id="hosts-grid">
      <div class="empty-state" style="grid-column:1/-1">Loading hosts...</div>
    </div>
  </div>

  <!-- ══ AI INTELLIGENCE PAGE ══ -->
  <div class="page" id="page-intel">
    <div class="page-header">
      <div class="page-title"><i data-lucide="brain-circuit" style="width:16px;height:16px;vertical-align:-2px"></i> Network Intelligence</div>
      <div style="font-size:11px;color:var(--muted);font-family:'JetBrains Mono',monospace" id="intel-context-label">General Network Assistant</div>
    </div>
    <div style="flex:1;display:flex;flex-direction:column;overflow:hidden;padding:0 20px 20px">
      <!-- Context cards row -->
      <div id="intel-context-bar" style="display:flex;gap:10px;padding:12px 0;flex-shrink:0;overflow-x:auto;scrollbar-width:none">
        <button class="intel-ctx-btn active" onclick="setIntelContext('network',this)"><i data-lucide="globe" style="width:12px;height:12px"></i> Full Network</button>
        <button class="intel-ctx-btn" onclick="setIntelContext('alarms',this)"><i data-lucide="alert-triangle" style="width:12px;height:12px"></i> Active Alarms</button>
        <button class="intel-ctx-btn" onclick="setIntelContext('topology',this)"><i data-lucide="git-branch" style="width:12px;height:12px"></i> Topology</button>
        <button class="intel-ctx-btn" onclick="setIntelContext('performance',this)"><i data-lucide="activity" style="width:12px;height:12px"></i> Performance</button>
      </div>
      <!-- Messages -->
      <div id="intel-messages" style="flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:12px;padding:4px 0 12px;scroll-behavior:smooth"></div>
      <!-- Input -->
      <div style="display:flex;gap:10px;flex-shrink:0;border-top:1px solid var(--border);padding-top:14px">
        <textarea id="intel-input" placeholder="Ask about your network, request analysis, or get Zabbix recommendations..."
          style="flex:1;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 14px;color:#fff;font-family:'Space Grotesk',sans-serif;font-size:12px;resize:none;height:56px;outline:none;transition:border-color 0.15s"
          onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendIntelMessage()}"
          onfocus="this.style.borderColor='var(--cyan)'" onblur="this.style.borderColor='var(--border)'"></textarea>
        <button class="btn btn-primary" style="height:56px;width:56px;padding:0;flex-shrink:0;display:flex;align-items:center;justify-content:center" onclick="sendIntelMessage()">
          <i data-lucide="send" style="width:16px;height:16px"></i>
        </button>
      </div>
    </div>
  </div>

  <!-- ══ SETTINGS PAGE ══ -->
  <div class="page" id="page-settings">
    <div class="settings-wrap">
      <h2 style="color:#fff;font-size:18px">⚙️ Settings</h2>

      <!-- Zabbix config -->
      <div class="settings-card">
        <h3>Zabbix Connection</h3>
        <div class="ef">
          <label>Server URL</label>
          <input type="url" id="cfg-url" placeholder="http://zabbix.tabadul.iq" />
        </div>
        <div class="ef">
          <label>API Token</label>
          <input type="text" id="cfg-token" placeholder="Token..." />
        </div>
        <div class="ef">
          <label>Refresh Interval (seconds)</label>
          <input type="number" id="cfg-refresh" value="30" min="10" max="300" />
        </div>
        <div style="display:flex;gap:10px;margin-top:4px">
          <button class="btn btn-ghost" onclick="testZabbix()">🔌 Test Connection</button>
          <button class="btn btn-primary" onclick="saveZabbixConfig()">Save</button>
        </div>
        <div id="cfg-result" style="margin-top:10px;font-size:11px;font-family:'JetBrains Mono',monospace"></div>
      </div>

      <!-- Claude API key -->
      <div class="settings-card">
        <h3>Claude AI — Map Import</h3>
        <p style="font-size:11px;color:var(--muted);margin-bottom:12px">Required for importing network maps from PNG/PDF. Uses Claude vision to extract topology.</p>
        <div class="ef">
          <label>Claude API Key</label>
          <input type="password" id="claude-key-input" placeholder="sk-ant-api03-..." autocomplete="off" />
        </div>
        <div id="claude-key-status" style="font-size:11px;font-family:'JetBrains Mono',monospace;margin-bottom:8px"></div>
        <button class="btn btn-primary" onclick="saveClaudeKey()">Save Key</button>
      </div>

      <!-- Saved layouts -->
      <div class="settings-card">
        <h3>Saved Layouts</h3>
        <div id="layouts-list" class="layouts-list">Loading...</div>
        <div style="margin-top:12px">
          <button class="btn btn-ghost" onclick="saveLayoutPrompt()">💾 Save Current Layout</button>
        </div>
      </div>

      <!-- Change password -->
      <div class="settings-card">
        <h3>Change Password</h3>
        <div class="ef"><label>Current Password</label><input type="password" id="pw-cur" /></div>
        <div class="ef"><label>New Password</label><input type="password" id="pw-new" /></div>
        <div class="ef"><label>Confirm New Password</label><input type="password" id="pw-con" /></div>
        <button class="btn btn-primary" onclick="changePassword()">Update Password</button>
        <div id="pw-result" style="margin-top:10px;font-size:11px;font-family:'JetBrains Mono',monospace"></div>
      </div>
    </div>
  </div>
</div><!-- /main-area -->

<!-- ══ HOST AI CHAT PANEL ══ -->
<div id="host-chat-panel" style="position:fixed;bottom:0;right:320px;width:380px;background:var(--surface);border:1px solid var(--border2);border-bottom:none;border-radius:12px 12px 0 0;box-shadow:0 -8px 40px rgba(0,0,0,0.5);z-index:200;display:none;flex-direction:column;max-height:520px">
  <div style="display:flex;align-items:center;gap:8px;padding:10px 14px;border-bottom:1px solid var(--border);cursor:pointer;flex-shrink:0" onclick="toggleHostChat()">
    <div style="width:26px;height:26px;border-radius:8px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;flex-shrink:0">
      <i data-lucide="bot" style="width:14px;height:14px;color:#fff"></i>
    </div>
    <div style="flex:1">
      <div style="font-size:12px;font-weight:600;color:#fff">AI Network Assistant</div>
      <div id="hc-context-label" style="font-size:10px;color:var(--muted);font-family:'JetBrains Mono',monospace">No host selected</div>
    </div>
    <i data-lucide="chevron-down" id="hc-chevron" style="width:14px;height:14px;color:var(--muted);transition:transform 0.2s"></i>
  </div>
  <div id="hc-body" style="display:flex;flex-direction:column;flex:1;overflow:hidden">
    <div id="hc-messages" style="flex:1;overflow-y:auto;padding:12px 14px;display:flex;flex-direction:column;gap:10px;min-height:200px;max-height:340px;scroll-behavior:smooth"></div>
    <div style="padding:10px 12px;border-top:1px solid var(--border);display:flex;gap:8px;flex-shrink:0">
      <input id="hc-input" placeholder="Ask about this host..." style="flex:1;background:var(--surface2);border:1px solid var(--border);border-radius:6px;padding:7px 10px;color:#fff;font-family:'Space Grotesk',sans-serif;font-size:11px;outline:none" onkeydown="if(event.key==='Enter')sendHostChat()">
      <button onclick="sendHostChat()" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);border:none;border-radius:6px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0">
        <i data-lucide="send" style="width:13px;height:13px;color:#fff"></i>
      </button>
    </div>
  </div>
</div>

<!-- ══ IMPORT MAP MODAL ══ -->
<div class="modal-overlay" id="import-modal">
  <div class="modal-card" style="width:580px;max-width:96vw">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3 style="margin:0">📥 Import Network Map</h3>
      <button onclick="closeImportModal()" style="background:none;border:none;color:var(--muted);font-size:18px;cursor:pointer">✕</button>
    </div>

    <!-- Step indicator -->
    <div class="import-steps" id="imp-steps">
      <div class="import-step active" id="imp-s1">1 · Upload</div>
      <div class="import-step" id="imp-s2">2 · Review</div>
      <div class="import-step" id="imp-s3">3 · Create</div>
    </div>

    <!-- Step 1: Upload -->
    <div id="imp-step1">
      <div class="ef">
        <label>Map Name</label>
        <input id="imp-name" placeholder="e.g. Payment Network Map" />
      </div>
      <div class="drop-zone" id="imp-drop" onclick="document.getElementById('imp-file').click()">
        <div class="drop-zone-icon">📄</div>
        <div class="drop-zone-lbl"><b>Click to upload</b> or drag & drop</div>
        <div class="drop-zone-lbl" style="font-size:10px;margin-top:4px">PNG · JPG · PDF</div>
        <input type="file" id="imp-file" accept=".png,.jpg,.jpeg,.pdf,image/*,application/pdf" style="display:none" onchange="onFileSelected(this)">
      </div>
      <div id="imp-file-info" style="font-size:11px;color:var(--cyan);font-family:'JetBrains Mono',monospace;margin-top:8px;min-height:16px"></div>
      <div id="imp-err" style="color:var(--red);font-size:11px;font-family:'JetBrains Mono',monospace;margin-top:6px;min-height:14px"></div>
      <div style="display:flex;gap:10px;margin-top:16px">
        <button class="btn btn-ghost" onclick="closeImportModal()">Cancel</button>
        <button class="btn btn-primary" id="imp-analyze-btn" onclick="doAnalyzeMap()" disabled>Analyze with Claude →</button>
      </div>
    </div>

    <!-- Step 2: Review results -->
    <div id="imp-step2" style="display:none">
      <div class="import-summary" id="imp-summary"></div>
      <p style="font-size:11px;color:var(--muted);margin-bottom:6px">
        ✓ Matched nodes will be added to the map. Unmatched nodes (no IP or not found in Zabbix) are shown struck-through and will be skipped.
      </p>
      <div style="overflow-y:auto;max-height:320px;border:1px solid var(--border);border-radius:6px">
        <table class="preview-table">
          <thead><tr>
            <th>Device Name</th><th>IP</th><th>Type</th><th>Zabbix Host</th><th>Status</th>
          </tr></thead>
          <tbody id="imp-preview-body"></tbody>
        </table>
      </div>
      <div style="display:flex;gap:10px;margin-top:16px">
        <button class="btn btn-ghost" onclick="impGoStep(1)">← Back</button>
        <button class="btn btn-primary" id="imp-create-btn" onclick="doCreateMap()">Create Map →</button>
      </div>
    </div>

    <!-- Step 3: Done -->
    <div id="imp-step3" style="display:none;text-align:center;padding:20px 0">
      <div style="font-size:40px;margin-bottom:12px">✅</div>
      <div id="imp-done-msg" style="color:#fff;font-size:15px;margin-bottom:6px"></div>
      <div style="color:var(--muted);font-size:12px;margin-bottom:20px">Map is now available in the sidebar</div>
      <button class="btn btn-primary" onclick="closeImportModal()">Done</button>
    </div>
  </div>
</div>

<!-- ══ ADD NODE MODAL ══ -->
<div class="modal-overlay" id="add-modal">
  <div class="modal-card">
    <h3>＋ Add New Node</h3>
    <div class="ef"><label>Display Label *</label><input id="add-label" placeholder="e.g. App Server 01" /></div>
    <div class="ef-row">
      <div class="ef"><label>IP Address</label><input id="add-ip" placeholder="10.1.5.20" /></div>
      <div class="ef"><label>Status</label>
        <select id="add-status">
          <option value="ok">● ONLINE</option>
          <option value="warn">⚠ WARNING</option>
          <option value="crit">✗ CRITICAL</option>
          <option value="info">ℹ INFO</option>
        </select>
      </div>
    </div>
    <div class="ef"><label>Role / Description</label><input id="add-role" placeholder="Payment Application Server" /></div>
    <div class="ef-row">
      <div class="ef"><label>Type</label>
        <select id="add-type">
          <option value="external">🌐 External</option>
          <option value="wan">📡 WAN</option>
          <option value="switch">🔀 Switch</option>
          <option value="firewall">🛡 Firewall</option>
          <option value="palo">🔥 Palo Alto</option>
          <option value="f5">⚖️ F5</option>
          <option value="server" selected>🖥 Server</option>
          <option value="dbserver">🗄 DB Server</option>
          <option value="hsm">🔐 HSM</option>
          <option value="infra">🏗 Infra</option>
        </select>
      </div>
      <div class="ef"><label>Layer Column</label>
        <select id="add-layer">
          <option value="ext">External</option>
          <option value="wan">WAN/ISP</option>
          <option value="inet">Internet SW</option>
          <option value="core">Core SW</option>
          <option value="fw">Payment FW</option>
          <option value="fp">IPS/Firepower</option>
          <option value="tor">NX-OS TOR</option>
          <option value="app">App FW/LB</option>
          <option value="srv" selected>Servers</option>
          <option value="hsm">HSM/Infra</option>
        </select>
      </div>
    </div>
    <div class="ef"><label>Zabbix Host ID (optional — links live status)</label><input id="add-zbxid" placeholder="e.g. 10084" /></div>
    <div class="ef"><label>Interfaces (one per line)</label><textarea id="add-ifaces" rows="3" placeholder="Eth0/1 → Core-SW"></textarea></div>
    <div class="modal-btns">
      <button class="btn btn-ghost" onclick="closeModal('add-modal')">Cancel</button>
      <button class="btn btn-primary" onclick="submitAddNode()">Add Node</button>
    </div>
  </div>
</div>

<!-- ══ SAVE LAYOUT MODAL ══ -->
<div class="modal-overlay" id="layout-modal">
  <div class="modal-card" style="width:360px">
    <h3>💾 Save Layout</h3>
    <div class="ef"><label>Layout Name</label><input id="layout-name" placeholder="e.g. Default View" /></div>
    <div class="ef" style="display:flex;align-items:center;gap:8px">
      <input type="checkbox" id="layout-default" style="width:auto;padding:0">
      <label style="display:inline;font-size:12px;color:var(--text);font-family:'Inter',sans-serif;letter-spacing:0">Set as default layout</label>
    </div>
    <div class="modal-btns">
      <button class="btn btn-ghost" onclick="closeModal('layout-modal')">Cancel</button>
      <button class="btn btn-success" onclick="submitSaveLayout()">Save Layout</button>
    </div>
  </div>
</div>

<!-- TOOLTIP -->
<div id="vis-tip">
  <div class="t-name" id="tip-name"></div>
  <div class="t-ip"   id="tip-ip"></div>
  <div class="t-role" id="tip-role"></div>
  <div class="t-alarm" id="tip-alarm" style="display:none"></div>
</div>

<script>
// ═══════════════════════════════════════════════════════════
//  CONSTANTS
// ═══════════════════════════════════════════════════════════
const SEV = {
  5:{label:'DISASTER', color:'#FF1744', icon:'💀', bg:'rgba(255,23,68,0.08)'},
  4:{label:'HIGH',     color:'#FF6D00', icon:'🔴', bg:'rgba(255,109,0,0.08)'},
  3:{label:'AVERAGE',  color:'#FFB300', icon:'🟠', bg:'rgba(255,179,0,0.08)'},
  2:{label:'WARNING',  color:'#78909C', icon:'🟡', bg:'rgba(120,144,156,0.06)'},
  1:{label:'INFO',     color:'#00B0FF', icon:'🔵', bg:'rgba(0,176,255,0.06)'},
  0:{label:'NC',       color:'#9E9E9E', icon:'⚪', bg:'rgba(158,158,158,0.04)'},
};

function makeSVG(emoji,bg,sz=44){
  const s=`<svg xmlns="http://www.w3.org/2000/svg" width="${sz}" height="${sz}">
    <rect width="${sz}" height="${sz}" rx="8" fill="${bg}"/>
    <text x="${sz/2}" y="${sz*.68}" font-size="${sz*.45}" text-anchor="middle" dominant-baseline="middle">${emoji}</text>
  </svg>`;
  return 'data:image/svg+xml;base64,'+btoa(unescape(encodeURIComponent(s)));
}
const ICONS={external:makeSVG('🌐','#f1c40f33'),wan:makeSVG('📡','#00bcd433'),switch:makeSVG('🔀','#2176ae44'),firewall:makeSVG('🛡','#c0392b44'),palo:makeSVG('🔥','#d3540044'),f5:makeSVG('⚖️','#1e844944'),server:makeSVG('🖥','#11786544'),dbserver:makeSVG('🗄','#922b5a44'),hsm:makeSVG('🔐','#8e44ad44'),infra:makeSVG('🏗','#54637544')};
const COLORS={external:{border:'#f1c40f',font:'#f1c40f'},wan:{border:'#00bcd4',font:'#80deea'},switch:{border:'#3498db',font:'#90caf9'},firewall:{border:'#e74c3c',font:'#ef9a9a'},palo:{border:'#e67e22',font:'#ffb74d'},f5:{border:'#2ecc71',font:'#a5d6a7'},server:{border:'#1abc9c',font:'#80cbc4'},dbserver:{border:'#e91e63',font:'#f48fb1'},hsm:{border:'#9b59b6',font:'#ce93d8'},infra:{border:'#78909c',font:'#b0bec5'}};
const LX={ext:-900,wan:-680,inet:-440,core:-200,fw:40,fp:270,tor:510,app:750,srv:970,hsm:1200};

// ═══════════════════════════════════════════════════════════
//  STATE
// ═══════════════════════════════════════════════════════════
const S = {
  zabbixHosts:{},        // hostid -> host obj
  zabbixProblems:[],     // all active problems
  nodeHostMap:{},        // nodeId -> zabbixHostId
  dbNodes:{},            // nodeId -> db row (custom)
  currentAlarmFilter: -1,
  hostFilterMode:'all',
  hostSearchQ:'',
  allProblems:[],
  allHosts:[],
  refreshTimer:null,
  refreshInterval:30000,
  lastRefreshTs:null,
  currentMapId: 0,       // 0 = default Network Map, >0 = imported layout id
  currentMapName:'Network Map',
  currentMapHostIds: new Set(), // zabbixHostIds on current map (empty = show all)
  importAnalysisResult: null,   // holds last Claude analysis
};

// ═══════════════════════════════════════════════════════════
//  DEFAULT DEVICE DATA (built-in nodes)
// ═══════════════════════════════════════════════════════════
const deviceData = {
  visa:       {name:'VISA Network',role:'External Payment Network',ip:'External',type:'external',status:'ok',ifaces:['P2P Link'],info:{Protocol:'ISO 8583',Auth:'VisaNet'}},
  mc:         {name:'MasterCard',role:'External Payment Network',ip:'External',type:'external',status:'ok',ifaces:['MC Switch P14'],info:{Protocol:'ISO 8583'}},
  cbi:        {name:'CBI — Central Bank Iraq',role:'Regulatory Authority',ip:'External',type:'external',status:'ok',ifaces:['CBI SW'],info:{Protocol:'SWIFT'}},
  isp:        {name:'ISP Uplink',role:'Internet Service Provider',ip:'204.106.240.53',type:'external',status:'ok',ifaces:['ScopeSky','Passport-SS'],info:{Provider:'ScopeSky / Passport'}},
  dr:         {name:'DR Site',role:'Disaster Recovery',ip:'Remote',type:'infra',status:'info',ifaces:['P2P'],info:{Mode:'Active-Passive',RPO:'4hr',RTO:'8hr'}},
  scopesky:   {name:'ScopeSky ISP',role:'WAN / P2P Link',ip:'204.106.240.53',type:'wan',status:'ok',ifaces:['To Internet-SW'],info:{Type:'Fiber'}},
  'passport-ss':{name:'PassPort LocalSS',role:'WAN / P2P Link',ip:'Private',type:'wan',status:'ok',ifaces:['To Internet-SW'],info:{Type:'MPLS'}},
  'asia-local':{name:'Asia Local',role:'WAN / P2P Link',ip:'Private',type:'wan',status:'ok',ifaces:['To Internet-SW'],info:{Type:'Leased Line'}},
  'zain-m2m': {name:'Zain M2M',role:'M2M / IoT Network',ip:'Private',type:'wan',status:'ok',ifaces:['To Internet-SW'],info:{Type:'4G/LTE',Devices:'389'}},
  'mc-sw':    {name:'MC Switch',role:'MasterCard Switch P14',ip:'Internal',type:'wan',status:'ok',ifaces:['P14 → Internet-SW'],info:{}},
  'cbi-sw':   {name:'CBI Switch',role:'Central Bank Switch',ip:'Internal',type:'wan',status:'ok',ifaces:['To Internet-SW'],info:{}},
  'inet-sw':  {name:'Internet Switch',role:'L3 Edge Switching',ip:'10.1.0.15',type:'switch',status:'ok',ifaces:['Gi5/0/1 → Core-SW'],info:{Model:'Cisco 6800'}},
  'core-sw':  {name:'Core Switch',role:'L3 Core Distribution',ip:'10.1.0.5',type:'switch',status:'ok',ifaces:['→ Payment FW × 2'],info:{Model:'Catalyst 6800',VLAN:'Payment,Mgmt'}},
  'pmt-fw1':  {name:'FortiGate 601 — Primary',role:'Next-Gen Firewall (Active)',ip:'10.1.0.2',type:'firewall',status:'ok',ifaces:['PORT1 ← Core','PORT2 → FP1','HA'],info:{Model:'FortiGate 601E',Mode:'HA Active',IPS:'Enabled'}},
  'pmt-fw2':  {name:'FortiGate 601 — Secondary',role:'Next-Gen Firewall (Passive)',ip:'10.1.0.3',type:'firewall',status:'ok',ifaces:['PORT1 ← Core','PORT2 → FP2','HA'],info:{Model:'FortiGate 601E',Mode:'HA Passive'}},
  fp1:        {name:'Cisco Firepower 1',role:'IPS / NGIPS (Active)',ip:'10.1.1.1',type:'firewall',status:'ok',ifaces:['Eth1/15 ← FG1','Eth1/47 → TOR1'],info:{Model:'FP 4150',Mode:'HA Active',IPS:'Snort 3'}},
  fp2:        {name:'Cisco Firepower 2',role:'IPS / NGIPS (Passive)',ip:'10.1.1.2',type:'firewall',status:'ok',ifaces:['Eth1/15 ← FG2','Eth1/47 → TOR2'],info:{Model:'FP 4150',Mode:'HA Passive'}},
  tor1:       {name:'NXOS TOR Switch 1',role:'Top-of-Rack VPC Primary',ip:'10.1.2.1',type:'switch',status:'ok',ifaces:['Eth1/47 ← FP1','Eth1/11 → Palo','Eth1/36 → F5'],info:{Model:'Nexus 93xx',VPC:'Primary'}},
  tor2:       {name:'NXOS TOR Switch 2',role:'Top-of-Rack VPC Secondary',ip:'10.1.2.2',type:'switch',status:'ok',ifaces:['Eth1/47 ← FP2','Eth1/36 → F5','Eth1/3-7 → HSM'],info:{Model:'Nexus 93xx',VPC:'Secondary'}},
  palo1:      {name:'Palo Alto — Unit 1',role:'App-Layer FW (Active)',ip:'10.1.3.1',type:'palo',status:'ok',ifaces:['Eth1/11 ← TOR'],info:{Model:'PA-5250',Mode:'HA Active',AppID:'Enabled'}},
  palo2:      {name:'Palo Alto — Unit 2',role:'App-Layer FW (Passive)',ip:'10.1.3.2',type:'palo',status:'ok',ifaces:['Eth1/11 ← TOR'],info:{Model:'PA-5250',Mode:'HA Passive'}},
  'f5-1':     {name:'F5 BIG-IP LTM 1',role:'Load Balancer (Active)',ip:'10.1.0.11',type:'f5',status:'ok',ifaces:['Eth1/36 ← TOR1','→ Servers'],info:{Model:'i7800',Mode:'HA Active',SSL:'Offload'}},
  'f5-2':     {name:'F5 BIG-IP LTM 2',role:'Load Balancer (Passive)',ip:'10.1.0.12',type:'f5',status:'ok',ifaces:['Eth1/36 ← TOR2'],info:{Model:'i7800',Mode:'HA Passive'}},
  ag1000:     {name:'AG1000 Router',role:'Aggregation Router',ip:'10.1.2.100',type:'switch',status:'ok',ifaces:['← TOR2'],info:{Model:'AG1000'}},
  'web-servers':{name:'WEB Servers Cluster',role:'Payment Application Servers',ip:'10.100.x.x',type:'server',status:'ok',ifaces:['← TOR1+2','← F5 VIP'],info:{OS:'RHEL 8',Instances:'4×',Cluster:'Active-Active'}},
  'db-servers': {name:'DB Servers Cluster',role:'Payment Database Servers',ip:'10.200.x.x',type:'dbserver',status:'ok',ifaces:['← TOR1+2','← F5 VIP'],info:{DB:'Oracle RAC / MS SQL',Mode:'HA Active-Active'}},
  'hsm-auth1':{name:'HSM-Auth-1',role:'Authentication HSM',ip:'100.66.0.122',type:'hsm',status:'ok',ifaces:['← TOR2'],info:{Model:'Thales payShield 10K',Function:'PIN/Key Mgmt',FIPS:'140-2 L3'}},
  'hsm-auth2':{name:'HSM-Auth-2',role:'Authentication HSM',ip:'100.66.0.123',type:'hsm',status:'ok',ifaces:['Eth1/3 ← TOR2'],info:{Model:'Thales payShield 10K',Function:'PIN/Key Mgmt',FIPS:'140-2 L3'}},
  'hsm-acs1': {name:'HSM-ACS-1',role:'Access Control Server HSM',ip:'100.66.0.124',type:'hsm',status:'ok',ifaces:['Eth1/4 ← TOR2'],info:{Model:'Thales payShield',Function:'EMV ACS',FIPS:'140-2 L3'}},
  'hsm-acs2': {name:'HSM-ACS-2',role:'Access Control Server HSM',ip:'100.66.0.125',type:'hsm',status:'ok',ifaces:['Eth1/5 ← TOR2'],info:{Model:'Thales payShield',Function:'EMV ACS',FIPS:'140-2 L3'}},
  veeam:      {name:'VeeamSRV',role:'Backup Server',ip:'10.50.0.10',type:'infra',status:'ok',ifaces:['Eth1/43 ← TOR1'],info:{Software:'Veeam B&R v12'}},
  olvm:       {name:'OLVM Manager',role:'Oracle Linux Virt Mgr',ip:'10.50.0.20',type:'infra',status:'ok',ifaces:['Eth1/39 ← TOR1'],info:{Platform:'OLVM 4.5',Hosts:'8 hypervisors'}},
  'hv-prod':  {name:'HV-PROD',role:'Hyper-V Production Host',ip:'10.50.0.30',type:'infra',status:'ok',ifaces:['Eth1/30 ← TOR1'],info:{Platform:'Hyper-V 2022'}},
  hnv03:      {name:'HNV03',role:'Hyper-V Node 3',ip:'10.50.0.33',type:'infra',status:'ok',ifaces:['Eth1/26 ← TOR1'],info:{}},
  hnv04:      {name:'HNV04',role:'Hyper-V Node 4',ip:'10.50.0.34',type:'infra',status:'ok',ifaces:['Eth1/24 ← TOR2'],info:{}},
  'perso-fiber':{name:'Perso-Fiber',role:'Personalization Fiber Channel',ip:'10.50.0.40',type:'infra',status:'ok',ifaces:['Eth1/32 ← TOR1'],info:{Type:'FC SAN'}},
};

// ═══════════════════════════════════════════════════════════
//  VIS-NETWORK SETUP
// ═══════════════════════════════════════════════════════════
function mkNode(id,label,type,x,y,extra={}){
  const c=COLORS[type]||COLORS.switch;
  return {id,x,y,label,shape:'image',image:ICONS[type]||ICONS.switch,
    size:extra.size||24,font:{color:c.font,size:extra.fontSize||11,bold:true,face:'Inter',multi:true},
    color:{border:c.border,background:'transparent',highlight:{border:'#00d4ff',background:'rgba(0,212,255,0.1)'},hover:{border:'#00d4ff'}},
    borderWidth:2,borderWidthSelected:3,
    shadow:{enabled:true,color:c.border+'44',size:12,x:0,y:2},
    _type:type,...extra};
}
function mkEdge(f,t,col,o={}){return{from:f,to:t,color:{color:col,opacity:0.85,highlight:'#00d4ff',hover:'#00d4ff'},...o}}
function mkHA(f,t,col,lbl){return mkEdge(f,t,col,{width:1,dashes:[5,4],label:lbl,font:{color:col+'99',size:9,background:'rgba(10,14,26,.9)'},smooth:{enabled:true,type:'curvedCW',roundness:0.5}})}

const visNodes = new vis.DataSet([
  mkNode('visa','VISA\nNetwork','external',-900,-320,{size:28}),
  mkNode('isp','ISP\n204.106.240.53','external',-900,-160,{size:24}),
  mkNode('cbi','CBI\nCent.Bank Iraq','external',-900,0,{size:22}),
  mkNode('mc','MasterCard','external',-900,160,{size:26}),
  mkNode('dr','DR Site','infra',-900,320,{size:20}),
  mkNode('scopesky','ScopeSky ISP','wan',-680,-290,{size:18,fontSize:9}),
  mkNode('passport-ss','PassPort-SS','wan',-680,-180,{size:18,fontSize:9}),
  mkNode('asia-local','Asia Local','wan',-680,-60,{size:18,fontSize:9}),
  mkNode('zain-m2m','Zain M2M','wan',-680,60,{size:18,fontSize:9}),
  mkNode('mc-sw','MC Switch\nP14','switch',-680,190,{size:18,fontSize:9}),
  mkNode('cbi-sw','CBI Switch','switch',-680,300,{size:18,fontSize:9}),
  mkNode('inet-sw','Internet Switch\n10.1.0.15','switch',-440,0,{size:34,fontSize:13}),
  mkNode('core-sw','Core Switch\n10.1.0.5','switch',-200,0,{size:34,fontSize:13}),
  mkNode('pmt-fw1','FortiGate 601\nPrimary ●','firewall',40,-90,{size:28,fontSize:11}),
  mkNode('pmt-fw2','FortiGate 601\nSecondary ○','firewall',40,90,{size:28,fontSize:11}),
  mkNode('fp1','Firepower IPS\nUnit 1 ●','firewall',270,-90,{size:26,fontSize:11}),
  mkNode('fp2','Firepower IPS\nUnit 2 ○','firewall',270,90,{size:26,fontSize:11}),
  mkNode('tor1','NXOS-TOR 1\nVPC Primary','switch',510,-130,{size:30,fontSize:12}),
  mkNode('tor2','NXOS-TOR 2\nVPC Secondary','switch',510,130,{size:30,fontSize:12}),
  mkNode('ag1000','AG1000\nAgg.Router','switch',510,380,{size:22,fontSize:10}),
  mkNode('palo1','Palo Alto\nUnit 1 ●','palo',750,-280,{size:26,fontSize:11}),
  mkNode('palo2','Palo Alto\nUnit 2 ○','palo',750,-160,{size:26,fontSize:11}),
  mkNode('f5-1','F5 BIG-IP\n10.1.0.11 ●','f5',750,160,{size:26,fontSize:11}),
  mkNode('f5-2','F5 BIG-IP\n10.1.0.12 ○','f5',750,280,{size:26,fontSize:11}),
  mkNode('web-servers','WEB Servers\nCluster','server',970,-130,{size:30,fontSize:12}),
  mkNode('db-servers','DB Servers\nCluster','dbserver',970,130,{size:30,fontSize:12}),
  mkNode('hsm-auth1','HSM-Auth-1\n100.66.0.122','hsm',1200,-390,{size:22,fontSize:9}),
  mkNode('hsm-auth2','HSM-Auth-2\n100.66.0.123','hsm',1200,-290,{size:22,fontSize:9}),
  mkNode('hsm-acs1','HSM-ACS-1\n100.66.0.124','hsm',1200,-190,{size:22,fontSize:9}),
  mkNode('hsm-acs2','HSM-ACS-2\n100.66.0.125','hsm',1200,-90,{size:22,fontSize:9}),
  mkNode('veeam','VeeamSRV','infra',1200,60,{size:18,fontSize:9}),
  mkNode('olvm','OLVM Mgr','infra',1200,150,{size:18,fontSize:9}),
  mkNode('hv-prod','HV-PROD','infra',1200,240,{size:18,fontSize:9}),
  mkNode('hnv03','HNV03','infra',1200,320,{size:18,fontSize:9}),
  mkNode('hnv04','HNV04','infra',1200,400,{size:18,fontSize:9}),
  mkNode('perso-fiber','Perso-Fiber','infra',1200,480,{size:18,fontSize:9}),
]);

const visEdges = new vis.DataSet([
  mkEdge('visa','inet-sw','#f1c40f',{width:3,arrows:{to:{enabled:true,scaleFactor:0.6}}}),
  mkEdge('isp','inet-sw','#f1c40f',{width:3,arrows:{to:{enabled:true,scaleFactor:0.6}}}),
  mkEdge('cbi','inet-sw','#f1c40f',{width:2,arrows:{to:{enabled:true,scaleFactor:0.6}}}),
  mkEdge('mc','inet-sw','#f1c40f',{width:3,arrows:{to:{enabled:true,scaleFactor:0.6}}}),
  mkEdge('dr','inet-sw','#78909c',{width:1,dashes:[6,4]}),
  mkEdge('scopesky','inet-sw','#00bcd4',{width:1,dashes:[4,4]}),
  mkEdge('passport-ss','inet-sw','#00bcd4',{width:1,dashes:[4,4]}),
  mkEdge('asia-local','inet-sw','#00bcd4',{width:1,dashes:[4,4]}),
  mkEdge('zain-m2m','inet-sw','#00bcd4',{width:1,dashes:[4,4]}),
  mkEdge('mc-sw','inet-sw','#3498db',{width:1,label:'P14',font:{color:'#55555599',size:9,background:'rgba(10,14,26,.8)'}}),
  mkEdge('cbi-sw','inet-sw','#3498db',{width:1}),
  mkEdge('inet-sw','core-sw','#3498db',{width:5,label:'Gi5/0/1',font:{color:'#3498db',size:10,background:'rgba(10,14,26,.9)'}}),
  mkEdge('core-sw','pmt-fw1','#e74c3c',{width:4,label:'PORT1',font:{color:'#e74c3c',size:9,background:'rgba(10,14,26,.9)'}}),
  mkEdge('core-sw','pmt-fw2','#e74c3c',{width:4,label:'PORT1',font:{color:'#e74c3c',size:9,background:'rgba(10,14,26,.9)'}}),
  mkHA('pmt-fw1','pmt-fw2','#e74c3c','HA SYNC'),
  mkEdge('pmt-fw1','fp1','#e74c3c',{width:3,label:'Gi1/0/15',font:{color:'#e74c3c',size:9,background:'rgba(10,14,26,.9)'}}),
  mkEdge('pmt-fw2','fp2','#e74c3c',{width:3,label:'Gi1/0/15',font:{color:'#e74c3c',size:9,background:'rgba(10,14,26,.9)'}}),
  mkHA('fp1','fp2','#e74c3c','HA SYNC'),
  mkEdge('fp1','tor1','#3498db',{width:4,label:'Eth1/47',font:{color:'#3498db',size:9,background:'rgba(10,14,26,.9)'}}),
  mkEdge('fp2','tor2','#3498db',{width:4,label:'Eth1/47',font:{color:'#3498db',size:9,background:'rgba(10,14,26,.9)'}}),
  mkHA('tor1','tor2','#3498db','VPC PEER'),
  mkEdge('tor1','palo1','#e67e22',{width:2,label:'Eth1/11',font:{color:'#e67e22',size:9,background:'rgba(10,14,26,.9)'}}),
  mkEdge('tor1','palo2','#e67e22',{width:1}),mkEdge('tor2','palo1','#e67e22',{width:1}),mkEdge('tor2','palo2','#e67e22',{width:2}),
  mkHA('palo1','palo2','#e67e22','HA'),
  mkEdge('tor1','f5-1','#2ecc71',{width:2,label:'Eth1/36',font:{color:'#2ecc71',size:9,background:'rgba(10,14,26,.9)'}}),
  mkEdge('tor2','f5-2','#2ecc71',{width:2,label:'Eth1/36',font:{color:'#2ecc71',size:9,background:'rgba(10,14,26,.9)'}}),
  mkHA('f5-1','f5-2','#2ecc71','HA'),
  mkEdge('tor2','ag1000','#78909c',{width:2}),
  mkEdge('tor1','web-servers','#1abc9c',{width:3}),mkEdge('tor2','web-servers','#1abc9c',{width:2}),
  mkEdge('tor1','db-servers','#e91e63',{width:3}),mkEdge('tor2','db-servers','#e91e63',{width:2}),
  mkEdge('f5-1','web-servers','#2ecc71',{width:2,dashes:[4,4],arrows:{to:{enabled:true,scaleFactor:0.5}}}),
  mkEdge('f5-2','db-servers','#2ecc71',{width:1,dashes:[4,4],arrows:{to:{enabled:true,scaleFactor:0.5}}}),
  mkEdge('tor2','hsm-auth1','#9b59b6',{width:2,label:'Eth1/3',font:{color:'#9b59b6',size:9,background:'rgba(10,14,26,.9)'}}),
  mkEdge('tor2','hsm-auth2','#9b59b6',{width:2}),
  mkEdge('tor2','hsm-acs1','#9b59b6',{width:2,label:'Eth1/4',font:{color:'#9b59b6',size:9,background:'rgba(10,14,26,.9)'}}),
  mkEdge('tor2','hsm-acs2','#9b59b6',{width:2,label:'Eth1/5',font:{color:'#9b59b6',size:9,background:'rgba(10,14,26,.9)'}}),
  mkEdge('tor1','veeam','#78909c',{width:1,label:'Eth1/43',font:{color:'#55555599',size:8,background:'rgba(10,14,26,.9)'}}),
  mkEdge('tor1','olvm','#78909c',{width:1,label:'Eth1/39',font:{color:'#55555599',size:8,background:'rgba(10,14,26,.9)'}}),
  mkEdge('tor1','hv-prod','#78909c',{width:1,label:'Eth1/30',font:{color:'#55555599',size:8,background:'rgba(10,14,26,.9)'}}),
  mkEdge('tor1','hnv03','#78909c',{width:1,label:'Eth1/26',font:{color:'#55555599',size:8,background:'rgba(10,14,26,.9)'}}),
  mkEdge('tor1','perso-fiber','#78909c',{width:1,label:'Eth1/32',font:{color:'#55555599',size:8,background:'rgba(10,14,26,.9)'}}),
  mkEdge('tor2','hnv04','#78909c',{width:1,label:'Eth1/24',font:{color:'#55555599',size:8,background:'rgba(10,14,26,.9)'}}),
]);

const visNetwork = new vis.Network(document.getElementById('vis-network'), {nodes:visNodes,edges:visEdges}, {
  physics:{enabled:false},
  interaction:{hover:true,zoomView:true,dragView:true,dragNodes:true,tooltipDelay:200},
  edges:{smooth:{enabled:true,type:'cubicBezier',forceDirection:'horizontal',roundness:0.35},font:{color:'#666',size:9,align:'middle',background:'rgba(8,12,20,.9)',strokeWidth:0},selectionWidth:3},
  nodes:{borderWidth:2,shadow:{enabled:true,color:'rgba(0,0,0,.5)',size:10,x:0,y:3}},
});

// Save positions on drag
visNetwork.on('dragEnd', p => { if(p.nodes.length) savePositions(); });

// ═══════════════════════════════════════════════════════════
//  ZABBIX STATUS — LIVE NODE COLORING + ALARM DOTS
// ═══════════════════════════════════════════════════════════
function updateMapStatus(){
  const overlay = document.getElementById('alarm-overlay');
  overlay.innerHTML = '';

  visNodes.getIds().forEach(nid=>{
    const hostId = S.nodeHostMap[nid];
    if(!hostId) return; // no Zabbix link

    const host = S.zabbixHosts[hostId];
    if(!host) return;

    const sev  = host.worst_severity || 0;
    const down = host.available == 2;
    const c    = COLORS[deviceData[nid]?.type || 'switch'];

    let border, shadow, bw=2;
    if(down){ border='#FF1744'; shadow='#FF174488'; bw=3; }
    else if(sev>=5){ border='#FF1744'; shadow='#FF174488'; bw=3; }
    else if(sev>=4){ border='#FF6D00'; shadow='#FF6D0088'; bw=3; }
    else if(sev>=3){ border='#FFB300'; shadow='#FFB30088'; bw=2; }
    else if(sev>=1){ border='#78909C'; shadow='#78909C66'; }
    else { border=c.border; shadow=c.border+'44'; }

    visNodes.update({id:nid,color:{border,background:'transparent',highlight:{border:'#00d4ff'}},
      shadow:{enabled:true,color:shadow,size:sev>=4?22:12,x:0,y:0},borderWidth:bw});

    // Draw alarm dot overlay for serious problems
    if(sev>=3 || down){
      try{
        const pos = visNetwork.getPosition(nid);
        const dom = visNetwork.canvasToDOM(pos);
        const dot = document.createElement('div');
        dot.className = 'alarm-dot';
        dot.style.cssText = `left:${dom.x}px;top:${dom.y}px;background:${border};box-shadow:0 0 8px ${border}`;
        overlay.appendChild(dot);
      }catch(e){}
    }
  });
}

// Update alarm dots on pan/zoom too
visNetwork.on('zoom',  updateMapStatus);
visNetwork.on('dragEnd', e=>{ if(!e.nodes.length) updateMapStatus(); });

// ═══════════════════════════════════════════════════════════
//  LIVE DATA REFRESH
// ═══════════════════════════════════════════════════════════
let refreshSeq = 0;
async function refreshData(){
  const t0 = Date.now();
  try{
    const data = await api('api/zabbix.php?action=status');
    if(!data) return;

    // Build host lookup
    S.zabbixHosts = {};
    (data.hosts||[]).forEach(h=>{ S.zabbixHosts[h.hostid]=h; });
    S.zabbixProblems = data.problems || [];
    S.allHosts = data.hosts || [];

    // Update header stats
    const c = data.counts||{};
    document.getElementById('sc-total').textContent  = c.total||0;
    document.getElementById('sc-ok').textContent     = c.ok||0;
    document.getElementById('sc-prob').textContent   = c.with_problems||0;
    document.getElementById('sc-alarms').textContent = c.alarms||0;

    // Alarm badge
    const badge = document.getElementById('alarm-badge');
    if(c.alarms>0){
      badge.textContent = c.alarms>99?'99+':c.alarms;
      badge.classList.remove('hidden');
      badge.classList.toggle('badge-blink', c.alarms>0);
    } else {
      badge.classList.add('hidden');
    }

    S.lastRefreshTs = new Date();
    document.getElementById('refresh-info').textContent = 'Updated '+S.lastRefreshTs.toLocaleTimeString();

    // Update map colors
    updateMapStatus();

    // If on alarms page, refresh view
    if(document.querySelector('.nav-item.active').dataset.page === 'alarms') renderAlarms();
    if(document.querySelector('.nav-item.active').dataset.page === 'hosts')  renderHosts();

  }catch(e){
    document.getElementById('refresh-info').textContent = 'Connection error';
  }
}

function startRefresh(){
  refreshData();
  if(S.refreshTimer) clearInterval(S.refreshTimer);
  S.refreshTimer = setInterval(refreshData, S.refreshInterval);
}

// ═══════════════════════════════════════════════════════════
//  TOOLTIP
// ═══════════════════════════════════════════════════════════
const tip = document.getElementById('vis-tip');
visNetwork.on('hoverNode', p=>{
  const d = {...(deviceData[p.node]||{}), ...(S.dbNodes[p.node]||{})};
  if(!d.name) return;
  document.getElementById('tip-name').textContent = d.name;
  document.getElementById('tip-ip').textContent   = d.ip||'';
  document.getElementById('tip-role').textContent = d.role||'';
  const hid = S.nodeHostMap[p.node];
  const host = hid && S.zabbixHosts[hid];
  const ta = document.getElementById('tip-alarm');
  if(host && host.problem_count>0){
    ta.textContent = `⚠ ${host.problem_count} active problem${host.problem_count>1?'s':''}`;
    ta.style.display='block';
  } else { ta.style.display='none'; }
  tip.style.display='block';
});
visNetwork.on('blurNode', ()=>{ tip.style.display='none'; });
document.addEventListener('mousemove', e=>{ tip.style.left=(e.clientX+14)+'px'; tip.style.top=(e.clientY+14)+'px'; });

// ═══════════════════════════════════════════════════════════
//  DETAIL PANEL
// ═══════════════════════════════════════════════════════════
const typeEmoji={external:'🌐',wan:'📡',switch:'🔀',firewall:'🛡️',palo:'🔥',f5:'⚖️',server:'🖥️',dbserver:'🗄️',hsm:'🔐',infra:'🏗️'};
const typeColors={external:'#f1c40f',wan:'#00bcd4',switch:'#3498db',firewall:'#e74c3c',palo:'#e67e22',f5:'#2ecc71',server:'#1abc9c',dbserver:'#e91e63',hsm:'#9b59b6',infra:'#78909c'};
let panelNodeId = null;

visNetwork.on('click', p=>{
  if(p.nodes.length){ showPanel(p.nodes[0]); }
  else { closePanel(); }
});

function showPanel(nid){
  panelNodeId = nid;
  const d = {...(deviceData[nid]||{}), ...(S.dbNodes[nid]||{})};
  const col   = typeColors[d.type]||'#3498db';
  const emoji = typeEmoji[d.type]||'💻';

  // Zabbix host data
  const hostId = S.nodeHostMap[nid];
  const host   = hostId ? S.zabbixHosts[hostId] : null;

  const sClass = {ok:'pill-ok',warn:'pill-warn',crit:'pill-crit',info:'pill-info'}[d.status]||'pill-info';
  const sLabel = {ok:'ONLINE',warn:'WARNING',crit:'CRITICAL',info:'INFO'}[d.status]||'UNKNOWN';

  let zbxHtml = '';
  if(host){
    const avail = host.available==1?'<span class="sev-pill pill-ok">● AVAILABLE</span>':host.available==2?'<span class="sev-pill pill-down">✗ UNREACHABLE</span>':'<span class="sev-pill pill-info">? UNKNOWN</span>';
    zbxHtml = `<div class="info-section">
      <div class="info-sec-title">🔵 Zabbix Live</div>
      <div class="info-row"><span class="info-key">Availability</span><span class="info-val">${avail}</span></div>
      <div class="info-row"><span class="info-key">Active Problems</span><span class="info-val" style="color:${host.problem_count>0?'var(--red)':'var(--green)'}">${host.problem_count}</span></div>
      ${host.problem_count>0?`<div class="info-row"><span class="info-key">Worst Severity</span><span class="info-val">${SEV[host.worst_severity]?.icon||''} ${SEV[host.worst_severity]?.label||''}</span></div>`:''}
      ${(host.problems||[]).slice(0,4).map(pr=>`<div class="info-row" style="background:${SEV[pr.severity]?.bg||''}"><span class="info-key" style="color:${SEV[pr.severity]?.color||'#fff'}">${SEV[pr.severity]?.icon||''} ${pr.name}</span></div>`).join('')}
    </div>`;
  }

  let infoHtml = Object.entries(d.info||{}).map(([k,v])=>`<div class="info-row"><span class="info-key">${k}</span><span class="info-val">${v}</span></div>`).join('');
  let ifaceHtml = (d.ifaces||[]).map(i=>`<span class="iface-tag">${i}</span>`).join('');

  document.getElementById('dp-body').innerHTML = `
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
      <div style="width:44px;height:44px;border-radius:10px;background:${col}22;border:1px solid ${col}44;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0">${emoji}</div>
      <div><div style="font-size:14px;font-weight:700;color:#fff">${d.name||nid}</div><div style="font-size:10px;color:var(--muted);font-family:'JetBrains Mono',monospace">${d.role||''}</div></div>
    </div>
    ${zbxHtml}
    <div class="info-section">
      <div class="info-sec-title">Network</div>
      <div class="info-row"><span class="info-key">IP</span><span class="info-val" style="color:var(--cyan)">${d.ip||'—'}</span></div>
      <div class="info-row"><span class="info-key">Type</span><span class="info-val">${d.type||'—'}</span></div>
      <div class="info-row"><span class="info-key">Status</span><span class="info-val"><span class="sev-pill ${sClass}">${sLabel}</span></span></div>
      ${hostId?`<div class="info-row"><span class="info-key">Zabbix ID</span><span class="info-val" style="color:var(--cyan)">${hostId}</span></div>`:''}
    </div>
    ${infoHtml?`<div class="info-section"><div class="info-sec-title">Config</div>${infoHtml}</div>`:''}
    ${ifaceHtml?`<div class="info-section"><div class="info-sec-title">Interfaces</div><div style="margin-top:4px">${ifaceHtml}</div></div>`:''}
  `;

  document.getElementById('dp-footer').style.display='flex';
  document.getElementById('dp-footer').innerHTML=`
    <button class="btn btn-ghost" style="flex:1" onclick="showEditPanel('${nid}')"><i data-lucide="pencil" style="width:12px;height:12px;vertical-align:-1px"></i> Edit</button>
    <button class="btn btn-primary" style="flex:1;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-color:transparent" onclick="openHostChat('${nid}')"><i data-lucide="bot" style="width:12px;height:12px;vertical-align:-1px"></i> Ask AI</button>
    <button class="btn btn-danger" onclick="deleteNode('${nid}')"><i data-lucide="trash-2" style="width:12px;height:12px"></i></button>
  `;
  lucide.createIcons();
  document.getElementById('detail-panel').classList.add('open');
}

function showEditPanel(nid){
  const d = {...(deviceData[nid]||{}), ...(S.dbNodes[nid]||{})};
  const hostId = S.nodeHostMap[nid]||'';
  document.getElementById('dp-title').textContent='Edit Device';
  const ifStr = (d.ifaces||[]).join('\n');
  const infoStr = Object.entries(d.info||{}).map(([k,v])=>`${k}: ${v}`).join('\n');
  const sopts=['ok','warn','crit','info'].map(s=>`<option value="${s}"${d.status===s?' selected':''}>${{ok:'● ONLINE',warn:'⚠ WARNING',crit:'✗ CRITICAL',info:'ℹ INFO'}[s]}</option>`).join('');
  const topts=['external','wan','switch','firewall','palo','f5','server','dbserver','hsm','infra'].map(t=>`<option value="${t}"${d.type===t?' selected':''}>${t}</option>`).join('');

  document.getElementById('dp-body').innerHTML=`
    <div class="ef"><label>Name</label><input class="edit-inp" id="e-name" value="${d.name||''}"></div>
    <div class="ef"><label>IP Address</label><input class="edit-inp" id="e-ip" value="${d.ip||''}"></div>
    <div class="ef"><label>Role</label><input class="edit-inp" id="e-role" value="${d.role||''}"></div>
    <div class="ef-row">
      <div class="ef"><label>Status</label><select class="edit-inp" id="e-status">${sopts}</select></div>
      <div class="ef"><label>Type</label><select class="edit-inp" id="e-type">${topts}</select></div>
    </div>
    <div class="ef"><label>Zabbix Host ID (links live data)</label><input class="edit-inp" id="e-zbxid" value="${hostId}" placeholder="e.g. 10084"></div>
    <div class="ef"><label>Interfaces (one per line)</label><textarea class="edit-inp" id="e-ifaces" rows="3">${ifStr}</textarea></div>
    <div class="ef"><label>Config (Key: Value per line)</label><textarea class="edit-inp" id="e-info" rows="3">${infoStr}</textarea></div>
  `;
  document.getElementById('dp-footer').innerHTML=`
    <button class="btn btn-ghost" style="flex:1" onclick="showPanel('${nid}')">Cancel</button>
    <button class="btn btn-primary" style="flex:1" onclick="saveNodeEdit('${nid}')">Save</button>
  `;
  // Inline edit styles
  document.querySelectorAll('.edit-inp').forEach(el=>{
    el.style.cssText='width:100%;background:#111d35;border:1px solid #1e2d4a;border-radius:6px;padding:7px 10px;color:#fff;font-size:11px;font-family:JetBrains Mono,monospace;outline:none;transition:border-color .15s;box-sizing:border-box;';
    el.addEventListener('focus',()=>el.style.borderColor='#00d4ff');
    el.addEventListener('blur', ()=>el.style.borderColor='#1e2d4a');
  });
}

async function saveNodeEdit(nid){
  const name   = document.getElementById('e-name').value.trim() || nid;
  const ip     = document.getElementById('e-ip').value.trim();
  const role   = document.getElementById('e-role').value.trim();
  const status = document.getElementById('e-status').value;
  const type   = document.getElementById('e-type').value;
  const zbxid  = document.getElementById('e-zbxid').value.trim();
  const ifaces = document.getElementById('e-ifaces').value.split('\n').map(s=>s.trim()).filter(Boolean);
  const info   = {};
  document.getElementById('e-info').value.split('\n').forEach(l=>{
    const i=l.indexOf(':'); if(i>0) info[l.slice(0,i).trim()]=l.slice(i+1).trim();
  });

  // Update state
  const existing = deviceData[nid]||{};
  const updated = {...existing,name,ip,role,status,type,ifaces,info};
  S.dbNodes[nid] = updated;
  if(zbxid){ S.nodeHostMap[nid]=zbxid; } else { delete S.nodeHostMap[nid]; }

  // Update vis label
  const showIp = ip && !['External','Internal','Remote','Private','Unknown',''].includes(ip);
  visNodes.update({id:nid, label:name+(showIp?'\n'+ip:'')});

  // Save to DB
  const pos = visNetwork.getPosition(nid);
  await api('api/nodes.php',{method:'POST',body:JSON.stringify({
    id:nid, label:name+(showIp?'\n'+ip:''), ip, role, type,
    x:pos.x, y:pos.y, status, ifaces, info, zabbix_host_id:zbxid||null,
    layer_key: layerKeyFromX(pos.x),
  })});

  saveNodeHostMap();
  updateMapStatus();
  showPanel(nid);
}

function closePanel(){
  document.getElementById('detail-panel').classList.remove('open');
  document.getElementById('dp-title').textContent='Device Details';
  panelNodeId=null;
}

// ═══════════════════════════════════════════════════════════
//  ADD / DELETE NODES
// ═══════════════════════════════════════════════════════════
function openAddModal(){ document.getElementById('add-modal').classList.add('open'); document.getElementById('add-label').focus(); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }

function layerKeyFromX(x){
  const keys=Object.keys(LX);
  return keys.reduce((best,k)=>Math.abs(LX[k]-x)<Math.abs(LX[best]-x)?k:best, keys[0]);
}

function getNextY(lk){
  const x=LX[lk]||LX.srv;
  const layerNodes=visNodes.get().filter(n=>Math.abs(n.x-x)<20);
  if(!layerNodes.length) return 0;
  return Math.max(...layerNodes.map(n=>n.y||0))+120;
}

async function submitAddNode(){
  const label  = document.getElementById('add-label').value.trim(); if(!label){alert('Label required');return;}
  const ip     = document.getElementById('add-ip').value.trim()||'Unknown';
  const role   = document.getElementById('add-role').value.trim()||'Custom Node';
  const type   = document.getElementById('add-type').value;
  const lk     = document.getElementById('add-layer').value;
  const status = document.getElementById('add-status').value;
  const ifaces = document.getElementById('add-ifaces').value.split('\n').map(s=>s.trim()).filter(Boolean);
  const zbxid  = document.getElementById('add-zbxid').value.trim();

  const id  = 'custom_'+Date.now();
  const x   = LX[lk]||LX.srv;
  const y   = getNextY(lk);
  const lbl = label+(ip!=='Unknown'?'\n'+ip:'');

  visNodes.add(mkNode(id,lbl,type,x,y,{size:22,fontSize:10}));
  deviceData[id]={name:label,ip,role,type,status,ifaces,info:{}};
  if(zbxid) S.nodeHostMap[id]=zbxid;

  await api('api/nodes.php',{method:'POST',body:JSON.stringify({
    id,label:lbl,ip,role,type,layer_key:lk,x,y,status,ifaces,info:{},zabbix_host_id:zbxid||null,
  })});
  saveNodeHostMap();
  closeModal('add-modal');
  ['add-label','add-ip','add-role','add-ifaces','add-zbxid'].forEach(i=>document.getElementById(i).value='');
  showPanel(id);
}

async function deleteNode(nid){
  if(!confirm(`Delete node "${deviceData[nid]?.name||nid}"? This removes all its connections.`)) return;
  visEdges.remove(visNetwork.getConnectedEdges(nid));
  visNodes.remove(nid);
  delete deviceData[nid]; delete S.dbNodes[nid]; delete S.nodeHostMap[nid];
  await api(`api/nodes.php?id=${nid}`,{method:'DELETE'});
  saveNodeHostMap();
  closePanel();
}

// ═══════════════════════════════════════════════════════════
//  LAYOUT SAVE / LOAD
// ═══════════════════════════════════════════════════════════
function savePositions(){
  const pos=visNetwork.getPositions();
  localStorage.setItem('tab_positions',JSON.stringify(pos));
  // Persist to DB for custom nodes
  const updates = Object.entries(pos)
    .filter(([id])=>id.startsWith('custom_')||S.dbNodes[id])
    .map(([id,p])=>({id,x:p.x,y:p.y}));
  if(updates.length) api('api/nodes.php',{method:'PATCH',body:JSON.stringify(updates)});
}

function loadSavedPositions(){
  const raw=localStorage.getItem('tab_positions');
  if(!raw) return;
  const pos=JSON.parse(raw);
  const updates=Object.entries(pos).filter(([id])=>visNodes.get(id)).map(([id,p])=>({id,x:p.x,y:p.y}));
  if(updates.length) visNodes.update(updates);
}

function saveLayoutPrompt(){ document.getElementById('layout-modal').classList.add('open'); document.getElementById('layout-name').focus(); }

async function submitSaveLayout(){
  const name=document.getElementById('layout-name').value.trim(); if(!name){alert('Name required');return;}
  const isDefault=document.getElementById('layout-default').checked;
  const positions=visNetwork.getPositions();
  await api('api/layout.php',{method:'POST',body:JSON.stringify({name,positions,is_default:isDefault})});
  closeModal('layout-modal');
  document.getElementById('layout-name').value='';
  if(document.querySelector('.nav-item.active').dataset.page==='settings') loadLayouts();
}

async function loadLayouts(){
  const rows=await api('api/layout.php')||[];
  const el=document.getElementById('layouts-list');
  if(!rows.length){el.innerHTML='<div style="color:var(--muted);font-size:12px">No saved layouts yet.</div>';return;}
  el.innerHTML=rows.map(r=>`
    <div class="layout-row">
      <span class="layout-name">${r.name}${r.is_default?'<span style="color:var(--cyan);font-size:9px;margin-left:6px">DEFAULT</span>':''}</span>
      <span class="layout-date">${new Date(r.created_at).toLocaleDateString()}</span>
      <button class="btn btn-ghost" style="padding:4px 10px;font-size:10px" onclick="applyLayout(${r.id})">Load</button>
      <button class="btn btn-danger" style="padding:4px 8px;font-size:10px" onclick="deleteLayout(${r.id},this)">✕</button>
    </div>`).join('');
}

async function applyLayout(id){
  const row=await api(`api/layout.php?id=${id}`);
  if(!row||!row.positions) return;
  const updates=Object.entries(row.positions).filter(([id])=>visNodes.get(id)).map(([id,p])=>({id,x:p.x,y:p.y}));
  if(updates.length){ visNodes.update(updates); visNetwork.fit({animation:{duration:500}}); }
}

async function deleteLayout(id,btn){
  if(!confirm('Delete this layout?')) return;
  await api(`api/layout.php?id=${id}`,{method:'DELETE'});
  btn.closest('.layout-row').remove();
}

function resetLayout(){
  localStorage.removeItem('tab_positions');
  location.reload();
}

// ═══════════════════════════════════════════════════════════
//  NODE-HOST MAP PERSISTENCE
// ═══════════════════════════════════════════════════════════
function saveNodeHostMap(){
  localStorage.setItem('tab_nhm',JSON.stringify(S.nodeHostMap));
}
function loadNodeHostMap(){
  const raw=localStorage.getItem('tab_nhm');
  if(raw) S.nodeHostMap=JSON.parse(raw);
}

// ═══════════════════════════════════════════════════════════
//  LAYER FILTER / SEARCH
// ═══════════════════════════════════════════════════════════
function filterLayer(layer,btn){
  document.querySelectorAll('.tb-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  if(layer==='all'){visNodes.getIds().forEach(id=>visNodes.update({id,hidden:false,opacity:1}));visEdges.getIds().forEach(id=>visEdges.update({id,hidden:false}));return;}
  const m={external:['external','wan'],switching:['switch'],firewall:['firewall'],loadbalancer:['palo','f5'],servers:['server','dbserver','infra'],hsm:['hsm']};
  const show=m[layer]||[];
  visNodes.get().forEach(n=>{const d=deviceData[n.id];const v=d&&show.includes(d.type);visNodes.update({id:n.id,hidden:!v,opacity:v?1:0.12});});
}

function searchNode(q){
  if(!q.trim()){visNodes.get().forEach(n=>visNodes.update({id:n.id,opacity:1}));return;}
  q=q.toLowerCase();
  visNodes.get().forEach(n=>{
    const d=deviceData[n.id];
    const m=n.id.includes(q)||d?.name?.toLowerCase().includes(q)||d?.ip?.toLowerCase().includes(q)||d?.role?.toLowerCase().includes(q);
    visNodes.update({id:n.id,opacity:m?1:0.1});
  });
}

// ═══════════════════════════════════════════════════════════
//  ALARMS PAGE
// ═══════════════════════════════════════════════════════════
async function refreshAlarms(){
  const params=[];
  if(S.currentAlarmFilter>=0) params.push('severity='+S.currentAlarmFilter);
  const probs=await api('api/zabbix.php?action=problems'+(params.length?'&'+params.join('&'):''))||[];
  S.allProblems=probs;
  renderAlarms();
}

function filterAlarms(sev,btn){
  document.querySelectorAll('.filter-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  S.currentAlarmFilter=sev;
  refreshAlarms();
}

function timeDiff(ts){
  const d=Math.floor((Date.now()/1000)-ts);
  if(d<60) return d+'s';
  if(d<3600) return Math.floor(d/60)+'m';
  if(d<86400) return Math.floor(d/3600)+'h '+Math.floor((d%3600)/60)+'m';
  return Math.floor(d/86400)+'d '+Math.floor((d%86400)/3600)+'h';
}

function renderAlarms(){
  let probs = S.allProblems;
  // Filter to current map's hosts only
  if(S.currentMapHostIds.size>0){
    probs=probs.filter(p=>{
      const ids=(p.host_ids||[]).concat((p.hosts||[]).map(h=>h.hostid));
      return ids.some(hid=>S.currentMapHostIds.has(hid));
    });
  }
  if(S.currentAlarmFilter==='unack') probs=probs.filter(p=>p.acknowledged=='0');
  else if(S.currentAlarmFilter>=0)   probs=probs.filter(p=>(p.severity||p.priority)==S.currentAlarmFilter);

  const tbody=document.getElementById('alarms-tbody');
  if(!probs.length){
    tbody.innerHTML=`<tr><td colspan="8" class="empty-state" style="color:var(--green)">✓ No active alarms</td></tr>`;
    return;
  }

  tbody.innerHTML=probs.map(p=>{
    const sv=parseInt(p.severity||p.priority||0);
    const s=SEV[sv]||SEV[0];
    const acked=p.acknowledged=='1';
    const hosts=(p.hosts||[]).map(h=>h.name||h.host).join(', ')||'—';
    const since=new Date(p.clock*1000).toLocaleString();
    return `<tr style="border-left:4px solid ${s.color};background:${s.bg}">
      <td style="width:4px;padding:0;background:${s.color}"></td>
      <td><span style="color:${s.color};font-weight:700;font-size:11px;font-family:'JetBrains Mono',monospace">${s.icon} ${s.label}</span></td>
      <td><span class="alarm-host">${hosts}</span></td>
      <td><span class="alarm-name">${p.name||p.trigger_desc||'—'}</span></td>
      <td><span class="duration">${timeDiff(p.clock)}</span></td>
      <td><span class="duration">${since}</span></td>
      <td><span class="sev-pill ${acked?'pill-ok':'pill-crit'}" style="font-size:9px">${acked?'✓ ACK':'OPEN'}</span></td>
      <td>${!acked?`<button class="ack-btn" onclick="ackAlarm('${p.eventid}',this)">Acknowledge</button>`:''}</td>
    </tr>`;
  }).join('');
}

async function ackAlarm(eventid,btn){
  btn.textContent='Acking...'; btn.disabled=true;
  await api('api/zabbix.php?action=acknowledge',{method:'POST',body:JSON.stringify({eventid,message:'Acknowledged via Tabadul NOC'})});
  btn.closest('tr').querySelector('.sev-pill').className='sev-pill pill-ok';
  btn.closest('tr').querySelector('.sev-pill').textContent='✓ ACK';
  btn.remove();
}

// ═══════════════════════════════════════════════════════════
//  HOSTS PAGE
// ═══════════════════════════════════════════════════════════
function renderHosts(){
  let hosts=[...S.allHosts];
  // Filter to current map's hosts only
  if(S.currentMapHostIds.size>0) hosts=hosts.filter(h=>S.currentMapHostIds.has(h.hostid));
  const q=S.hostSearchQ.toLowerCase();
  if(q) hosts=hosts.filter(h=>(h.name||h.host).toLowerCase().includes(q)||h.ip?.includes(q));
  if(S.hostFilterMode==='problems') hosts=hosts.filter(h=>h.problem_count>0);
  if(S.hostFilterMode==='ok')       hosts=hosts.filter(h=>h.problem_count===0&&h.available==1);
  if(S.hostFilterMode==='down')     hosts=hosts.filter(h=>h.available==2);

  const grid=document.getElementById('hosts-grid');
  if(!hosts.length){grid.innerHTML='<div class="empty-state" style="grid-column:1/-1">No hosts found.</div>';return;}

  hosts.sort((a,b)=>b.worst_severity-a.worst_severity||b.problem_count-a.problem_count);

  grid.innerHTML=hosts.map(h=>{
    const sv=h.worst_severity||0;
    const down=h.available==2;
    const s=SEV[sv]||SEV[0];
    const stripe=down?'#FF1744':sv>=1?s.color:'#00e676';
    const cls=['host-card', h.problem_count>0?'has-problems':'', sv>=5?'has-disaster':'', down?'unavailable':''].filter(Boolean).join(' ');
    const badge=h.problem_count>0?`<span class="problem-badge${sv<=3?' avg':sv===4?' warn':''}">${h.problem_count} ${sv>=5?'💀':sv>=4?'🔴':sv>=3?'🟠':'⚠'}</span>`:`<span class="sev-pill pill-ok" style="font-size:9px">✓ OK</span>`;
    const avail=down?`<span style="color:var(--red);font-size:9px;font-family:'JetBrains Mono',monospace">✗ UNREACHABLE</span>`:h.available==1?`<span style="color:var(--green);font-size:9px;font-family:'JetBrains Mono',monospace">● AVAILABLE</span>`:'';
    return `<div class="${cls}" onclick="highlightHostOnMap('${h.hostid}')">
      <div class="hc-top-stripe" style="background:${stripe}"></div>
      <div class="hc-name">${h.name||h.host}</div>
      <div class="hc-ip">${h.ip||'No IP'}</div>
      <div class="hc-bottom">${avail}${badge}</div>
    </div>`;
  }).join('');
}

function filterHosts(q){ S.hostSearchQ=q; renderHosts(); }
function hostFilter(mode,btn){
  S.hostFilterMode=mode;
  document.querySelectorAll('#page-hosts .filter-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  renderHosts();
}

function highlightHostOnMap(hostId){
  navigate('map');
  // Find node linked to this host
  const nid=Object.keys(S.nodeHostMap).find(k=>S.nodeHostMap[k]===hostId);
  if(nid){
    visNetwork.selectNodes([nid]);
    visNetwork.focus(nid,{scale:1.5,animation:{duration:500}});
    showPanel(nid);
  }
}

// ═══════════════════════════════════════════════════════════
//  SETTINGS PAGE
// ═══════════════════════════════════════════════════════════
async function loadSettingsData(){
  const cfg=await api('api/zabbix.php?action=config');
  if(cfg){
    document.getElementById('cfg-url').value=cfg.url||'';
    document.getElementById('cfg-token').value=cfg.token_masked||'';
    document.getElementById('cfg-refresh').value=cfg.refresh||30;
  }
  // Load Claude key status
  const ck=await api('api/import.php?action=getkey');
  const ckStatus=document.getElementById('claude-key-status');
  if(ck?.has_key) ckStatus.innerHTML=`<span style="color:var(--green)">✓ Key saved: ${ck.masked}</span>`;
  else ckStatus.innerHTML=`<span style="color:var(--muted)">No key saved</span>`;
  await loadLayouts();
}

async function saveClaudeKey(){
  const key=document.getElementById('claude-key-input').value.trim();
  const st=document.getElementById('claude-key-status');
  if(!key){st.innerHTML='<span style="color:var(--red)">Enter a key</span>';return;}
  const r=await api('api/import.php?action=savekey',{method:'POST',body:JSON.stringify({claude_key:key})});
  if(r?.ok){
    st.innerHTML='<span style="color:var(--green)">✓ Saved</span>';
    document.getElementById('claude-key-input').value='';
  } else {
    st.innerHTML=`<span style="color:var(--red)">✗ ${r?.error||'Error'}</span>`;
  }
}

async function testZabbix(){
  document.getElementById('cfg-result').textContent='Testing...';
  const r=await api('api/zabbix.php?action=test');
  if(r?.ok) document.getElementById('cfg-result').innerHTML=`<span style="color:var(--green)">✓ Connected — Zabbix ${r.version}</span>`;
  else document.getElementById('cfg-result').innerHTML=`<span style="color:var(--red)">✗ ${r?.error||'Connection failed'}</span>`;
}

async function saveZabbixConfig(){
  const url     = document.getElementById('cfg-url').value.trim();
  const token   = document.getElementById('cfg-token').value.trim();
  const refresh = parseInt(document.getElementById('cfg-refresh').value)||30;
  const r=await api('api/zabbix.php?action=config',{method:'POST',body:JSON.stringify({url,token,refresh})});
  S.refreshInterval=refresh*1000;
  if(r?.ok){document.getElementById('cfg-result').innerHTML='<span style="color:var(--green)">✓ Saved. Reconnecting...</span>';startRefresh();}
}

async function changePassword(){
  const cur=document.getElementById('pw-cur').value;
  const nw =document.getElementById('pw-new').value;
  const cn =document.getElementById('pw-con').value;
  const res=document.getElementById('pw-result');
  if(nw!==cn){res.innerHTML='<span style="color:var(--red)">Passwords do not match</span>';return;}
  const r=await api('api/auth.php?action=password',{method:'POST',body:JSON.stringify({current:cur,new:nw})});
  if(r?.ok){res.innerHTML='<span style="color:var(--green)">✓ Password updated</span>';['pw-cur','pw-new','pw-con'].forEach(i=>document.getElementById(i).value='');}
  else res.innerHTML=`<span style="color:var(--red)">✗ ${r?.error||'Error'}</span>`;
}

// ═══════════════════════════════════════════════════════════
//  MAP MANAGEMENT
// ═══════════════════════════════════════════════════════════
async function loadMaps(){
  const layouts=await api('api/layout.php')||[];
  const container=document.getElementById('map-list');
  // Keep the default "Network Map" item (mapid=0)
  const defaultItem=`<div class="map-list-item${S.currentMapId===0?' active-map':''}" data-mapid="0" onclick="switchMap(0,'Network Map')"><span>🗺️</span><span>Network Map</span></div>`;
  const imported=layouts.map(l=>`
    <div class="map-list-item${S.currentMapId==l.id?' active-map':''}" data-mapid="${l.id}" onclick="switchMap(${l.id},'${l.name.replace(/'/g,"\\'")}')">
      <span>🗺</span><span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${l.name}</span>
      <span class="map-del" onclick="deleteMap(event,${l.id})">✕</span>
    </div>`).join('');
  container.innerHTML=defaultItem+imported;
}

async function switchMap(id, name){
  S.currentMapId=id;
  S.currentMapName=name;
  // Update active state in sidebar
  document.querySelectorAll('.map-list-item').forEach(el=>{
    el.classList.toggle('active-map', parseInt(el.dataset.mapid)===id);
  });
  // Update page title
  document.getElementById('page-title').textContent=name;
  if(id===0){
    // Default map: use all nodeHostMap entries from vis nodes
    S.currentMapHostIds=new Set(Object.values(S.nodeHostMap).filter(Boolean));
    navigate('map');
    return;
  }
  // Load imported layout
  const layout=await api('api/layout.php?id='+id);
  if(!layout){return;}
  // Build nodeHostMap from this layout's nodes
  const newMap={};
  visNodes.clear(); visEdges.clear();
  (layout.nodes||[]).forEach(n=>{
    if(n.zabbix_host_id) newMap[n.id]=n.zabbix_host_id;
    visNodes.add(mkNode(n.id, n.label, n.type||'switch', n.x, n.y, {size:22,fontSize:10}));
    deviceData[n.id]={name:n.label,ip:n.ip,role:'',type:n.type||'switch',status:n.status||'ok',ifaces:n.ifaces||[],info:n.info||{}};
  });
  S.nodeHostMap=newMap;
  S.currentMapHostIds=new Set(Object.values(newMap).filter(Boolean));
  saveNodeHostMap();
  navigate('map');
  setTimeout(()=>visNetwork.fit({animation:{duration:500}}),100);
  updateMapStatus();
  renderHosts();
  renderAlarms();
}

async function deleteMap(evt, id){
  evt.stopPropagation();
  if(!confirm('Delete this map and all its nodes?')) return;
  await api('api/layout.php?id='+id,{method:'DELETE'});
  if(S.currentMapId===id) switchMap(0,'Network Map');
  loadMaps();
}

// ═══════════════════════════════════════════════════════════
//  IMPORT MODAL
// ═══════════════════════════════════════════════════════════
let _impFile=null;

function openImportModal(){
  _impFile=null;
  S.importAnalysisResult=null;
  document.getElementById('imp-name').value='';
  document.getElementById('imp-file-info').textContent='';
  document.getElementById('imp-err').textContent='';
  document.getElementById('imp-analyze-btn').disabled=true;
  impGoStep(1);
  document.getElementById('import-modal').style.display='flex';
}
function closeImportModal(){ document.getElementById('import-modal').style.display='none'; }

function impGoStep(n){
  [1,2,3].forEach(i=>{
    document.getElementById('imp-step'+i).style.display=i===n?'':'none';
    const s=document.getElementById('imp-s'+i);
    s.className='import-step'+(i<n?' done':i===n?' active':'');
  });
}

function onFileSelected(input){
  _impFile=input.files[0];
  if(_impFile){
    document.getElementById('imp-file-info').textContent='📎 '+_impFile.name+' ('+Math.round(_impFile.size/1024)+' KB)';
    document.getElementById('imp-analyze-btn').disabled=false;
    document.getElementById('imp-err').textContent='';
  }
}

// Drag & drop support
document.addEventListener('DOMContentLoaded',()=>{
  const dz=document.getElementById('imp-drop');
  if(!dz) return;
  dz.addEventListener('dragover',e=>{e.preventDefault();dz.classList.add('drag-over');});
  dz.addEventListener('dragleave',()=>dz.classList.remove('drag-over'));
  dz.addEventListener('drop',e=>{
    e.preventDefault(); dz.classList.remove('drag-over');
    const f=e.dataTransfer.files[0];
    if(f){ _impFile=f; document.getElementById('imp-file-info').textContent='📎 '+f.name+' ('+Math.round(f.size/1024)+' KB)'; document.getElementById('imp-analyze-btn').disabled=false; }
  });
});

async function doAnalyzeMap(){
  const name=document.getElementById('imp-name').value.trim();
  const errEl=document.getElementById('imp-err');
  if(!name){errEl.textContent='Enter a map name';return;}
  if(!_impFile){errEl.textContent='Select a file';return;}
  const btn=document.getElementById('imp-analyze-btn');
  btn.textContent='Analyzing…'; btn.disabled=true;
  errEl.textContent='';

  const fd=new FormData();
  fd.append('map',_impFile);

  try{
    const resp=await fetch('api/import.php?action=analyze',{method:'POST',credentials:'include',body:fd});
    const r=await resp.json();
    if(r.error){errEl.textContent='✗ '+r.error;btn.textContent='Analyze with Claude →';btn.disabled=false;return;}
    S.importAnalysisResult=r;
    renderImportPreview(r);
    impGoStep(2);
  }catch(e){
    errEl.textContent='Request failed: '+e.message;
    btn.textContent='Analyze with Claude →';btn.disabled=false;
  }
}

function renderImportPreview(r){
  const sum=document.getElementById('imp-summary');
  sum.innerHTML=`
    <div class="sum-chip" style="border-color:var(--green);color:var(--green)">✓ ${r.matched} matched</div>
    <div class="sum-chip" style="border-color:var(--red);color:var(--muted)">✗ ${r.skipped} not in Zabbix</div>
    <div class="sum-chip">📊 ${r.total} total extracted</div>`;
  const tbody=document.getElementById('imp-preview-body');
  tbody.innerHTML=r.nodes.map(n=>`
    <tr class="${n.matched?'matched':'unmatched'}">
      <td>${n.name}</td>
      <td style="font-family:'JetBrains Mono',monospace">${n.ip||'—'}</td>
      <td>${n.type}</td>
      <td style="font-size:10px;color:${n.matched?'var(--cyan)':'var(--muted)'}">${n.matched?(n.zabbix_host?.name||n.zabbix_host?.host):'Not found'}</td>
      <td><span class="match-pill ${n.matched?'match-ok':'match-no'}">${n.matched?'✓ MATCH':'✗ SKIP'}</span></td>
    </tr>`).join('');
  const hasMatches=r.matched>0;
  document.getElementById('imp-create-btn').disabled=!hasMatches;
}

async function doCreateMap(){
  const name=document.getElementById('imp-name').value.trim();
  const matched=(S.importAnalysisResult?.nodes||[]).filter(n=>n.matched);
  const btn=document.getElementById('imp-create-btn');
  btn.textContent='Creating…'; btn.disabled=true;
  const r=await api('api/import.php?action=create',{method:'POST',body:JSON.stringify({name, nodes:matched})});
  if(r?.ok){
    document.getElementById('imp-done-msg').textContent=`"${name}" created with ${r.nodes_created} nodes`;
    impGoStep(3);
    loadMaps();
    // Auto-switch to the new map
    switchMap(r.layout_id, name);
  } else {
    btn.textContent='Create Map →'; btn.disabled=false;
    alert(r?.error||'Failed to create map');
  }
}

// ═══════════════════════════════════════════════════════════
//  NAVIGATION
// ═══════════════════════════════════════════════════════════
const PAGE_TITLES={map:'Network Map',alarms:'Active Alarms',hosts:'Hosts',intel:'AI Network Intelligence',settings:'Settings'};
function navigate(page){
  document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
  document.getElementById('page-'+page).classList.add('active');
  document.querySelectorAll('.nav-item[data-page]').forEach(n=>n.classList.toggle('active',n.dataset.page===page));
  document.getElementById('page-title').textContent=PAGE_TITLES[page]||page;
  if(page==='alarms')   refreshAlarms();
  if(page==='hosts')    renderHosts();
  if(page==='settings') loadSettingsData();
  if(page==='intel')    initIntelPage();
  if(page==='map')      setTimeout(()=>visNetwork.fit({animation:{duration:400}}),50);
}

// ═══════════════════════════════════════════════════════════
//  AUTH
// ═══════════════════════════════════════════════════════════
async function api(path,opts={}){
  try{
    const r=await fetch(path,{credentials:'include',headers:{'Content-Type':'application/json'},...opts});
    if(r.status===401){showLogin();return null;}
    return r.json();
  }catch(e){return null;}
}

function showLogin(){document.getElementById('login-overlay').style.display='flex';}

async function doLogin(){
  const u=document.getElementById('l-user').value.trim();
  const p=document.getElementById('l-pass').value;
  const e=document.getElementById('l-err');
  const btn=document.querySelector('.login-btn');
  if(!u||!p){e.textContent='Enter username and password';return;}
  btn.textContent='Signing in…';btn.disabled=true;
  try{
    const resp=await fetch('api/auth.php?action=login',{
      method:'POST',credentials:'include',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({username:u,password:p})
    });
    let r=null;
    try{r=await resp.json();}catch(_){
      e.textContent='Server error — make sure you ran install.php first';
      btn.textContent='Sign In →';btn.disabled=false;return;
    }
    if(r?.ok){
      document.getElementById('login-overlay').style.display='none';
      e.textContent='';
      init();
    } else {
      e.textContent=r?.error||'Invalid credentials';
      document.getElementById('l-pass').value='';
      document.getElementById('l-pass').focus();
    }
  }catch(err){
    e.textContent='Cannot reach server';
  }
  btn.textContent='Sign In →';btn.disabled=false;
}

async function doLogout(){
  await api('api/auth.php?action=logout');
  showLogin();
}

// ═══════════════════════════════════════════════════════════
//  LOAD DB NODES (custom nodes added by user)
// ═══════════════════════════════════════════════════════════
async function loadDbNodes(){
  const rows=await api('api/nodes.php')||[];
  rows.forEach(r=>{
    S.dbNodes[r.id]=r;
    if(r.zabbix_host_id) S.nodeHostMap[r.id]=r.zabbix_host_id;
    // If it's a custom node not already in vis, add it
    if(r.id.startsWith('custom_') && !visNodes.get(r.id)){
      visNodes.add(mkNode(r.id,r.label,r.type,r.x||0,r.y||0,{size:22,fontSize:10}));
      deviceData[r.id]={name:r.label,ip:r.ip,role:r.role,type:r.type,status:r.status,ifaces:r.ifaces,info:r.info};
    } else if(visNodes.get(r.id)){
      // Update existing node with DB overrides
      const showIp = r.ip && !['External','Internal','Remote','Private','Unknown',''].includes(r.ip);
      visNodes.update({id:r.id, label:r.label+(showIp&&!r.label.includes(r.ip)?'\n'+r.ip:'')});
      deviceData[r.id]={...deviceData[r.id],...r,ifaces:r.ifaces,info:r.info};
    }
  });
}

// ═══════════════════════════════════════════════════════════
//  AI CHAT — HOST CONTEXT
// ═══════════════════════════════════════════════════════════
const HC = { open:false, messages:[], hostContext:null, thinking:false };

function openHostChat(nid){
  const d = {...(deviceData[nid]||{}), ...(S.dbNodes[nid]||{})};
  const hostId = S.nodeHostMap[nid]||'';
  const zbxHost = hostId ? S.zabbixHosts[hostId] : null;
  const probs   = zbxHost ? (zbxHost.problems||[]) : [];

  HC.hostContext = {
    name:       d.name||nid,
    ip:         d.ip||'',
    type:       d.type||'switch',
    role:       d.role||'',
    status:     d.status||'ok',
    zabbix_id:  hostId,
    problems:   probs,
    ifaces:     d.ifaces||[],
    info:       d.info||{},
  };
  HC.messages = [];

  document.getElementById('hc-context-label').textContent = d.name||nid;
  document.getElementById('hc-messages').innerHTML='';

  const panel=document.getElementById('host-chat-panel');
  panel.style.display='flex';
  HC.open=true;
  document.getElementById('hc-chevron').style.transform='rotate(0deg)';

  // Auto-start: send greeting from AI
  hcAppendThinking();
  apiChat({
    messages:[{role:'user',content:`I'm reviewing ${d.name||nid} (IP: ${d.ip||'unknown'}). Please greet me and start your investigation.`}],
    mode:'host', host_context:HC.hostContext
  }).then(r=>{
    hcRemoveThinking();
    if(r?.reply){
      HC.messages.push({role:'assistant',content:r.reply});
      hcRenderMsg('assistant',r.reply);
    }
  });
}

function toggleHostChat(){
  HC.open=!HC.open;
  document.getElementById('hc-body').style.display=HC.open?'flex':'none';
  document.getElementById('hc-chevron').style.transform=HC.open?'rotate(0deg)':'rotate(180deg)';
}

function hcAppendThinking(){
  const el=document.createElement('div');
  el.className='ai-msg assistant'; el.id='hc-thinking';
  el.innerHTML='<div class="ai-bubble thinking">Analyzing...</div>';
  document.getElementById('hc-messages').appendChild(el);
  scrollHC();
}
function hcRemoveThinking(){const el=document.getElementById('hc-thinking');if(el)el.remove();}

function hcRenderMsg(role,text){
  const el=document.createElement('div');
  el.className='ai-msg '+role;
  el.innerHTML=`<div class="ai-bubble ${role}">${text.replace(/\n/g,'<br>').replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>')}</div>`;
  document.getElementById('hc-messages').appendChild(el);
  scrollHC();
}
function scrollHC(){const el=document.getElementById('hc-messages');if(el)el.scrollTop=el.scrollHeight;}

async function sendHostChat(){
  if(HC.thinking) return;
  const inp=document.getElementById('hc-input');
  const text=inp.value.trim();
  if(!text) return;
  inp.value='';
  HC.messages.push({role:'user',content:text});
  hcRenderMsg('user',text);
  HC.thinking=true;
  hcAppendThinking();
  const msgs=[...HC.messages];
  const r=await apiChat({messages:msgs,mode:'host',host_context:HC.hostContext});
  hcRemoveThinking();
  HC.thinking=false;
  if(r?.reply){
    HC.messages.push({role:'assistant',content:r.reply});
    hcRenderMsg('assistant',r.reply);
  } else if(r?.error){
    hcRenderMsg('assistant','⚠ '+r.error);
  }
}

// ═══════════════════════════════════════════════════════════
//  AI CHAT — INTEL PAGE
// ═══════════════════════════════════════════════════════════
const INTEL = { messages:[], context:'network', thinking:false, initialized:false };

function initIntelPage(){
  if(INTEL.initialized) return;
  INTEL.initialized=true;
  // Welcome message
  const welcome=`**Welcome to NOC Sentinel Intelligence**\n\nI have access to your live network data:\n- **${document.getElementById('sc-total').textContent}** monitored hosts\n- **${document.getElementById('sc-alarms').textContent}** active alarms\n\nI'm here to help you:\n• Analyze current network health\n• Identify monitoring gaps\n• Provide Zabbix configuration guidance\n• Learn about your network topology\n\nWhat would you like to explore?`;
  INTEL.messages=[];
  document.getElementById('intel-messages').innerHTML='';
  intelRenderMsg('assistant',welcome);
}

function setIntelContext(ctx,btn){
  INTEL.context=ctx;
  document.querySelectorAll('.intel-ctx-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('intel-context-label').textContent={
    network:'Full Network Analysis',alarms:'Alarm Analysis',
    topology:'Topology Review',performance:'Performance Analysis'}[ctx]||ctx;
}

function intelRenderMsg(role,text){
  const el=document.createElement('div');
  el.className='ai-msg '+role;
  const badge=role==='assistant'?'<div class="ai-badge"><i data-lucide="bot" style="width:9px;height:9px"></i> NOC Sentinel</div>':'';
  el.innerHTML=`${badge}<div class="ai-bubble ${role}">${text.replace(/\n/g,'<br>').replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>').replace(/^• /gm,'◆ ')}</div>`;
  document.getElementById('intel-messages').appendChild(el);
  document.getElementById('intel-messages').scrollTop=1e9;
  lucide.createIcons();
}

function intelAppendThinking(){
  const el=document.createElement('div');el.className='ai-msg assistant';el.id='intel-thinking';
  el.innerHTML='<div class="ai-bubble thinking"><i data-lucide="loader" style="width:11px;height:11px;animation:spin 1s linear infinite;vertical-align:-1px"></i> Thinking...</div>';
  document.getElementById('intel-messages').appendChild(el);
  document.getElementById('intel-messages').scrollTop=1e9;
  lucide.createIcons();
}

async function sendIntelMessage(){
  if(INTEL.thinking) return;
  const inp=document.getElementById('intel-input');
  const text=inp.value.trim(); if(!text) return;
  inp.value='';
  INTEL.messages.push({role:'user',content:text});
  intelRenderMsg('user',text);
  INTEL.thinking=true;
  intelAppendThinking();
  const r=await apiChat({
    messages:[...INTEL.messages],
    mode:'network',
    context_focus:INTEL.context,
    network_stats:{
      total:parseInt(document.getElementById('sc-total').textContent)||0,
      ok:parseInt(document.getElementById('sc-ok').textContent)||0,
      with_problems:parseInt(document.getElementById('sc-prob').textContent)||0,
      alarms:parseInt(document.getElementById('sc-alarms').textContent)||0,
    }
  });
  const thinking=document.getElementById('intel-thinking');if(thinking)thinking.remove();
  INTEL.thinking=false;
  if(r?.reply){
    INTEL.messages.push({role:'assistant',content:r.reply});
    intelRenderMsg('assistant',r.reply);
  } else {
    intelRenderMsg('assistant','⚠ '+(r?.error||'No response — check Claude API key in Settings'));
  }
}

async function apiChat(payload){
  const stats={
    total:parseInt(document.getElementById('sc-total').textContent)||0,
    ok:parseInt(document.getElementById('sc-ok').textContent)||0,
    with_problems:parseInt(document.getElementById('sc-prob').textContent)||0,
    alarms:parseInt(document.getElementById('sc-alarms').textContent)||0,
    unavailable:parseInt(document.getElementById('sc-prob').textContent)||0,
  };
  return await api('api/chat.php',{method:'POST',body:JSON.stringify({...payload,network_stats:stats})});
}

// ═══════════════════════════════════════════════════════════
//  INIT
// ═══════════════════════════════════════════════════════════
async function init(){
  lucide.createIcons();
  loadNodeHostMap();
  await loadDbNodes();
  loadSavedPositions();
  // Default map: build host filter from nodeHostMap
  S.currentMapHostIds=new Set(Object.values(S.nodeHostMap).filter(Boolean));
  startRefresh();
  loadMaps();
  visNetwork.once('stabilized',()=>visNetwork.fit({animation:{duration:700}}));
  window.addEventListener('resize',()=>visNetwork.fit());
}

// Auto-init if already logged in (PHP session)
<?php if($loggedIn): ?>
document.addEventListener('DOMContentLoaded', init);
<?php else: ?>
document.getElementById('l-user').focus();
<?php endif; ?>

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open');}));
</script>
</body>
</html>
