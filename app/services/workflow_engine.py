"""
Workflow engine: APScheduler-based trigger system.
Handles schedule-based and alarm-based workflow triggers.
"""
import asyncio
import json
import logging
import time
from typing import Any

from app.database import fetch_all, execute
from app.services.zabbix_client import call_zabbix
from app.services.ai_stream import stream_ai

logger = logging.getLogger(__name__)

_scheduler = None
_last_alarm_ids: set[str] = set()


def get_scheduler():
    global _scheduler
    if _scheduler is None:
        try:
            from apscheduler.schedulers.asyncio import AsyncIOScheduler
            _scheduler = AsyncIOScheduler()
        except ImportError:
            logger.warning("APScheduler not installed — workflow scheduling unavailable")
    return _scheduler


async def start_engine():
    """Called on app startup. Load scheduled workflows and start the scheduler."""
    sched = get_scheduler()
    if not sched:
        return

    await _register_scheduled_workflows()
    sched.add_job(_poll_alarm_workflows, "interval", seconds=30, id="alarm_poll", replace_existing=True)
    sched.start()
    logger.info("Workflow engine started.")


async def stop_engine():
    sched = get_scheduler()
    if sched and sched.running:
        sched.shutdown(wait=False)


async def _register_scheduled_workflows():
    sched = get_scheduler()
    if not sched:
        return
    workflows = await fetch_all(
        "SELECT * FROM workflows WHERE is_active=1 AND trigger_type='schedule'"
    )
    for wf in workflows:
        _register_schedule(sched, wf)


def _register_schedule(sched, wf: dict):
    cfg = {}
    try:
        cfg = json.loads(wf.get("trigger_config") or "{}")
    except Exception:
        pass
    cron_expr = cfg.get("cron", "0 8 * * *")  # default: 8 AM daily
    try:
        from apscheduler.triggers.cron import CronTrigger
        parts = cron_expr.split()
        trigger = CronTrigger(
            minute=parts[0] if len(parts) > 0 else "*",
            hour=parts[1]   if len(parts) > 1 else "*",
            day=parts[2]    if len(parts) > 2 else "*",
            month=parts[3]  if len(parts) > 3 else "*",
            day_of_week=parts[4] if len(parts) > 4 else "*",
        )
        job_id = f"wf_{wf['id']}"
        sched.add_job(
            _run_workflow,
            trigger=trigger,
            id=job_id,
            replace_existing=True,
            args=[wf["id"], {"trigger": "schedule", "cron": cron_expr}],
        )
        logger.info(f"Registered scheduled workflow {wf['id']} ({wf['name']}) — cron: {cron_expr}")
    except Exception as e:
        logger.warning(f"Failed to register workflow {wf['id']}: {e}")


async def _poll_alarm_workflows():
    """Check for new Zabbix alarms and trigger matching workflows."""
    global _last_alarm_ids
    workflows = await fetch_all(
        "SELECT * FROM workflows WHERE is_active=1 AND trigger_type='alarm'"
    )
    if not workflows:
        return

    problems_raw = await call_zabbix("problem.get", {
        "output": ["eventid", "objectid", "name", "severity", "clock"],
        "sortfield": "eventid", "sortorder": "DESC", "limit": 50,
    })
    if not isinstance(problems_raw, list):
        return

    new_ids = {p["eventid"] for p in problems_raw if isinstance(p, dict)}
    truly_new = new_ids - _last_alarm_ids
    _last_alarm_ids = new_ids

    if not truly_new:
        return

    new_problems = [p for p in problems_raw if p.get("eventid") in truly_new]

    for wf in workflows:
        cfg = {}
        try:
            cfg = json.loads(wf.get("trigger_config") or "{}")
        except Exception:
            pass
        min_sev = int(cfg.get("severity_min", 3))
        host_filter = (cfg.get("host_filter") or "").lower()

        for p in new_problems:
            sev = int(p.get("severity", 0))
            name = (p.get("name") or "").lower()
            if sev < min_sev:
                continue
            if host_filter and host_filter not in name:
                continue
            asyncio.create_task(_run_workflow(wf["id"], {"trigger": "alarm", "problem": p}))


async def _run_workflow(workflow_id: int, trigger_data: dict):
    """Execute a single workflow: get AI analysis → execute action → log run."""
    wf = await fetch_all("SELECT * FROM workflows WHERE id=%s LIMIT 1", (workflow_id,))
    if not wf:
        return
    wf = wf[0]

    run_id = await execute(
        "INSERT INTO workflow_runs (workflow_id, trigger_data, status) VALUES (%s,%s,'running')",
        (workflow_id, json.dumps(trigger_data, default=str)),
    )

    try:
        ai_response = await _get_ai_response(wf, trigger_data)
        action_result = await _execute_action(wf, trigger_data, ai_response)
        await execute(
            "UPDATE workflow_runs SET ai_response=%s, action_result=%s, status='success' WHERE id=%s",
            (ai_response[:4000] if ai_response else "", action_result, run_id),
        )
    except Exception as e:
        logger.error(f"Workflow {workflow_id} run failed: {e}")
        await execute(
            "UPDATE workflow_runs SET status='error', action_result=%s WHERE id=%s",
            (str(e)[:500], run_id),
        )


async def _get_ai_response(wf: dict, trigger_data: dict) -> str:
    from app.database import fetch_one

    prompt_tmpl = wf.get("prompt_template") or "Analyze this event and provide a brief assessment."
    prompt = prompt_tmpl
    if "problem" in trigger_data:
        p = trigger_data["problem"]
        host_name = str(p.get("hosts", ["?"])[0] if p.get("hosts") else "?")
        prompt = (prompt_tmpl
                  .replace("{alarm_name}", p.get("name", ""))
                  .replace("{host}", host_name)
                  .replace("{severity}", str(p.get("severity", 0))))

    cfg = await fetch_one("SELECT * FROM zabbix_config LIMIT 1") or {}
    provider = cfg.get("default_ai_provider") or "claude"
    model    = cfg.get("default_ai_model")    or ""
    key_map  = {"claude": "claude_key", "openai": "openai_key", "gemini": "gemini_key", "grok": "grok_key"}
    api_key  = cfg.get(key_map.get(provider, "claude_key"), "")

    if not api_key:
        return "[No AI key configured]"

    model_defaults = {"claude": "claude-haiku-4-5-20251001", "openai": "gpt-4o-mini",
                      "gemini": "gemini-2.0-flash", "grok": "grok-2-latest"}
    model = model or model_defaults.get(provider, "claude-haiku-4-5-20251001")

    system = (
        "You are NOC Sentinel AI for Tabadul payment infrastructure. "
        "Provide concise, actionable analysis. Be direct and specific."
    )

    full_response = ""
    async for chunk in stream_ai(provider, api_key, model, system, prompt):
        if "data" in chunk:
            try:
                d = json.loads(chunk["data"])
                full_response += d.get("t", "")
            except Exception:
                pass

    return full_response


async def _execute_action(wf: dict, trigger_data: dict, ai_response: str) -> str:
    action_type = wf.get("action_type") or "log"
    cfg = {}
    try:
        cfg = json.loads(wf.get("action_config") or "{}")
    except Exception:
        pass

    if action_type == "log":
        logger.info(f"[WF {wf['id']}] {wf['name']}: {ai_response[:200]}")
        return "logged"

    elif action_type == "webhook":
        url = cfg.get("url", "")
        if not url:
            return "error: no webhook URL"
        import httpx
        body = {
            "workflow": wf["name"],
            "trigger": trigger_data,
            "ai_response": ai_response,
        }
        try:
            async with httpx.AsyncClient(verify=False, timeout=10) as client:
                resp = await client.post(url, json=body,
                                          headers=cfg.get("headers", {}))
            return f"webhook: {resp.status_code}"
        except Exception as e:
            return f"webhook error: {e}"

    elif action_type == "zabbix_ack":
        p = trigger_data.get("problem", {})
        eventid = p.get("eventid")
        if eventid:
            await call_zabbix("event.acknowledge", {
                "eventids": [eventid],
                "action": 6,
                "message": f"Auto-acknowledged by workflow '{wf['name']}': {ai_response[:200]}",
            })
            return f"acknowledged event {eventid}"
        return "no eventid"

    elif action_type == "whatsapp_group":
        emp_id    = cfg.get("emp_id", "aria")
        group_jid = cfg.get("group_jid", "")
        if not group_jid:
            return "error: no group JID configured"
        import httpx
        wa_service = "http://localhost:3001"
        msg = ai_response[:3800] if ai_response else "[No response]"
        try:
            async with httpx.AsyncClient(timeout=15) as client:
                resp = await client.post(
                    f"{wa_service}/send/{emp_id}",
                    json={"to": group_jid, "message": msg},
                )
            return f"whatsapp_group: HTTP {resp.status_code}"
        except Exception as e:
            return f"whatsapp_group error: {e}"

    return f"unknown action: {action_type}"


async def trigger_workflow_manually(workflow_id: int) -> dict:
    """Manually trigger a workflow. Returns run status."""
    await _run_workflow(workflow_id, {"trigger": "manual", "ts": int(time.time())})
    return {"ok": True, "workflow_id": workflow_id}


async def reload_scheduled_workflows():
    """Re-register all scheduled workflows (call after create/update/delete)."""
    sched = get_scheduler()
    if not sched:
        return
    # Remove existing workflow jobs
    for job in sched.get_jobs():
        if job.id.startswith("wf_"):
            job.remove()
    await _register_scheduled_workflows()
