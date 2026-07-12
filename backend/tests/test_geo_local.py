"""Tests for reader-location-aware Local News (haversine proximity)."""
import json

import pytest
from django.urls import reverse
from django.utils import timezone

from apps.articles.models import Article, ArticleEntity, Category
from apps.articles.services import geo


# ── pure helpers ─────────────────────────────────────────────────────────────

class TestHaversineAndGazetteer:
    def test_haversine_kampala_to_gulu(self):
        (k_lat, k_lon) = geo.GAZETTEER['kampala']
        (g_lat, g_lon) = geo.GAZETTEER['gulu']
        d = geo.haversine_km(k_lat, k_lon, g_lat, g_lon)
        assert 250 < d < 300  # ~270 km by road-less great circle

    def test_haversine_zero(self):
        assert geo.haversine_km(2.0, 32.0, 2.0, 32.0) == pytest.approx(0.0, abs=1e-6)

    def test_geocode_exact_and_suffix_and_token(self):
        assert geo.geocode_place('Gulu') == geo.GAZETTEER['gulu']
        assert geo.geocode_place('Gulu District') == geo.GAZETTEER['gulu']
        assert geo.geocode_place('Lira City') == geo.GAZETTEER['lira']
        assert geo.geocode_place('somewhere unknown') is None

    def test_in_uganda(self):
        assert geo.in_uganda(*geo.GAZETTEER['gulu'])
        assert not geo.in_uganda(51.5, -0.12)  # London


# ── article geocoding ────────────────────────────────────────────────────────

@pytest.mark.django_db
class TestArticleGeocode:
    def _article(self, category, slug, place):
        art = Article.objects.create(
            title=f'Story in {place}', slug=slug, status='published',
            published_at=timezone.now(), category=category, content='<p>x</p>',
        )
        ArticleEntity.objects.create(article=art, entity_text=place,
                                     entity_type='gpe', salience=0.9)
        return art

    def test_apply_geocode_sets_coords(self, category):
        art = self._article(category, 'geo-gulu', 'Gulu')
        assert geo.apply_geocode(art) is True
        art.refresh_from_db()
        assert art.geo_place == 'Gulu'
        assert art.latitude == pytest.approx(geo.GAZETTEER['gulu'][0])

    def test_apply_geocode_no_placeable_entity(self, category):
        art = Article.objects.create(
            title='No place', slug='geo-none', status='published',
            published_at=timezone.now(), category=category, content='<p>x</p>',
        )
        ArticleEntity.objects.create(article=art, entity_text='Atlantis',
                                     entity_type='gpe', salience=0.9)
        assert geo.apply_geocode(art) is False
        art.refresh_from_db()
        assert art.latitude is None


# ── reader-location endpoint + resolution ────────────────────────────────────

@pytest.mark.django_db
class TestReaderLocationEndpoint:
    URL = 'core_api:set_reader_location'

    def _post(self, client, payload):
        return client.post(reverse(self.URL), data=json.dumps(payload),
                           content_type='application/json')

    def test_set_gps(self, client):
        resp = self._post(client, {'lat': 2.77, 'lon': 32.30})
        assert resp.status_code == 200 and resp.json()['ok']
        assert client.session['reader_loc']['source'] == 'gps'

    def test_set_manual_place(self, client):
        resp = self._post(client, {'place': 'gulu'})
        assert resp.json()['ok']
        s = client.session['reader_loc']
        assert s['source'] == 'manual'
        assert s['lat'] == pytest.approx(geo.GAZETTEER['gulu'][0])

    def test_manual_unknown_place_rejected(self, client):
        resp = self._post(client, {'place': 'narnia'})
        assert resp.status_code == 400

    def test_out_of_range_rejected(self, client):
        assert self._post(client, {'lat': 999, 'lon': 0}).status_code == 400

    def test_clear(self, client):
        self._post(client, {'place': 'gulu'})
        resp = self._post(client, {'clear': True})
        assert resp.json()['cleared']
        assert 'reader_loc' not in client.session


# ── localized feed ordering ──────────────────────────────────────────────────

@pytest.mark.django_db
class TestLocalNewsRanking:
    def _local_cat(self):
        return Category.objects.create(name='Local News', slug='local-news',
                                       show_in_nav=True, show_in_sidebar=True)

    def _art(self, cat, slug, lat, lon, title):
        return Article.objects.create(
            title=title, slug=slug, status='published',
            published_at=timezone.now(), category=cat, content='<p>x</p>',
            latitude=lat, longitude=lon,
        )

    def test_nearest_article_ranks_first(self, client):
        cat = self._local_cat()
        self._art(cat, 'a-kampala', *geo.GAZETTEER['kampala'], 'Kampala story')
        self._art(cat, 'a-gulu', *geo.GAZETTEER['gulu'], 'Gulu story')
        # Reader sits in Gulu
        client.post(reverse('core_api:set_reader_location'),
                    data=json.dumps({'place': 'gulu'}), content_type='application/json')
        resp = client.get(reverse('articles_frontend:category', args=['local-news']))
        assert resp.status_code == 200
        assert resp.context['localized'] is True
        titles = [a.title for a in resp.context['articles']]
        assert titles[0] == 'Gulu story'  # nearest first

    def test_without_location_not_localized(self, client):
        cat = self._local_cat()
        self._art(cat, 'a-kampala', *geo.GAZETTEER['kampala'], 'Kampala story')
        resp = client.get(reverse('articles_frontend:category', args=['local-news']))
        # No session location and IP lookup returns nothing for testclient → not localized
        assert resp.context['localized'] is False
