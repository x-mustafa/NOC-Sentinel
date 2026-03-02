from fastapi import APIRouter, Request, HTTPException, Depends
from pydantic import BaseModel
from collections import defaultdict
import secrets
import time

from app.database import fetch_one, execute
from app.deps import get_session
from app.services.ldap_auth import try_ldap_auth
from app.utils.password import hash_password, verify_password

router = APIRouter()

# ── Login rate limiter (in-memory; replace with Redis in scaled deployments) ──
_login_attempts: dict[str, list[float]] = defaultdict(list)
_LOGIN_WINDOW   = 900   # 15-minute sliding window
_LOGIN_MAX      = 10    # max attempts before lockout


def _check_rate_limit(ip: str) -> None:
    now      = time.time()
    recent   = [t for t in _login_attempts[ip] if now - t < _LOGIN_WINDOW]
    _login_attempts[ip] = recent
    if len(recent) >= _LOGIN_MAX:
        raise HTTPException(429, "Too many login attempts. Try again in 15 minutes.")
    _login_attempts[ip].append(now)


class LoginBody(BaseModel):
    username: str
    password: str


class PasswordBody(BaseModel):
    current: str
    new: str


@router.post("/login")
async def login(body: LoginBody, request: Request):
    _check_rate_limit(request.client.host if request.client else "unknown")
    username = body.username.strip()
    password = body.password

    if not username or not password:
        raise HTTPException(400, "Username and password required")

    # Try LDAP first
    try:
        ldap_cfg = await fetch_one("SELECT * FROM ldap_config WHERE enabled=1 LIMIT 1")
    except Exception:
        ldap_cfg = None

    if ldap_cfg:
        ldap_result = await try_ldap_auth(username, password, ldap_cfg)
        if ldap_result is False:
            raise HTTPException(401, "Invalid credentials")
        if isinstance(ldap_result, dict):
            existing = await fetch_one("SELECT id FROM users WHERE username=%s LIMIT 1", (username,))
            if existing:
                await execute(
                    "UPDATE users SET role=%s, display_name=%s, email=%s, ldap_dn=%s, last_login=NOW() WHERE id=%s",
                    (ldap_result["role"], ldap_result["display_name"],
                     ldap_result["email"], ldap_result["dn"], existing["id"]),
                )
                uid = existing["id"]
            else:
                uid = await execute(
                    "INSERT INTO users (username, password_hash, role, display_name, email, ldap_dn, last_login) "
                    "VALUES (%s,%s,%s,%s,%s,%s,NOW())",
                    (username, hash_password(secrets.token_hex(16)),
                     ldap_result["role"], ldap_result["display_name"],
                     ldap_result["email"], ldap_result["dn"]),
                )
            request.session["uid"]      = uid
            request.session["username"] = username
            request.session["role"]     = ldap_result["role"]
            return {"ok": True, "user": {"id": uid, "username": username, "role": ldap_result["role"]}}

    # Local DB auth
    row = await fetch_one("SELECT * FROM users WHERE username=%s LIMIT 1", (username,))
    if row and verify_password(password, row["password_hash"]):
        await execute("UPDATE users SET last_login=NOW() WHERE id=%s", (row["id"],))
        request.session["uid"]      = row["id"]
        request.session["username"] = row["username"]
        request.session["role"]     = row["role"]
        return {"ok": True, "user": {"id": row["id"], "username": row["username"], "role": row["role"]}}

    raise HTTPException(401, "Invalid credentials")


@router.post("/logout")
async def logout(request: Request):
    request.session.clear()
    return {"ok": True}


@router.get("/me")
async def me(session: dict = Depends(get_session)):
    return {"id": session["uid"], "username": session["username"], "role": session["role"]}


@router.post("/password")
async def change_password(body: PasswordBody, session: dict = Depends(get_session)):
    if len(body.new) < 4:
        raise HTTPException(400, "Password too short (min 4)")
    row = await fetch_one("SELECT password_hash FROM users WHERE id=%s", (session["uid"],))
    if not row or not verify_password(body.current, row["password_hash"]):
        raise HTTPException(403, "Current password incorrect")
    await execute("UPDATE users SET password_hash=%s WHERE id=%s",
                  (hash_password(body.new), session["uid"]))
    return {"ok": True}
