"""Rewriter should no-op (not 401-spam) when no valid OpenAI key is configured."""
import pytest


class TestValidKeyDetection:
    @pytest.mark.parametrize('key', [
        '', 'CHANGE_ME_OPENAI_API_KEY', 'your-api-key-here',
        'placeholder', 'sk-XXXXXXXX', 'not-an-sk-key',
    ])
    def test_rejects_missing_or_placeholder(self, settings, key):
        from apps.rewriter.service import has_valid_openai_key
        settings.OPENAI_API_KEY = key
        assert has_valid_openai_key() is False

    def test_accepts_real_looking_key(self, settings):
        from apps.rewriter.service import has_valid_openai_key
        settings.OPENAI_API_KEY = 'sk-proj-abcdef1234567890realkey'
        assert has_valid_openai_key() is True


@pytest.mark.django_db
class TestQueueSkips:
    def test_process_queue_skips_without_key(self, settings, category):
        from apps.articles.models import Article
        from apps.rewriter.tasks import process_queue
        settings.REWRITER_ENABLED = True
        settings.OPENAI_API_KEY = 'CHANGE_ME'
        from django.utils import timezone
        Article.objects.create(
            title='Queued one', slug='rw-1', status='published',
            published_at=timezone.now(), category=category, rewrite_status='queued',
        )
        result = process_queue()
        assert 'skipped' in result.lower()
        # The queued article must NOT have been flipped to 'failed'.
        assert Article.objects.get(slug='rw-1').rewrite_status == 'queued'
