"""
Async multi-provider SSE streaming for AI employees.
Supports: Claude (Anthropic), OpenAI, Gemini, Grok (xAI)
Each function is an async generator yielding SSE dicts for sse_starlette.
"""
import json
import httpx
import logging
from typing import AsyncGenerator

logger = logging.getLogger(__name__)

SSE_DONE  = {"event": "done",  "data": "{}"}
TIMEOUT   = httpx.Timeout(120.0, connect=15.0)
NO_VERIFY = httpx.create_ssl_context() if False else False   # verify=False


def _sse_text(text: str) -> dict:
    return {"data": json.dumps({"t": text}, ensure_ascii=False)}


def _sse_error(msg: str) -> dict:
    return {"event": "error", "data": json.dumps({"error": msg})}


async def stream_claude(
    key: str, model: str, system: str, user_msg: str,
    images: list[dict] = None, history: list[dict] = None
) -> AsyncGenerator[dict, None]:
    images  = images  or []
    history = history or []
    user_content = []
    for img in images:
        user_content.append({
            "type": "image",
            "source": {"type": "base64", "media_type": img["type"], "data": img["data"]},
        })
    user_content.append({"type": "text", "text": user_msg})
    msg_content = user_msg if (not images and len(user_content) == 1) else user_content

    # Build messages array with history
    messages = []
    for m in history:
        messages.append({"role": m["role"], "content": m["content"]})
    messages.append({"role": "user", "content": msg_content})

    payload = {
        "model":     model,
        "max_tokens": 4096,
        "stream":    True,
        "system":    system,
        "messages":  messages,
    }
    headers = {
        "Content-Type":      "application/json",
        "x-api-key":         key,
        "anthropic-version": "2023-06-01",
    }

    try:
        async with httpx.AsyncClient(verify=False, timeout=TIMEOUT) as client:
            async with client.stream("POST", "https://api.anthropic.com/v1/messages",
                                     headers=headers, json=payload) as resp:
                if resp.status_code != 200:
                    body = await resp.aread()
                    yield _sse_error(f"Claude API error {resp.status_code}: {body.decode()[:200]}")
                    return

                async for line in resp.aiter_lines():
                    if not line.startswith("data:"):
                        continue
                    raw = line[5:].strip()
                    if raw == "[DONE]":
                        break
                    try:
                        ev = json.loads(raw)
                    except Exception:
                        continue
                    if ev.get("type") == "content_block_delta":
                        text = ev.get("delta", {}).get("text", "")
                        if text:
                            yield _sse_text(text)
                    elif ev.get("type") == "message_stop":
                        break
                    elif ev.get("type") == "error":
                        yield _sse_error(ev.get("error", {}).get("message", "Stream error"))
                        return
    except Exception as e:
        yield _sse_error(f"Claude stream error: {e}")
        return

    yield SSE_DONE


async def stream_openai(
    key: str, model: str, system: str, user_msg: str,
    images: list[dict] = None, history: list[dict] = None,
    api_url: str = "https://api.openai.com/v1/chat/completions"
) -> AsyncGenerator[dict, None]:
    images  = images  or []
    history = history or []
    user_content = []
    for img in images:
        user_content.append({
            "type": "image_url",
            "image_url": {"url": f"data:{img['type']};base64,{img['data']}"},
        })
    user_content.append({"type": "text", "text": user_msg})
    msg_content = user_msg if (not images and len(user_content) == 1) else user_content

    # Build messages with history
    messages = [{"role": "system", "content": system}]
    for m in history:
        messages.append({"role": m["role"], "content": m["content"]})
    messages.append({"role": "user", "content": msg_content})

    payload = {
        "model":     model,
        "max_tokens": 4096,
        "stream":    True,
        "messages":  messages,
    }
    headers = {"Content-Type": "application/json", "Authorization": f"Bearer {key}"}

    try:
        async with httpx.AsyncClient(verify=False, timeout=TIMEOUT) as client:
            async with client.stream("POST", api_url, headers=headers, json=payload) as resp:
                if resp.status_code != 200:
                    body = await resp.aread()
                    yield _sse_error(f"API error {resp.status_code}: {body.decode()[:200]}")
                    return

                async for line in resp.aiter_lines():
                    if not line.startswith("data:"):
                        continue
                    raw = line[5:].strip()
                    if raw == "[DONE]":
                        break
                    try:
                        ev = json.loads(raw)
                    except Exception:
                        continue
                    choice = ev.get("choices", [{}])[0]
                    text = choice.get("delta", {}).get("content", "")
                    if text:
                        yield _sse_text(text)
                    if choice.get("finish_reason") == "stop":
                        break
    except Exception as e:
        yield _sse_error(f"OpenAI stream error: {e}")
        return

    yield SSE_DONE


async def stream_grok(
    key: str, model: str, system: str, user_msg: str,
    images: list[dict] = None, history: list[dict] = None
) -> AsyncGenerator[dict, None]:
    async for chunk in stream_openai(
        key, model, system, user_msg, images, history,
        api_url="https://api.x.ai/v1/chat/completions"
    ):
        yield chunk


async def stream_gemini(
    key: str, model: str, system: str, user_msg: str,
    images: list[dict] = None, history: list[dict] = None
) -> AsyncGenerator[dict, None]:
    images  = images  or []
    history = history or []
    url = (
        f"https://generativelanguage.googleapis.com/v1beta/models/"
        f"{model}:streamGenerateContent?key={key}&alt=sse"
    )
    parts = []
    for img in images:
        parts.append({"inlineData": {"mimeType": img["type"], "data": img["data"]}})
    parts.append({"text": user_msg})

    # Build contents with history
    contents = []
    for m in history:
        role = "user" if m["role"] == "user" else "model"
        contents.append({"role": role, "parts": [{"text": m["content"]}]})
    contents.append({"role": "user", "parts": parts})

    payload = {
        "system_instruction": {"parts": [{"text": system}]},
        "contents":           contents,
        "generationConfig":   {"maxOutputTokens": 4096},
    }
    headers = {"Content-Type": "application/json"}

    try:
        async with httpx.AsyncClient(verify=False, timeout=TIMEOUT) as client:
            async with client.stream("POST", url, headers=headers, json=payload) as resp:
                if resp.status_code != 200:
                    body = await resp.aread()
                    yield _sse_error(f"Gemini API error {resp.status_code}: {body.decode()[:200]}")
                    return

                async for line in resp.aiter_lines():
                    if not line.startswith("data:"):
                        continue
                    raw = line[5:].strip()
                    try:
                        ev = json.loads(raw)
                    except Exception:
                        continue
                    candidate = ev.get("candidates", [{}])[0]
                    text = candidate.get("content", {}).get("parts", [{}])[0].get("text", "")
                    if text:
                        yield _sse_text(text)
                    if candidate.get("finishReason") == "STOP":
                        break
    except Exception as e:
        yield _sse_error(f"Gemini stream error: {e}")
        return

    yield SSE_DONE


async def stream_ai(
    provider: str, key: str, model: str, system: str, user_msg: str,
    images: list[dict] = None, history: list[dict] = None
) -> AsyncGenerator[dict, None]:
    """Route to the correct provider's streaming function."""
    if provider == "claude":
        gen = stream_claude(key, model, system, user_msg, images, history)
    elif provider == "openai":
        gen = stream_openai(key, model, system, user_msg, images, history)
    elif provider == "grok":
        gen = stream_grok(key, model, system, user_msg, images, history)
    elif provider == "gemini":
        gen = stream_gemini(key, model, system, user_msg, images, history)
    else:
        yield _sse_error(f"Unknown provider: {provider}")
        return

    async for chunk in gen:
        yield chunk
