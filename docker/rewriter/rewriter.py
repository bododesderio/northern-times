"""
AI article rewriter — calls Ollama to rewrite crawled articles
in the configured publication's editorial style.

Supports:
  - Single-pass rewrite for normal articles
  - Chunked rewrite for long articles (40K+ words)
  - Configurable editorial rules from admin UI
  - Word count tolerance with one retry
"""

import logging
import os
import re

PUBLICATION_NAME = os.environ.get("APP_NAME", "The Northern Times")

import httpx

logger = logging.getLogger(__name__)

OLLAMA_URL     = os.getenv("OLLAMA_URL", "http://ollama:11434")
OLLAMA_MODEL   = os.getenv("OLLAMA_MODEL", "llama3:8b")
WORD_TOLERANCE = int(os.getenv("REWRITER_WORD_TOLERANCE", "50"))

# Block-level HTML tags used for chunk splitting
_BLOCK_TAGS = re.compile(
    r"(<(?:p|h[1-6]|blockquote|ul|ol|li|figure|table|div|hr|section|article|aside|pre|dl|dd|dt)[^>]*>)",
    re.IGNORECASE,
)


def _word_count(text: str) -> int:
    return len(re.sub(r"<[^>]+>", "", text).split())


def _ollama(prompt: str, model: str, timeout: int = 120) -> str | None:
    try:
        resp = httpx.post(
            f"{OLLAMA_URL}/api/generate",
            json={
                "model": model,
                "prompt": prompt,
                "stream": False,
                "options": {"temperature": 0.7, "num_predict": 4096},
            },
            timeout=timeout,
        )
        resp.raise_for_status()
        return (resp.json().get("response") or "").strip()
    except Exception as e:
        logger.error("Ollama call failed: %s", e)
        return None


def _rules_block(rules: str | None) -> str:
    """Build the editorial guidelines section for the prompt."""
    if not rules or not rules.strip():
        return ""
    return (
        "\nEDITORIAL GUIDELINES (follow these strictly):\n"
        + rules.strip()
        + "\n"
    )


def _split_into_chunks(content: str, max_words: int) -> list[str]:
    """
    Split HTML content at block-level tag boundaries into chunks
    that each fit within max_words.

    Returns a list of HTML fragments. If the content is already
    short enough, returns [content] as a single-element list.
    """
    total_wc = _word_count(content)
    if total_wc <= max_words:
        return [content]

    # Split at block-level tag boundaries, keeping the tags
    parts = _BLOCK_TAGS.split(content)

    chunks = []
    current_chunk = ""
    current_wc = 0

    for part in parts:
        part_wc = _word_count(part)

        # If adding this part would exceed the limit, finalize current chunk
        if current_wc > 0 and (current_wc + part_wc) > max_words:
            if current_chunk.strip():
                chunks.append(current_chunk)
            current_chunk = part
            current_wc = part_wc
        else:
            current_chunk += part
            current_wc += part_wc

    # Don't forget the last chunk
    if current_chunk.strip():
        chunks.append(current_chunk)

    # Fallback: if splitting produced nothing useful, return as single chunk
    if not chunks:
        return [content]

    logger.info("Split article into %d chunks (total %d words, max %d/chunk)",
                len(chunks), total_wc, max_words)
    return chunks


def _rewrite_body_single(content: str, model: str, rules: str | None) -> tuple[str | None, int]:
    """Rewrite a single body (or chunk). Returns (text, word_count) or (None, 0)."""
    orig_wc = _word_count(content)
    lo, hi = orig_wc - WORD_TOLERANCE, orig_wc + WORD_TOLERANCE
    rules_text = _rules_block(rules)

    prompt = (
        f"You are a senior editor at {PUBLICATION_NAME}, a professional Ugandan daily newspaper.\n"
        f"Rewrite the following news article body in {PUBLICATION_NAME} editorial style:\n"
        "- Clear, professional East African English\n"
        "- Preserve every fact, name, date, figure and quote exactly\n"
        f"- Target length: approximately {orig_wc} words (acceptable range {lo}–{hi} words)\n"
        "- Third-person journalistic style; no opinions or new information\n"
        "- Output ONLY the rewritten article body — no title, no labels, no preamble\n"
        f"{rules_text}\n"
        f"ORIGINAL ARTICLE:\n{content}\n\nREWRITTEN ARTICLE:"
    )
    result = _ollama(prompt, model)
    if not result:
        return None, 0

    new_wc = _word_count(result)

    # Retry once if word count deviates beyond tolerance
    if abs(new_wc - orig_wc) > WORD_TOLERANCE:
        strict_prompt = (
            f"You are a senior editor at {PUBLICATION_NAME}, a professional Ugandan daily newspaper.\n"
            f"Rewrite this news article body. It MUST be between {lo} and {hi} words.\n"
            "Keep all facts exactly as given. Professional East African English. Third-person style.\n"
            "Output ONLY the rewritten body.\n"
            f"{rules_text}\n"
            f"ORIGINAL ARTICLE:\n{content}\n\n"
            f"REWRITTEN ARTICLE ({lo}–{hi} words):"
        )
        retry = _ollama(strict_prompt, model)
        if retry:
            result = retry
            new_wc = _word_count(result)
        if abs(new_wc - orig_wc) > WORD_TOLERANCE:
            logger.warning(
                "Word count deviation accepted: orig=%d rewritten=%d tolerance=±%d",
                orig_wc, new_wc, WORD_TOLERANCE,
            )

    return result, new_wc


def rewrite_article(
    title: str,
    content: str,
    excerpt: str,
    model: str | None = None,
    rules: str | None = None,
    max_chunk_words: int = 4000,
) -> dict:
    model = model or OLLAMA_MODEL
    orig_wc = _word_count(content)
    rules_text = _rules_block(rules)

    # ── Rewrite body (single-pass or chunked) ────────────────────
    chunks = _split_into_chunks(content, max_chunk_words)
    chunks_processed = len(chunks)

    if chunks_processed == 1:
        # Single-pass rewrite
        new_body, new_wc = _rewrite_body_single(content, model, rules)
        if not new_body:
            return {"success": False, "error": "Ollama unreachable or returned empty response"}
    else:
        # Chunked rewrite for long articles
        logger.info("Chunked rewrite: %d chunks for %d-word article", chunks_processed, orig_wc)
        rewritten_chunks = []
        total_new_wc = 0

        for i, chunk in enumerate(chunks, 1):
            chunk_wc = _word_count(chunk)
            lo = chunk_wc - WORD_TOLERANCE
            hi = chunk_wc + WORD_TOLERANCE

            chunk_prompt = (
                f"You are a senior editor at {PUBLICATION_NAME}, a professional Ugandan daily newspaper.\n"
                f"You are rewriting PART {i} OF {chunks_processed} of a longer article.\n"
                f"Rewrite this section in {PUBLICATION_NAME} editorial style:\n"
                "- Clear, professional East African English\n"
                "- Preserve every fact, name, date, figure and quote exactly\n"
                f"- Target length: approximately {chunk_wc} words\n"
                "- Third-person journalistic style; no opinions or new information\n"
                "- Maintain consistent tone and style with the rest of the article\n"
                "- Output ONLY the rewritten section — no labels, no preamble\n"
                f"{rules_text}\n"
                f"ORIGINAL SECTION:\n{chunk}\n\nREWRITTEN SECTION:"
            )
            rewritten = _ollama(chunk_prompt, model)
            if not rewritten:
                return {
                    "success": False,
                    "error": f"Chunk {i}/{chunks_processed} failed — Ollama returned empty",
                }

            rewritten_chunks.append(rewritten)
            total_new_wc += _word_count(rewritten)
            logger.info("  Chunk %d/%d done (%d words)", i, chunks_processed, _word_count(rewritten))

        new_body = "\n\n".join(rewritten_chunks)
        new_wc = total_new_wc

    # ── Rewrite headline ─────────────────────────────────────────
    title_prompt = (
        f"Rewrite this news headline for {PUBLICATION_NAME} newspaper.\n"
        "Keep the same meaning and all key facts. Be concise and professional.\n"
        "Output ONLY the rewritten headline — no quotes, no labels.\n"
        f"{rules_text}\n"
        f"ORIGINAL HEADLINE: {title}\nREWRITTEN HEADLINE:"
    )
    new_title = _ollama(title_prompt, model, timeout=30) or title
    new_title = new_title.strip().strip('"').strip("'")

    # ── Rewrite excerpt ──────────────────────────────────────────
    if excerpt or new_body:
        source = new_body[:600] if new_body else excerpt
        exc_prompt = (
            f"Write a one-sentence summary (max 30 words) of this article for {PUBLICATION_NAME}.\n"
            "Output ONLY the summary sentence — no labels, no quotes.\n"
            f"{rules_text}\n"
            f"ARTICLE: {source}\nSUMMARY:"
        )
        new_excerpt = _ollama(exc_prompt, model, timeout=30) or excerpt
        new_excerpt = new_excerpt.strip().strip('"').strip("'")
    else:
        new_excerpt = excerpt

    return {
        "success": True,
        "title": new_title,
        "content": new_body,
        "excerpt": new_excerpt,
        "word_count": new_wc,
        "original_word_count": orig_wc,
        "model_used": model,
        "chunks_processed": chunks_processed,
    }
