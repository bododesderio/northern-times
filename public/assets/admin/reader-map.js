/**
 * Northern Times — Reader Heatmap (Leaflet Satellite View)
 *
 * Interactive satellite map with heat overlay showing reader locations.
 * Features: satellite tiles, heatmap layer, city markers, zoom/pan,
 *           tooltips, period selector, live ping animation.
 *
 * Dependencies (loaded from CDN in dashboard.php):
 *   - Leaflet 1.9.4
 *   - Leaflet.heat
 */
(function () {
  'use strict';

  const API_URL = '/admin/api/reader-map';

  const container = document.getElementById('nt-reader-map');
  if (!container) return;

  const mapWrap  = container.querySelector('.rm-map-wrap');
  const topList  = container.querySelector('.rm-top-list');
  const totalEl  = container.querySelector('.rm-total');
  const periodBtns = container.querySelectorAll('.rm-period-btn');

  let currentPeriod = '30d';
  let map, heatLayer, markersGroup;
  let citiesData = [];

  // ── Dot Tiers ─────────────────────────────────────────────
  function dotRadius(visitors) {
    if (visitors >= 1000) return 14;
    if (visitors >= 201)  return 10;
    if (visitors >= 51)   return 7;
    return 4;
  }

  function dotColor(visitors) {
    if (visitors >= 1000) return '#ef4444';
    if (visitors >= 201)  return '#f97316';
    if (visitors >= 51)   return '#facc15';
    return '#4ade80';
  }

  // ── Init Map ──────────────────────────────────────────────
  function initMap() {
    if (map) { map.remove(); map = null; }

    const rect = mapWrap.getBoundingClientRect();
    const h = Math.max(400, rect.width * 0.5);
    mapWrap.style.height = h + 'px';

    map = L.map(mapWrap, {
      center: [20, 15],
      zoom: 2,
      minZoom: 2,
      maxZoom: 18,
      zoomControl: false,
      attributionControl: false,
      worldCopyJump: true
    });

    // Satellite tile layer (ESRI World Imagery — free, no API key)
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
      maxZoom: 18
    }).addTo(map);

    // Semi-transparent label overlay for country/city names
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
      maxZoom: 18,
      opacity: 0.65
    }).addTo(map);

    // Small attribution in corner
    L.control.attribution({ position: 'bottomleft', prefix: false })
      .addAttribution('Tiles &copy; Esri')
      .addTo(map);

    // Initialize heat layer (empty)
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

    // Markers layer group for city dots
    markersGroup = L.layerGroup().addTo(map);

    // Adjust visibility based on zoom
    map.on('zoomend', function () {
      var z = map.getZoom();
      if (z >= 6) {
        // Show individual markers, hide heatmap at high zoom
        if (!map.hasLayer(markersGroup)) map.addLayer(markersGroup);
        if (map.hasLayer(heatLayer)) map.removeLayer(heatLayer);
      } else {
        // Show heatmap, hide markers at low zoom
        if (!map.hasLayer(heatLayer)) map.addLayer(heatLayer);
        if (map.hasLayer(markersGroup)) map.removeLayer(markersGroup);
      }
    });

    // Load initial data
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
      })
      .catch(function (err) {
        console.error('Reader map API error:', err);
      });
  }

  // ── Render Heatmap Layer ──────────────────────────────────
  function renderHeatmap() {
    if (!heatLayer) return;

    var maxVisitors = 1;
    citiesData.forEach(function (c) {
      if (c.visitors > maxVisitors) maxVisitors = c.visitors;
    });

    var points = citiesData.map(function (c) {
      var intensity = Math.min(1, (c.visitors / maxVisitors) * 0.8 + 0.2);
      return [c.lat, c.lng, intensity];
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
        '<div style="font-family:system-ui;min-width:140px;">' +
          '<strong style="font-size:14px;">' + escHtml(c.city) + '</strong>' +
          '<span style="color:#999;font-size:12px;margin-left:4px;">' + escHtml(c.country) + '</span><br>' +
          '<span style="font-size:20px;font-weight:700;color:' + color + ';">' +
            Number(c.visitors).toLocaleString() +
          '</span>' +
          '<span style="font-size:12px;color:#888;margin-left:4px;">visitors (' + c.percentage + '%)</span>' +
        '</div>';

      marker.bindPopup(popupHtml, { className: 'rm-popup', closeButton: false });

      marker.on('mouseover', function () { this.openPopup(); });
      marker.on('mouseout', function () { this.closePopup(); });

      markersGroup.addLayer(marker);
    });
  }

  // ── Render Top Cities List ────────────────────────────────
  function renderTopCities() {
    if (!topList) return;

    var html = '';
    var top8 = citiesData.slice(0, 8);

    top8.forEach(function (c) {
      var color = dotColor(c.visitors);
      html += '<div class="rm-city-row" style="cursor:pointer;" data-lat="' + c.lat + '" data-lng="' + c.lng + '">' +
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

    // Click to fly to city
    topList.querySelectorAll('.rm-city-row[data-lat]').forEach(function (row) {
      row.addEventListener('click', function () {
        var lat = parseFloat(this.dataset.lat);
        var lng = parseFloat(this.dataset.lng);
        if (map && !isNaN(lat) && !isNaN(lng)) {
          map.flyTo([lat, lng], 10, { duration: 1.2 });
        }
      });
    });
  }

  // ── Period Selector ───────────────────────────────────────
  periodBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      periodBtns.forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      currentPeriod = btn.dataset.period;
      fetchData(currentPeriod);
    });
  });

  // ── Zoom Controls ─────────────────────────────────────────
  var zoomInBtn  = container.querySelector('.rm-zoom-in');
  var zoomOutBtn = container.querySelector('.rm-zoom-out');
  var resetBtn   = container.querySelector('.rm-zoom-reset');

  if (zoomInBtn) {
    zoomInBtn.addEventListener('click', function () {
      if (map) map.zoomIn(1);
    });
  }
  if (zoomOutBtn) {
    zoomOutBtn.addEventListener('click', function () {
      if (map) map.zoomOut(1);
    });
  }
  if (resetBtn) {
    resetBtn.addEventListener('click', function () {
      if (map) map.flyTo([20, 15], 2, { duration: 0.8 });
    });
  }

  // ── Responsive ────────────────────────────────────────────
  var resizeTimer;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
      if (map) map.invalidateSize();
    }, 200);
  });

  // ── Live Ping Flash ──────────────────────────────────────
  function flashPings(pings) {
    if (!map || !pings || !pings.length) return;

    pings.forEach(function (p) {
      if (!p.lat || !p.lng) return;
      var lat = +p.lat, lng = +p.lng;

      // Expanding ring
      var ring = L.circleMarker([lat, lng], {
        radius: 5,
        fillColor: '#22c55e',
        fillOpacity: 0.9,
        color: '#22c55e',
        weight: 2,
        opacity: 1
      }).addTo(map);

      var frame = 0;
      var maxFrames = 30;
      function animate() {
        frame++;
        var progress = frame / maxFrames;
        var r = 5 + progress * 25;
        var opacity = 1 - progress;
        ring.setRadius(r);
        ring.setStyle({ opacity: opacity, fillOpacity: opacity * 0.3 });
        if (frame < maxFrames) {
          requestAnimationFrame(animate);
        } else {
          map.removeLayer(ring);
        }
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

  // ── Expose API ────────────────────────────────────────────
  window.ntReaderMap = {
    flashPings: flashPings,
    refresh: function (period) {
      currentPeriod = period || currentPeriod;
      fetchData(currentPeriod);
    }
  };

  // ── Boot ──────────────────────────────────────────────────
  initMap();

})();
