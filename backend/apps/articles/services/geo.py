"""Geo utilities for reader-location-aware "Local News".

- A curated Uganda gazetteer (place name -> lat/lon), weighted toward Northern
  Uganda (the publication's beat) plus the major national cities/districts.
- Haversine distance.
- Article geocoding from stored GPE named entities.
- Reader-location resolution (session GPS -> IP GeoIP), cached on the request.

No PostGIS: distance is computed in Python / SQL haversine, which is fine at this
scale.
"""
from __future__ import annotations

import math
from typing import Optional, Tuple

# ── Gazetteer ────────────────────────────────────────────────────────────────
# name (lowercase) -> (latitude, longitude). Districts, major towns, and the
# sub-region hubs. Approximate town-centre coordinates.
GAZETTEER: dict[str, Tuple[float, float]] = {
    # Lango sub-region (home turf)
    'lira': (2.2350, 32.9099), 'dokolo': (1.9186, 33.1750), 'apac': (1.9755, 32.5344),
    'oyam': (2.3833, 32.4667), 'kole': (2.4033, 32.8000), 'alebtong': (2.2545, 33.2480),
    'otuke': (2.5170, 33.4670), 'amolatar': (1.6300, 32.8300), 'kwania': (1.9500, 32.4000),
    # Acholi sub-region
    'gulu': (2.7746, 32.2990), 'kitgum': (3.2783, 32.8867), 'pader': (2.8000, 33.1333),
    'agago': (2.9200, 33.3500), 'amuru': (2.9600, 32.0800), 'nwoya': (2.6300, 31.9700),
    'lamwo': (3.5700, 32.8000), 'omoro': (2.6300, 32.5300), 'patongo': (2.7500, 33.3200),
    # Teso
    'soroti': (1.7146, 33.6111), 'kumi': (1.4600, 33.9360), 'katakwi': (1.8900, 33.9660),
    'amuria': (2.0300, 33.6400), 'serere': (1.5000, 33.5480), 'ngora': (1.4500, 33.7700),
    'bukedea': (1.3550, 34.1000), 'kaberamaido': (1.7300, 33.1600),
    # Karamoja
    'moroto': (2.5342, 34.6667), 'kotido': (3.0000, 34.1330), 'kaabong': (3.5130, 34.1200),
    'napak': (2.3600, 34.2500), 'nakapiripirit': (1.9100, 34.7000), 'amudat': (1.9500, 34.9500),
    # West Nile
    'arua': (3.0201, 30.9110), 'nebbi': (2.4753, 31.0890), 'pakwach': (2.4600, 31.4900),
    'zombo': (2.5100, 30.9100), 'moyo': (3.6489, 31.7220), 'adjumani': (3.3778, 31.7908),
    'yumbe': (3.4650, 31.2470), 'koboko': (3.4130, 30.9600), 'maracha': (3.2870, 30.9370),
    'madi-okollo': (2.9200, 31.1500), 'obongi': (3.3900, 31.5300),
    # Central / Kampala metro
    'kampala': (0.3476, 32.5825), 'wakiso': (0.4044, 32.4595), 'mukono': (0.3533, 32.7553),
    'entebbe': (0.0512, 32.4637), 'mpigi': (0.2270, 32.3130), 'luwero': (0.8490, 32.4990),
    'nakasongola': (1.3090, 32.4570), 'kayunga': (0.7020, 32.8890), 'masaka': (-0.3410, 31.7340),
    'mubende': (0.5570, 31.3950), 'mityana': (0.4170, 32.0430),
    # East
    'jinja': (0.4244, 33.2041), 'iganga': (0.6090, 33.4690), 'mbale': (1.0644, 34.1797),
    'tororo': (0.6929, 34.1810), 'busia': (0.4640, 34.0920), 'bugiri': (0.5340, 33.7420),
    'kapchorwa': (1.3970, 34.4500), 'sironko': (1.2300, 34.2470), 'pallisa': (1.1450, 33.7090),
    'kamuli': (0.9470, 33.1200),
    # West / South-west
    'mbarara': (-0.6072, 30.6545), 'fort portal': (0.6710, 30.2750), 'kasese': (0.1833, 30.0833),
    'hoima': (1.4350, 31.3520), 'masindi': (1.6740, 31.7150), 'kabale': (-1.2410, 29.9860),
    'bushenyi': (-0.5420, 30.1860), 'ntungamo': (-0.8790, 30.2640), 'kisoro': (-1.2850, 29.6850),
    'rukungiri': (-0.7890, 29.9410), 'ibanda': (-0.1340, 30.4930), 'kabarole': (0.6710, 30.2750),
    # Sub-region hubs (aliases → their principal town)
    'acholi': (2.7746, 32.2990), 'lango': (2.2350, 32.9099), 'karamoja': (2.5342, 34.6667),
    'west nile': (3.0201, 30.9110), 'teso': (1.7146, 33.6111), 'buganda': (0.3476, 32.5825),
    'ankole': (-0.6072, 30.6545), 'bugisu': (1.0644, 34.1797), 'busoga': (0.4244, 33.2041),
    'lango sub-region': (2.2350, 32.9099), 'acholi sub-region': (2.7746, 32.2990),
    'northern uganda': (2.7746, 32.2990), 'kampala city': (0.3476, 32.5825),
    'lira city': (2.2350, 32.9099), 'gulu city': (2.7746, 32.2990),
}

# Fallback centre when a reader is in Uganda but unplaceable, or non-local:
# the publication's home city (Lira) anchors the default "Local" feed.
DEFAULT_CENTER: Tuple[float, float] = GAZETTEER['lira']
UGANDA_BBOX = (-1.5, 4.3, 29.5, 35.1)  # (min_lat, max_lat, min_lon, max_lon)


def haversine_km(lat1: float, lon1: float, lat2: float, lon2: float) -> float:
    """Great-circle distance between two points in kilometres."""
    r = 6371.0088
    p1, p2 = math.radians(lat1), math.radians(lat2)
    dphi = math.radians(lat2 - lat1)
    dlmb = math.radians(lon2 - lon1)
    a = math.sin(dphi / 2) ** 2 + math.cos(p1) * math.cos(p2) * math.sin(dlmb / 2) ** 2
    return 2 * r * math.asin(min(1.0, math.sqrt(a)))


def in_uganda(lat: float, lon: float) -> bool:
    lo_lat, hi_lat, lo_lon, hi_lon = UGANDA_BBOX
    return lo_lat <= lat <= hi_lat and lo_lon <= lon <= hi_lon


def geocode_place(name: str) -> Optional[Tuple[float, float]]:
    """Resolve a place name to coordinates via the gazetteer (exact then token)."""
    if not name:
        return None
    key = name.strip().lower()
    if key in GAZETTEER:
        return GAZETTEER[key]
    # strip trailing "district"/"city"/"town"
    for suffix in (' district', ' city', ' town', ' municipality'):
        if key.endswith(suffix):
            base = key[: -len(suffix)].strip()
            if base in GAZETTEER:
                return GAZETTEER[base]
    # single-token match against multi-word place, e.g. "Gulu Town" -> "gulu"
    for token in key.split():
        if token in GAZETTEER:
            return GAZETTEER[token]
    return None


def geocode_article(article) -> Optional[Tuple[str, float, float]]:
    """Best-effort (place, lat, lon) from the article's most salient GPE entity."""
    entities = (article.entities
                .filter(entity_type='gpe')
                .order_by('-salience')
                .values_list('entity_text', flat=True))
    for text in entities:
        coords = geocode_place(text)
        if coords:
            return (text, coords[0], coords[1])
    return None


def reader_location(request, allow_ip: bool = True) -> Optional[dict]:
    """Resolve the reader's location for this request, cached on the request.

    Precedence: an explicit choice stored in the session (manual picker or GPS)
    → IP GeoIP (only if ``allow_ip``) → None. Returns {lat, lon, source, city?}.

    ``allow_ip=False`` keeps this cheap for the global context processor (no
    external GeoIP call on every page); the Local News view opts into the IP
    fallback where a location actually drives ranking.
    """
    sess = getattr(request, 'session', None)
    stored = sess.get('reader_loc') if sess is not None else None
    if stored and 'lat' in stored and 'lon' in stored:
        return dict(stored)

    if not allow_ip:
        return None

    cached = getattr(request, '_reader_loc_ip', 'unset')
    if cached != 'unset':
        return cached

    loc = None
    try:
        from apps.analytics.services.geoip import get_client_ip, lookup
        geo = lookup(get_client_ip(request))
        if geo.get('latitude') is not None and geo.get('longitude') is not None:
            loc = {
                'lat': geo['latitude'], 'lon': geo['longitude'],
                'source': 'ip', 'city': geo.get('city') or '',
            }
    except Exception:
        loc = None

    request._reader_loc_ip = loc
    return loc


def apply_geocode(article, save: bool = True) -> bool:
    """Geocode an article from its entities and store lat/lon/geo_place.

    Returns True if coordinates were set. Safe to call repeatedly.
    """
    hit = geocode_article(article)
    if not hit:
        return False
    place, lat, lon = hit
    article.geo_place = place[:120]
    article.latitude = lat
    article.longitude = lon
    if save:
        article.save(update_fields=['geo_place', 'latitude', 'longitude'])
    return True
