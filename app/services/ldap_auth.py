from typing import Union
import logging

logger = logging.getLogger(__name__)


async def try_ldap_auth(username: str, password: str, cfg: dict) -> Union[dict, bool, None]:
    """
    Returns:
        dict  — authenticated, with role/display_name/email/dn
        False — user found but wrong password
        None  — user not found in LDAP (fall through to local auth)
    """
    try:
        from ldap3 import Server, Connection, ALL, SUBTREE
        import ldap3
    except ImportError:
        logger.warning("ldap3 not installed — LDAP auth unavailable")
        return None

    host = cfg.get("host", "")
    port = int(cfg.get("port", 389))
    bind_dn = cfg.get("bind_dn", "")
    bind_pass = cfg.get("bind_pass", "")
    base_dn = cfg.get("base_dn", "")
    user_filter_tmpl = cfg.get("user_filter", "(&(objectClass=user)(sAMAccountName=%s))")
    admin_group = (cfg.get("admin_group") or "").lower()
    operator_group = (cfg.get("operator_group") or "").lower()
    use_tls = bool(cfg.get("use_tls", 0))

    if not host:
        return None

    try:
        tls = ldap3.Tls() if use_tls else None
        server = Server(host, port=port, get_info=ALL, connect_timeout=8, tls=tls)
        conn = Connection(server, user=bind_dn, password=bind_pass, auto_bind=True)
    except Exception as e:
        logger.warning(f"LDAP bind failed: {e}")
        return None

    user_filter = user_filter_tmpl.replace("%s", username)
    try:
        conn.search(base_dn, user_filter, search_scope=SUBTREE,
                    attributes=["dn", "displayName", "mail", "memberOf"])
    except Exception as e:
        logger.warning(f"LDAP search failed: {e}")
        return None

    if not conn.entries:
        conn.unbind()
        return None  # not in LDAP

    entry = conn.entries[0]
    user_dn = str(entry.entry_dn)

    # Verify password by binding as the user
    try:
        user_conn = Connection(server, user=user_dn, password=password, auto_bind=True)
        user_conn.unbind()
    except Exception:
        conn.unbind()
        return False  # user exists but wrong password

    # Determine role from group membership
    role = "viewer"
    member_of = []
    if hasattr(entry, "memberOf") and entry.memberOf:
        member_of = [str(g).lower() for g in entry.memberOf.values]

    if admin_group and any(admin_group in g for g in member_of):
        role = "admin"
    elif operator_group and any(operator_group in g for g in member_of):
        role = "operator"

    display_name = str(entry.displayName) if hasattr(entry, "displayName") and entry.displayName else username
    email = str(entry.mail) if hasattr(entry, "mail") and entry.mail else ""

    conn.unbind()

    return {
        "dn": user_dn,
        "display_name": display_name,
        "email": email,
        "role": role,
    }


async def test_ldap_connection(host: str, port: int, bind_dn: str, bind_pass: str,
                                base_dn: str, use_tls: bool = False) -> dict:
    """Test LDAP connectivity. Returns {ok, message} or raises."""
    try:
        from ldap3 import Server, Connection, ALL, BASE
        import ldap3
    except ImportError:
        return {"ok": False, "error": "ldap3 not installed"}

    try:
        tls = ldap3.Tls() if use_tls else None
        server = Server(host, port=port, get_info=ALL, connect_timeout=8, tls=tls)
        conn = Connection(server, user=bind_dn, password=bind_pass, auto_bind=True)
    except Exception as e:
        return {"ok": False, "error": f"Bind failed: {e}"}

    try:
        conn.search(base_dn, "(objectClass=*)", search_scope=BASE, attributes=["dn"], size_limit=1)
        count = len(conn.entries)
    except Exception:
        count = 0
    finally:
        conn.unbind()

    return {"ok": True, "message": f"Connected successfully. Base DN returned {count} entr{'y' if count == 1 else 'ies'}."}
