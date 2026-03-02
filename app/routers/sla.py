"""
F14 — SLA Real-time Tracker

Track uptime SLA per critical payment path (services).
Downtime events are logged as outage_start/outage_end pairs;
the system auto-calculates accumulated downtime_min.
VEGA's prompt receives the current-month SLA status automatically.
"""
import datetime
import logging
from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel, Field
from typing import Optional, Literal

from app.deps import get_session, require_operator
from app.database import fetch_one, fetch_all, execute

logger = logging.getLogger(__name__)
router = APIRouter()


# ── Request models ──────────────────────────────────────────────────────────────

class SLAServiceBody(BaseModel):
    service:    str = Field(..., max_length=200)
    target_sla: float = 99.99   # e.g. 99.99 → 99.99%


class SLAEventBody(BaseModel):
    service:      str = Field(..., max_length=200)
    event_type:   Literal["outage_start", "outage_end", "degraded_start", "degraded_end"]
    occurred_at:  Optional[str] = None    # ISO datetime; defaults to NOW()
    zabbix_event: Optional[str] = None
    impact_note:  Optional[str] = Field(None, max_length=500)


# ── Helpers ─────────────────────────────────────────────────────────────────────

def _current_month() -> str:
    return datetime.date.today().replace(day=1).isoformat()


def _minutes_in_month() -> int:
    today = datetime.date.today()
    # days elapsed this month × 1440
    return today.day * 24 * 60


async def _ensure_sla_row(service: str, month: str) -> None:
    """Create sla_tracker row if not present."""
    existing = await fetch_one(
        "SELECT id FROM sla_tracker WHERE service=%s AND month=%s",
        (service, month),
    )
    if not existing:
        await execute(
            "INSERT IGNORE INTO sla_tracker (service, target_sla, month) VALUES (%s, 99.99, %s)",
            (service, month),
        )


async def _recalc_downtime(service: str, month: str) -> None:
    """
    Recalculate total downtime_min for `service` this month from sla_events pairs.
    Pairs: outage_start → outage_end (or degraded_start → degraded_end).
    """
    month_dt   = datetime.date.fromisoformat(month)
    month_start = datetime.datetime(month_dt.year, month_dt.month, 1)
    # End of month boundary
    if month_dt.month == 12:
        month_end = datetime.datetime(month_dt.year + 1, 1, 1)
    else:
        month_end = datetime.datetime(month_dt.year, month_dt.month + 1, 1)

    events = await fetch_all(
        "SELECT event_type, occurred_at FROM sla_events "
        "WHERE service=%s AND occurred_at >= %s AND occurred_at < %s "
        "ORDER BY occurred_at ASC",
        (service, month_start, month_end),
    )

    total_min = 0
    outage_open_at = None
    degraded_open_at = None

    for ev in events:
        et = ev.get("event_type", "")
        ts = ev.get("occurred_at")
        if ts is None:
            continue
        if isinstance(ts, str):
            ts = datetime.datetime.fromisoformat(ts)

        if et == "outage_start":
            outage_open_at = ts
        elif et == "outage_end" and outage_open_at:
            diff = (ts - outage_open_at).total_seconds() / 60
            total_min += int(diff)
            outage_open_at = None
        elif et == "degraded_start":
            degraded_open_at = ts
        elif et == "degraded_end" and degraded_open_at:
            diff = (ts - degraded_open_at).total_seconds() / 60
            # Degraded counts as 50% downtime weight
            total_min += int(diff * 0.5)
            degraded_open_at = None

    # Handle still-open outage: count up to now
    now = datetime.datetime.utcnow()
    if outage_open_at and outage_open_at < now:
        diff = (min(now, month_end) - outage_open_at).total_seconds() / 60
        total_min += int(diff)
    if degraded_open_at and degraded_open_at < now:
        diff = (min(now, month_end) - degraded_open_at).total_seconds() / 60
        total_min += int(diff * 0.5)

    await execute(
        "UPDATE sla_tracker SET downtime_min=%s WHERE service=%s AND month=%s",
        (total_min, service, month),
    )


def _compute_uptime(target_sla: float, downtime_min: int, month: str) -> dict:
    minutes_in_month = _minutes_in_month()
    uptime_pct = round((minutes_in_month - downtime_min) / minutes_in_month * 100, 4)
    budget_min = round((100 - target_sla) / 100 * minutes_in_month, 1)
    used_pct   = round(downtime_min / budget_min * 100, 1) if budget_min > 0 else 0
    status     = "ok"
    if uptime_pct < target_sla:
        status = "breached"
    elif used_pct >= 80:
        status = "at_risk"
    return {
        "uptime_pct":   uptime_pct,
        "downtime_min": downtime_min,
        "budget_min":   budget_min,
        "used_pct":     used_pct,
        "status":       status,
    }


# ── List current-month SLA ──────────────────────────────────────────────────────

@router.get("")
async def get_current_sla(session: dict = Depends(get_session)):
    """Return SLA status for all tracked services for the current month."""
    month = _current_month()
    rows  = await fetch_all(
        "SELECT service, target_sla, downtime_min, calculated_at "
        "FROM sla_tracker WHERE month=%s ORDER BY service",
        (month,),
    )
    result = []
    for r in rows:
        computed = _compute_uptime(
            float(r.get("target_sla", 99.99)),
            int(r.get("downtime_min", 0)),
            month,
        )
        result.append({
            "service":        r["service"],
            "target_sla":     float(r["target_sla"]),
            "month":          month,
            "calculated_at":  str(r.get("calculated_at", "")),
            **computed,
        })
    return result


@router.get("/{service}")
async def get_service_sla(
    service: str,
    month: Optional[str] = None,
    session: dict = Depends(get_session),
):
    """Return SLA history for a specific service (current month by default)."""
    month = month or _current_month()
    row   = await fetch_one(
        "SELECT * FROM sla_tracker WHERE service=%s AND month=%s",
        (service, month),
    )
    if not row:
        raise HTTPException(404, f"No SLA data for service '{service}' in {month}")

    computed = _compute_uptime(
        float(row.get("target_sla", 99.99)),
        int(row.get("downtime_min", 0)),
        month,
    )
    return {
        "service":    row["service"],
        "target_sla": float(row["target_sla"]),
        "month":      month,
        **computed,
    }


# ── Add / Update service target ─────────────────────────────────────────────────

@router.post("/service")
async def upsert_service(body: SLAServiceBody, session: dict = Depends(require_operator)):
    """Add a new tracked service or update its SLA target for the current month."""
    month = _current_month()
    await execute(
        "INSERT INTO sla_tracker (service, target_sla, month) VALUES (%s,%s,%s) "
        "ON DUPLICATE KEY UPDATE target_sla=%s",
        (body.service, body.target_sla, month, body.target_sla),
    )
    return {"ok": True, "service": body.service, "target_sla": body.target_sla}


# ── Log outage event ─────────────────────────────────────────────────────────────

@router.post("/event")
async def log_sla_event(body: SLAEventBody, session: dict = Depends(get_session)):
    """
    Log an outage or degraded-service event.
    When event_type is *_end, downtime is automatically recalculated.
    """
    month = _current_month()
    await _ensure_sla_row(body.service, month)

    occurred_at = body.occurred_at or datetime.datetime.utcnow().isoformat()

    event_id = await execute(
        "INSERT INTO sla_events (service, event_type, zabbix_event, impact_note, occurred_at) "
        "VALUES (%s,%s,%s,%s,%s)",
        (body.service, body.event_type, body.zabbix_event, body.impact_note, occurred_at),
    )

    # Recalculate accumulated downtime whenever an outage_end or degraded_end arrives
    if body.event_type in ("outage_end", "degraded_end"):
        await _recalc_downtime(body.service, month)

    return {"ok": True, "event_id": event_id}


# ── Event history ────────────────────────────────────────────────────────────────

@router.get("/events/{service}")
async def get_service_events(
    service: str,
    month: Optional[str] = None,
    session: dict = Depends(get_session),
):
    """Return outage/degraded events for a service this month."""
    month = month or _current_month()
    month_dt    = datetime.date.fromisoformat(month)
    month_start = datetime.datetime(month_dt.year, month_dt.month, 1)
    if month_dt.month == 12:
        month_end = datetime.datetime(month_dt.year + 1, 1, 1)
    else:
        month_end = datetime.datetime(month_dt.year, month_dt.month + 1, 1)

    rows = await fetch_all(
        "SELECT id, event_type, zabbix_event, impact_note, occurred_at "
        "FROM sla_events "
        "WHERE service=%s AND occurred_at >= %s AND occurred_at < %s "
        "ORDER BY occurred_at ASC",
        (service, month_start, month_end),
    )
    for r in rows:
        r["occurred_at"] = str(r.get("occurred_at", ""))
    return rows
