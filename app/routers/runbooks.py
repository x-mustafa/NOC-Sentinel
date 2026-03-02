"""
F6 — Living Runbook System

AI employees maintain a library of runbooks (SOP documents) that are
automatically injected into workflow prompts when alarm keywords match.
Runbooks can be drafted by AI from resolved incidents and promoted to
'approved' status by operators.
"""
import logging
from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel, Field
from typing import Optional, Literal

from app.deps import get_session, require_operator
from app.database import fetch_one, fetch_all, execute

logger = logging.getLogger(__name__)
router = APIRouter()

_VALID_EMPLOYEES = {"aria", "nexus", "cipher", "vega"}


# ── Request models ──────────────────────────────────────────────────────────────

class RunbookBody(BaseModel):
    title:             str = Field(..., max_length=300)
    author_id:         Optional[str] = None
    trigger_desc:      Optional[str] = None
    trigger_keywords:  Optional[str] = None   # comma-separated
    symptoms:          Optional[str] = None
    diagnosis:         Optional[str] = None
    resolution:        Optional[str] = None
    prevention:        Optional[str] = None
    rollback:          Optional[str] = None
    estimated_mttr:    Optional[int] = None   # minutes
    related_hosts:     Optional[str] = None   # comma-separated
    status:            Literal["draft", "approved", "deprecated"] = "draft"


class RunbookPatchBody(BaseModel):
    title:             Optional[str] = Field(None, max_length=300)
    trigger_desc:      Optional[str] = None
    trigger_keywords:  Optional[str] = None
    symptoms:          Optional[str] = None
    diagnosis:         Optional[str] = None
    resolution:        Optional[str] = None
    prevention:        Optional[str] = None
    rollback:          Optional[str] = None
    estimated_mttr:    Optional[int] = None
    related_hosts:     Optional[str] = None
    status:            Optional[Literal["draft", "approved", "deprecated"]] = None
    last_tested:       Optional[str] = None   # YYYY-MM-DD


class MatchBody(BaseModel):
    text: str = Field(..., max_length=1000)   # alarm name or problem description


# ── List / Create ───────────────────────────────────────────────────────────────

@router.get("")
async def list_runbooks(
    status: Optional[str] = None,
    author: Optional[str] = None,
    session: dict = Depends(get_session),
):
    """List runbooks. Defaults to non-deprecated."""
    where, params = [], []
    if status:
        where.append("status=%s")
        params.append(status)
    else:
        where.append("status != 'deprecated'")
    if author:
        where.append("author_id=%s")
        params.append(author)

    clause = "WHERE " + " AND ".join(where) if where else ""
    rows = await fetch_all(
        f"SELECT id, title, author_id, trigger_keywords, estimated_mttr, "
        f"status, related_hosts, last_tested, updated_at "
        f"FROM runbooks {clause} ORDER BY updated_at DESC LIMIT 100",
        tuple(params) or None,
    )
    for r in rows:
        r["updated_at"] = str(r.get("updated_at", ""))
        r["last_tested"] = str(r.get("last_tested", "") or "")
    return rows


@router.post("")
async def create_runbook(body: RunbookBody, session: dict = Depends(get_session)):
    """Create a new runbook."""
    if body.author_id and body.author_id not in _VALID_EMPLOYEES:
        raise HTTPException(400, f"Invalid author_id: {body.author_id}")

    rb_id = await execute(
        "INSERT INTO runbooks "
        "(title, author_id, trigger_desc, trigger_keywords, symptoms, diagnosis, "
        "resolution, prevention, rollback, estimated_mttr, related_hosts, status) "
        "VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)",
        (
            body.title,
            body.author_id,
            body.trigger_desc,
            body.trigger_keywords,
            body.symptoms,
            body.diagnosis,
            body.resolution,
            body.prevention,
            body.rollback,
            body.estimated_mttr,
            body.related_hosts,
            body.status,
        ),
    )
    return {"ok": True, "id": rb_id}


# ── Single Runbook ──────────────────────────────────────────────────────────────

@router.get("/{rb_id}")
async def get_runbook(rb_id: int, session: dict = Depends(get_session)):
    """Get full runbook content."""
    row = await fetch_one("SELECT * FROM runbooks WHERE id=%s", (rb_id,))
    if not row:
        raise HTTPException(404, "Runbook not found")
    row["updated_at"] = str(row.get("updated_at", ""))
    row["created_at"] = str(row.get("created_at", ""))
    row["last_tested"] = str(row.get("last_tested", "") or "")
    return row


@router.patch("/{rb_id}")
async def update_runbook(
    rb_id: int,
    body: RunbookPatchBody,
    session: dict = Depends(get_session),
):
    """Update runbook fields."""
    row = await fetch_one("SELECT id FROM runbooks WHERE id=%s", (rb_id,))
    if not row:
        raise HTTPException(404, "Runbook not found")

    field_map = {
        "title":            body.title,
        "trigger_desc":     body.trigger_desc,
        "trigger_keywords": body.trigger_keywords,
        "symptoms":         body.symptoms,
        "diagnosis":        body.diagnosis,
        "resolution":       body.resolution,
        "prevention":       body.prevention,
        "rollback":         body.rollback,
        "estimated_mttr":   body.estimated_mttr,
        "related_hosts":    body.related_hosts,
        "status":           body.status,
        "last_tested":      body.last_tested,
    }
    sets, vals = [], []
    for col, val in field_map.items():
        if val is not None:
            sets.append(f"{col}=%s")
            vals.append(val)

    if sets:
        vals.append(rb_id)
        await execute(
            "UPDATE runbooks SET " + ",".join(sets) + " WHERE id=%s",
            tuple(vals),
        )
    return {"ok": True}


@router.put("/{rb_id}/approve")
async def approve_runbook(rb_id: int, session: dict = Depends(require_operator)):
    """Promote a draft runbook to approved status (operator only)."""
    row = await fetch_one("SELECT id, status FROM runbooks WHERE id=%s", (rb_id,))
    if not row:
        raise HTTPException(404, "Runbook not found")
    await execute(
        "UPDATE runbooks SET status='approved' WHERE id=%s",
        (rb_id,),
    )
    return {"ok": True}


@router.delete("/{rb_id}")
async def deprecate_runbook(rb_id: int, session: dict = Depends(require_operator)):
    """Mark a runbook as deprecated (soft delete)."""
    row = await fetch_one("SELECT id FROM runbooks WHERE id=%s", (rb_id,))
    if not row:
        raise HTTPException(404, "Runbook not found")
    await execute(
        "UPDATE runbooks SET status='deprecated' WHERE id=%s",
        (rb_id,),
    )
    return {"ok": True}


# ── Keyword Matching ────────────────────────────────────────────────────────────

@router.post("/match")
async def match_runbooks(body: MatchBody, session: dict = Depends(get_session)):
    """
    Find approved runbooks whose trigger_keywords match the given text.
    Returns top 3 matches with a relevance score.
    """
    matches = await find_matching_runbooks(body.text)
    return matches


async def find_matching_runbooks(alarm_text: str, limit: int = 3) -> list[dict]:
    """
    Score approved runbooks by keyword overlap with alarm_text.
    Returns at most `limit` runbooks above score 0, sorted by score desc.
    Used by the workflow engine for automatic injection.
    """
    rows = await fetch_all(
        "SELECT id, title, trigger_keywords, symptoms, diagnosis, resolution, "
        "estimated_mttr FROM runbooks WHERE status='approved'",
    )
    if not rows:
        return []

    alarm_lower = alarm_text.lower()
    scored = []
    for r in rows:
        kw_str = (r.get("trigger_keywords") or "").lower()
        keywords = [k.strip() for k in kw_str.split(",") if k.strip()]
        if not keywords:
            continue
        score = sum(1 for kw in keywords if kw in alarm_lower)
        if score > 0:
            scored.append({"score": score, **r})

    scored.sort(key=lambda x: x["score"], reverse=True)
    return scored[:limit]


def format_runbook_for_prompt(rb: dict) -> str:
    """Format a runbook as a condensed block for system prompt injection."""
    lines = [f"\n---- RUNBOOK: {rb.get('title', 'Untitled')} ----"]
    if rb.get("symptoms"):
        lines.append(f"  Symptoms: {str(rb['symptoms'])[:300]}")
    if rb.get("diagnosis"):
        lines.append(f"  Diagnosis: {str(rb['diagnosis'])[:400]}")
    if rb.get("resolution"):
        lines.append(f"  Resolution: {str(rb['resolution'])[:500]}")
    if rb.get("rollback"):
        lines.append(f"  Rollback: {str(rb['rollback'])[:200]}")
    if rb.get("estimated_mttr"):
        lines.append(f"  Est. MTTR: {rb['estimated_mttr']} min")
    lines.append("---- END RUNBOOK ----")
    return "\n".join(lines)
