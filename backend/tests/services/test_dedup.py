import pytest

from apps.enrichment.dedup import DuplicateChecker


@pytest.fixture
def checker():
    return DuplicateChecker()


class TestTokenize:
    def test_basic_tokenization(self, checker):
        tokens = checker._tokenize('Uganda president signs new bill into law')
        assert 'uganda' in tokens
        assert 'president' in tokens
        assert 'signs' in tokens
        assert 'bill' in tokens
        assert 'law' in tokens

    def test_stop_word_removal(self, checker):
        tokens = checker._tokenize('the president of the country')
        assert 'the' not in tokens
        assert 'of' not in tokens
        assert 'president' in tokens
        assert 'country' in tokens

    def test_short_word_removal(self, checker):
        tokens = checker._tokenize('a an is are the ox')
        # All words <= 2 chars or stop words should be excluded
        assert 'a' not in tokens
        assert 'an' not in tokens
        assert 'is' not in tokens
        assert 'ox' not in tokens

    def test_case_normalization(self, checker):
        tokens = checker._tokenize('BREAKING NEWS Uganda')
        assert 'breaking' in tokens
        assert 'news' in tokens
        assert 'uganda' in tokens

    def test_empty_string(self, checker):
        assert checker._tokenize('') == set()

    def test_special_characters(self, checker):
        tokens = checker._tokenize("Uganda's economy: growing fast!")
        assert 'uganda' in tokens
        assert 'economy' in tokens
        assert 'growing' in tokens
        assert 'fast' in tokens


class TestJaccardSimilarity:
    def test_identical_texts(self, checker):
        text = 'Uganda president signs major infrastructure bill'
        sim = checker.jaccard_similarity(text, text)
        assert sim == 1.0

    def test_completely_different(self, checker):
        text1 = 'Uganda president signs infrastructure bill'
        text2 = 'Football match results Saturday evening'
        sim = checker.jaccard_similarity(text1, text2)
        assert sim < 0.2

    def test_similar_texts(self, checker):
        text1 = 'Uganda president signs new education bill'
        text2 = 'Uganda president approves new education law'
        sim = checker.jaccard_similarity(text1, text2)
        assert 0.3 <= sim <= 0.8

    def test_empty_texts(self, checker):
        assert checker.jaccard_similarity('', '') == 0.0
        assert checker.jaccard_similarity('hello world', '') == 0.0
        assert checker.jaccard_similarity('', 'hello world') == 0.0

    def test_symmetry(self, checker):
        text1 = 'President visits northern region'
        text2 = 'Northern region visited by president'
        assert checker.jaccard_similarity(text1, text2) == checker.jaccard_similarity(text2, text1)

    def test_subset_text(self, checker):
        text1 = 'Uganda economy growth'
        text2 = 'Uganda economy growth report quarterly'
        sim = checker.jaccard_similarity(text1, text2)
        # Subset should be high but not 1.0
        assert 0.5 <= sim < 1.0

    def test_returns_float(self, checker):
        sim = checker.jaccard_similarity('hello world', 'hello there')
        assert isinstance(sim, float)


class TestIsDuplicate:
    def test_exact_match(self, checker):
        title = 'Uganda signs new trade agreement'
        existing = ['Uganda signs new trade agreement']
        assert checker.is_duplicate(title, existing) is True

    def test_fuzzy_match(self, checker):
        title = 'Uganda president signs new education bill'
        existing = ['Uganda president approves education bill']
        # These should be similar enough at default threshold (0.55)
        sim = checker.jaccard_similarity(title, existing[0])
        expected = sim >= 0.55
        assert checker.is_duplicate(title, existing) is expected

    def test_no_match(self, checker):
        title = 'Local football team wins championship'
        existing = [
            'Uganda president signs new bill',
            'East African economy grows 5%',
        ]
        assert checker.is_duplicate(title, existing) is False

    def test_empty_existing(self, checker):
        assert checker.is_duplicate('Any title here', []) is False

    def test_custom_threshold(self, checker):
        title = 'Uganda president speaks at summit'
        existing = ['Uganda leader talks at conference']
        # With very low threshold, should match
        assert checker.is_duplicate(title, existing, threshold=0.1) is True
        # With very high threshold, should not match
        assert checker.is_duplicate(title, existing, threshold=0.99) is False

    def test_multiple_existing_one_match(self, checker):
        title = 'Breaking news from Kampala today'
        existing = [
            'Football scores from weekend matches',
            'Breaking news from Kampala today',
            'Weather forecast for next week',
        ]
        assert checker.is_duplicate(title, existing) is True

    def test_only_stop_words_title(self, checker):
        title = 'the a an is are'
        existing = ['the a an is are']
        # All stop words get removed, empty sets -> 0.0 similarity
        assert checker.is_duplicate(title, existing) is False
