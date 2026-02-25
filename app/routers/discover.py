import json
import math
from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from typing import List, Any

from app.deps import get_session, require_operator
from app.services.zabbix_client import call_zabbix
from app.database import execute

router = APIRouter()


def _detect_type(host: str, name: str) -> str:
    s = (host + " " + name).lower()
    import re
    if re.search(r"fortigate|fortinet", s):                        return "firewall"
    if re.search(r"\bpalo\b|pa-\d", s):                           return "palo"
    if re.search(r"\bf5\b|bigip|loadbal", s):                     return "f5"
    if re.search(r"\bhsm\b", s):                                   return "hsm"
    if re.search(r"switch|\bsw[-_]|\btor\b|nexus|catalyst", s):   return "switch"
    if re.search(r"\bfw\b|firewall|\bftd\b|\bips\b", s):          return "firewall"
    if re.search(r"database|\bdb[-_]|\bsql\b|oracle|mysql|redis", s): return "dbserver"
    if re.search(r"esxi|vmware|vcenter|hyper.v|\bhvn\b", s):      return "infra"
    if re.search(r"backup|veeam|storeonce|\bsan\b|storage", s):   return "infra"
    if re.search(r"router|ag1000|gateway|\bwan\b|\bisp\b", s):    return "wan"
    return "server"


@router.get("/scan")
async def scan(session: dict = Depends(get_session)):
    hosts_raw = await call_zabbix("host.get", {
        "output": ["hostid", "host", "name", "status"],
        "selectInterfaces": ["ip", "main"],
        "monitored_hosts": 1,
        "limit": 1000,
    })
    if not isinstance(hosts_raw, list):
        raise HTTPException(500, "Zabbix error")

    subnets: dict = {}
    for h in hosts_raw:
        ip = ""
        for iface in h.get("interfaces", []):
            if str(iface.get("main")) == "1":
                ip = iface.get("ip", "")
                break
        parts = ip.split(".")
        sub = (f"{parts[0]}.{parts[1]}.{parts[2]}.0/24"
               if len(parts) == 4 else "unassigned")
        if sub not in subnets:
            subnets[sub] = {"subnet": sub, "hosts": []}
        subnets[sub]["hosts"].append({
            "hostid": h["hostid"],
            "host":   h["host"],
            "name":   h["name"],
            "ip":     ip,
            "type":   _detect_type(h["host"], h["name"]),
        })

    main_subnets, singletons = [], []
    for s in subnets.values():
        s["hosts"].sort(key=lambda x: x["host"])
        if len(s["hosts"]) >= 2:
            main_subnets.append(s)
        else:
            singletons.extend(s["hosts"])
    main_subnets.sort(key=lambda x: -len(x["hosts"]))

    return {"subnets": main_subnets, "singletons": singletons}


class SubnetSelection(BaseModel):
    subnet: str
    label: str = ""
    hosts: List[Any] = []


class CreateMapBody(BaseModel):
    name: str = "Auto-Discovered Map"
    subnets: List[SubnetSelection]


@router.post("/create")
async def create_map(body: CreateMapBody, session: dict = Depends(require_operator)):
    if not body.subnets:
        raise HTTPException(400, "No subnets selected")

    layout_id = await execute(
        "INSERT INTO map_layouts (name, positions, is_default) VALUES (%s,'{}',0)",
        (body.name,),
    )

    cols = max(1, math.ceil(math.sqrt(len(body.subnets))))
    gap  = 800
    nodes_created = 0
    edges = []

    for si, subnet in enumerate(body.subnets):
        cx = (si % cols) * gap
        cy = (si // cols) * gap
        hosts  = subnet.hosts
        n      = len(hosts)
        label  = subnet.label.strip() or subnet.subnet
        radius = max(200, n * 28)

        hub_id = f"hub_{layout_id}_{si}"
        await execute(
            "INSERT INTO map_nodes (id, label, ip, type, x, y, layout_id, zabbix_host_id, status) "
            "VALUES (%s,%s,%s,'switch',%s,%s,%s,NULL,'ok') "
            "ON DUPLICATE KEY UPDATE label=VALUES(label),x=VALUES(x),y=VALUES(y),layout_id=VALUES(layout_id)",
            (hub_id, label, subnet.subnet, cx, cy, layout_id),
        )
        nodes_created += 1

        for hi, host in enumerate(hosts):
            angle = (2 * math.pi * hi) / max(1, n) - math.pi / 2
            x = round(cx + radius * math.cos(angle))
            y = round(cy + radius * math.sin(angle))
            node_id = f"disc_{layout_id}_{nodes_created}"
            await execute(
                "INSERT INTO map_nodes (id, label, ip, type, x, y, layout_id, zabbix_host_id, status) "
                "VALUES (%s,%s,%s,%s,%s,%s,%s,%s,'ok') "
                "ON DUPLICATE KEY UPDATE label=VALUES(label),x=VALUES(x),y=VALUES(y),"
                "layout_id=VALUES(layout_id),zabbix_host_id=VALUES(zabbix_host_id)",
                (node_id, host.get("name") or host.get("host"), host.get("ip", ""),
                 host.get("type", "server"), x, y, layout_id, host.get("hostid")),
            )
            edges.append({"from": hub_id, "to": node_id})
            nodes_created += 1

    await execute(
        "UPDATE map_layouts SET positions=%s WHERE id=%s",
        (json.dumps({"edges": edges}), layout_id),
    )
    return {"ok": True, "layout_id": layout_id, "nodes_created": nodes_created}
