from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel, Field
from typing import Optional
from app.database import fetch_all, fetch_one, execute
from app.deps import get_session, require_operator, require_admin

router = APIRouter(prefix="/api/vault", tags=["vault"])


class VaultEntry(BaseModel):
    name:          str = Field(..., max_length=200)
    category:      str = Field("Other", max_length=50)
    value:         str = Field(..., max_length=4000)
    notes:         Optional[str] = Field("", max_length=1000)
    share_with_ai: int = 1


@router.get("")
async def list_vault(session: dict = Depends(require_operator)):
    """List vault entries with values masked. Requires operator role."""
    rows = await fetch_all(
        "SELECT id, name, category, notes, share_with_ai, created_at, "
        "CONCAT(LEFT(`value`,4), REPEAT('*', GREATEST(0, CHAR_LENGTH(`value`)-4))) AS value_masked "
        "FROM vault_entries ORDER BY category, name"
    )
    return rows


@router.get("/secrets")
async def get_vault_secrets(session: dict = Depends(require_operator)):
    """Return full values for AI context injection. Requires operator role."""
    rows = await fetch_all(
        "SELECT name, category, value, notes FROM vault_entries WHERE share_with_ai=1 ORDER BY category, name"
    )
    return rows


@router.get("/{entry_id}")
async def get_vault_entry(entry_id: int, session: dict = Depends(require_operator)):
    row = await fetch_one("SELECT * FROM vault_entries WHERE id=%s", (entry_id,))
    if not row:
        raise HTTPException(404, "Not found")
    return row


@router.post("")
async def create_vault_entry(body: VaultEntry, session: dict = Depends(require_operator)):
    entry_id = await execute(
        "INSERT INTO vault_entries (name, category, value, notes, share_with_ai) VALUES (%s,%s,%s,%s,%s)",
        (body.name, body.category, body.value, body.notes or "", body.share_with_ai),
    )
    return {"id": entry_id}


@router.put("/{entry_id}")
async def update_vault_entry(entry_id: int, body: VaultEntry, session: dict = Depends(require_operator)):
    row = await fetch_one("SELECT id FROM vault_entries WHERE id=%s", (entry_id,))
    if not row:
        raise HTTPException(404, "Not found")
    await execute(
        "UPDATE vault_entries SET name=%s, category=%s, value=%s, notes=%s, share_with_ai=%s WHERE id=%s",
        (body.name, body.category, body.value, body.notes or "", body.share_with_ai, entry_id),
    )
    return {"ok": True}


@router.delete("/{entry_id}")
async def delete_vault_entry(entry_id: int, session: dict = Depends(require_admin)):
    await execute("DELETE FROM vault_entries WHERE id=%s", (entry_id,))
    return {"ok": True}
