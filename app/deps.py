from fastapi import Request, HTTPException


def get_session(request: Request) -> dict:
    uid = request.session.get("uid")
    if not uid:
        raise HTTPException(status_code=401, detail="Unauthorized")
    return dict(request.session)


def require_admin(request: Request) -> dict:
    session = get_session(request)
    if session.get("role") != "admin":
        raise HTTPException(status_code=403, detail="Admin only")
    return session


def require_operator(request: Request) -> dict:
    session = get_session(request)
    if session.get("role") not in ("admin", "operator"):
        raise HTTPException(status_code=403, detail="Operator+ required")
    return session
