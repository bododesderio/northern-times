import pytest
from apps.core.sanitizer import sanitize, strip_tags


class TestSanitizeArticle:
    def test_allows_headings(self):
        html = '<h2>Title</h2><p>Content</p>'
        result = sanitize(html, profile='article')
        assert '<h2>' in result
        assert '<p>' in result

    def test_allows_all_heading_levels(self):
        for level in range(2, 7):
            html = f'<h{level}>Heading</h{level}>'
            result = sanitize(html, profile='article')
            assert f'<h{level}>' in result

    def test_strips_script(self):
        html = '<p>Safe</p><script>alert("xss")</script>'
        result = sanitize(html, profile='article')
        assert '<script>' not in result
        assert 'alert' not in result
        assert 'Safe' in result

    def test_strips_style(self):
        html = '<p>Text</p><style>body{display:none}</style>'
        result = sanitize(html, profile='article')
        assert '<style>' not in result

    def test_allows_images(self):
        html = '<img src="photo.jpg" alt="A photo" width="640" height="480">'
        result = sanitize(html, profile='article')
        assert '<img' in result
        assert 'src="photo.jpg"' in result
        assert 'alt="A photo"' in result

    def test_allows_links(self):
        html = '<a href="https://example.com">Link</a>'
        result = sanitize(html, profile='article')
        assert '<a href="https://example.com">' in result

    def test_allows_lists(self):
        html = '<ul><li>Item 1</li><li>Item 2</li></ul>'
        result = sanitize(html, profile='article')
        assert '<ul>' in result
        assert '<li>' in result

    def test_allows_blockquote(self):
        html = '<blockquote>Quote text</blockquote>'
        result = sanitize(html, profile='article')
        assert '<blockquote>' in result

    def test_allows_table(self):
        html = '<table><thead><tr><th>Col</th></tr></thead><tbody><tr><td>Val</td></tr></tbody></table>'
        result = sanitize(html, profile='article')
        assert '<table>' in result
        assert '<td>' in result

    def test_strips_onclick(self):
        html = '<p onclick="evil()">Text</p>'
        result = sanitize(html, profile='article')
        assert 'onclick' not in result
        assert 'Text' in result

    def test_allows_figure(self):
        html = '<figure><img src="x.jpg" alt=""><figcaption>Caption</figcaption></figure>'
        result = sanitize(html, profile='article')
        assert '<figure>' in result
        assert '<figcaption>' in result

    def test_allows_video(self):
        html = '<video src="video.mp4" controls></video>'
        result = sanitize(html, profile='article')
        assert '<video' in result

    def test_empty_input(self):
        assert sanitize('', profile='article') == ''
        assert sanitize(None, profile='article') == ''

    def test_unknown_profile_raises(self):
        with pytest.raises(ValueError, match='Unknown sanitizer profile'):
            sanitize('<p>Test</p>', profile='nonexistent')

    def test_strips_form_elements(self):
        html = '<form action="/"><input type="text"><button>Submit</button></form>'
        result = sanitize(html, profile='article')
        assert '<form' not in result
        assert '<input' not in result
        assert '<button' not in result


class TestSanitizeComment:
    def test_strips_images(self):
        html = '<p>Text</p><img src="x.jpg">'
        result = sanitize(html, profile='comment')
        assert '<img' not in result
        assert 'Text' in result

    def test_adds_nofollow(self):
        html = '<a href="https://example.com">Link</a>'
        result = sanitize(html, profile='comment')
        assert 'nofollow' in result

    def test_allows_basic_formatting(self):
        html = '<p>Hello <strong>bold</strong> and <em>italic</em></p>'
        result = sanitize(html, profile='comment')
        assert '<strong>' in result
        assert '<em>' in result

    def test_strips_headings(self):
        html = '<h2>Heading</h2><p>Text</p>'
        result = sanitize(html, profile='comment')
        assert '<h2>' not in result
        assert 'Heading' in result
        assert '<p>' in result

    def test_strips_div(self):
        html = '<div><p>Content</p></div>'
        result = sanitize(html, profile='comment')
        assert '<div>' not in result
        assert '<p>' in result

    def test_allows_br(self):
        html = '<p>Line 1<br>Line 2</p>'
        result = sanitize(html, profile='comment')
        assert '<br>' in result


class TestStripTags:
    def test_strip_tags(self):
        assert strip_tags('<p>Hello <b>world</b></p>') == 'Hello world'

    def test_strip_tags_empty(self):
        assert strip_tags('') == ''
        assert strip_tags(None) == ''

    def test_strip_tags_plain_text(self):
        assert strip_tags('no tags here') == 'no tags here'

    def test_strip_nested(self):
        html = '<div><p>Nested <span>content</span></p></div>'
        assert strip_tags(html) == 'Nested content'
