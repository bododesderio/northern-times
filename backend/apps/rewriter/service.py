import logging
import re

from django.conf import settings
from openai import OpenAI

logger = logging.getLogger(__name__)


def has_valid_openai_key() -> bool:
    """True only if a real OpenAI key is configured (not empty / placeholder).

    Prevents the rewriter from hammering the API with a placeholder key and
    marking every article 'failed' — articles still publish with their original
    crawled content, so a missing key just means "skip the optional rewrite".
    """
    key = (getattr(settings, 'OPENAI_API_KEY', '') or '').strip()
    if not key or not key.startswith('sk-'):
        return False
    upper = key.upper()
    return not any(tok in upper for tok in ('CHANGE', 'PLACEHOLDER', 'YOUR-', 'XXXX'))


class ArticleRewriter:
    """Rewrites articles using OpenAI API (GPT-4o-mini)."""

    def __init__(self):
        self.client = OpenAI(api_key=settings.OPENAI_API_KEY)
        self.model = settings.OPENAI_MODEL
        self.word_tolerance = settings.REWRITER_WORD_TOLERANCE

    # GPT-4o-mini pricing (per 1K tokens)
    INPUT_COST_PER_1K = 0.00015
    OUTPUT_COST_PER_1K = 0.0006

    def rewrite(self, article, enrich=False):
        """Rewrite a single article. Returns dict with title, content, excerpt, tokens, cost or None on failure.

        If enrich=True (or auto-detected for short/low-quality articles),
        the content is expanded ~1.8x with additional context before rewriting.
        """
        try:
            self._total_tokens = 0
            content = article.content or ''
            word_count = len(content.split())

            # Auto-detect enrichment need
            quality_score = getattr(article, 'quality_score', None) or 0
            if not enrich and (word_count < 300 or quality_score < 50):
                enrich = True
                logger.info(
                    f"Auto-enriching article {article.id} "
                    f"(words={word_count}, quality={quality_score})"
                )

            if enrich:
                content = self._enrich_content(article.title, content, word_count)

            title = self._rewrite_title(article.title)
            rewritten = self._rewrite_content(content, len(content.split()))
            excerpt = self._generate_excerpt(title, rewritten)
            tokens = self._total_tokens
            cost = round(tokens / 1000 * (self.INPUT_COST_PER_1K + self.OUTPUT_COST_PER_1K), 5)
            return {
                'title': title,
                'content': rewritten,
                'excerpt': excerpt,
                'tokens_used': tokens,
                'cost': cost,
                'enriched': enrich,
            }
        except Exception as e:
            logger.error(f"Rewrite failed for article {article.id}: {e}")
            return None

    def _enrich_content(self, title, content, word_count):
        """Expand thin content ~1.8x with contextual background using GPT."""
        target_words = int(word_count * 1.8)
        prompt = (
            f"You are a senior journalist expanding a thin news article. "
            f"The original is {word_count} words. Expand it to approximately "
            f"{target_words} words by adding relevant context, background, "
            f"and analysis. Preserve ALL original facts, names, dates, and quotes. "
            f"Use professional East African English and third-person journalistic style. "
            f"Preserve HTML formatting. Return only the expanded article body."
        )
        response = self.client.chat.completions.create(
            model=self.model,
            messages=[
                {"role": "system", "content": prompt},
                {"role": "user", "content": f"Title: {title}\n\nContent:\n{content}"},
            ],
            max_tokens=4096,
            temperature=0.6,
        )
        self._total_tokens += response.usage.total_tokens if response.usage else 0
        enriched = response.choices[0].message.content.strip()
        logger.info(
            f"Enriched content from {word_count} to ~{len(enriched.split())} words"
        )
        return enriched

    def rewrite_batch(self, articles):
        """Rewrite multiple articles. Returns list of results (dict or None per article)."""
        results = []
        for article in articles:
            results.append(self.rewrite(article))
        return results

    def _rewrite_title(self, title):
        """Rewrite headline for originality while preserving meaning."""
        response = self.client.chat.completions.create(
            model=self.model,
            messages=[
                {
                    "role": "system",
                    "content": (
                        "You are a senior news editor. Rewrite this headline to be original "
                        "while preserving all facts, names, and meaning. Return only the "
                        "rewritten headline, no quotes or explanation."
                    ),
                },
                {"role": "user", "content": title},
            ],
            max_tokens=100,
            temperature=0.7,
        )
        self._total_tokens += response.usage.total_tokens if response.usage else 0
        return response.choices[0].message.content.strip()

    def _rewrite_content(self, content, target_word_count):
        """Rewrite article body. Chunks long articles (>3000 words)."""
        if target_word_count > 3000:
            return self._rewrite_chunked(content, target_word_count)

        tolerance = self.word_tolerance
        prompt = (
            f"You are a senior editor at a professional news publication. "
            f"Rewrite this article to be completely original while preserving ALL facts, "
            f"names, dates, quotes, and meaning. Use professional East African English. "
            f"Third-person journalistic style. Target word count: {target_word_count} "
            f"(±{tolerance} words). Preserve HTML formatting (paragraphs, headings, lists). "
            f"Return only the rewritten article body, no preamble."
        )

        response = self.client.chat.completions.create(
            model=self.model,
            messages=[
                {"role": "system", "content": prompt},
                {"role": "user", "content": content},
            ],
            max_tokens=4096,
            temperature=0.6,
        )
        self._total_tokens += response.usage.total_tokens if response.usage else 0
        return response.choices[0].message.content.strip()

    def _rewrite_chunked(self, content, target_word_count):
        """Split long content at paragraph boundaries and rewrite each chunk."""
        # Split at block-level HTML boundaries
        chunks = re.split(r'(?<=</p>|</h[2-6]>|</ul>|</ol>|</blockquote>)', content)

        # Group chunks into ~2000 word batches
        batches = []
        current_batch = []
        current_count = 0
        for chunk in chunks:
            words = len(chunk.split())
            if current_count + words > 2000 and current_batch:
                batches.append(''.join(current_batch))
                current_batch = [chunk]
                current_count = words
            else:
                current_batch.append(chunk)
                current_count += words
        if current_batch:
            batches.append(''.join(current_batch))

        # Rewrite each batch
        rewritten_parts = []
        for i, batch in enumerate(batches):
            batch_target = len(batch.split())
            logger.info(f"Rewriting chunk {i + 1}/{len(batches)} ({batch_target} words)")
            rewritten = self._rewrite_content(batch, batch_target)
            rewritten_parts.append(rewritten)

        return '\n\n'.join(rewritten_parts)

    def _generate_excerpt(self, title, content):
        """Generate a one-sentence summary (max 30 words)."""
        response = self.client.chat.completions.create(
            model=self.model,
            messages=[
                {
                    "role": "system",
                    "content": (
                        "Write a one-sentence news summary (max 30 words) for this article. "
                        "Return only the summary, no quotes."
                    ),
                },
                {"role": "user", "content": f"Title: {title}\n\nContent: {content[:2000]}"},
            ],
            max_tokens=60,
            temperature=0.5,
        )
        self._total_tokens += response.usage.total_tokens if response.usage else 0
        return response.choices[0].message.content.strip()
