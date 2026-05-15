import pytest

from apps.enrichment.quality import QualityScorer


@pytest.fixture
def scorer():
    return QualityScorer()


class TestQualityScorer:
    def test_short_content_scores_low(self, scorer):
        text = 'Short article.'
        score = scorer.score(text)
        assert score < 20

    def test_very_short_scores_zero_or_minimal(self, scorer):
        text = 'Hi.'
        score = scorer.score(text)
        assert score <= 10

    def test_long_well_structured_content_scores_high(self, scorer):
        # 800+ words, multiple paragraphs, headings, images, links
        paragraphs = []
        for i in range(8):
            paragraphs.append(f'<h2>Section {i + 1}</h2>')
            paragraphs.append(
                '<p>' + ' '.join(['This is a well-written sentence with good variety.'] * 15) + '</p>'
            )
        html = (
            '<img src="a.jpg" alt="photo">'
            '<img src="b.jpg" alt="photo">'
            '<a href="https://example.com">Link 1</a>'
            '<a href="https://example2.com">Link 2</a>'
            '<a href="https://example3.com">Link 3</a>'
            + '\n'.join(paragraphs)
        )
        plain = ' '.join(['This is a well-written sentence with good variety.'] * 120)
        score = scorer.score(plain, html=html)
        assert score >= 60

    def test_medium_content(self, scorer):
        # ~400 words with some structure
        words = ' '.join(['word'] * 400)
        html = f'<p>{words}</p><p>Another paragraph here with some more words.</p><p>Third paragraph.</p>'
        score = scorer.score(words, html=html)
        assert 15 <= score <= 60

    def test_html_ratio_penalty(self, scorer):
        # Lots of HTML relative to text
        text = 'Short'
        html = '<div>' * 50 + text + '</div>' * 50
        score_with_ratio = scorer.score(text, html=html)

        # Same text without excessive HTML
        plain_html = f'<p>{text}</p>'
        score_without_ratio = scorer.score(text, html=plain_html)

        # The excessive HTML version should score lower (or equal if both are 0)
        assert score_with_ratio <= score_without_ratio

    def test_heading_structure_adds_points(self, scorer):
        words = ' '.join(['word'] * 200)
        html_no_headings = f'<p>{words}</p>'
        html_with_headings = f'<h2>Title</h2><p>{words}</p><h3>Sub</h3><p>More</p>'
        score_no = scorer.score(words, html=html_no_headings)
        score_with = scorer.score(words, html=html_with_headings)
        assert score_with >= score_no

    def test_images_add_points(self, scorer):
        words = ' '.join(['word'] * 200)
        html_no_img = f'<p>{words}</p>'
        html_with_img = f'<img src="a.jpg"><img src="b.jpg"><p>{words}</p>'
        score_no = scorer.score(words, html=html_no_img)
        score_with = scorer.score(words, html=html_with_img)
        assert score_with > score_no

    def test_score_bounded_0_to_100(self, scorer):
        assert scorer.score('') >= 0
        assert scorer.score('') <= 100
        big = ' '.join(['word'] * 2000)
        assert scorer.score(big) <= 100

    def test_paragraph_structure_adds_points(self, scorer):
        words = ' '.join(['word'] * 200)
        one_para = f'<p>{words}</p>'
        five_paras = ''.join(
            f'<p>{" ".join(["word"] * 40)}</p>' for _ in range(5)
        )
        score_one = scorer.score(words, html=one_para)
        score_five = scorer.score(words, html=five_paras)
        assert score_five >= score_one

    def test_links_add_points(self, scorer):
        words = ' '.join(['word'] * 200)
        html_no = f'<p>{words}</p>'
        html_with = f'<a href="#">1</a><a href="#">2</a><a href="#">3</a><p>{words}</p>'
        score_no = scorer.score(words, html=html_no)
        score_with = scorer.score(words, html=html_with)
        assert score_with > score_no

    def test_empty_text_no_crash(self, scorer):
        assert scorer.score('') == 0
        assert scorer.score('', html='') == 0
