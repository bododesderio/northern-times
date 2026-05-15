from datetime import timedelta
from unittest.mock import MagicMock

import pytest
from django.utils import timezone

from apps.core.helpers import (
    format_file_size,
    generate_excerpt,
    get_client_ip,
    reading_time,
    relative_time,
)


class TestRelativeTime:
    def test_just_now(self):
        now = timezone.now()
        assert relative_time(now) == 'just now'

    def test_minutes_ago(self):
        dt = timezone.now() - timedelta(minutes=5)
        result = relative_time(dt)
        assert '5 minutes ago' == result

    def test_one_minute_ago(self):
        dt = timezone.now() - timedelta(minutes=1, seconds=10)
        result = relative_time(dt)
        assert result == '1 minute ago'

    def test_hours_ago(self):
        dt = timezone.now() - timedelta(hours=3)
        result = relative_time(dt)
        assert '3 hours ago' == result

    def test_one_hour_ago(self):
        dt = timezone.now() - timedelta(hours=1, minutes=10)
        result = relative_time(dt)
        assert result == '1 hour ago'

    def test_days_ago(self):
        dt = timezone.now() - timedelta(days=2)
        result = relative_time(dt)
        assert '2 days ago' == result

    def test_weeks_ago(self):
        dt = timezone.now() - timedelta(weeks=2)
        result = relative_time(dt)
        assert '2 weeks ago' == result

    def test_months_ago(self):
        dt = timezone.now() - timedelta(days=60)
        result = relative_time(dt)
        assert '2 months ago' == result

    def test_years_ago(self):
        dt = timezone.now() - timedelta(days=400)
        result = relative_time(dt)
        assert '1 year ago' == result

    def test_none_returns_empty(self):
        assert relative_time(None) == ''

    def test_future_returns_just_now(self):
        dt = timezone.now() + timedelta(hours=1)
        assert relative_time(dt) == 'just now'


class TestReadingTime:
    def test_empty_text(self):
        assert reading_time('') == 1

    def test_short_text(self):
        assert reading_time('Hello world') == 1

    def test_medium_text(self):
        words = ' '.join(['word'] * 400)
        assert reading_time(words) == 2

    def test_long_text(self):
        words = ' '.join(['word'] * 1000)
        assert reading_time(words) == 5

    def test_strips_html(self):
        html = '<p>' + ' '.join(['word'] * 400) + '</p>'
        assert reading_time(html) == 2

    def test_none_text(self):
        assert reading_time(None) == 1


class TestGenerateExcerpt:
    def test_short_text_unchanged(self):
        text = 'Short text here'
        assert generate_excerpt(text) == 'Short text here'

    def test_truncates_long_text(self):
        text = 'A ' * 200
        result = generate_excerpt(text, max_length=50)
        assert len(result) <= 53  # 50 + '...'
        assert result.endswith('...')

    def test_strips_html(self):
        html = '<p>Hello <strong>world</strong></p>'
        result = generate_excerpt(html)
        assert '<' not in result
        assert 'Hello world' in result

    def test_empty_input(self):
        assert generate_excerpt('') == ''

    def test_word_boundary_truncation(self):
        text = 'The quick brown fox jumps over the lazy dog'
        result = generate_excerpt(text, max_length=20)
        # Should not cut in the middle of a word
        assert not result.rstrip('.').endswith('fo')

    def test_collapses_whitespace(self):
        text = 'Hello   \n\n   world   again'
        result = generate_excerpt(text)
        assert '  ' not in result


class TestFormatFileSize:
    def test_bytes(self):
        assert format_file_size(500) == '500 B'

    def test_zero(self):
        assert format_file_size(0) == '0 B'

    def test_kilobytes(self):
        result = format_file_size(2048)
        assert 'KB' in result
        assert result == '2.0 KB'

    def test_megabytes(self):
        result = format_file_size(1_500_000)
        assert 'MB' in result

    def test_gigabytes(self):
        result = format_file_size(2_500_000_000)
        assert 'GB' in result

    def test_none(self):
        assert format_file_size(None) == '0 B'

    def test_negative(self):
        assert format_file_size(-1) == '0 B'


class TestGetClientIp:
    def _make_request(self, **meta):
        request = MagicMock()
        request.META = meta
        return request

    def test_x_forwarded_for(self):
        request = self._make_request(
            HTTP_X_FORWARDED_FOR='1.2.3.4, 5.6.7.8',
            REMOTE_ADDR='127.0.0.1',
        )
        assert get_client_ip(request) == '1.2.3.4'

    def test_x_real_ip(self):
        request = self._make_request(
            HTTP_X_REAL_IP='10.0.0.1',
            REMOTE_ADDR='127.0.0.1',
        )
        assert get_client_ip(request) == '10.0.0.1'

    def test_remote_addr_fallback(self):
        request = self._make_request(REMOTE_ADDR='192.168.1.1')
        assert get_client_ip(request) == '192.168.1.1'

    def test_no_headers_defaults(self):
        request = self._make_request()
        assert get_client_ip(request) == '127.0.0.1'

    def test_x_forwarded_for_priority(self):
        request = self._make_request(
            HTTP_X_FORWARDED_FOR='1.1.1.1',
            HTTP_X_REAL_IP='2.2.2.2',
            REMOTE_ADDR='3.3.3.3',
        )
        assert get_client_ip(request) == '1.1.1.1'
