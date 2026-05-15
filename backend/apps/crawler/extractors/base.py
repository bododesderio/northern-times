"""Base extractor interface."""
from dataclasses import dataclass, field


@dataclass
class ExtractionResult:
    """Result from a content extraction attempt."""
    content: str = ''
    title: str = ''
    author: str = ''
    published_date: str = ''
    image_url: str = ''
    language: str = 'en'
    word_count: int = 0
    strategy: str = ''
    is_paywall: bool = False
    is_truncated: bool = False
    metadata: dict = field(default_factory=dict)

    @property
    def is_valid(self) -> bool:
        return len(self.content.strip()) >= 200


class BaseExtractor:
    """Interface for content extractors."""

    def extract(self, html: str, url: str, **kwargs) -> ExtractionResult:
        raise NotImplementedError
