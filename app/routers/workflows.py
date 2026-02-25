import json
from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from typing import Optional, List

from app.deps import get_session, require_admin, require_operator
from app.database import fetch_all, fetch_one, execute
from app.services.workflow_engine import trigger_workflow_manually, reload_scheduled_workflows

router = APIRouter()


class WorkflowBody(BaseModel):
    name: str
    description: str = ""
    trigger_type: str = "manual"          # alarm | schedule | threshold | manual
    trigger_config: Optional[str] = None  # JSON string
    employee_id: str = "aria"
    prompt_template: str = "Analyze the current network state and provide a brief status report."
    action_type: str = "log"              # log | webhook | zabbix_ack | email
    action_config: Optional[str] = None  # JSON string
    is_active: bool = True


@router.get("")
async def list_workflows(session: dict = Depends(get_session)):
    rows = await fetch_all("SELECT * FROM workflows ORDER BY id")
    for r in rows:
        r["is_active"] = bool(r.get("is_active"))
    return rows


@router.post("")
async def create_workflow(body: WorkflowBody, session: dict = Depends(require_operator)):
    if not body.name:
        raise HTTPException(400, "Name required")
    wf_id = await execute(
        "INSERT INTO workflows (name, description, trigger_type, trigger_config, "
        "employee_id, prompt_template, action_type, action_config, is_active) "
        "VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s)",
        (body.name, body.description, body.trigger_type,
         body.trigger_config, body.employee_id,
         body.prompt_template, body.action_type,
         body.action_config, int(body.is_active)),
    )
    await reload_scheduled_workflows()
    return {"ok": True, "id": wf_id}


@router.get("/{wf_id}")
async def get_workflow(wf_id: int, session: dict = Depends(get_session)):
    row = await fetch_one("SELECT * FROM workflows WHERE id=%s", (wf_id,))
    if not row:
        raise HTTPException(404, "Not found")
    row["is_active"] = bool(row.get("is_active"))
    return row


@router.put("/{wf_id}")
async def update_workflow(wf_id: int, body: WorkflowBody, session: dict = Depends(require_operator)):
    row = await fetch_one("SELECT id FROM workflows WHERE id=%s", (wf_id,))
    if not row:
        raise HTTPException(404, "Not found")
    await execute(
        "UPDATE workflows SET name=%s, description=%s, trigger_type=%s, trigger_config=%s, "
        "employee_id=%s, prompt_template=%s, action_type=%s, action_config=%s, is_active=%s "
        "WHERE id=%s",
        (body.name, body.description, body.trigger_type,
         body.trigger_config, body.employee_id,
         body.prompt_template, body.action_type,
         body.action_config, int(body.is_active), wf_id),
    )
    await reload_scheduled_workflows()
    return {"ok": True}


@router.delete("/{wf_id}")
async def delete_workflow(wf_id: int, session: dict = Depends(require_admin)):
    await execute("DELETE FROM workflow_runs WHERE workflow_id=%s", (wf_id,))
    await execute("DELETE FROM workflows WHERE id=%s", (wf_id,))
    await reload_scheduled_workflows()
    return {"ok": True}


@router.post("/{wf_id}/trigger")
async def manual_trigger(wf_id: int, session: dict = Depends(require_operator)):
    row = await fetch_one("SELECT id FROM workflows WHERE id=%s", (wf_id,))
    if not row:
        raise HTTPException(404, "Workflow not found")
    result = await trigger_workflow_manually(wf_id)
    return result


@router.get("/{wf_id}/runs")
async def get_runs(wf_id: int, session: dict = Depends(get_session)):
    rows = await fetch_all(
        "SELECT id, trigger_data, ai_response, action_result, status, created_at "
        "FROM workflow_runs WHERE workflow_id=%s ORDER BY created_at DESC LIMIT 50",
        (wf_id,),
    )
    return rows


class TestWebhookBody(BaseModel):
    url: str
    payload: dict = {}


@router.post("/test-webhook")
async def test_webhook(body: TestWebhookBody, session: dict = Depends(get_session)):
    """Send a test ping to a webhook URL (for n8n testing)."""
    import httpx
    try:
        async with httpx.AsyncClient(verify=False, timeout=10) as client:
            resp = await client.post(body.url, json=body.payload,
                                     headers={"Content-Type": "application/json",
                                              "X-Source": "NOC-Sentinel-Test"})
        return {"ok": True, "status": f"HTTP {resp.status_code}"}
    except Exception as e:
        return {"ok": False, "error": str(e)}


# ── WhatsApp proxy routes (all browser→server→Node.js, so remote browsers work) ──

WA_SERVICE = "http://localhost:3001"


async def _wa_get(path: str):
    import httpx
    try:
        async with httpx.AsyncClient(timeout=10) as client:
            resp = await client.get(f"{WA_SERVICE}{path}")
        if resp.status_code == 200:
            return resp.json()
        return {"error": resp.text[:200]}
    except Exception as e:
        return {"error": str(e)}


async def _wa_post(path: str, body: dict = None):
    import httpx
    try:
        async with httpx.AsyncClient(timeout=15) as client:
            resp = await client.post(f"{WA_SERVICE}{path}", json=body or {})
        return resp.json()
    except Exception as e:
        return {"error": str(e)}


async def _wa_delete(path: str):
    import httpx
    try:
        async with httpx.AsyncClient(timeout=10) as client:
            resp = await client.delete(f"{WA_SERVICE}{path}")
        return resp.json()
    except Exception as e:
        return {"error": str(e)}


@router.get("/wa/status")
async def wa_status(session: dict = Depends(get_session)):
    return await _wa_get("/status")


@router.get("/wa/log/{emp_id}")
async def wa_log(emp_id: str, session: dict = Depends(get_session)):
    return await _wa_get(f"/log/{emp_id}")


@router.get("/wa/groups/{emp_id}")
async def wa_groups(emp_id: str, session: dict = Depends(get_session)):
    return await _wa_get(f"/groups/{emp_id}")


class WaSendBody(BaseModel):
    to: str
    message: str


@router.post("/wa/send/{emp_id}")
async def wa_send(emp_id: str, body: WaSendBody, session: dict = Depends(get_session)):
    return await _wa_post(f"/send/{emp_id}", {"to": body.to, "message": body.message})


@router.post("/wa/reconnect/{emp_id}")
async def wa_reconnect(emp_id: str, session: dict = Depends(get_session)):
    return await _wa_post(f"/reconnect/{emp_id}")


@router.delete("/wa/logout/{emp_id}")
async def wa_logout(emp_id: str, session: dict = Depends(require_operator)):
    return await _wa_delete(f"/logout/{emp_id}")


# Keep old route for backward compat
@router.get("/wa-groups/{emp_id}")
async def get_wa_groups(emp_id: str, session: dict = Depends(get_session)):
    return await _wa_get(f"/groups/{emp_id}")
