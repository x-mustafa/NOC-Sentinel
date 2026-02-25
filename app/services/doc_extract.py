"""
Document text extraction for AI employee attachments.
Supports: PDF, DOCX, PPTX, XLSX, TXT, MD, CSV, legacy DOC/PPT
"""
import os
import re
import io
import logging
from pathlib import Path

logger = logging.getLogger(__name__)


def extract_doc_text(name: str, mime_type: str, raw: bytes) -> str:
    ext = Path(name).suffix.lower().lstrip(".")

    if ext in ("txt", "md", "csv", "log", "json", "xml", "yaml", "yml"):
        try:
            return raw[:12000].decode("utf-8", errors="replace")
        except Exception:
            return ""

    if ext in ("docx", "pptx", "xlsx"):
        return _extract_zip_xml(raw, ext)

    if ext == "doc":
        return _extract_binary_strings(raw)

    if ext == "ppt":
        return _extract_binary_strings(raw)

    if ext == "pdf":
        return _extract_pdf_text(raw)

    # Fallback — try as text
    try:
        text = raw.decode("utf-8", errors="replace")
        return re.sub(r"[^\x20-\x7E\n\r\t]", "", text)
    except Exception:
        return ""


def _extract_zip_xml(raw: bytes, ext: str) -> str:
    import zipfile
    text = ""
    try:
        with zipfile.ZipFile(io.BytesIO(raw)) as zf:
            if ext == "docx":
                with zf.open("word/document.xml") as f:
                    xml = f.read().decode("utf-8", errors="replace")
                xml = xml.replace("</w:p>", "\n").replace("</w:tr>", "\n")
                text = re.sub(r"<[^>]+>", "", xml)

            elif ext == "pptx":
                slide_names = sorted(
                    [n for n in zf.namelist() if re.match(r"ppt/slides/slide\d+\.xml", n)]
                )
                for i, slide_name in enumerate(slide_names, 1):
                    with zf.open(slide_name) as f:
                        xml = f.read().decode("utf-8", errors="replace")
                    xml = xml.replace("</a:p>", "\n")
                    text += f"-- Slide {i} --\n" + re.sub(r"<[^>]+>", "", xml) + "\n"

            elif ext == "xlsx":
                if "xl/sharedStrings.xml" in zf.namelist():
                    with zf.open("xl/sharedStrings.xml") as f:
                        ss = f.read().decode("utf-8", errors="replace")
                    matches = re.findall(r"<t[^>]*>(.*?)</t>", ss, re.DOTALL)
                    text = "\t".join(matches)

    except Exception as e:
        logger.warning(f"ZIP extraction failed: {e}")
        return f"[Could not extract: {e}]"

    # Clean up whitespace
    text = re.sub(r"[ \t]{2,}", " ", text)
    return text.strip()


def _extract_pdf_text(raw: bytes) -> str:
    # Try pypdf first
    try:
        import pypdf
        reader = pypdf.PdfReader(io.BytesIO(raw))
        pages = []
        for page in reader.pages:
            try:
                pages.append(page.extract_text() or "")
            except Exception:
                pass
        text = "\n".join(pages).strip()
        if text:
            return text
    except Exception:
        pass

    # Fallback: regex-based extraction from raw PDF stream
    raw_str = raw.decode("latin-1", errors="replace")
    text = ""
    bt_blocks = re.findall(r"BT\b(.+?)\bET", raw_str, re.DOTALL)
    for blk in bt_blocks:
        # Tj operator: (text) Tj
        for t in re.findall(r"\(([^)\\]*(?:\\.[^)\\]*)*)\)\s*Tj", blk, re.DOTALL):
            text += _pdf_unescape(t) + " "
        # TJ operator: [(text) n ...] TJ
        for arr in re.findall(r"\[([^\]]*)\]\s*TJ", blk, re.DOTALL):
            for t in re.findall(r"\(([^)\\]*(?:\\.[^)\\]*)*)\)", arr, re.DOTALL):
                text += _pdf_unescape(t)
            text += " "
        if re.search(r"T[dD*]", blk):
            text += "\n"

    text = re.sub(r"\s{3,}", "\n", text).strip()
    return text or "[PDF text could not be extracted — may be image-based]"


def _pdf_unescape(s: str) -> str:
    return (s.replace("\\n", "\n").replace("\\r", "\r").replace("\\t", "\t")
             .replace("\\\\", "\\").replace("\\(", "(").replace("\\)", ")"))


def _extract_binary_strings(raw: bytes) -> str:
    matches = re.findall(rb"[\x20-\x7E]{5,}", raw)
    return " ".join(m.decode("ascii", errors="replace") for m in matches)
