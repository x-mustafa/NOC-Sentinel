"""
Microsoft 365 integration service.
- Send emails via SMTP (smtp.office365.com)
- Send Teams messages via incoming webhook
- Read recent inbox via IMAP
- Each AI employee is identified by name in the From display name
"""
import asyncio
import email as email_lib
import imaplib
import logging
import ssl
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from typing import Optional

import httpx

logger = logging.getLogger(__name__)

EMPLOYEE_NAMES = {
    "aria":   "ARIA — NOC Analyst",
    "nexus":  "NEXUS — Infrastructure Engineer",
    "cipher": "CIPHER — Security Analyst",
    "vega":   "VEGA — Site Reliability Engineer",
}


# ── Email sending via SMTP ────────────────────────────────────────────────────

async def send_email(
    to: str | list[str],
    subject: str,
    body: str,
    employee_id: str = "aria",
    html: bool = False,
) -> dict:
    """Send an email via Office 365 SMTP on behalf of an AI employee."""
    from app.config import settings
    if not settings.ms365_email or not settings.ms365_password:
        return {"ok": False, "error": "M365 credentials not configured"}

    try:
        import aiosmtplib
    except ImportError:
        return {"ok": False, "error": "aiosmtplib not installed — run: pip install aiosmtplib"}

    to_list = [to] if isinstance(to, str) else to
    emp_name = EMPLOYEE_NAMES.get(employee_id, employee_id.upper())
    from_addr = f"{emp_name} via NOC Sentinel <{settings.ms365_email}>"

    msg = MIMEMultipart("alternative")
    msg["Subject"] = subject
    msg["From"]    = from_addr
    msg["To"]      = ", ".join(to_list)
    msg["Reply-To"] = settings.ms365_email

    content_type = "html" if html else "plain"
    msg.attach(MIMEText(body, content_type, "utf-8"))

    try:
        await aiosmtplib.send(
            msg,
            hostname=settings.ms365_smtp_host,
            port=settings.ms365_smtp_port,
            username=settings.ms365_email,
            password=settings.ms365_password,
            start_tls=True,
        )
        logger.info(f"Email sent to {to_list} from {employee_id}")
        return {"ok": True, "to": to_list, "subject": subject}
    except Exception as e:
        logger.error(f"SMTP send failed: {e}")
        return {"ok": False, "error": str(e)}


# ── Teams message via incoming webhook ────────────────────────────────────────

async def send_teams_message(
    webhook_url: str,
    message: str,
    title: str = "",
    employee_id: str = "aria",
) -> dict:
    """Send a message to a Microsoft Teams channel via incoming webhook."""
    if not webhook_url:
        return {"ok": False, "error": "No Teams webhook URL configured"}

    emp_name  = EMPLOYEE_NAMES.get(employee_id, employee_id.upper())
    emp_colors = {"aria": "00D4FF", "nexus": "A855F7", "cipher": "FF8C00", "vega": "4ADE80"}
    color = emp_colors.get(employee_id, "0078D4")

    # Adaptive Card payload for rich Teams message
    card_body = []
    if title:
        card_body.append({"type": "TextBlock", "text": title, "weight": "Bolder", "size": "Medium"})
    card_body.append({
        "type": "TextBlock", "text": message,
        "wrap": True, "color": "Default"
    })
    card_body.append({
        "type": "TextBlock",
        "text": f"— {emp_name} | NOC Sentinel",
        "size": "Small", "color": "Accent", "isSubtle": True
    })

    payload = {
        "type": "message",
        "attachments": [{
            "contentType": "application/vnd.microsoft.card.adaptive",
            "content": {
                "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
                "type": "AdaptiveCard",
                "version": "1.4",
                "body": card_body,
                "msteams": {"width": "Full"}
            }
        }]
    }

    try:
        async with httpx.AsyncClient(timeout=15, verify=False) as client:
            resp = await client.post(webhook_url, json=payload,
                                     headers={"Content-Type": "application/json"})
        if resp.status_code in (200, 202):
            return {"ok": True, "status": resp.status_code}
        return {"ok": False, "error": f"HTTP {resp.status_code}: {resp.text[:200]}"}
    except Exception as e:
        return {"ok": False, "error": str(e)}


# ── Read inbox via IMAP (sync wrapped) ────────────────────────────────────────

def _imap_fetch_inbox(email_addr: str, password: str, host: str, port: int, limit: int = 10) -> list[dict]:
    """Sync IMAP read — call via asyncio.to_thread."""
    try:
        ctx = ssl.create_default_context()
        ctx.check_hostname = False
        ctx.verify_mode = ssl.CERT_NONE
        mail = imaplib.IMAP4_SSL(host, port, ssl_context=ctx)
        mail.login(email_addr, password)
        mail.select("INBOX")
        _, data = mail.search(None, "ALL")
        ids = data[0].split()
        ids = ids[-limit:] if len(ids) > limit else ids
        ids = list(reversed(ids))  # newest first

        messages = []
        for uid in ids:
            _, msg_data = mail.fetch(uid, "(RFC822)")
            raw = msg_data[0][1]
            msg = email_lib.message_from_bytes(raw)
            subject = str(msg.get("Subject", "(no subject)"))
            from_   = str(msg.get("From", ""))
            date_   = str(msg.get("Date", ""))
            body    = ""
            if msg.is_multipart():
                for part in msg.walk():
                    if part.get_content_type() == "text/plain":
                        body = part.get_payload(decode=True).decode(errors="replace")
                        break
            else:
                body = msg.get_payload(decode=True).decode(errors="replace")
            messages.append({
                "uid":     uid.decode(),
                "subject": subject,
                "from":    from_,
                "date":    date_,
                "preview": body[:300].strip(),
            })
        mail.logout()
        return messages
    except Exception as e:
        logger.error(f"IMAP fetch failed: {e}")
        return [{"error": str(e)}]


async def get_inbox(limit: int = 10) -> list[dict]:
    """Read recent emails from NOC Sentinel inbox."""
    from app.config import settings
    if not settings.ms365_email or not settings.ms365_password:
        return [{"error": "M365 credentials not configured"}]
    return await asyncio.to_thread(
        _imap_fetch_inbox,
        settings.ms365_email, settings.ms365_password,
        settings.ms365_imap_host, settings.ms365_imap_port,
        limit,
    )


# ── Test connectivity ─────────────────────────────────────────────────────────

async def test_smtp() -> dict:
    """Test SMTP connection without sending an email."""
    from app.config import settings
    if not settings.ms365_email or not settings.ms365_password:
        return {"ok": False, "error": "Credentials not configured"}
    try:
        import aiosmtplib
        smtp = aiosmtplib.SMTP(
            hostname=settings.ms365_smtp_host,
            port=settings.ms365_smtp_port,
            start_tls=True,
        )
        await smtp.connect()
        await smtp.login(settings.ms365_email, settings.ms365_password)
        await smtp.quit()
        return {"ok": True, "message": f"SMTP connected as {settings.ms365_email}"}
    except Exception as e:
        return {"ok": False, "error": str(e)}
