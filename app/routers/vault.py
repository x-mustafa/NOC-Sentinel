from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from app.database import fetch_all, fetch_one, execute
from app.deps import get_session

router = APIRouter(prefix="/api/vault", tags=["vault"])


class VaultEntry(BaseModel):
    name: str
    category: str = "Other"
    value: str
    notes: str = ""
    share_with_ai: int = 1


@router.get("")
async def list_vault(session: dict = Depends(get_session)):
    rows = await fetch_all(
        "SELECT id, name, category, notes, share_with_ai, created_at, "
        "CONCAT(LEFT(`value`,4), REPEAT('*', GREATEST(0, CHAR_LENGTH(`value`)-4))) AS value_masked "
        "FROM vault_entries ORDER BY category, name"
    )
    return rows


@router.get("/secrets")
async def get_vault_secrets(session: dict = Depends(get_session)):
    """Return full values — used for injecting into AI employee context."""
    rows = await fetch_all(
        "SELECT name, category, value, notes FROM vault_entries WHERE share_with_ai=1 ORDER BY category, name"
    )
    return rows


@router.get("/{entry_id}")
async def get_vault_entry(entry_id: int, session: dict = Depends(get_session)):
    row = await fetch_one("SELECT * FROM vault_entries WHERE id=%s", (entry_id,))
    if not row:
        raise HTTPException(404, "Not found")
    return row


@router.post("")
async def create_vault_entry(body: VaultEntry, session: dict = Depends(get_session)):
    entry_id = await execute(
        "INSERT INTO vault_entries (name, category, value, notes, share_with_ai) VALUES (%s,%s,%s,%s,%s)",
        (body.name, body.category, body.value, body.notes, body.share_with_ai),
    )
    return {"id": entry_id}


@router.put("/{entry_id}")
async def update_vault_entry(entry_id: int, body: VaultEntry, session: dict = Depends(get_session)):
    await execute(
        "UPDATE vault_entries SET name=%s, category=%s, value=%s, notes=%s, share_with_ai=%s WHERE id=%s",
        (body.name, body.category, body.value, body.notes, body.share_with_ai, entry_id),
    )
    return {"ok": True}


@router.delete("/{entry_id}")
async def delete_vault_entry(entry_id: int, session: dict = Depends(get_session)):
    await execute("DELETE FROM vault_entries WHERE id=%s", (entry_id,))
    return {"ok": True}
