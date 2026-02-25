from fastapi import FastAPI
from fastapi.staticfiles import StaticFiles
from fastapi.responses import FileResponse, JSONResponse
from starlette.middleware.sessions import SessionMiddleware
from contextlib import asynccontextmanager
import logging
import os

from app.config import settings
from app.database import close_pool, run_migration
from app.routers import auth, zabbix, nodes, users, discover, import_router, chat, office, workflows, vault
from app.services.workflow_engine import start_engine, stop_engine

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


@asynccontextmanager
async def lifespan(app: FastAPI):
    logger.info("Starting NOC Sentinel Python backend...")
    await run_migration()
    logger.info("DB migrations done.")
    await start_engine()
    yield
    await stop_engine()
    await close_pool()
    logger.info("Shutdown complete.")


app = FastAPI(title="NOC Sentinel", version="2.0.0", lifespan=lifespan)

app.add_middleware(
    SessionMiddleware,
    secret_key=settings.app_secret,
    max_age=settings.session_max_age,
    same_site="lax",
    https_only=False,
    session_cookie="noc_session",
)

# API routers
app.include_router(auth.router,          prefix="/api/auth",      tags=["auth"])
app.include_router(zabbix.router,        prefix="/api/zabbix",    tags=["zabbix"])
app.include_router(nodes.router,         prefix="/api/nodes",     tags=["nodes"])
app.include_router(nodes.layout_router,  prefix="/api/layout",    tags=["layout"])
app.include_router(users.router,         prefix="/api/users",     tags=["users"])
app.include_router(discover.router,      prefix="/api/discover",  tags=["discover"])
app.include_router(import_router.router, prefix="/api/import",    tags=["import"])
app.include_router(chat.router,          prefix="/api/chat",      tags=["chat"])
app.include_router(office.router,        prefix="/api/office",    tags=["office"])
app.include_router(workflows.router,     prefix="/api/workflows", tags=["workflows"])
app.include_router(vault.router)

# Static files
static_dir = os.path.join(os.path.dirname(__file__), "..", "static")
static_dir = os.path.normpath(static_dir)
if os.path.isdir(static_dir):
    app.mount("/static", StaticFiles(directory=static_dir), name="static")


_NO_CACHE = {"Cache-Control": "no-cache, no-store, must-revalidate", "Pragma": "no-cache"}


@app.get("/")
async def index():
    html_path = os.path.join(static_dir, "index.html")
    if os.path.isfile(html_path):
        return FileResponse(html_path, headers=_NO_CACHE)
    return JSONResponse({"status": "NOC Sentinel API running", "docs": "/docs"})


@app.get("/{path:path}")
async def spa_fallback(path: str):
    if path.startswith("api/"):
        return JSONResponse({"error": "Not found"}, status_code=404)
    # Serve static files directly (images, fonts, etc.)
    file_path = os.path.join(static_dir, path)
    if os.path.isfile(file_path):
        return FileResponse(file_path)
    # SPA fallback — return index.html for everything else
    html_path = os.path.join(static_dir, "index.html")
    if os.path.isfile(html_path):
        return FileResponse(html_path, headers=_NO_CACHE)
    return JSONResponse({"error": "Frontend not built yet"}, status_code=404)
