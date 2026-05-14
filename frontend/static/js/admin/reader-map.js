/**
 * Northern Times — Reader Heatmap (Leaflet Satellite View)
 *
 * Interactive satellite map with heat overlay showing reader locations.
 * Features: satellite tiles (street-level zoom), heatmap layer, city markers,
 *           click-to-detail analytics panel, real-time pulse, period selector.
 *
 * Dependencies (loaded from CDN in dashboard.php):
 *   - Leaflet 1.9.4
 *   - Leaflet.heat
 */
(function () {
  'use strict';

  var API_URL      = '/admin/api/reader-map';
  var CITY_API_URL = '/admin/api/reader-map/city';
  var PULSE_URL    = '/admin/api/dashboard-pulse';
  var PULSE_INTERVAL = 30000; // 30 seconds

  var container = document.getElementById('nt-reader-map');
  if (!container) return;

  var mapWrap    = container.querySelector('.rm-map-wrap');
  var topList    = container.querySelector('.rm-top-list');
  var totalEl    = container.querySelector('.rm-total');
  var periodBtns = container.querySelectorAll('.rm-period-btn');

  var currentPeriod = '30d';
  var map, heatLayer, markersGroup;
  var citiesData = [];
  var detailPanel = null;

  // ── Dot Tiers ─────────────────────────────────────────────
  function dotRadius(v) {
    if (v >= 1000) return 14;
    if (v >= 201)  return 10;
    if (v >= 51)   return 7;
    return 4;
  }

  function dotColor(v) {
    if (v >= 1000) return '#ef4444';
    if (v >= 201)  return '#f97316';
    if (v >= 51)   return '#facc15';
    return '#4ade80';
  }

  // ── Init Map ──────────────────────────────────────────────
  function initMap() {
    if (typeof L === 'undefined') {
      mapWrap.innerHTML = '<p style="color:#999;text-align:center;padding:40px;">Map library not loaded.</p>';
      return;
    }

    if (map) { map.remove(); map = null; }

    if (!mapWrap.offsetHeight || mapWrap.offsetHeight < 100) {
      mapWrap.style.height = '420px';
    }

    map = L.map(mapWrap, {
      center: [1.5, 32.5],
      zoom: 3,
      minZoom: 2,
      maxZoom: 20,
      zoomControl: false,
      attributionControl: false,
      worldCopyJump: true
    });

    // Satellite tile layer (ESRI World Imagery — free, no API key, up to zoom 20)
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
      maxZoom: 20,
      maxNativeZoom: 18
    }).addTo(map);

    // Label overlay for country/city names
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
      maxZoom: 20,
      maxNativeZoom: 18,
      opacity: 0.65
    }).addTo(map);

    // OSM overlay for street-level detail at high zoom
    var osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 20,
      minZoom: 16,
      opacity: 0.7,
      attribution: ''
    });

    L.control.attribution({ position: 'bottomleft', prefix: false })
      .addAttribution('Tiles &copy; Esri &amp; OSM')
      .addTo(map);

    // Initialize heat layer
    heatLayer = L.heatLayer([], {
      radius: 35,
      blur: 25,
      maxZoom: 10,
      max: 1.0,
      gradient: {
        0.1: '#1a237e',
        0.25: '#0d47a1',
        0.4: '#00bcd4',
        0.55: '#4caf50',
        0.7: '#ffeb3b',
        0.85: '#ff9800',
        1.0: '#f44336'
      }
    }).addTo(map);

    markersGroup = L.layerGroup().addTo(map);

    // Adjust visibility based on zoom
    map.on('zoomend', function () {
      var z = map.getZoom();
      if (z >= 16 && !map.hasLayer(osmLayer)) {
        map.addLayer(osmLayer);
      } else if (z < 16 && map.hasLayer(osmLayer)) {
        map.removeLayer(osmLayer);
      }
      if (z >= 6) {
        if (!map.hasLayer(markersGroup)) map.addLayer(markersGroup);
        if (map.hasLayer(heatLayer)) map.removeLayer(heatLayer);
      } else {
        if (!map.hasLayer(heatLayer)) map.addLayer(heatLayer);
        if (map.hasLayer(markersGroup)) map.removeLayer(markersGroup);
      }
    });

    // Force Leaflet to recalculate container size
    setTimeout(function () { map.invalidateSize(); }, 100);

    fetchData(currentPeriod);
  }

  // ── Fetch & Render Data ───────────────────────────────────
  function fetchData(period) {
    fetch(API_URL + '?period=' + period)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        citiesData = data.cities || [];
        totalEl.textContent = Number(data.total_visitors || 0).toLocaleString() + ' visitors';
        renderHeatmap();
        renderMarkers();
        renderTopCities();
        if (citiesData.length > 0 && map) {
          var bounds = citiesData.map(function(c) { return [c.lat, c.lng]; });
          if (bounds.length === 1) {
            map.flyTo(bounds[0], 6, { duration: 1 });
          } else {
            map.fitBounds(bounds, { padding: [40, 40], maxZoom: 8 });
          }
        }
      })
      .catch(function (err) {
        console.error('Reader map API error:', err);
      });
  }

  // ── Render Heatmap Layer ──────────────────────────────────
  function renderHeatmap() {
    if (!heatLayer) return;
    var maxV = 1;
    citiesData.forEach(function (c) { if (c.visitors > maxV) maxV = c.visitors; });
    var points = citiesData.map(function (c) {
      return [c.lat, c.lng, Math.min(1, (c.visitors / maxV) * 0.8 + 0.2)];
    });
    heatLayer.setLatLngs(points);
  }

  // ── Render City Markers ───────────────────────────────────
  function renderMarkers() {
    if (!markersGroup) return;
    markersGroup.clearLayers();

    citiesData.forEach(function (c) {
      if (!c.lat || !c.lng) return;
      var color = dotColor(c.visitors);
      var radius = dotRadius(c.visitors);

      var marker = L.circleMarker([c.lat, c.lng], {
        radius: radius,
        fillColor: color,
        fillOpacity: 0.85,
        color: color,
        weight: 1,
        opacity: 0.9,
        className: c.visitors >= 1000 ? 'rm-marker-pulse' : ''
      });

      var popupHtml =
        '<div class="rm-popup-inner">' +
          '<strong>' + escHtml(c.city) + '</strong>' +
          '<span class="rm-popup-country">' + escHtml(c.country) + '</span><br>' +
          '<span class="rm-popup-visitors" style="color:' + color + ';">' +
            Number(c.visitors).toLocaleString() +
          '</span>' +
          '<span class="rm-popup-pct">visitors (' + c.percentage + '%)</span>' +
          '<div class="rm-popup-action" data-city="' + escAttr(c.city) + '">View Details →</div>' +
        '</div>';

      marker.bindPopup(popupHtml, { className: 'rm-popup', closeButton: false, maxWidth: 220 });
      marker.on('mouseover', function () { this.openPopup(); });

      markersGroup.addLayer(marker);
    });

    // Delegate click on "View Details" inside popups
    map.on('popupopen', function (e) {
      var detailBtn = e.popup.getElement().querySelector('.rm-popup-action');
      if (detailBtn) {
        detailBtn.addEventListener('click', function () {
          var city = this.dataset.city;
          map.closePopup();
          openCityDetail(city);
        });
      }
    });
  }

  // ── Render Top Cities List ────────────────────────────────
  function renderTopCities() {
    if (!topList) return;
    var html = '';
    var top8 = citiesData.slice(0, 8);

    top8.forEach(function (c) {
      var color = dotColor(c.visitors);
      html += '<div class="rm-city-row" style="cursor:pointer;" data-city="' + escAttr(c.city) + '" data-lat="' + c.lat + '" data-lng="' + c.lng + '">' +
        '<span class="rm-city-dot" style="background:' + color + ';"></span>' +
        '<span class="rm-city-name">' + escHtml(c.city) + '</span>' +
        '<span class="rm-city-count">' + Number(c.visitors).toLocaleString() + '</span>' +
        '<span class="rm-city-pct">' + c.percentage + '%</span>' +
        '</div>';
    });

    if (top8.length === 0) {
      html = '<p style="color:#888;font-size:13px;text-align:center;padding:12px;">No geo data yet. Visitors will appear after they browse the site.</p>';
    }

    topList.innerHTML = html;

    // Click → fly + open detail
    topList.querySelectorAll('.rm-city-row[data-city]').forEach(function (row) {
      row.addEventListener('click', function () {
        var lat = parseFloat(this.dataset.lat);
        var lng = parseFloat(this.dataset.lng);
        var city = this.dataset.city;
        if (map && !isNaN(lat) && !isNaN(lng)) {
          map.flyTo([lat, lng], 10, { duration: 1.2 });
        }
        openCityDetail(city);
      });
    });
  }

  // ── City Detail Panel ─────────────────────────────────────
  function openCityDetail(city) {
    if (!detailPanel) {
      detailPanel = document.createElement('div');
      detailPanel.className = 'rm-detail-panel';
      container.appendChild(detailPanel);
    }

    detailPanel.innerHTML = '<div class="rm-detail-loading">Loading analytics for <strong>' + escHtml(city) + '</strong>...</div>';
    detailPanel.style.display = 'block';

    fetch(CITY_API_URL + '?city=' + encodeURIComponent(city) + '&period=' + currentPeriod)
      .then(function (r) { return r.json(); })
      .then(function (data) { renderCityDetail(data); })
      .catch(function () {
        detailPanel.innerHTML = '<div class="rm-detail-loading">Failed to load city data.</div>';
      });
  }

  function renderCityDetail(data) {
    if (!detailPanel) return;

    var loc = data.location || {};
    var html = '<div class="rm-detail-header">' +
      '<div>' +
        '<h4 class="rm-detail-title">' + escHtml(data.city) + '</h4>' +
        '<span class="rm-detail-sub">' + escHtml((loc.region || '') + (loc.region ? ', ' : '') + (loc.country || '')) + '</span>' +
      '</div>' +
      '<div style="display:flex;align-items:center;gap:12px;">' +
        '<span class="rm-detail-total">' + Number(data.total).toLocaleString() + ' visitors</span>' +
        '<button class="rm-detail-close" title="Close">&times;</button>' +
      '</div>' +
    '</div>';

    // Grid of analytics cards
    html += '<div class="rm-detail-grid">';

    // Devices
    html += renderDetailCard('Devices', data.devices, 'device', function (d) {
      return deviceIcon(d.device) + ' ' + escHtml(d.device);
    });

    // Browsers
    html += renderDetailCard('Browsers', data.browsers, 'browser', function (b) {
      return escHtml(b.browser);
    });

    // Operating Systems
    html += renderDetailCard('OS', data.os, 'os', function (o) {
      return escHtml(o.os);
    });

    // Top Categories
    html += renderDetailCard('Top Categories', data.categories, 'category', function (c) {
      return '<a href="/admin/articles?category=' + escAttr(c.slug) + '" style="color:var(--accent);text-decoration:none;">' + escHtml(c.category) + '</a>';
    });

    // Top Pages
    html += renderDetailCard('Top Landing Pages', (data.top_pages || []).slice(0, 6), 'page', function (p) {
      var label = p.page === '/' ? 'Homepage' : p.page.replace(/^\//, '').substring(0, 40);
      return '<span title="' + escAttr(p.page) + '">' + escHtml(label) + '</span>';
    });

    // Popup Interactions
    var popupData = formatPopupStats(data.popup_stats || []);
    html += '<div class="rm-detail-card">' +
      '<div class="rm-detail-card-title">Popup Interactions</div>';
    if (popupData.length > 0) {
      popupData.forEach(function (p) {
        html += '<div class="rm-detail-row">' +
          '<span class="rm-detail-label">' + escHtml(p.metric) + '</span>' +
          '<span class="rm-detail-value">' + Number(p.count).toLocaleString() + '</span>' +
        '</div>';
      });
    } else {
      html += '<div class="rm-detail-empty">No popup data</div>';
    }
    html += '</div>';

    // Ad Interactions
    var adData = formatAdStats(data.ad_stats || []);
    html += '<div class="rm-detail-card">' +
      '<div class="rm-detail-card-title">Ad Interactions</div>';
    if (adData.length > 0) {
      adData.forEach(function (a) {
        html += '<div class="rm-detail-row">' +
          '<span class="rm-detail-label">' + escHtml(a.metric) + '</span>' +
          '<span class="rm-detail-value">' + Number(a.count).toLocaleString() + '</span>' +
        '</div>';
      });
      // CTR
      var adImp = 0, adClk = 0;
      adData.forEach(function(a) {
        if (a.metric === 'Impressions') adImp = a.count;
        if (a.metric === 'Clicks') adClk = a.count;
      });
      if (adImp > 0) {
        html += '<div class="rm-detail-row">' +
          '<span class="rm-detail-label" style="font-weight:600;">CTR</span>' +
          '<span class="rm-detail-value" style="color:var(--accent);">' + ((adClk / adImp) * 100).toFixed(1) + '%</span>' +
        '</div>';
      }
    } else {
      html += '<div class="rm-detail-empty">No ad data</div>';
    }
    html += '</div>';

    // Top Ad Slots
    var topAds = data.top_ads || [];
    if (topAds.length > 0) {
      html += '<div class="rm-detail-card">' +
        '<div class="rm-detail-card-title">Top Ad Slots</div>';
      topAds.forEach(function (a) {
        html += '<div class="rm-detail-row">' +
          '<span class="rm-detail-label">' + escHtml(a.label || a.slot_name) +
            ' <small style="color:#888;">(' + escHtml(a.event_type) + ')</small></span>' +
          '<span class="rm-detail-value">' + Number(a.count).toLocaleString() + '</span>' +
        '</div>';
      });
      html += '</div>';
    }

    html += '</div>'; // end grid

    // Timeline sparkline (simple CSS bars)
    if (data.timeline && data.timeline.length > 1) {
      html += renderTimeline(data.timeline);
    }

    detailPanel.innerHTML = html;

    // Close button
    detailPanel.querySelector('.rm-detail-close').addEventListener('click', function () {
      detailPanel.style.display = 'none';
    });
  }

  function renderDetailCard(title, items, key, labelFn) {
    var html = '<div class="rm-detail-card">' +
      '<div class="rm-detail-card-title">' + title + '</div>';
    if (items && items.length > 0) {
      items.forEach(function (item) {
        var pct = item.pct || (item.count ? 0 : 0);
        html += '<div class="rm-detail-row">' +
          '<span class="rm-detail-label">' + labelFn(item) + '</span>' +
          '<div class="rm-detail-bar-wrap">' +
            '<div class="rm-detail-bar" style="width:' + Math.max(2, pct) + '%;"></div>' +
          '</div>' +
          '<span class="rm-detail-value">' + Number(item.count).toLocaleString() +
            (pct ? ' <small>(' + pct + '%)</small>' : '') + '</span>' +
        '</div>';
      });
    } else {
      html += '<div class="rm-detail-empty">No data yet</div>';
    }
    html += '</div>';
    return html;
  }

  function renderTimeline(timeline) {
    var maxC = 1;
    timeline.forEach(function (t) { var c = parseInt(t.count); if (c > maxC) maxC = c; });
    var html = '<div class="rm-detail-card" style="grid-column:1/-1;">' +
      '<div class="rm-detail-card-title">Daily Visitors</div>' +
      '<div class="rm-timeline">';
    timeline.forEach(function (t) {
      var c = parseInt(t.count);
      var h = Math.max(3, (c / maxC) * 60);
      var day = t.date.substring(5); // MM-DD
      html += '<div class="rm-tl-bar-wrap" title="' + t.date + ': ' + c + ' visitors">' +
        '<div class="rm-tl-bar" style="height:' + h + 'px;"></div>' +
        '<div class="rm-tl-label">' + day + '</div>' +
      '</div>';
    });
    html += '</div></div>';
    return html;
  }

  function formatPopupStats(stats) {
    var m = {};
    stats.forEach(function (s) { m[s.metric] = parseInt(s.count); });
    var result = [];
    if (m.impressions) result.push({ metric: 'Impressions', count: m.impressions });
    if (m.conversions) result.push({ metric: 'Conversions', count: m.conversions });
    if (m.dismissals) result.push({ metric: 'Dismissals', count: m.dismissals });
    return result;
  }

  function formatAdStats(stats) {
    var m = {};
    stats.forEach(function (s) { m[s.metric] = parseInt(s.count); });
    var result = [];
    if (m.impressions) result.push({ metric: 'Impressions', count: m.impressions });
    if (m.clicks) result.push({ metric: 'Clicks', count: m.clicks });
    return result;
  }

  function deviceIcon(type) {
    switch ((type || '').toLowerCase()) {
      case 'mobile':  return '📱';
      case 'tablet':  return '📱';
      case 'desktop': return '💻';
      case 'bot':     return '🤖';
      default:        return '❓';
    }
  }

  // ── Period Selector ───────────────────────────────────────
  periodBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      periodBtns.forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      currentPeriod = btn.dataset.period;
      fetchData(currentPeriod);
      if (detailPanel) detailPanel.style.display = 'none';
    });
  });

  // ── Zoom Controls ─────────────────────────────────────────
  var zoomInBtn  = container.querySelector('.rm-zoom-in');
  var zoomOutBtn = container.querySelector('.rm-zoom-out');
  var resetBtn   = container.querySelector('.rm-zoom-reset');

  if (zoomInBtn) zoomInBtn.addEventListener('click', function () { if (map) map.zoomIn(1); });
  if (zoomOutBtn) zoomOutBtn.addEventListener('click', function () { if (map) map.zoomOut(1); });
  if (resetBtn) resetBtn.addEventListener('click', function () {
    if (map) map.flyTo([1.5, 32.5], 3, { duration: 0.8 });
  });

  // ── Responsive ────────────────────────────────────────────
  var resizeTimer;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () { if (map) map.invalidateSize(); }, 200);
  });

  // ── Live Ping Flash ──────────────────────────────────────
  function flashPings(pings) {
    if (!map || !pings || !pings.length) return;
    pings.forEach(function (p) {
      if (!p.lat || !p.lng) return;
      var lat = +p.lat, lng = +p.lng;
      var ring = L.circleMarker([lat, lng], {
        radius: 5, fillColor: '#22c55e', fillOpacity: 0.9,
        color: '#22c55e', weight: 2, opacity: 1
      }).addTo(map);
      var frame = 0, maxFrames = 30;
      function animate() {
        frame++;
        var progress = frame / maxFrames;
        ring.setRadius(5 + progress * 25);
        ring.setStyle({ opacity: 1 - progress, fillOpacity: (1 - progress) * 0.3 });
        if (frame < maxFrames) requestAnimationFrame(animate);
        else map.removeLayer(ring);
      }
      requestAnimationFrame(animate);
    });
  }

  // ── Utilities ─────────────────────────────────────────────
  function escHtml(str) {
    var div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
  }
  function escAttr(str) {
    return (str || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  // ── Real-time Pulse Polling ──────────────────────────────
  var pulseTimer = null;
  var liveIndicator = null;

  function startPulse() {
    if (pulseTimer) return;

    // Create live indicator
    if (!liveIndicator) {
      liveIndicator = document.createElement('div');
      liveIndicator.className = 'rm-live-indicator';
      liveIndicator.innerHTML = '<span class="rm-live-dot"></span> LIVE';
      var header = container.querySelector('.rm-header') || container.firstElementChild;
      if (header) header.appendChild(liveIndicator);
    }

    function poll() {
      fetch(PULSE_URL)
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.pings && data.pings.length > 0) {
            flashPings(data.pings);
          }
          // Update visitor count badge
          if (data.visitors_today !== undefined && totalEl) {
            var current = totalEl.textContent;
            if (current.indexOf('today') === -1) {
              // Don't override period total, but show live count
            }
          }
        })
        .catch(function () {});
    }

    poll(); // Initial call
    pulseTimer = setInterval(poll, PULSE_INTERVAL);
  }

  function stopPulse() {
    if (pulseTimer) {
      clearInterval(pulseTimer);
      pulseTimer = null;
    }
    if (liveIndicator) {
      liveIndicator.remove();
      liveIndicator = null;
    }
  }

  // Start polling when map is visible (IntersectionObserver)
  if (window.IntersectionObserver) {
    var visObs = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) startPulse();
        else stopPulse();
      });
    }, { threshold: 0.1 });
    visObs.observe(container);
  }

  // ── Expose API ────────────────────────────────────────────
  window.ntReaderMap = {
    flashPings: flashPings,
    refresh: function (period) {
      currentPeriod = period || currentPeriod;
      fetchData(currentPeriod);
    },
    startPulse: startPulse,
    stopPulse: stopPulse
  };

  // ── Boot ──────────────────────────────────────────────────
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { setTimeout(initMap, 50); });
  } else {
    setTimeout(initMap, 50);
  }

})();
