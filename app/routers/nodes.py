import json
from fastapi import APIRouter, Depends, Query, HTTPException
from pydantic import BaseModel
from typing import Optional, List, Any

from app.deps import get_session, require_admin, require_operator
from app.database import fetch_all, fetch_one, execute

router        = APIRouter()
layout_router = APIRouter()


# ── NODES ─────────────────────────────────────────────────────────────────────

@router.get("")
async def get_nodes(
    layout_id: Optional[int] = Query(default=None),
    session: dict = Depends(get_session),
):
    if layout_id is not None:
        rows = await fetch_all(
            "SELECT * FROM map_nodes WHERE layout_id=%s ORDER BY created_at", (layout_id,)
        )
    else:
        rows = await fetch_all("SELECT * FROM map_nodes ORDER BY created_at")
    for r in rows:
        r["ifaces"] = json.loads(r.get("ifaces") or "[]") or []
        r["info"]   = json.loads(r.get("info")   or "{}") or {}
        r["x"]      = float(r.get("x") or 0)
        r["y"]      = float(r.get("y") or 0)
    return rows


@router.get("/{node_id}")
async def get_node(node_id: str, session: dict = Depends(get_session)):
    r = await fetch_one("SELECT * FROM map_nodes WHERE id=%s", (node_id,))
    if not r:
        raise HTTPException(404, "Not found")
    r["ifaces"] = json.loads(r.get("ifaces") or "[]") or []
    r["info"]   = json.loads(r.get("info")   or "{}") or {}
    return r


class NodeBody(BaseModel):
    id: str
    label: str
    ip: str = ""
    role: str = ""
    type: str = "switch"
    layer_key: str = "srv"
    x: float = 0
    y: float = 0
    status: str = "ok"
    ifaces: List[Any] = []
    info: dict = {}
    zabbix_host_id: Optional[str] = None
    layout_id: Optional[int] = None


@router.post("")
async def upsert_node(body: NodeBody, session: dict = Depends(require_operator)):
    await execute(
        """INSERT INTO map_nodes
            (id, label, ip, role, type, layer_key, x, y, status, ifaces, info, zabbix_host_id, layout_id)
           VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
           ON DUPLICATE KEY UPDATE
             label=VALUES(label), ip=VALUES(ip), role=VALUES(role), type=VALUES(type),
             layer_key=VALUES(layer_key), x=VALUES(x), y=VALUES(y), status=VALUES(status),
             ifaces=VALUES(ifaces), info=VALUES(info),
             zabbix_host_id=VALUES(zabbix_host_id), layout_id=VALUES(layout_id)""",
        (body.id, body.label, body.ip, body.role, body.type, body.layer_key,
         body.x, body.y, body.status,
         json.dumps(body.ifaces), json.dumps(body.info),
         body.zabbix_host_id, body.layout_id),
    )
    return {"ok": True, "id": body.id}


class PositionItem(BaseModel):
    id: str
    x: float
    y: float


@router.patch("")
async def bulk_update_positions(
    items: List[PositionItem],
    session: dict = Depends(require_operator),
):
    for item in items:
        await execute("UPDATE map_nodes SET x=%s, y=%s WHERE id=%s",
                      (item.x, item.y, item.id))
    return {"ok": True}


@router.delete("/{node_id}")
async def delete_node(node_id: str, session: dict = Depends(require_admin)):
    await execute("DELETE FROM map_nodes WHERE id=%s", (node_id,))
    return {"ok": True}


# ── LAYOUTS ───────────────────────────────────────────────────────────────────

@layout_router.get("")
async def get_layouts(session: dict = Depends(get_session)):
    rows = await fetch_all(
        "SELECT id, name, is_default, created_at FROM map_layouts ORDER BY is_default DESC, created_at DESC"
    )
    return rows


@layout_router.get("/{layout_id}")
async def get_layout(layout_id: int, session: dict = Depends(get_session)):
    row = await fetch_one("SELECT * FROM map_layouts WHERE id=%s", (layout_id,))
    if not row:
        raise HTTPException(404, "Not found")
    row["positions"] = json.loads(row.get("positions") or "{}")
    nodes = await fetch_all("SELECT * FROM map_nodes WHERE layout_id=%s ORDER BY created_at", (layout_id,))
    for n in nodes:
        n["ifaces"] = json.loads(n.get("ifaces") or "[]") or []
        n["info"]   = json.loads(n.get("info")   or "{}") or {}
        n["x"]      = float(n.get("x") or 0)
        n["y"]      = float(n.get("y") or 0)
    row["nodes"] = nodes
    return row


class LayoutBody(BaseModel):
    name: str
    positions: dict = {}
    is_default: bool = False


@layout_router.post("")
async def create_layout(body: LayoutBody, session: dict = Depends(require_operator)):
    if not body.name:
        raise HTTPException(400, "Name required")
    if body.is_default:
        await execute("UPDATE map_layouts SET is_default=0")
    lid = await execute(
        "INSERT INTO map_layouts (name, positions, is_default) VALUES (%s,%s,%s)",
        (body.name, json.dumps(body.positions), 1 if body.is_default else 0),
    )
    return {"ok": True, "id": lid}


@layout_router.delete("/{layout_id}")
async def delete_layout(layout_id: int, session: dict = Depends(require_admin)):
    await execute("DELETE FROM map_nodes WHERE layout_id=%s", (layout_id,))
    await execute("DELETE FROM map_layouts WHERE id=%s", (layout_id,))
    return {"ok": True}
