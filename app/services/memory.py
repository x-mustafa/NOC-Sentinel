"""
Employee memory system.
After each completed task, extract learnings and store them.
On the next task, inject past memories into the system prompt.
"""
import json
import logging
import httpx
from app.database import fetch_all, execute

logger = logging.getLogger(__name__)

MAX_MEMORY_INJECT = 8   # how many past memories to inject per task
MAX_MEMORY_STORE  = 200  # keep at most this many per employee


async def get_memory_context(employee_id: str) -> str:
    """Fetch recent memories and format them for system prompt injection."""
    memories = await fetch_all(
        "SELECT task_summary, key_learnings FROM employee_memory "
        "WHERE employee_id = %s ORDER BY created_at DESC LIMIT %s",
        (employee_id, MAX_MEMORY_INJECT),
    )
    if not memories:
        return ""

    lines = []
    for m in memories:
        summary = m.get("task_summary") or ""
        learnings = m.get("key_learnings") or ""
        if summary or learnings:
            lines.append(f"- Task: {summary}\n  Learned: {learnings}")

    if not lines:
        return ""

    return "\n\nPAST EXPERIENCE (what you've learned from previous tasks):\n" + "\n".join(lines)


async def save_memory(
    employee_id: str,
    task_type: str,
    task_prompt: str,
    ai_response: str,
    api_key: str,
    provider: str = "claude",
    model: str = "claude-haiku-4-5-20251001",
) -> None:
    """
    After a task completes, ask the AI to summarise it and extract learnings.
    Store the result in employee_memory.
    """
    if not ai_response or len(ai_response) < 50:
        return

    extraction_prompt = (
        "Summarise the following AI-generated work in 1 short sentence (max 120 chars). "
        "Then list 2-3 key technical learnings or findings as a comma-separated list.\n\n"
        f"TASK: {task_prompt[:300]}\n\nRESPONSE EXCERPT:\n{ai_response[:1500]}\n\n"
        "Reply in EXACTLY this JSON format (no markdown, no extra text):\n"
        '{"summary": "...", "learnings": "..."}'
    )

    result = None
    try:
        if provider == "claude":
            result = await _call_claude(api_key, model, extraction_prompt)
        elif provider in ("openai", "grok"):
            url = "https://api.openai.com/v1/chat/completions" if provider == "openai" else "https://api.x.ai/v1/chat/completions"
            result = await _call_openai_compat(api_key, model, url, extraction_prompt)
        elif provider == "gemini":
            result = await _call_gemini(api_key, model, extraction_prompt)
    except Exception as e:
        logger.warning(f"Memory extraction failed: {e}")
        return

    if not result:
        return

    try:
        data = json.loads(result)
        summary   = str(data.get("summary", ""))[:500]
        learnings = str(data.get("learnings", ""))[:1000]
    except Exception:
        # Try to parse partial response
        summary   = task_prompt[:120]
        learnings = ai_response[:200]

    await execute(
        "INSERT INTO employee_memory (employee_id, task_type, task_summary, key_learnings) VALUES (%s,%s,%s,%s)",
        (employee_id, task_type, summary, learnings),
    )

    # Prune old memories beyond MAX_MEMORY_STORE
    await execute(
        "DELETE FROM employee_memory WHERE employee_id = %s AND id NOT IN "
        "(SELECT id FROM (SELECT id FROM employee_memory WHERE employee_id = %s "
        "ORDER BY created_at DESC LIMIT %s) t)",
        (employee_id, employee_id, MAX_MEMORY_STORE),
    )


async def get_memories(employee_id: str, limit: int = 50) -> list[dict]:
    return await fetch_all(
        "SELECT id, task_type, task_summary, key_learnings, created_at "
        "FROM employee_memory WHERE employee_id = %s ORDER BY created_at DESC LIMIT %s",
        (employee_id, limit),
    )


# ── Mini AI callers for extraction (non-streaming) ────────────────────────────

async def _call_claude(key: str, model: str, prompt: str) -> str | None:
    async with httpx.AsyncClient(verify=False, timeout=30) as client:
        resp = await client.post(
            "https://api.anthropic.com/v1/messages",
            headers={"x-api-key": key, "anthropic-version": "2023-06-01", "Content-Type": "application/json"},
            json={"model": model, "max_tokens": 300,
                  "messages": [{"role": "user", "content": prompt}]},
        )
        data = resp.json()
        return data.get("content", [{}])[0].get("text")


async def _call_openai_compat(key: str, model: str, url: str, prompt: str) -> str | None:
    async with httpx.AsyncClient(verify=False, timeout=30) as client:
        resp = await client.post(
            url,
            headers={"Authorization": f"Bearer {key}", "Content-Type": "application/json"},
            json={"model": model, "max_tokens": 300,
                  "messages": [{"role": "user", "content": prompt}]},
        )
        data = resp.json()
        return data.get("choices", [{}])[0].get("message", {}).get("content")


async def _call_gemini(key: str, model: str, prompt: str) -> str | None:
    url = f"https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={key}"
    async with httpx.AsyncClient(verify=False, timeout=30) as client:
        resp = await client.post(
            url,
            headers={"Content-Type": "application/json"},
            json={"contents": [{"role": "user", "parts": [{"text": prompt}]}],
                  "generationConfig": {"maxOutputTokens": 300}},
        )
        data = resp.json()
        return data.get("candidates", [{}])[0].get("content", {}).get("parts", [{}])[0].get("text")
