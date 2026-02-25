import aiomysql
from app.config import settings
import logging

logger = logging.getLogger(__name__)

_pool: aiomysql.Pool | None = None


async def get_pool() -> aiomysql.Pool:
    global _pool
    if _pool is None:
        _pool = await aiomysql.create_pool(
            host=settings.db_host,
            port=settings.db_port,
            user=settings.db_user,
            password=settings.db_pass,
            db=settings.db_name,
            charset="utf8mb4",
            autocommit=True,
            minsize=2,
            maxsize=20,
        )
    return _pool


async def close_pool():
    global _pool
    if _pool:
        _pool.close()
        await _pool.wait_closed()
        _pool = None


async def fetch_one(sql: str, params=None) -> dict | None:
    pool = await get_pool()
    async with pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute(sql, params or ())
            return await cur.fetchone()


async def fetch_all(sql: str, params=None) -> list[dict]:
    pool = await get_pool()
    async with pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute(sql, params or ())
            return await cur.fetchall()


async def execute(sql: str, params=None) -> int:
    """Returns lastrowid for INSERT, rowcount for UPDATE/DELETE."""
    pool = await get_pool()
    async with pool.acquire() as conn:
        async with conn.cursor() as cur:
            await cur.execute(sql, params or ())
            return cur.lastrowid or cur.rowcount


async def execute_many(sql: str, params_list: list) -> None:
    pool = await get_pool()
    async with pool.acquire() as conn:
        async with conn.cursor() as cur:
            await cur.executemany(sql, params_list)


async def run_migration():
    """Create new tables needed by Python version if they don't exist."""
    sqls = [
        """CREATE TABLE IF NOT EXISTS `employee_profiles` (
            `id` VARCHAR(20) PRIMARY KEY,
            `title` VARCHAR(100),
            `responsibilities` TEXT,
            `daily_tasks` TEXT,
            `system_prompt` TEXT,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB""",

        """CREATE TABLE IF NOT EXISTS `employee_memory` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `employee_id` VARCHAR(20) NOT NULL,
            `task_type` VARCHAR(50),
            `task_summary` VARCHAR(500),
            `outcome_summary` TEXT,
            `key_learnings` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_emp_time` (`employee_id`, `created_at`)
        ) ENGINE=InnoDB""",

        """CREATE TABLE IF NOT EXISTS `workflows` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(120) NOT NULL,
            `description` TEXT,
            `trigger_type` ENUM('alarm','schedule','threshold','manual') DEFAULT 'manual',
            `trigger_config` TEXT,
            `employee_id` VARCHAR(20),
            `prompt_template` TEXT,
            `action_type` ENUM('log','webhook','zabbix_ack','email') DEFAULT 'log',
            `action_config` TEXT,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB""",

        """CREATE TABLE IF NOT EXISTS `workflow_runs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `workflow_id` INT NOT NULL,
            `trigger_data` TEXT,
            `ai_response` TEXT,
            `action_result` TEXT,
            `status` ENUM('running','success','error') DEFAULT 'running',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_wf` (`workflow_id`, `created_at`)
        ) ENGINE=InnoDB""",

        """CREATE TABLE IF NOT EXISTS `team_sessions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `topic` VARCHAR(500),
            `participants` TEXT,
            `transcript` LONGTEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_ts_created` (`created_at`)
        ) ENGINE=InnoDB""",

        # Widen action_type to VARCHAR so new action types (whatsapp_group, etc.) can be stored
        """ALTER TABLE `workflows`
           MODIFY COLUMN `action_type` VARCHAR(30) DEFAULT 'log'""",

        """CREATE TABLE IF NOT EXISTS `vault_entries` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(200) NOT NULL,
            `category` VARCHAR(50) DEFAULT 'Other',
            `value` TEXT NOT NULL,
            `notes` TEXT,
            `share_with_ai` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB""",
    ]
    for sql in sqls:
        try:
            await execute(sql)
        except Exception as e:
            logger.warning(f"Migration step failed (may already exist): {e}")

    # Seed default employee profiles
    defaults = [
        ("aria",   "NOC Analyst",             "Alarm triage, incident lifecycle, SLA tracking, shift handover",
         '["Morning shift check","Review critical alarms","Shift handover briefing"]', None),
        ("nexus",  "Infrastructure Engineer", "Network devices, ISP uplinks, HA pairs, capacity planning, automation",
         '["Daily infrastructure health check","Device performance review","Capacity report"]', None),
        ("cipher", "Security Analyst",        "NGFW rules, IPS/IDS tuning, PCI-DSS compliance, threat hunting",
         '["Daily security posture review","Alarm pattern analysis","Threat assessment"]', None),
        ("vega",   "Site Reliability Engineer", "SLOs/SLIs, runbooks, monitoring gaps, DR testing, error budgets",
         '["Daily reliability review","Error budget estimate","Monitoring gap analysis"]', None),
    ]
    for emp_id, title, resp, daily_tasks, prompt in defaults:
        try:
            await execute(
                "INSERT IGNORE INTO employee_profiles (id, title, responsibilities, daily_tasks, system_prompt) VALUES (%s,%s,%s,%s,%s)",
                (emp_id, title, resp, daily_tasks, prompt),
            )
        except Exception:
            pass
