import re
import logging

logger = logging.getLogger(__name__)


class QualityScorer:
    """Heuristic content quality scoring (0-100)."""

    def score(self, text: str, html: str = '') -> int:
        """Score content quality based on multiple heuristics."""
        score = 0
        plain = re.sub(r'<[^>]+>', '', html) if html else text
        # Empty/whitespace-only content has no quality — return 0 before the
        # paragraph heuristic (plain.count('\n\n') + 1) grants a phantom point.
        if not plain or not plain.strip():
            return 0
        word_count = len(plain.split())

        # Length score (0-30)
        if word_count >= 800:
            score += 30
        elif word_count >= 400:
            score += 20
        elif word_count >= 200:
            score += 10
        elif word_count >= 100:
            score += 5

        # Paragraph structure (0-15)
        paragraphs = len(re.findall(r'<p[\s>]', html)) if html else plain.count('\n\n') + 1
        if paragraphs >= 5:
            score += 15
        elif paragraphs >= 3:
            score += 10
        elif paragraphs >= 1:
            score += 5

        # Heading structure (0-10)
        headings = len(re.findall(r'<h[2-6][\s>]', html)) if html else 0
        if headings >= 3:
            score += 10
        elif headings >= 1:
            score += 5

        # Images (0-10)
        images = len(re.findall(r'<img[\s>]', html)) if html else 0
        if images >= 2:
            score += 10
        elif images >= 1:
            score += 5

        # Links (0-10)
        links = len(re.findall(r'<a[\s>]', html)) if html else 0
        if links >= 3:
            score += 10
        elif links >= 1:
            score += 5

        # Sentence variety (0-10) -- average sentence length variation
        sentences = re.split(r'[.!?]+', plain)
        if len(sentences) >= 5:
            lengths = [len(s.split()) for s in sentences if s.strip()]
            if lengths:
                avg = sum(lengths) / len(lengths)
                variance = sum((l - avg) ** 2 for l in lengths) / len(lengths)
                if variance > 20:  # Good variety
                    score += 10
                elif variance > 5:
                    score += 5

        # HTML-to-text ratio penalty (0 to -10)
        if html:
            html_ratio = len(html) / max(len(plain), 1)
            if html_ratio > 5:  # Too much HTML vs content
                score -= 10
            elif html_ratio > 3:
                score -= 5

        # Readability bonus (0-5) -- not too short, not too long avg sentence
        if sentences:
            avg_len = word_count / max(len(sentences), 1)
            if 12 <= avg_len <= 25:
                score += 5

        return max(0, min(100, score))
