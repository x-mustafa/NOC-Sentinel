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
/* ── TOGGLE SWITCH ── */
.toggle-switch{position:relative;display:inline-block;width:36px;height:20px;flex-shrink:0}
.toggle-switch input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;cursor:pointer;inset:0;background:var(--surface3);border-radius:20px;transition:.3s;border:1px solid var(--border2)}
.toggle-slider:before{content:'';position:absolute;width:14px;height:14px;left:2px;bottom:2px;background:var(--muted);border-radius:50%;transition:.3s}
.toggle-switch input:checked+.toggle-slider{background:rgba(0,212,255,0.2);border-color:var(--cyan)}
.toggle-switch input:checked+.toggle-slider:before{transform:translateX(16px);background:var(--cyan)}
/* ── USER TABLE ── */
.user-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)}
.user-row:last-child{border-bottom:none}
.role-badge{font-size:9px;font-weight:700;padding:2px 7px;border-radius:10px;font-family:'JetBrains Mono',monospace;text-transform:uppercase}
.role-badge.admin{background:rgba(187,134,252,0.15);color:#bb86fc;border:1px solid #bb86fc44}
.role-badge.operator{background:rgba(0,212,255,0.1);color:var(--cyan);border:1px solid rgba(0,212,255,0.3)}
.role-badge.viewer{background:rgba(74,96,128,0.2);color:var(--muted);border:1px solid var(--border)}
.map-alarm-row{display:flex;align-items:center;gap:10px;padding:8px 14px;border-bottom:1px solid var(--border);cursor:pointer;transition:background 0.1s}
.map-alarm-row:hover{background:var(--surface2)}
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
        <div class="map-list-item" data-mapid="-1" onclick="switchMap(-1,'POS MAP')">
          <span>🗺️</span><span>POS MAP</span>
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
      <button class="tb-btn" id="tb-traffic" onclick="toggleTraffic()" title="Toggle Traffic Animation" style="display:flex;align-items:center;gap:4px">
        <i data-lucide="activity" style="width:12px;height:12px"></i> Traffic
      </button>
      <button class="tb-btn" onclick="saveLayoutPrompt()">💾 Save Layout</button>
      <button class="tb-btn" onclick="resetLayout()">↺ Reset</button>
      <button class="tb-btn" onclick="openDiscoverModal()" title="Auto-Discover from Zabbix" style="display:flex;align-items:center;gap:4px">
        <i data-lucide="radar" style="width:12px;height:12px"></i> Discover
      </button>
      <button class="tb-btn add" onclick="openAddModal()">＋ Add Node</button>
    </div>
    <!-- Canvas -->
    <div id="map-wrap">
      <div id="vis-network"></div>
      <canvas id="traffic-canvas" style="position:absolute;top:0;left:0;pointer-events:none;z-index:5;display:block"></canvas>
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

      <!-- User Management (admin only) -->
      <div class="settings-card" id="user-mgmt-card" style="display:none">
        <h3 style="display:flex;align-items:center;gap:8px"><i data-lucide="users" style="width:15px;height:15px;color:var(--cyan)"></i> User Management</h3>
        <div id="users-table" style="margin:12px 0;min-height:40px;font-size:12px">Loading...</div>
        <button class="btn btn-primary" onclick="openAddUserModal()" style="margin-top:4px"><i data-lucide="user-plus" style="width:12px;height:12px;vertical-align:-1px"></i> Add User</button>
      </div>

      <!-- Active Directory LDAP (admin only) -->
      <div class="settings-card" id="ldap-card" style="display:none">
        <h3 style="display:flex;align-items:center;gap:8px"><i data-lucide="building-2" style="width:15px;height:15px;color:var(--cyan)"></i> Active Directory (LDAP)</h3>
        <p style="font-size:11px;color:var(--muted);margin-bottom:12px">Authenticate users via Microsoft AD. Local admin always works as fallback.</p>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
          <label style="font-size:12px;color:var(--text)">Enable LDAP</label>
          <label class="toggle-switch"><input type="checkbox" id="ldap-enabled"><span class="toggle-slider"></span></label>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="ef" style="margin:0"><label>AD Server Host</label><input type="text" id="ldap-host" placeholder="dc01.tabadul.iq"></div>
          <div class="ef" style="margin:0"><label>Port</label><input type="number" id="ldap-port" value="389" min="1" max="65535"></div>
        </div>
        <div class="ef"><label>Base DN</label><input type="text" id="ldap-base-dn" placeholder="DC=tabadul,DC=iq"></div>
        <div class="ef"><label>Bind DN (service account)</label><input type="text" id="ldap-bind-dn" placeholder="CN=noc-svc,OU=Service,DC=tabadul,DC=iq"></div>
        <div class="ef"><label>Bind Password</label><input type="password" id="ldap-bind-pass" placeholder="Service account password"></div>
        <div class="ef"><label>User Filter</label><input type="text" id="ldap-user-filter" placeholder="(&(objectClass=user)(sAMAccountName=%s))"></div>
        <div class="ef"><label>Admin Group DN (optional)</label><input type="text" id="ldap-admin-group" placeholder="CN=NOC-Admins,OU=Groups,DC=tabadul,DC=iq"></div>
        <div class="ef"><label>Operator Group DN (optional)</label><input type="text" id="ldap-operator-group" placeholder="CN=NOC-Operators,OU=Groups,DC=tabadul,DC=iq"></div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
          <label style="font-size:12px;color:var(--text)">Use TLS (STARTTLS)</label>
          <label class="toggle-switch"><input type="checkbox" id="ldap-use-tls"><span class="toggle-slider"></span></label>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <button class="btn btn-ghost" onclick="testLdap()"><i data-lucide="plug" style="width:12px;height:12px;vertical-align:-1px"></i> Test Connection</button>
          <button class="btn btn-primary" onclick="saveLdap()">Save LDAP Config</button>
        </div>
        <div id="ldap-result" style="margin-top:10px;font-size:11px;font-family:'JetBrains Mono',monospace"></div>
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

<!-- ══ MAP ALARMS PANEL ══ -->
<div id="map-alarm-panel" style="position:fixed;bottom:0;left:var(--sidebar);width:340px;background:var(--surface);border:1px solid var(--border2);border-bottom:none;border-radius:12px 12px 0 0;box-shadow:0 -6px 30px rgba(0,0,0,0.45);z-index:150;user-select:none">
  <div onclick="toggleMapAlarmPanel()" style="display:flex;align-items:center;gap:8px;padding:9px 14px;cursor:pointer;border-bottom:1px solid transparent;transition:border-color 0.2s" id="map-alarm-header">
    <i data-lucide="bell-ring" style="width:14px;height:14px;color:var(--yellow);flex-shrink:0"></i>
    <span style="font-size:12px;font-weight:600;flex:1;color:#fff">Map Alarms</span>
    <span id="map-alarm-count" class="nav-badge hidden" style="margin-left:0">0</span>
    <i data-lucide="chevron-up" id="map-alarm-chevron" style="width:13px;height:13px;color:var(--muted);transition:transform 0.2s;flex-shrink:0"></i>
  </div>
  <div id="map-alarm-body" style="display:none;max-height:280px;overflow-y:auto">
    <div id="map-alarm-list" style="padding:4px 0"></div>
  </div>
</div>

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

<!-- ══ ADD USER MODAL ══ -->
<div class="modal-overlay" id="add-user-modal">
  <div class="modal-card" style="width:400px;max-width:96vw">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3 style="margin:0;display:flex;align-items:center;gap:8px"><i data-lucide="user-plus" style="width:16px;height:16px;color:var(--cyan)"></i> Add User</h3>
      <button onclick="document.getElementById('add-user-modal').classList.remove('open')" style="background:none;border:none;color:var(--muted);font-size:18px;cursor:pointer">✕</button>
    </div>
    <div class="ef"><label>Username *</label><input type="text" id="nu-username" placeholder="john.doe" autocomplete="off"></div>
    <div class="ef"><label>Display Name</label><input type="text" id="nu-display" placeholder="John Doe"></div>
    <div class="ef"><label>Email</label><input type="email" id="nu-email" placeholder="john@tabadul.iq"></div>
    <div class="ef">
      <label>Role *</label>
      <select id="nu-role" style="background:var(--surface2);border:1px solid var(--border);color:#fff;border-radius:6px;padding:8px 10px">
        <option value="viewer">Viewer — Read only</option>
        <option value="operator">Operator — Edit nodes, ack alarms</option>
        <option value="admin">Admin — Full access</option>
      </select>
    </div>
    <div class="ef"><label>Password *</label><input type="password" id="nu-pass" placeholder="Min 4 characters" autocomplete="new-password"></div>
    <div id="nu-result" style="font-size:11px;font-family:'JetBrains Mono',monospace;min-height:16px;margin-bottom:8px"></div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button class="btn btn-ghost" onclick="document.getElementById('add-user-modal').classList.remove('open')">Cancel</button>
      <button class="btn btn-primary" onclick="doAddUser()"><i data-lucide="check" style="width:12px;height:12px;vertical-align:-1px"></i> Create User</button>
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

<!-- ══ DISCOVER MODAL ══ -->
<div class="modal-overlay" id="discover-modal" style="display:none">
  <div class="modal-card" style="width:640px;max-width:96vw;max-height:88vh;display:flex;flex-direction:column">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-shrink:0">
      <h3 style="margin:0;font-size:15px;font-weight:700;color:#fff">
        <i data-lucide="radar" style="width:15px;height:15px;vertical-align:-2px;color:var(--cyan)"></i>
        Zabbix Topology Auto-Discovery
      </h3>
      <button class="dp-close" onclick="closeDiscoverModal()">×</button>
    </div>

    <!-- Step 1: Scan & Select -->
    <div id="disc-step1" style="flex:1;overflow:hidden;display:flex;flex-direction:column">
      <div class="ef" style="flex-shrink:0">
        <label>Map Name</label>
        <input class="edit-inp" id="disc-name" value="Auto-Discovered Map" />
      </div>
      <div id="disc-scan-state" style="text-align:center;padding:30px 0;color:var(--muted);font-size:12px">
        Click Scan to discover hosts from Zabbix
      </div>
      <div id="disc-subnet-list" style="flex:1;overflow-y:auto;display:none"></div>
      <div style="flex-shrink:0;margin-top:12px;display:flex;gap:8px;align-items:center">
        <button class="btn btn-ghost" id="disc-scan-btn" onclick="doDiscoverScan()">
          <i data-lucide="search" style="width:12px;height:12px;vertical-align:-1px"></i> Scan Zabbix
        </button>
        <span id="disc-scan-summary" style="font-size:11px;color:var(--muted)"></span>
        <button class="btn btn-primary" id="disc-create-btn" style="margin-left:auto;display:none" onclick="doDiscoverCreate()">
          Create Map →
        </button>
      </div>
    </div>

    <!-- Step 2: Done -->
    <div id="disc-step2" style="display:none;text-align:center;padding:40px 0">
      <div id="disc-done-msg" style="font-size:14px;color:var(--green);margin-bottom:12px"></div>
      <button class="btn btn-primary" onclick="closeDiscoverModal()">Done</button>
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

function makeIcon(type){
  const sz=48;
  const icons={
    external:['#f1c40f','M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z'],
    wan:['#00bcd4','M1 9l2 2c4.97-4.97 13.03-4.97 18 0l2-2C16.93 2.93 7.08 2.93 1 9zm8 8l3 3 3-3c-1.65-1.66-4.34-1.66-6 0zm-4-4l2 2c2.76-2.76 7.24-2.76 10 0l2-2C15.14 9.14 8.87 9.14 5 13z'],
    switch:['#3498db','M6.99 11L3 15l3.99 4v-3H14v-2H6.99v-3zm7.02-2L18 5l-3.99-4v3H10v2h7.01v3z'],
    firewall:['#e74c3c','M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 4.99L18 8.74V11c0 3.61-2.43 7.01-6 8.06C8.43 18.01 6 14.61 6 11V8.74l6-2.75z'],
    palo:['#e67e22','M13.5.67s.74 2.65.74 4.8c0 2.06-1.35 3.73-3.41 3.73-2.07 0-3.63-1.67-3.63-3.73l.03-.36C5.21 7.51 4 10.62 4 14c0 4.42 3.58 8 8 8s8-3.58 8-8C20 8.61 17.41 3.8 13.5.67zM11.71 19c-1.78 0-3.22-1.4-3.22-3.14 0-1.62 1.05-2.76 2.81-3.12 1.77-.36 3.6-1.21 4.62-2.58.39 1.29.59 2.65.59 4.04 0 2.65-2.15 4.8-4.8 4.8z'],
    f5:['#2ecc71','M6 2v3H3l4.5 4.5L12 5H9V2H6zm12 17v-3h3l-4.5-4.5L12 16h3v3h3zM3.51 6.51L2 8l10 10 1.49-1.49L3.51 6.51zM22 8l-1.49-1.49-4.24 4.24 1.49 1.49L22 8z'],
    server:['#1abc9c','M20 2H4c-1.1 0-2 .9-2 2v4c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 6H4V4h16v4zM4 14h16c1.1 0 2-.9 2-2v-.5H2V12c0 1.1.9 2 2 2zm0 6h16c1.1 0 2-.9 2-2v-.5H2V18c0 1.1.9 2 2 2zM6 5.5h2v1H6zm0 6h2v1H6zm0 6h2v1H6z'],
    dbserver:['#e91e63','M12 3C7.58 3 4 4.79 4 7v10c0 2.21 3.59 4 8 4s8-1.79 8-4V7c0-2.21-3.58-4-8-4zm6 14c0 .5-2.13 2-6 2s-6-1.5-6-2v-2.23c1.61.78 3.72 1.23 6 1.23s4.39-.45 6-1.23V17zm0-5c0 .5-2.13 2-6 2s-6-1.5-6-2v-2.23C7.61 10.55 9.72 11 12 11s4.39-.45 6-1.23V12zm-6-3C8.13 9 6 7.5 6 7s2.13-2 6-2 6 1.5 6 2-2.13 2-6 2z'],
    hsm:['#9b59b6','M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z'],
    infra:['#78909c','M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z'],
  };
  const [color,path]=icons[type]||icons.infra;
  const svg=`<svg xmlns="http://www.w3.org/2000/svg" width="${sz}" height="${sz}" viewBox="0 0 ${sz} ${sz}"><rect width="${sz}" height="${sz}" rx="10" fill="${color}" fill-opacity="0.2"/><rect x="1.5" y="1.5" width="${sz-3}" height="${sz-3}" rx="8.5" fill="none" stroke="${color}" stroke-width="1.5" opacity="0.6"/><g transform="translate(12,12)" fill="${color}"><path d="${path}"/></g></svg>`;
  return 'data:image/svg+xml;base64,'+btoa(unescape(encodeURIComponent(svg)));
}
const ICONS={external:makeIcon('external'),wan:makeIcon('wan'),switch:makeIcon('switch'),firewall:makeIcon('firewall'),palo:makeIcon('palo'),f5:makeIcon('f5'),server:makeIcon('server'),dbserver:makeIcon('dbserver'),hsm:makeIcon('hsm'),infra:makeIcon('infra')};
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
  myRole: 'admin',       // current user role: admin | operator | viewer
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
    size:extra.size||30,font:{color:c.font,size:extra.fontSize||11,bold:true,face:'Space Grotesk',multi:true},
    color:{border:c.border,background:'transparent',highlight:{border:'#00d4ff',background:'rgba(0,212,255,0.1)'},hover:{border:'#00d4ff'}},
    borderWidth:2,borderWidthSelected:3,
    shadow:{enabled:true,color:c.border+'44',size:14,x:0,y:2},
    _type:type,...extra};
}
function mkEdge(f,t,col,o={}){return{from:f,to:t,color:{color:col,opacity:0.85,highlight:'#00d4ff',hover:'#00d4ff'},_origColor:col,...o}}
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

// ── Snapshot default map so we can restore it when switching back to id=0
const _defaultNodes = visNodes.get();
const _defaultEdges = visEdges.get();
const _defaultDeviceData = JSON.parse(JSON.stringify(deviceData));

// ═══════════════════════════════════════════════════════════
//  POS MAP — Built-in combined map (Asia + Zain + Passport)
// ═══════════════════════════════════════════════════════════
const _posmapDeviceData = {
  'pos-asia-wan':     {name:'Asia Link P2P',    role:'WAN / P2P Link — Asia ISP',       ip:'172.22.223.130', type:'wan',      status:'ok', ifaces:['Gig 1/0/7 → InternetSwitch'],              info:{Tunnel:'T7-T2AsiaPri','FGT-WAN-IP':'172.22.223.129',Type:'P2P Leased Line'}},
  'pos-zain-wan':     {name:'Zain Link P2P',    role:'WAN / P2P Link — Zain ISP',        ip:'10.106.23.193',  type:'wan',      status:'ok', ifaces:['Gi1/0/28 → InternetSwitch'],               info:{Tunnel:'T10-T2Zain',  'FGT-WAN-IP':'10.106.23.194', Type:'P2P Leased Line'}},
  'pos-passport-wan': {name:'Passport Link P2P',role:'WAN / P2P Link — Passport ISP',   ip:'172.17.252.9',   type:'wan',      status:'ok', ifaces:['Te1/1/3 → InternetSwitch'],                info:{Tunnel:'T2-SSTTa',    'FGT-WAN-IP':'172.17.252.14', Type:'P2P Leased Line'}},
  'pos-inet-sw':      {name:'Internet Switch',  role:'L3 Edge Switching',                ip:'10.1.0.15',      type:'switch',   status:'ok', ifaces:['Gig 1/0/7 ← Asia','Gi1/0/28 ← Zain','Te1/1/3 ← Passport','Po10 → CoreSwitch'], info:{Model:'Cisco Switch'}},
  'pos-core-sw':      {name:'Core Switch',      role:'L3 Core Distribution',             ip:'10.1.0.5',       type:'switch',   status:'ok', ifaces:['Po10 ← InternetSwitch','Po12 → FGT'],       info:{Model:'Cisco CoreSwitch',VLAN:'POS'}},
  'pos-fgt':          {name:'FGT Edge Firewall',role:'Next-Gen Firewall (Multi-WAN)',    ip:'10.1.0.1',       type:'firewall', status:'ok', ifaces:['UPLINK/Po10 ← CoreSwitch','T7-T2AsiaPri (172.22.223.129)','T10-T2Zain (10.106.23.194)','T2-SSTTa (172.17.252.14)','Port 3 → FTD'], info:{Model:'FortiGate',Tunnels:'Asia / Zain / Passport',Mode:'Multi-WAN'}},
  'pos-ftd':          {name:'FTD',              role:'Firepower Threat Defense IPS',    ip:'100.65.0.241',   type:'firewall', status:'ok', ifaces:['port 3 ← FGT','Eth1/47 → TOR1','vlan20'],     info:{Model:'Cisco FTD',VLAN:'20'}},
  'pos-tor1':         {name:'TOR 1',            role:'Top-of-Rack Switch',              ip:'10.1.0.7',       type:'switch',   status:'ok', ifaces:['Eth1/47 ← FTD','Eth1/37 → TOR-LAN1','Eth1/40 → F5','Po20 → SVLP / SVFE'], info:{Model:'Cisco Nexus'}},
  'pos-tor-lan1':     {name:'TOR-LAN 1',        role:'TOR LAN Access Switch',           ip:'10.1.0.81',      type:'switch',   status:'ok', ifaces:['Te1/1/1 ← TOR1','→ AG1000'],                 info:{Model:'Cisco Catalyst'}},
  'pos-ag1000':       {name:'AG1000',           role:'Aggregation Router',              ip:'10.65.0.149',    type:'switch',   status:'ok', ifaces:['← TOR-LAN1'],                                info:{Model:'AG1000'}},
  'pos-svlp':         {name:'SVLP',             role:'POS Application Server',          ip:'100.66.0.3',     type:'server',   status:'ok', ifaces:['Po20 ← TOR1'],                               info:{Role:'SVLP'}},
  'pos-f5':           {name:'F5',               role:'Load Balancer',                   ip:'10.1.0.11',      type:'f5',       status:'ok', ifaces:['Eth1/40 ← TOR1'],                            info:{Model:'F5 BIG-IP'}},
  'pos-svfe':         {name:'SVFE',             role:'POS Frontend Application Server', ip:'100.66.0.6',     type:'server',   status:'ok', ifaces:['Po20 ← TOR1'],                               info:{Role:'SVFE'}},
};

function buildPosmapNodes(){
  return [
    // ── WAN / ISP layer (left, spread vertically)
    mkNode('pos-asia-wan',    'Asia Link P2P\n172.22.223.130',   'wan',      -750, -180, {size:24, fontSize:10}),
    mkNode('pos-zain-wan',    'Zain Link P2P\n10.106.23.193',    'wan',      -750,    0, {size:24, fontSize:10}),
    mkNode('pos-passport-wan','Passport Link P2P\n172.17.252.9', 'wan',      -750,  180, {size:24, fontSize:10}),
    // ── Internet Switch
    mkNode('pos-inet-sw',     'Internet Switch\n10.1.0.15',      'switch',   -480,    0, {size:32, fontSize:12}),
    // ── Core Switch
    mkNode('pos-core-sw',     'Core Switch\n10.1.0.5',           'switch',   -220,    0, {size:32, fontSize:12}),
    // ── FortiGate (multi-WAN)
    mkNode('pos-fgt',         'FGT Edge FW\n10.1.0.1',           'firewall',   50,    0, {size:30, fontSize:11}),
    // ── FTD
    mkNode('pos-ftd',         'FTD\n100.65.0.241',               'firewall',  300,    0, {size:28, fontSize:11}),
    // ── TOR switches
    mkNode('pos-tor1',        'TOR 1\n10.1.0.7',                 'switch',    560, -120, {size:28, fontSize:11}),
    mkNode('pos-tor-lan1',    'TOR-LAN 1\n10.1.0.81',            'switch',    560,  120, {size:28, fontSize:11}),
    // ── End devices
    mkNode('pos-svlp',        'SVLP\n100.66.0.3',                'server',    820, -220, {size:22, fontSize:10}),
    mkNode('pos-f5',          'F5\n10.1.0.11',                   'f5',        820,  -80, {size:22, fontSize:10}),
    mkNode('pos-svfe',        'SVFE\n100.66.0.6',                'server',    820,   80, {size:22, fontSize:10}),
    mkNode('pos-ag1000',      'AG1000\n10.65.0.149',             'switch',    820,  220, {size:22, fontSize:10}),
  ];
}

function buildPosmapEdges(){
  return [
    // ── ISP → InternetSwitch
    mkEdge('pos-asia-wan',    'pos-inet-sw', '#00bcd4', {width:2, label:'Gig 1/0/7',  font:{color:'#00bcd4', size:9, background:'rgba(10,14,26,.9)'}}),
    mkEdge('pos-zain-wan',    'pos-inet-sw', '#00bcd4', {width:2, label:'Gi1/0/28',   font:{color:'#00bcd4', size:9, background:'rgba(10,14,26,.9)'}}),
    mkEdge('pos-passport-wan','pos-inet-sw', '#00bcd4', {width:2, label:'Te1/1/3',    font:{color:'#00bcd4', size:9, background:'rgba(10,14,26,.9)'}}),
    // ── InternetSwitch → CoreSwitch
    mkEdge('pos-inet-sw',  'pos-core-sw', '#3498db', {width:4, label:'Po10', font:{color:'#3498db', size:9, background:'rgba(10,14,26,.9)'}}),
    // ── CoreSwitch → FGT
    mkEdge('pos-core-sw',  'pos-fgt',     '#e74c3c', {width:3, label:'Po12 / FGT-EDG UPLINK', font:{color:'#e74c3c', size:9, background:'rgba(10,14,26,.9)'}}),
    // ── FGT → FTD
    mkEdge('pos-fgt',      'pos-ftd',     '#e74c3c', {width:3, label:'Port 3', font:{color:'#e74c3c', size:9, background:'rgba(10,14,26,.9)'}}),
    // ── FTD → TOR1
    mkEdge('pos-ftd',      'pos-tor1',    '#3498db', {width:4, label:'Eth1/47 / vlan20', font:{color:'#3498db', size:9, background:'rgba(10,14,26,.9)'}}),
    // ── TOR1 → TOR-LAN1
    mkEdge('pos-tor1',     'pos-tor-lan1','#3498db', {width:3, label:'Eth1/37 ↔ Te1/1/1', font:{color:'#3498db', size:9, background:'rgba(10,14,26,.9)'}}),
    // ── TOR-LAN1 → AG1000
    mkEdge('pos-tor-lan1', 'pos-ag1000',  '#78909c', {width:2}),
    // ── TOR1 → SVLP / F5 / SVFE
    mkEdge('pos-tor1',     'pos-svlp',    '#1abc9c', {width:2, label:'Po20',   font:{color:'#1abc9c', size:9, background:'rgba(10,14,26,.9)'}}),
    mkEdge('pos-tor1',     'pos-f5',      '#2ecc71', {width:2, label:'Eth1/40',font:{color:'#2ecc71', size:9, background:'rgba(10,14,26,.9)'}}),
    mkEdge('pos-tor1',     'pos-svfe',    '#1abc9c', {width:2, label:"Po20",   font:{color:'#1abc9c', size:9, background:'rgba(10,14,26,.9)'}}),
  ];
}

// Save positions on drag
visNetwork.on('dragEnd', p => { if(p.nodes.length) savePositions(); });

// ═══════════════════════════════════════════════════════════
//  ZABBIX STATUS — LIVE NODE COLORING + ALARM DOTS
// ═══════════════════════════════════════════════════════════
function updateMapStatus(){
  // Status badges are drawn on the traffic canvas per-frame (follows pan/zoom automatically)
  // Here we only update vis-network border/shadow for extra visual weight
  visNodes.getIds().forEach(nid=>{
    const hostId = S.nodeHostMap[nid];
    if(!hostId) return;

    const host = S.zabbixHosts[hostId];
    if(!host) return;

    const sev  = host.worst_severity || 0;
    const down = host.available == 2;
    const nodeType = deviceData[nid]?.type || S.dbNodes[nid]?.type || 'switch';
    const c    = COLORS[nodeType] || COLORS.switch;

    let border, shadow, bw=2;
    if(down)       { border='#FF1744'; shadow='#FF174455'; bw=4; }
    else if(sev>=5){ border='#FF1744'; shadow='#FF174455'; bw=3; }
    else if(sev>=4){ border='#FF6D00'; shadow='#FF6D0055'; bw=3; }
    else if(sev>=3){ border='#FFB300'; shadow='#FFB30055'; bw=2; }
    else if(sev>=1){ border='#78909C'; shadow='#78909C44'; bw=2; }
    else           { border=c.border;  shadow=c.border+'33'; bw=2; }

    visNodes.update({id:nid,
      color:{border,background:'transparent',highlight:{border:'#00d4ff'}},
      shadow:{enabled:true,color:shadow,size:down?28:sev>=4?20:10,x:0,y:0},
      borderWidth:bw});
  });
  updateEdgeStatus();
}

// ── EDGE LINK-DOWN STATUS ────────────────────────────────
function updateEdgeStatus(){
  visEdges.getIds().forEach(eid=>{
    const e = visEdges.get(eid);
    const fromHost = S.zabbixHosts[S.nodeHostMap[e.from]];
    const toHost   = S.zabbixHosts[S.nodeHostMap[e.to]];
    if(!fromHost && !toHost) return; // neither end mapped, skip
    const down   = (fromHost?.available==2) || (toHost?.available==2);
    const maxSev = Math.max(fromHost?.worst_severity||0, toHost?.worst_severity||0);
    if(down){
      visEdges.update({id:eid,color:{color:'#FF1744',opacity:0.9},dashes:[6,4],width:2});
    } else if(maxSev>=4){
      visEdges.update({id:eid,color:{color:'#FF6D00',opacity:0.85},dashes:[4,3],width:1.5});
    } else if(maxSev>=3){
      visEdges.update({id:eid,color:{color:'#FFB300',opacity:0.7},dashes:false,width:1});
    } else {
      // restore original color
      visEdges.update({id:eid,color:{color:e._origColor||'#1e3a5f',opacity:0.85},dashes:false,width:1});
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
    const mapIds = [...S.currentMapHostIds];
    const qs = mapIds.length ? '&' + mapIds.map(h=>'hostids[]='+encodeURIComponent(h)).join('&') : '';
    const data = await api('api/zabbix.php?action=status'+qs);
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

    // Update map colors + edge status
    updateMapStatus();

    // Update floating map alarm panel
    renderMapAlarmPanel();

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
    ${hostId?`<div class="info-section" id="dp-perf-section">
      <div class="info-sec-title">Performance (1h)</div>
      <div id="dp-graphs" style="display:flex;flex-direction:column;gap:8px;margin-top:6px">
        <div style="color:var(--muted);font-size:11px;text-align:center;padding:8px 0">Loading…</div>
      </div>
    </div>`:''}
  `;

  if(hostId) loadPanelGraphs(hostId);

  document.getElementById('dp-footer').style.display='flex';
  const canEdit=S.myRole!=='viewer';
  const canDelete=S.myRole==='admin';
  document.getElementById('dp-footer').innerHTML=`
    ${canEdit?`<button class="btn btn-ghost" style="flex:1" onclick="showEditPanel('${nid}')"><i data-lucide="pencil" style="width:12px;height:12px;vertical-align:-1px"></i> Edit</button>`:''}
    <button class="btn btn-primary" style="flex:1;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-color:transparent" onclick="openHostChat('${nid}')"><i data-lucide="bot" style="width:12px;height:12px;vertical-align:-1px"></i> Ask AI</button>
    ${canDelete?`<button class="btn btn-danger" onclick="deleteNode('${nid}')"><i data-lucide="trash-2" style="width:12px;height:12px"></i></button>`:''}
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
// Default node → Zabbix host mappings (verified against zabbix.tabadul.iq)
const DEFAULT_NODE_HOST_MAP = {
  'inet-sw':   '10785',  // INT-SW            10.1.0.15
  'core-sw':   '10929',  // CoreSwitch        10.1.0.5
  'pmt-fw1':   '10907',  // Fortigate FW      10.1.0.1
  'pmt-fw2':   '11108',  // Fortigate FW 2    10.1.0.202
  'fp1':       '10839',  // FTD-1 (Firepower) 10.1.0.4
  'fp2':       '10840',  // FTD-2             10.1.0.6
  'tor1':      '10898',  // Tor-1             10.1.0.7
  'tor2':      '10899',  // Tor-2             10.1.0.8
  'palo1':     '10832',  // PA-1              10.1.0.13
  'palo2':     '10831',  // PA-2              10.1.0.14
  'f5-1':      '10830',  // F5-1              10.1.0.11
  'f5-2':      '10829',  // F5-2              10.1.0.12
  'hsm-auth1': '10875',  // HSM-Auth-1        100.66.0.122
  'hsm-auth2': '10876',  // HSM-Auth-2        100.66.0.123
  'hsm-acs1':  '10877',  // HSM-ACS-1         100.66.0.124
  'hsm-acs2':  '10878',  // HSM-ACS-2         100.66.0.125
  'ag1000':    '10871',  // AG-01 (AG1000)    100.64.2.68
  'olvm':      '10776',  // MGM - OLVM        10.1.0.25
  'scopesky':  '10906',  // ScopeSky-Public   204.106.240.53
  'dr':        '10900',  // DR-Monitor        100.127.40.2
  'veeam':     '10841',  // ITP-e01-veeam     100.65.0.247
  'hv-prod':   '10778',  // MGM-HVN-PROD01    10.1.0.21
};

function loadNodeHostMap(){
  const raw=localStorage.getItem('tab_nhm');
  const stored = raw ? JSON.parse(raw) : {};
  // Merge: defaults first, then stored (stored values override defaults)
  S.nodeHostMap = {...DEFAULT_NODE_HOST_MAP, ...stored};
  // Persist merged result so future saves include defaults
  localStorage.setItem('tab_nhm', JSON.stringify(S.nodeHostMap));
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
  const mapIds=[...S.currentMapHostIds];
  mapIds.forEach(h=>params.push('hostids[]='+encodeURIComponent(h)));
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
  // Show admin-only sections if admin
  if(S.myRole==='admin'){
    document.getElementById('user-mgmt-card').style.display='block';
    document.getElementById('ldap-card').style.display='block';
    loadUsersTable();
    loadLdapConfig();
    lucide.createIcons();
  }
}

// ── USER MANAGEMENT ────────────────────────────────────────
async function loadUsersTable(){
  const users=await api('api/users.php')||[];
  const el=document.getElementById('users-table');
  if(!users.length){el.innerHTML='<div style="color:var(--muted);font-size:12px">No users found.</div>';return;}
  el.innerHTML=users.map(u=>`
    <div class="user-row">
      <div style="width:30px;height:30px;border-radius:8px;background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:13px">
        ${u.display_name?u.display_name[0].toUpperCase():u.username[0].toUpperCase()}
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-size:12px;font-weight:600;color:#fff">${u.display_name||u.username} ${u.is_ldap?'<span style="font-size:9px;color:var(--cyan);font-family:JetBrains Mono,monospace">[AD]</span>':''}</div>
        <div style="font-size:10px;color:var(--muted);font-family:JetBrains Mono,monospace">${u.username}${u.email?' · '+u.email:''}</div>
      </div>
      <span class="role-badge ${u.role}">${u.role}</span>
      <div style="font-size:9px;color:var(--muted);font-family:JetBrains Mono,monospace;text-align:right;flex-shrink:0">
        ${u.last_login?new Date(u.last_login).toLocaleDateString():'Never'}
      </div>
      <button onclick="editUserRole(${u.id},'${u.username}','${u.role}')" style="background:none;border:none;color:var(--muted);cursor:pointer;padding:4px" title="Edit"><i data-lucide="pencil" style="width:12px;height:12px"></i></button>
      <button onclick="deleteUser(${u.id},'${u.username}')" style="background:none;border:none;color:var(--muted);cursor:pointer;padding:4px" title="Delete"><i data-lucide="trash-2" style="width:12px;height:12px"></i></button>
    </div>`).join('');
  lucide.createIcons();
}

function openAddUserModal(){
  ['nu-username','nu-display','nu-email','nu-pass'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('nu-result').innerHTML='';
  document.getElementById('add-user-modal').classList.add('open');
  setTimeout(()=>document.getElementById('nu-username').focus(),100);
}

async function doAddUser(){
  const username=document.getElementById('nu-username').value.trim();
  const display=document.getElementById('nu-display').value.trim();
  const email=document.getElementById('nu-email').value.trim();
  const role=document.getElementById('nu-role').value;
  const pass=document.getElementById('nu-pass').value;
  const res=document.getElementById('nu-result');
  if(!username||!pass){res.innerHTML='<span style="color:var(--red)">Username and password required</span>';return;}
  const r=await api('api/users.php?action=create',{method:'POST',body:JSON.stringify({username,display_name:display,email,role,password:pass})});
  if(r?.ok){
    res.innerHTML='<span style="color:var(--green)">✓ User created</span>';
    setTimeout(()=>{document.getElementById('add-user-modal').classList.remove('open');loadUsersTable();},800);
  } else {
    res.innerHTML=`<span style="color:var(--red)">✗ ${r?.error||'Error'}</span>`;
  }
}

async function editUserRole(id,username,currentRole){
  const roles=['viewer','operator','admin'];
  const newRole=prompt(`Change role for "${username}".\nCurrent: ${currentRole}\nEnter new role (admin/operator/viewer):`);
  if(!newRole||!roles.includes(newRole)||newRole===currentRole) return;
  const r=await api('api/users.php?action=update',{method:'POST',body:JSON.stringify({id,role:newRole})});
  if(r?.ok) loadUsersTable();
  else alert('Error: '+(r?.error||'Failed'));
}

async function deleteUser(id,username){
  if(!confirm(`Delete user "${username}"? This cannot be undone.`)) return;
  const r=await api('api/users.php?id='+id,{method:'DELETE'});
  if(r?.ok) loadUsersTable();
  else alert('Error: '+(r?.error||'Failed'));
}

// ── LDAP / AD ──────────────────────────────────────────────
async function loadLdapConfig(){
  const cfg=await api('api/users.php?action=ldap_config');
  if(!cfg) return;
  document.getElementById('ldap-enabled').checked=cfg.enabled==1;
  document.getElementById('ldap-host').value=cfg.host||'';
  document.getElementById('ldap-port').value=cfg.port||389;
  document.getElementById('ldap-base-dn').value=cfg.base_dn||'';
  document.getElementById('ldap-bind-dn').value=cfg.bind_dn||'';
  document.getElementById('ldap-bind-pass').value=cfg.bind_pass_masked||'';
  document.getElementById('ldap-user-filter').value=cfg.user_filter||'(&(objectClass=user)(sAMAccountName=%s))';
  document.getElementById('ldap-admin-group').value=cfg.admin_group||'';
  document.getElementById('ldap-operator-group').value=cfg.operator_group||'';
  document.getElementById('ldap-use-tls').checked=cfg.use_tls==1;
}

async function saveLdap(){
  const payload={
    host: document.getElementById('ldap-host').value.trim(),
    port: parseInt(document.getElementById('ldap-port').value)||389,
    base_dn: document.getElementById('ldap-base-dn').value.trim(),
    bind_dn: document.getElementById('ldap-bind-dn').value.trim(),
    bind_pass: document.getElementById('ldap-bind-pass').value,
    user_filter: document.getElementById('ldap-user-filter').value.trim(),
    admin_group: document.getElementById('ldap-admin-group').value.trim(),
    operator_group: document.getElementById('ldap-operator-group').value.trim(),
    use_tls: document.getElementById('ldap-use-tls').checked?1:0,
    enabled: document.getElementById('ldap-enabled').checked?1:0,
  };
  const r=await api('api/users.php?action=ldap_save',{method:'POST',body:JSON.stringify(payload)});
  const res=document.getElementById('ldap-result');
  if(r?.ok) res.innerHTML='<span style="color:var(--green)">✓ LDAP config saved</span>';
  else res.innerHTML=`<span style="color:var(--red)">✗ ${r?.error||'Error'}</span>`;
}

async function testLdap(){
  const res=document.getElementById('ldap-result');
  res.innerHTML='<span style="color:var(--muted)">Testing connection...</span>';
  const payload={
    host: document.getElementById('ldap-host').value.trim(),
    port: parseInt(document.getElementById('ldap-port').value)||389,
    base_dn: document.getElementById('ldap-base-dn').value.trim(),
    bind_dn: document.getElementById('ldap-bind-dn').value.trim(),
    bind_pass: document.getElementById('ldap-bind-pass').value,
    use_tls: document.getElementById('ldap-use-tls').checked?1:0,
  };
  const r=await api('api/users.php?action=ldap_test',{method:'POST',body:JSON.stringify(payload)});
  if(r?.ok) res.innerHTML=`<span style="color:var(--green)">✓ ${r.message}</span>`;
  else res.innerHTML=`<span style="color:var(--red)">✗ ${r?.error||'Connection failed'}</span>`;
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
  const posmapItem=`<div class="map-list-item${S.currentMapId===-1?' active-map':''}" data-mapid="-1" onclick="switchMap(-1,'POS MAP')"><span>🗺️</span><span>POS MAP</span></div>`;
  const imported=layouts.map(l=>`
    <div class="map-list-item${S.currentMapId==l.id?' active-map':''}" data-mapid="${l.id}" onclick="switchMap(${l.id},'${l.name.replace(/'/g,"\\'")}')">
      <span>🗺</span><span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${l.name}</span>
      <span class="map-del" onclick="deleteMap(event,${l.id})">✕</span>
    </div>`).join('');
  container.innerHTML=defaultItem+posmapItem+imported;
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
    // Restore default map nodes/edges/deviceData from startup snapshot
    visNodes.clear(); visEdges.clear();
    visNodes.add(_defaultNodes);
    visEdges.add(_defaultEdges);
    Object.keys(deviceData).forEach(k=>delete deviceData[k]);
    Object.assign(deviceData, JSON.parse(JSON.stringify(_defaultDeviceData)));
    S.nodeHostMap={...DEFAULT_NODE_HOST_MAP};
    S.currentMapHostIds=new Set(Object.values(S.nodeHostMap).filter(Boolean));
    saveNodeHostMap();
    navigate('map');
    document.getElementById('page-title').textContent='Network Map';
    setTimeout(()=>visNetwork.fit({animation:{duration:500}}),100);
    updateMapStatus();
    renderHosts();
    renderAlarms();
    return;
  }
  if(id===-1){
    // Load built-in POS MAP (Asia + Zain + Passport combined)
    visNodes.clear(); visEdges.clear();
    visNodes.add(buildPosmapNodes());
    visEdges.add(buildPosmapEdges());
    Object.keys(deviceData).forEach(k=>delete deviceData[k]);
    Object.assign(deviceData, JSON.parse(JSON.stringify(_posmapDeviceData)));
    S.nodeHostMap={};
    S.currentMapHostIds=new Set();
    saveNodeHostMap();
    navigate('map');
    document.getElementById('page-title').textContent='POS MAP';
    setTimeout(()=>visNetwork.fit({animation:{duration:600}}),100);
    updateMapStatus();
    renderHosts();
    renderAlarms();
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
  // Load edges stored in positions.edges (auto-discovered maps: star topology per subnet)
  (layout.positions?.edges||[]).forEach(e=>{
    visEdges.add(mkEdge(e.from, e.to, '#1e3a5f'));
  });
  S.nodeHostMap=newMap;
  S.currentMapHostIds=new Set(Object.values(newMap).filter(Boolean));
  saveNodeHostMap();
  navigate('map');
  document.getElementById('page-title').textContent=name;
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
//  MAP ALARM PANEL
// ═══════════════════════════════════════════════════════════
const MAP_ALARM = { open: false };
function toggleMapAlarmPanel(){
  MAP_ALARM.open = !MAP_ALARM.open;
  const body = document.getElementById('map-alarm-body');
  const chev = document.getElementById('map-alarm-chevron');
  const hdr  = document.getElementById('map-alarm-header');
  body.style.display = MAP_ALARM.open ? 'block' : 'none';
  chev.style.transform = MAP_ALARM.open ? 'rotate(180deg)' : '';
  hdr.style.borderBottomColor = MAP_ALARM.open ? 'var(--border)' : 'transparent';
}

const SEV_COLORS=['#9E9E9E','#00B0FF','#78909C','#FFB300','#FF6D00','#FF1744'];
const SEV_ICONS =['⚪','🔵','🔵','🟡','🔴','💀'];
function renderMapAlarmPanel(){
  let probs = [...(S.allProblems||[])];
  if(S.currentMapHostIds.size>0)
    probs = probs.filter(p=>(p.host_ids||[]).some(id=>S.currentMapHostIds.has(id)));
  probs.sort((a,b)=>(b.severity||0)-(a.severity||0));

  const cnt = document.getElementById('map-alarm-count');
  cnt.textContent = probs.length;
  cnt.classList.toggle('hidden', probs.length===0);
  // Badge color: red if any sev>=4, orange if sev>=3, else default
  const worst = probs.length ? (probs[0].severity||0) : 0;
  cnt.style.background = worst>=4?'var(--red)':worst>=3?'var(--orange)':'var(--red)';

  const list = document.getElementById('map-alarm-list');
  if(!probs.length){
    list.innerHTML='<div style="padding:16px 14px;font-size:11px;color:var(--muted);font-family:\'JetBrains Mono\',monospace;text-align:center">✓ No active alarms on this map</div>';
    return;
  }
  // Find host name for each problem
  const hostNameMap={};
  Object.values(S.zabbixHosts).forEach(h=>{hostNameMap[h.hostid]=h.name||h.host;});
  list.innerHTML = probs.slice(0,30).map(p=>{
    const sev=(p.severity||0);
    const col=SEV_COLORS[sev]||'#9E9E9E';
    const hids=(p.host_ids||[]);
    const hn=hids.map(id=>hostNameMap[id]).filter(Boolean).join(', ')||'Unknown';
    const ago=timeDiff(p.clock||0);
    const ack=p.acknowledged=='1';
    // Find a nodeId to highlight
    const hostId=hids[0]||'';
    return `<div class="map-alarm-row" onclick="highlightHostOnMap('${hostId}')" style="border-left:3px solid ${col}">
      <div style="width:6px;height:6px;border-radius:50%;background:${col};flex-shrink:0;box-shadow:0 0 4px ${col}"></div>
      <div style="flex:1;min-width:0">
        <div style="font-size:10px;color:${col};font-weight:600;font-family:'JetBrains Mono',monospace;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${hn}</div>
        <div style="font-size:11px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${p.name||'Unknown problem'}</div>
      </div>
      <div style="font-size:9px;color:var(--muted);font-family:'JetBrains Mono',monospace;flex-shrink:0;text-align:right">
        ${ago}<br>${ack?'<span style="color:var(--green)">ACK</span>':'<span style="color:var(--orange)">UNACK</span>'}
      </div>
    </div>`;
  }).join('');
}

// ═══════════════════════════════════════════════════════════
//  TRAFFIC ANIMATION
// ═══════════════════════════════════════════════════════════
const TA = {
  data:         {},       // { hostId: { in: bps, out: bps } }
  packets:      [],       // [ { edgeId, progress, speed, color, dir } ]
  animFrame:    null,
  enabled:      true,     // controls packet drawing; canvas always runs for status badges
  lastFetch:    0,
  fetchInterval:60000,    // re-fetch every 60s
  canvas:       null,
  ctx:          null,
};

function formatBps(bps){
  if(bps>=1e9) return (bps/1e9).toFixed(1)+'Gbps';
  if(bps>=1e6) return (bps/1e6).toFixed(1)+'Mbps';
  if(bps>=1e3) return (bps/1e3).toFixed(1)+'Kbps';
  return Math.round(bps)+'bps';
}

function initTrafficCanvas(){
  TA.canvas = document.getElementById('traffic-canvas');
  TA.ctx    = TA.canvas ? TA.canvas.getContext('2d') : null;
  resizeTrafficCanvas();
  // Start canvas loop (handles packets + status badges every frame)
  if(!TA.animFrame) TA.animFrame = requestAnimationFrame(animateTraffic);
  // Spawn idle packets after vis-network has drawn its initial frame
  // Use both the stabilized event AND a fallback timeout for reliability
  let spawned = false;
  const doSpawn = ()=>{ if(!spawned){ spawned=true; spawnPacketsAll(); } };
  visNetwork.once('stabilized', ()=>{ setTimeout(doSpawn, 400); });
  setTimeout(doSpawn, 2000);                // fallback: always fires
  // Periodic Zabbix traffic fetch (enriches idle flow with real bps)
  setTimeout(refreshTrafficData, 5000);
}

function resizeTrafficCanvas(){
  if(!TA.canvas) return;
  const wrap = document.getElementById('map-wrap');
  if(!wrap) return;
  const w = wrap.clientWidth  || wrap.offsetWidth  || 0;
  const h = wrap.clientHeight || wrap.offsetHeight || 0;
  if(!w || !h){ setTimeout(resizeTrafficCanvas, 250); return; }
  TA.canvas.width        = w;
  TA.canvas.height       = h;
  TA.canvas.style.width  = w + 'px';
  TA.canvas.style.height = h + 'px';
}

function toggleTraffic(){
  TA.enabled = !TA.enabled;
  const btn = document.getElementById('tb-traffic');
  if(btn){
    btn.style.color     = TA.enabled ? 'var(--cyan)' : '';
    btn.style.boxShadow = TA.enabled ? '0 0 0 1px var(--cyan)' : '';
  }
  if(TA.enabled){ spawnPacketsAll(); refreshTrafficData(); }
  else { TA.packets=[]; updateEdgeTrafficLabels({}); }
  // Canvas loop keeps running regardless (status badges always need it)
}

async function refreshTrafficData(){
  if(!TA.enabled) return;

  // Try to enrich with real Zabbix bps — but ALWAYS spawn packets regardless
  const hostIds = Object.values(S.nodeHostMap).filter(Boolean);
  if(hostIds.length){
    const qs = hostIds.map(h=>'hosts[]='+encodeURIComponent(h)).join('&');
    try{
      const r = await fetch('api/zabbix.php?action=traffic&'+qs);
      if(r.ok){
        const j = await r.json();
        if(j && !j.error){ TA.data = j; updateEdgeTrafficLabels(TA.data); }
      }
    }catch(_){}
  }

  // Always respawn packets (idle flow for all reachable edges)
  spawnPacketsAll();
  TA.lastFetch = Date.now();
  setTimeout(refreshTrafficData, TA.fetchInterval);
}

function _bpsForEdge(eid){
  const e = visEdges.get(eid);
  if(!e) return 0;
  const fromHost = S.nodeHostMap[e.from];
  const toHost   = S.nodeHostMap[e.to];
  const d1 = (fromHost && TA.data[fromHost]) ? TA.data[fromHost] : null;
  const d2 = (toHost   && TA.data[toHost])   ? TA.data[toHost]   : null;
  const bps = Math.max(d1?((d1.in||0)+(d1.out||0)):0, d2?((d2.in||0)+(d2.out||0)):0);
  return bps;
}

function spawnPacketsAll(){
  TA.packets = [];   // clear and respawn fresh
  visEdges.getIds().forEach(eid=>{
    const e = visEdges.get(eid);
    if(!e) return;

    // Check if either endpoint is explicitly DOWN in Zabbix → no traffic
    const fromHostId = S.nodeHostMap[e.from];
    const toHostId   = S.nodeHostMap[e.to];
    const fromHost   = fromHostId ? S.zabbixHosts[fromHostId] : null;
    const toHost     = toHostId   ? S.zabbixHosts[toHostId]   : null;
    if(fromHost?.available==2 || toHost?.available==2) return; // dead link

    // Use real Zabbix bps when available, else idle default (node is reachable)
    const d1  = fromHostId ? TA.data[fromHostId] : null;
    const d2  = toHostId   ? TA.data[toHostId]   : null;
    const bps1 = d1 ? ((d1.in||0)+(d1.out||0)) : 0;
    const bps2 = d2 ? ((d2.in||0)+(d2.out||0)) : 0;
    const realBps = Math.max(bps1, bps2);
    // Idle default: 150Kbps so all reachable (or unmapped) links always animate
    const bps = realBps > 0 ? realBps : 150000;

    spawnPackets(eid, bps, realBps===0);
  });
}

function spawnPackets(eid, totalBps, isIdle=false){
  // Idle (no Zabbix data): 1-2 slow dim packets to show link is alive
  // Real traffic: 1-8 packets, speed + color by bps
  const count = isIdle
    ? 2
    : Math.max(1, Math.min(8, Math.round(Math.log2(totalBps/1000+2))));

  let color, alpha;
  if(isIdle){
    color='#1e6e8c'; alpha=0.55;                   // dim teal: alive but idle
  } else if(totalBps>=50e6){ color='#FF6D00'; alpha=0.9; }  // orange: >50Mbps
  else if(totalBps>=5e6)   { color='#00E676'; alpha=0.85; } // green: >5Mbps
  else if(totalBps>=500e3) { color='#00BCD4'; alpha=0.8; }  // cyan: moderate
  else                     { color='#0288D1'; alpha=0.65; } // dim blue: low

  const speed = isIdle
    ? 0.0008 + Math.random()*0.0005
    : 0.0015 + Math.log10(totalBps/1000+1)*0.0018;

  for(let i=0;i<count;i++){
    TA.packets.push({
      edgeId:   eid,
      progress: Math.random(),
      speed,
      color,
      alpha,
      dir:      (i%2===0) ? 1 : -1,
      isIdle,
    });
  }
}

function animateTraffic(){
  // Canvas always runs — handles both traffic packets AND node status badges
  TA.animFrame = requestAnimationFrame(animateTraffic);

  const canvas = TA.canvas;
  const ctx    = TA.ctx;
  if(!canvas||!ctx||!visNetwork) return;

  ctx.clearRect(0,0,canvas.width,canvas.height);

  // ── Traffic packets (only when enabled) ───────────────────
  if(TA.enabled && TA.packets.length){
    const dead = [];
    TA.packets.forEach((pkt, idx)=>{
      pkt.progress += pkt.speed * pkt.dir;
      if(pkt.progress>1||pkt.progress<0){
        pkt.dir *= -1;
        pkt.progress = pkt.progress>1 ? 1 : 0;
      }

      const e = visEdges.get(pkt.edgeId);
      if(!e){ dead.push(idx); return; }

      let p0, p3;
      try{
        p0 = visNetwork.canvasToDOM(visNetwork.getPosition(e.from));
        p3 = visNetwork.canvasToDOM(visNetwork.getPosition(e.to));
      }catch(_){ return; }

      // Match vis-network's cubicBezier horizontal force (roundness 0.35)
      // P1 leaves FROM node horizontally; P2 arrives at TO node horizontally
      const rn = 0.35;
      const p1 = { x: p0.x + rn*(p3.x-p0.x), y: p0.y };
      const p2 = { x: p0.x + (1-rn)*(p3.x-p0.x), y: p3.y };

      const t  = pkt.progress;
      const mt = 1 - t;
      const px = mt*mt*mt*p0.x + 3*mt*mt*t*p1.x + 3*mt*t*t*p2.x + t*t*t*p3.x;
      const py = mt*mt*mt*p0.y + 3*mt*mt*t*p1.y + 3*mt*t*t*p2.y + t*t*t*p3.y;

      const r = pkt.isIdle ? 2.5 : 3.5;
      ctx.globalAlpha = pkt.alpha || 0.85;
      ctx.beginPath();
      ctx.arc(px, py, r, 0, Math.PI*2);
      ctx.fillStyle   = pkt.color;
      ctx.shadowColor = pkt.color;
      ctx.shadowBlur  = pkt.isIdle ? 4 : 8;
      ctx.fill();
      ctx.shadowBlur  = 0;
      ctx.globalAlpha = 1;
    });
    for(let i=dead.length-1;i>=0;i--) TA.packets.splice(dead[i],1);
  }

  // ── Node status badges (ALWAYS drawn — follows pan/zoom perfectly) ──
  drawNodeStatusBadges(ctx);
}

// Draw per-node status indicators on the traffic canvas.
// Because this runs every RAF frame with canvasToDOM, badges never drift.
function drawNodeStatusBadges(ctx){
  if(!visNetwork || !Object.keys(S.zabbixHosts).length) return;
  const now = Date.now() / 1000;

  visNodes.getIds().forEach(nid=>{
    const hostId = S.nodeHostMap[nid];
    if(!hostId) return;
    const host = S.zabbixHosts[hostId];
    if(!host) return;

    const down = host.available==2;
    const sev  = host.worst_severity||0;
    if(!down && sev<1) return;  // all clear — no badge

    let dom;
    try{
      dom = visNetwork.canvasToDOM(visNetwork.getPosition(nid));
    }catch(_){ return; }

    const ndata    = visNodes.get(nid);
    const nodeSize = (ndata?.size||28);
    const scale    = visNetwork.getScale();
    const sr       = nodeSize * scale;  // scaled radius

    // ── Pulsing ring for DOWN nodes ───────────────────────
    if(down){
      const pulse = 0.4 + 0.6 * Math.abs(Math.sin(now * 2.5));
      ctx.beginPath();
      ctx.arc(dom.x, dom.y, sr + 6 + pulse*10, 0, Math.PI*2);
      ctx.strokeStyle = '#FF1744';
      ctx.lineWidth   = 2.5;
      ctx.globalAlpha = 0.25 + 0.35*pulse;
      ctx.stroke();
      ctx.globalAlpha = 1;
    }

    // ── Badge position: top-right corner of node ──────────
    const bx = dom.x + sr * 0.65 + 3;
    const by = dom.y - sr * 0.65 - 3;
    const r  = Math.max(8, Math.min(12, sr * 0.38));

    // Determine badge style
    let badgeColor, sym;
    if(down)       { badgeColor='#FF1744'; sym='x'; }
    else if(sev>=5){ badgeColor='#FF1744'; sym='!'; }
    else if(sev>=4){ badgeColor='#FF6D00'; sym='!'; }
    else if(sev>=3){ badgeColor='#FFB300'; sym='!'; }
    else           { badgeColor='#78909C'; sym='.'; }

    // Outer glow
    ctx.beginPath(); ctx.arc(bx, by, r+4, 0, Math.PI*2);
    ctx.fillStyle = badgeColor+'33'; ctx.fill();

    // Badge circle
    ctx.beginPath(); ctx.arc(bx, by, r, 0, Math.PI*2);
    ctx.fillStyle   = badgeColor;
    ctx.shadowColor = badgeColor;
    ctx.shadowBlur  = 10;
    ctx.fill();
    ctx.shadowBlur  = 0;
    ctx.strokeStyle = 'rgba(0,0,0,0.5)';
    ctx.lineWidth   = 1.5;
    ctx.stroke();

    // Symbol inside badge
    ctx.strokeStyle = 'white';
    ctx.fillStyle   = 'white';
    ctx.lineWidth   = Math.max(1.5, r*0.22);
    ctx.lineCap     = 'round';
    if(sym==='x'){
      const d=r*0.42;
      ctx.beginPath(); ctx.moveTo(bx-d,by-d); ctx.lineTo(bx+d,by+d); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(bx+d,by-d); ctx.lineTo(bx-d,by+d); ctx.stroke();
    } else if(sym==='!'){
      const d=r*0.38;
      ctx.beginPath(); ctx.moveTo(bx, by-d); ctx.lineTo(bx, by+d*0.2); ctx.stroke();
      ctx.beginPath(); ctx.arc(bx, by+d*0.72, r*0.15, 0, Math.PI*2); ctx.fill();
    } else {
      // info dot
      ctx.beginPath(); ctx.arc(bx, by, r*0.25, 0, Math.PI*2); ctx.fill();
    }
  });
}

function updateEdgeTrafficLabels(data){
  visEdges.getIds().forEach(eid=>{
    const bps = _bpsForEdge(eid);  // real Zabbix bps only
    const e   = visEdges.get(eid);
    if(!e) return;
    if(bps>0 && TA.enabled){
      visEdges.update({id:eid, label: formatBps(bps),
        font:{color:'#00BCD4',size:9,background:'rgba(10,14,26,0.85)'}});
    } else {
      const cur = e.label||'';
      if(/bps$/.test(cur)) visEdges.update({id:eid, label:'', font:{color:'',size:9,background:''}});
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
  // Fetch current user role
  const me=await api('api/auth.php?action=me');
  if(me?.role) S.myRole=me.role;
  applyRoleUI();
  loadNodeHostMap();
  await loadDbNodes();
  loadSavedPositions();
  // Default map: build host filter from nodeHostMap
  S.currentMapHostIds=new Set(Object.values(S.nodeHostMap).filter(Boolean));
  startRefresh();
  loadMaps();
  initTrafficCanvas();
  visNetwork.once('stabilized',()=>visNetwork.fit({animation:{duration:700}}));
  window.addEventListener('resize',()=>{ visNetwork.fit(); resizeTrafficCanvas(); });
}

function applyRoleUI(){
  // Hide write actions for viewer
  const isViewer=S.myRole==='viewer';
  const isOperatorPlus=S.myRole==='admin'||S.myRole==='operator';
  // Nodes toolbar add button
  const addBtn=document.querySelector('[onclick*="openAddNodeModal"],#tb-add');
  if(addBtn&&isViewer) addBtn.style.display='none';
  // Map import
  const impBtn=document.querySelector('[onclick*="openImportModal"]');
  if(impBtn&&isViewer) impBtn.style.display='none';
  // Save layout button
  document.querySelectorAll('[onclick*="saveLayoutPrompt"]').forEach(b=>{ if(isViewer)b.style.display='none'; });
}

// Auto-init if already logged in (PHP session)
<?php if($loggedIn): ?>
document.addEventListener('DOMContentLoaded', init);
<?php else: ?>
document.getElementById('l-user').focus();
<?php endif; ?>

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open');}));

// ── INLINE SPARKLINE GRAPHS ────────────────────────────────
function formatMetricValue(value, units){
  const v = parseFloat(value);
  if(isNaN(v)) return '—';
  if(units==='%') return v.toFixed(1)+'%';
  if(units==='bps'||units==='Bps') return formatBps(v*8);
  if(units==='B'){
    if(v>=1e9) return (v/1e9).toFixed(1)+'GB';
    if(v>=1e6) return (v/1e6).toFixed(1)+'MB';
    if(v>=1e3) return (v/1e3).toFixed(1)+'KB';
    return v+'B';
  }
  if(Math.abs(v)>=1e6) return (v/1e6).toFixed(1)+'M';
  if(Math.abs(v)>=1e3) return (v/1e3).toFixed(1)+'K';
  return v%1===0 ? v.toString() : v.toFixed(2);
}

function drawSparkline(canvas, points, accentColor){
  accentColor = accentColor||'#00d4ff';
  const ctx=canvas.getContext('2d');
  const W=canvas.width, H=canvas.height;
  ctx.clearRect(0,0,W,H);
  if(points.length<2) return;
  const vals=points.map(p=>p.v);
  const min=Math.min(...vals), max=Math.max(...vals);
  const range=max-min||1;
  const px=3;
  const xOf=i=>px+(i/(points.length-1))*(W-2*px);
  const yOf=v=>H-px-((v-min)/range)*(H-2*px);
  // gradient fill
  const grd=ctx.createLinearGradient(0,0,0,H);
  grd.addColorStop(0,accentColor+'55');
  grd.addColorStop(1,accentColor+'08');
  ctx.beginPath();
  ctx.moveTo(xOf(0),H);
  ctx.lineTo(xOf(0),yOf(points[0].v));
  points.forEach((p,i)=>ctx.lineTo(xOf(i),yOf(p.v)));
  ctx.lineTo(xOf(points.length-1),H);
  ctx.closePath();
  ctx.fillStyle=grd;
  ctx.fill();
  // line
  ctx.beginPath();
  ctx.strokeStyle=accentColor;
  ctx.lineWidth=1.5;
  ctx.lineJoin='round';
  points.forEach((p,i)=>i===0?ctx.moveTo(xOf(i),yOf(p.v)):ctx.lineTo(xOf(i),yOf(p.v)));
  ctx.stroke();
  // last-point dot
  ctx.beginPath();
  ctx.arc(xOf(points.length-1),yOf(points[points.length-1].v),2.5,0,Math.PI*2);
  ctx.fillStyle=accentColor;
  ctx.fill();
}

const SLOT_COLOR={cpu:'#f97316',mem:'#a78bfa',net:'#00d4ff'};

function renderMiniChart(container, metric){
  const color=SLOT_COLOR[metric.slot]||'#00d4ff';
  const wrap=document.createElement('div');
  wrap.style.cssText='background:var(--bg);border:1px solid var(--border);border-radius:6px;padding:7px 10px';
  const val=formatMetricValue(metric.lastvalue, metric.units);
  wrap.innerHTML=`
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px">
      <span style="font-size:10px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:170px">${metric.name}</span>
      <span style="font-size:11px;font-weight:700;color:${color};margin-left:6px;flex-shrink:0">${val}</span>
    </div>
    <canvas width="260" height="44" style="width:100%;height:44px;display:block"></canvas>
  `;
  container.appendChild(wrap);
  if(metric.history.length>=2) drawSparkline(wrap.querySelector('canvas'),metric.history,color);
}

async function loadPanelGraphs(hostId){
  const el=document.getElementById('dp-graphs');
  if(!el) return;
  try{
    const r=await fetch('api/zabbix.php?action=history&hostid='+encodeURIComponent(hostId));
    const data=await r.json();
    if(!Array.isArray(data)||!data.length){
      el.innerHTML='<div style="color:var(--muted);font-size:11px;text-align:center;padding:8px 0">No metrics found</div>';
      return;
    }
    el.innerHTML='';
    data.forEach(m=>renderMiniChart(el,m));
  }catch(_){
    el.innerHTML='<div style="color:var(--muted);font-size:11px;text-align:center;padding:8px 0">Could not load metrics</div>';
  }
}

// ── TOPOLOGY AUTO-DISCOVERY ────────────────────────────────
let _discData = null;

function openDiscoverModal(){
  _discData = null;
  document.getElementById('disc-step1').style.display='flex';
  document.getElementById('disc-step2').style.display='none';
  document.getElementById('disc-subnet-list').style.display='none';
  document.getElementById('disc-subnet-list').innerHTML='';
  document.getElementById('disc-scan-summary').textContent='';
  document.getElementById('disc-create-btn').style.display='none';
  document.getElementById('disc-scan-state').style.display='block';
  document.getElementById('disc-scan-state').textContent='Click Scan to discover hosts from Zabbix';
  document.getElementById('disc-scan-btn').disabled=false;
  document.getElementById('disc-scan-btn').innerHTML='<i data-lucide="search" style="width:12px;height:12px;vertical-align:-1px"></i> Scan Zabbix';
  document.getElementById('discover-modal').style.display='flex';
  lucide.createIcons();
}

function closeDiscoverModal(){
  document.getElementById('discover-modal').style.display='none';
}

async function doDiscoverScan(){
  const btn = document.getElementById('disc-scan-btn');
  btn.disabled = true;
  btn.innerHTML = '<i data-lucide="loader" style="width:12px;height:12px"></i> Scanning…';
  lucide.createIcons();
  document.getElementById('disc-scan-state').style.display='block';
  document.getElementById('disc-scan-state').textContent = 'Scanning Zabbix hosts…';
  document.getElementById('disc-subnet-list').style.display='none';
  document.getElementById('disc-create-btn').style.display='none';
  try {
    const r = await fetch('api/discover.php?action=scan', {credentials:'include'});
    _discData = await r.json();
    if(_discData.error){ document.getElementById('disc-scan-state').textContent='Error: '+_discData.error; }
    else { renderDiscoverSubnets(_discData); }
  } catch(e) {
    document.getElementById('disc-scan-state').textContent = 'Scan failed: ' + e.message;
  }
  btn.disabled = false;
  btn.innerHTML = '<i data-lucide="search" style="width:12px;height:12px;vertical-align:-1px"></i> Re-Scan';
  lucide.createIcons();
}

function renderDiscoverSubnets(data){
  const list = document.getElementById('disc-subnet-list');
  const subnets = data.subnets || [];
  const singletons = data.singletons || [];

  list.innerHTML = subnets.map((s,i) => `
    <div style="display:flex;align-items:center;gap:10px;padding:8px 10px;border:1px solid var(--border);border-radius:6px;margin-bottom:6px;background:var(--surface2)">
      <input type="checkbox" id="disc-chk-${i}" checked style="flex-shrink:0;accent-color:var(--cyan)">
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:8px">
          <span style="font-size:11px;font-weight:600;color:#fff;font-family:'JetBrains Mono',monospace">${s.subnet}</span>
          <span style="font-size:10px;color:var(--muted)">${s.hosts.length} hosts</span>
        </div>
        <input class="edit-inp" id="disc-label-${i}" value="${guessSubnetLabel(s.subnet)}"
          style="margin-top:4px;padding:3px 8px;font-size:10px;height:24px;width:200px">
      </div>
      <div style="font-size:10px;color:var(--muted);text-align:right;flex-shrink:0">
        ${s.hosts.slice(0,3).map(h=>`<div>${h.host}</div>`).join('')}
        ${s.hosts.length>3?`<div style="color:var(--cyan)">+${s.hosts.length-3} more</div>`:''}
      </div>
    </div>
  `).join('') + (singletons.length ? `
    <div style="padding:8px 10px;border:1px solid var(--border);border-radius:6px;background:var(--surface2)">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
        <input type="checkbox" id="disc-chk-single" style="accent-color:var(--cyan)">
        <span style="font-size:11px;font-weight:600;color:#fff">Single-host subnets</span>
        <span style="font-size:10px;color:var(--muted)">${singletons.length} hosts</span>
      </div>
    </div>
  ` : '');

  document.getElementById('disc-scan-state').style.display='none';
  list.style.display='block';
  document.getElementById('disc-scan-summary').textContent =
    subnets.length + ' subnets, ' +
    subnets.reduce((a,s)=>a+s.hosts.length,0) + ' hosts found';
  document.getElementById('disc-create-btn').style.display='block';
}

function guessSubnetLabel(subnet){
  const m = subnet.match(/^(\d+)\.(\d+)\.(\d+)/);
  if(!m) return subnet;
  const [,a,b,c] = m;
  if(a==='10'&&b==='1'&&c==='0')    return 'Core Network';
  if(a==='100'&&b==='66')           return 'Application Zone';
  if(a==='100'&&b==='65')           return 'Virtualization Zone';
  if(a==='100'&&b==='64'&&c==='2')  return 'Infrastructure Zone';
  if(a==='100'&&b==='64'&&c==='0')  return 'Management Zone';
  if(a==='100'&&b==='67')           return 'Office Network';
  if(a==='100'&&b==='127')          return 'DR Zone';
  if(a==='100'&&b==='69')           return 'Remote Sites';
  if(a==='127')                     return 'Loopback (skip)';
  return subnet;
}

async function doDiscoverCreate(){
  if(!_discData) return;
  const subnets = _discData.subnets || [];
  const singletons = _discData.singletons || [];
  const selected = [];

  subnets.forEach((s,i) => {
    if(document.getElementById('disc-chk-'+i)?.checked){
      selected.push({
        subnet: s.subnet,
        label:  document.getElementById('disc-label-'+i)?.value || s.subnet,
        hosts:  s.hosts,
      });
    }
  });
  if(document.getElementById('disc-chk-single')?.checked && singletons.length){
    selected.push({subnet:'other', label:'Other Hosts', hosts: singletons});
  }
  if(!selected.length){ alert('Select at least one subnet'); return; }

  const btn = document.getElementById('disc-create-btn');
  btn.disabled = true;
  btn.textContent = 'Creating…';

  const name = document.getElementById('disc-name').value.trim() || 'Auto-Discovered Map';
  try {
    const r = await api('api/discover.php?action=create', {
      method: 'POST',
      body: JSON.stringify({name, subnets: selected}),
    });
    if(r?.ok){
      document.getElementById('disc-step1').style.display='none';
      document.getElementById('disc-step2').style.display='block';
      document.getElementById('disc-done-msg').textContent =
        '✓ "' + name + '" created with ' + r.nodes_created + ' nodes';
      loadMaps();
      switchMap(r.layout_id, name);
    } else {
      alert('Error: ' + (r?.error||'Unknown'));
    }
  } catch(e){ alert('Create failed: '+e.message); }
  btn.disabled = false;
  btn.textContent = 'Create Map →';
}
</script>
</body>
</html>
