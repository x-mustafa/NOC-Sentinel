import json
import math
import base64
import re
from fastapi import APIRouter, Depends, HTTPException, UploadFile, File, Form
from pydantic import BaseModel
from typing import Optional, List, Any

import httpx

from app.deps import get_session, require_admin, require_operator
from app.database import fetch_one, execute
from app.services.zabbix_client import call_zabbix

router = APIRouter()


@router.post("/analyze")
async def analyze_diagram(
    map: UploadFile = File(...),
    session: dict = Depends(require_operator),
):
    cfg = await fetch_one("SELECT claude_key FROM zabbix_config LIMIT 1") or {}
    claude_key = cfg.get("claude_key", "")
    if not claude_key:
        raise HTTPException(400, "Claude API key not set — go to Settings → AI Providers")

    allowed_types = {"image/png", "image/jpeg", "image/gif", "image/webp", "application/pdf"}
    if map.content_type not in allowed_types:
        raise HTTPException(400, f"Unsupported file type: {map.content_type}")

    raw  = await map.read()
    b64  = base64.b64encode(raw).decode()

    if map.content_type == "application/pdf":
        media_block = {"type": "document", "source": {"type": "base64", "media_type": "application/pdf", "data": b64}}
    else:
        media_block = {"type": "image",    "source": {"type": "base64", "media_type": map.content_type, "data": b64}}

    prompt = (
        "This is a network topology diagram. Extract EVERY network device/node visible in the diagram.\n\n"
        "For each device return these exact fields:\n"
        "- name: the device label/hostname shown in the diagram\n"
        "- ip: the IP address shown (empty string \"\" if none visible)\n"
        "- type: one of: router, switch, firewall, server, load_balancer, storage, endpoint, other\n\n"
        "Return ONLY a valid JSON array with no markdown, no explanation, no code fences.\n"
        'Example: [{"name":"Core-SW-01","ip":"10.0.0.1","type":"switch"}]'
    )

    async with httpx.AsyncClient(verify=False, timeout=90) as client:
        resp = await client.post(
            "https://api.anthropic.com/v1/messages",
            headers={"x-api-key": claude_key, "anthropic-version": "2023-06-01",
                     "Content-Type": "application/json"},
            json={"model": "claude-opus-4-6", "max_tokens": 4096,
                  "messages": [{"role": "user", "content": [media_block, {"type": "text", "text": prompt}]}]},
        )
    data = resp.json()
    if "error" in data:
        raise HTTPException(500, data["error"].get("message", "Claude error"))

    text = data.get("content", [{}])[0].get("text", "")
    m = re.search(r"\[[\s\S]*\]", text)
    if not m:
        raise HTTPException(500, f"Claude did not return valid JSON. Response: {text[:300]}")
    extracted = json.loads(m.group(0))

    # Match each node to Zabbix by IP
    results = []
    for node in extracted:
        ip   = (node.get("ip") or "").strip()
        name = (node.get("name") or "Unknown").strip()
        ntype = node.get("type", "switch")
        zbx_host = None

        if ip:
            ifaces = await call_zabbix("hostinterface.get", {
                "output": ["hostid", "ip"],
                "search": {"ip": ip}, "searchExact": True,
            })
            if isinstance(ifaces, list) and ifaces:
                hosts = await call_zabbix("host.get", {
                    "output": ["hostid", "host", "name"],
                    "hostids": [ifaces[0]["hostid"]],
                })
                zbx_host = hosts[0] if isinstance(hosts, list) and hosts else None

        results.append({
            "name":          name,
            "ip":            ip,
            "type":          ntype,
            "zabbix_host":   zbx_host,
            "zabbix_hostid": zbx_host["hostid"] if zbx_host else None,
            "matched":       zbx_host is not None,
        })

    return {
        "nodes":   results,
        "total":   len(results),
        "matched": sum(1 for r in results if r["matched"]),
        "skipped": sum(1 for r in results if not r["matched"]),
    }


class ImportNode(BaseModel):
    name: str
    ip: str = ""
    type: str = "switch"
    zabbix_hostid: Optional[str] = None


class CreateImportBody(BaseModel):
    name: str
    nodes: List[ImportNode]


@router.post("/create")
async def create_import_map(body: CreateImportBody, session: dict = Depends(require_operator)):
    if not body.name:
        raise HTTPException(400, "Map name required")
    if not body.nodes:
        raise HTTPException(400, "No matched nodes to create")

    layout_id = await execute(
        "INSERT INTO map_layouts (name, positions, is_default) VALUES (%s,'{}',0)",
        (body.name,),
    )
    cols  = max(1, math.ceil(math.sqrt(len(body.nodes) * 1.6)))
    x_gap = 220; y_gap = 160; x_off = 150; y_off = 120
    positions = {}

    for i, n in enumerate(body.nodes):
        col = i % cols
        row = i // cols
        x   = x_off + col * x_gap
        y   = y_off + row * y_gap
        nid = f"map_{layout_id}_{i + 1}"
        await execute(
            "INSERT INTO map_nodes (id, label, ip, type, x, y, layout_id, zabbix_host_id, status) "
            "VALUES (%s,%s,%s,%s,%s,%s,%s,%s,'ok') "
            "ON DUPLICATE KEY UPDATE label=VALUES(label),ip=VALUES(ip),type=VALUES(type),"
            "x=VALUES(x),y=VALUES(y),layout_id=VALUES(layout_id),zabbix_host_id=VALUES(zabbix_host_id)",
            (nid, n.name, n.ip, n.type, x, y, layout_id, n.zabbix_hostid),
        )
        positions[nid] = {"x": x, "y": y}

    await execute("UPDATE map_layouts SET positions=%s WHERE id=%s",
                  (json.dumps(positions), layout_id))
    return {"ok": True, "layout_id": layout_id, "nodes_created": len(body.nodes)}


@router.get("/key")
async def get_claude_key(session: dict = Depends(get_session)):
    cfg = await fetch_one("SELECT claude_key FROM zabbix_config LIMIT 1") or {}
    key = cfg.get("claude_key", "")
    masked = (key[:8] + "*" * 24 + key[-4:]) if len(key) > 12 else ""
    return {"has_key": bool(key), "masked": masked}


class SaveKeyBody(BaseModel):
    claude_key: str


@router.post("/key")
async def save_claude_key(body: SaveKeyBody, session: dict = Depends(require_admin)):
    if not body.claude_key.strip():
        raise HTTPException(400, "Key required")
    await execute("UPDATE zabbix_config SET claude_key=%s", (body.claude_key.strip(),))
    return {"ok": True}


@router.get("/aikeys")
async def get_ai_keys(session: dict = Depends(get_session)):
    cfg = await fetch_one(
        "SELECT claude_key,openai_key,gemini_key,grok_key,default_ai_provider,default_ai_model "
        "FROM zabbix_config LIMIT 1"
    ) or {}

    def mask(k):
        k = k or ""
        return (k[:8] + "*" * 16 + k[-4:]) if len(k) > 12 else ""

    return {
        "claude":           {"has": bool(cfg.get("claude_key")),  "masked": mask(cfg.get("claude_key"))},
        "openai":           {"has": bool(cfg.get("openai_key")),  "masked": mask(cfg.get("openai_key"))},
        "gemini":           {"has": bool(cfg.get("gemini_key")),  "masked": mask(cfg.get("gemini_key"))},
        "grok":             {"has": bool(cfg.get("grok_key")),    "masked": mask(cfg.get("grok_key"))},
        "default_provider": cfg.get("default_ai_provider") or "claude",
        "default_model":    cfg.get("default_ai_model")    or "",
    }


class SaveAiKeysBody(BaseModel):
    claude_key: Optional[str] = None
    openai_key: Optional[str] = None
    gemini_key: Optional[str] = None
    grok_key: Optional[str] = None
    default_ai_provider: Optional[str] = None
    default_ai_model: Optional[str] = None


@router.post("/aikeys")
async def save_ai_keys(body: SaveAiKeysBody, session: dict = Depends(require_admin)):
    fields = ["claude_key", "openai_key", "gemini_key", "grok_key",
              "default_ai_provider", "default_ai_model"]
    sets, vals = [], []
    for f in fields:
        v = getattr(body, f, None)
        if v is not None:
            sets.append(f"{f}=%s")
            vals.append(v.strip() if isinstance(v, str) else v)
    if sets:
        await execute("UPDATE zabbix_config SET " + ",".join(sets), tuple(vals))
    return {"ok": True}
