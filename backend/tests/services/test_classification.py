"""Tests for topic-primary geo/topic classification (apps.crawler.classification).

The AI zero-shot classifier is stubbed so these tests exercise the routing logic
(keyword hints, shortlisting, region rules, residual buckets) deterministically
without loading a model.
"""
from dataclasses import dataclass

import pytest

from apps.crawler import classification
from apps.crawler.classification import (
    GeoClassifier,
    count_northern_matches,
    keyword_topic_hint,
    topic_scores,
    uganda_score,
)


@dataclass
class _Source:
    region: str


class _StubClassifier:
    """Returns a fixed (label, score) — or nothing — for the AI step."""
    def __init__(self, label=None, score=0.0):
        self.label, self.score = label, score
        self.calls = []

    def classify(self, title, text, labels):
        self.calls.append(list(labels))
        return (self.label, self.score)


def _seed_categories(db):
    from apps.articles.models import Category
    names = [
        ('northern-uganda', 'Northern Uganda'), ('world', 'World'),
        ('politics', 'Politics'), ('sports', 'Sports'), ('business', 'Business'),
        ('health', 'Health'), ('technology', 'Technology'),
        ('entertainment', 'Entertainment'), ('education', 'Education'),
        ('environment', 'Environment'), ('crime-security', 'Crime & Security'),
        ('lifestyle', 'Lifestyle'), ('opinion', 'Opinion'),
    ]
    for slug, name in names:
        Category.objects.get_or_create(slug=slug, defaults={'name': name})
    return [n for _, n in names]


# ---- pure helpers -----------------------------------------------------------

class TestKeywordHelpers:
    def test_northern_matches(self):
        assert count_northern_matches('a story from gulu and kitgum in acholi') >= 3
        assert count_northern_matches('a london fashion week story') == 0

    def test_uganda_score(self):
        assert uganda_score('museveni addressed parliament in kampala') >= 2
        assert uganda_score('a story about france and germany') == 0

    def test_topic_scores_sports(self):
        s = topic_scores('the football match ended with a goal and the coach praised the striker')
        assert s.get('Sports', 0) >= 3

    def test_keyword_hint_requires_clear_winner(self):
        # Strong, unambiguous sports signal → Sports.
        assert keyword_topic_hint(
            'football goal striker coach stadium league match'
        ) == 'Sports'
        # A political story that merely name-drops a stadium is not Sports.
        assert keyword_topic_hint('the president and parliament debated the budget') != 'Sports'


# ---- GeoClassifier routing --------------------------------------------------

@pytest.mark.django_db
class TestGeoClassifierRouting:
    def test_northern_uganda_wins(self, db):
        _seed_categories(db)
        geo = GeoClassifier(_StubClassifier())
        cat = geo.classify(
            _Source('ugandan'),
            'Gulu and Kitgum leaders meet in Acholi sub-region',
            'Officials from Gulu district and Lira met to discuss the north.',
            _seed_categories(db),
        )
        assert cat.slug == 'northern-uganda'

    def test_international_sports_goes_to_sports(self, db):
        names = _seed_categories(db)
        geo = GeoClassifier(_StubClassifier('Sports', 0.9))
        cat = geo.classify(
            _Source('international'),
            'Premier League: late goal wins the match',
            'The striker scored as the football coach celebrated the league win.',
            names,
        )
        assert cat.name == 'Sports'

    def test_international_politics_falls_to_world(self, db):
        names = _seed_categories(db)
        # AI would say Politics, but Politics is NOT eligible for international,
        # and there is no universal-topic signal → residual World.
        geo = GeoClassifier(_StubClassifier('Politics', 0.9))
        cat = geo.classify(
            _Source('international'),
            'UN Security Council debates sanctions',
            'Diplomats at the council discussed foreign policy and sanctions.',
            names,
        )
        assert cat.name == 'World'

    def test_no_signal_international_is_world(self, db):
        names = _seed_categories(db)
        geo = GeoClassifier(_StubClassifier())  # AI returns nothing
        cat = geo.classify(
            _Source('international'),
            'Russian missile attacks wound 11 in Kyiv',
            'An overnight strike hit the city.',
            names,
        )
        assert cat.name == 'World'

    def test_no_signal_ugandan_is_politics(self, db):
        names = _seed_categories(db)
        geo = GeoClassifier(_StubClassifier())
        cat = geo.classify(
            _Source('ugandan'),
            'Minister comments on Uganda affairs',
            'A general update from Kampala with no strong topic.',
            names,
        )
        assert cat.name == 'Politics'

    def test_ai_breaks_a_tie_over_tied_labels_only(self, db):
        names = _seed_categories(db)
        stub = _StubClassifier('Business', 0.8)
        geo = GeoClassifier(stub)
        # Exact tie: business (market, economy) == sports (football, stadium),
        # each 2 hits and below the strict-hint threshold, so the AI breaks it —
        # and only over the tied labels.
        cat = geo.classify(
            _Source('ugandan'),
            'Market economy update from the football stadium',
            'market economy football stadium',
            names,
        )
        assert stub.calls, 'AI should be called to break the tie'
        assert set(stub.calls[0]) <= {'Business', 'Sports'}
        assert cat.name == 'Business'  # AI's pick wins the tie

    def test_keyword_leader_wins_without_ai(self, db):
        names = _seed_categories(db)
        stub = _StubClassifier('Technology', 0.9)  # would mis-say Technology
        geo = GeoClassifier(stub)
        # Sports leads Technology on keyword hits → keyword wins, AI NOT consulted
        # (guards the real Bellingham-in-Technology regression).
        cat = geo.classify(
            _Source('international'),
            'Football star returns as coach eyes league goal',
            'football goal coach league match striker app data digital',
            names,
        )
        assert cat.name == 'Sports'
        assert not stub.calls
