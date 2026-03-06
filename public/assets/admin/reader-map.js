/**
 * Northern Times — Reader Heatmap (D3.js World Map)
 *
 * Interactive world map showing glowing dots on cities where readers are located.
 * Features: zoom/pan, click-to-zoom countries, dot tiers, tooltips, period selector.
 *
 * Dependencies (loaded from CDN in dashboard.php):
 *   - d3 v7
 *   - topojson v3
 */
(function () {
  'use strict';

  const WORLD_URL = 'https://cdn.jsdelivr.net/npm/world-atlas@2/countries-110m.json';
  const API_URL   = '/admin/api/reader-map';

  const container = document.getElementById('nt-reader-map');
  if (!container) return;

  const mapWrap = container.querySelector('.rm-map-wrap');
  const topList = container.querySelector('.rm-top-list');
  const totalEl = container.querySelector('.rm-total');
  const periodBtns = container.querySelectorAll('.rm-period-btn');

  let currentPeriod = '30d';
  let svg, mapGroup, projection, path, zoom;
  let width, height;
  let citiesData = [];

  // ── Dot Tiers ─────────────────────────────────────────────
  function dotRadius(visitors) {
    if (visitors >= 1000) return 12;
    if (visitors >= 201)  return 8;
    if (visitors >= 51)   return 5;
    return 3;
  }

  function dotColor(visitors) {
    if (visitors >= 1000) return '#ef4444';
    if (visitors >= 201)  return '#f97316';
    if (visitors >= 51)   return '#facc15';
    return '#4ade80';
  }

  function dotGlow(visitors) {
    if (visitors >= 1000) return 'pulsing';
    if (visitors >= 201)  return 'medium';
    if (visitors >= 51)   return 'subtle';
    return 'none';
  }

  // ── Init Map ──────────────────────────────────────────────
  function initMap() {
    const rect = mapWrap.getBoundingClientRect();
    width  = rect.width || 800;
    height = Math.max(350, width * 0.5);

    // Projection centered slightly toward Africa
    projection = d3.geoNaturalEarth1()
      .scale(width / 5.5)
      .translate([width / 2, height / 2]);

    path = d3.geoPath().projection(projection);

    svg = d3.select(mapWrap).append('svg')
      .attr('width', width)
      .attr('height', height)
      .style('background', '#1a1f2e')
      .style('border-radius', '10px');

    // Defs for glow filters
    const defs = svg.append('defs');

    // Subtle glow
    const filterSubtle = defs.append('filter').attr('id', 'glow-subtle');
    filterSubtle.append('feGaussianBlur').attr('stdDeviation', 2).attr('result', 'blur');
    filterSubtle.append('feMerge').selectAll('feMergeNode')
      .data(['blur', 'SourceGraphic']).join('feMergeNode').attr('in', d => d);

    // Medium glow
    const filterMedium = defs.append('filter').attr('id', 'glow-medium');
    filterMedium.append('feGaussianBlur').attr('stdDeviation', 3.5).attr('result', 'blur');
    filterMedium.append('feMerge').selectAll('feMergeNode')
      .data(['blur', 'SourceGraphic']).join('feMergeNode').attr('in', d => d);

    // Pulsing glow (large)
    const filterPulse = defs.append('filter').attr('id', 'glow-pulsing');
    filterPulse.append('feGaussianBlur').attr('stdDeviation', 5).attr('result', 'blur');
    filterPulse.append('feMerge').selectAll('feMergeNode')
      .data(['blur', 'SourceGraphic']).join('feMergeNode').attr('in', d => d);

    // Graticule
    const graticule = d3.geoGraticule();

    mapGroup = svg.append('g');

    // Ocean background
    mapGroup.append('rect')
      .attr('width', width * 3)
      .attr('height', height * 3)
      .attr('x', -width)
      .attr('y', -height)
      .attr('fill', '#1a1f2e');

    // Graticule lines
    mapGroup.append('path')
      .datum(graticule)
      .attr('d', path)
      .attr('fill', 'none')
      .attr('stroke', '#2a3040')
      .attr('stroke-width', 0.3);

    // Zoom behavior
    zoom = d3.zoom()
      .scaleExtent([1, 12])
      .on('zoom', function (event) {
        mapGroup.attr('transform', event.transform);
        // Scale dots inversely
        mapGroup.selectAll('.rm-dot')
          .attr('r', function (d) { return dotRadius(d.visitors) / event.transform.k; });
        mapGroup.selectAll('.rm-pulse')
          .attr('r', function (d) { return (dotRadius(d.visitors) + 4) / event.transform.k; });
        // Scale strokes
        mapGroup.selectAll('.rm-country')
          .attr('stroke-width', 0.4 / event.transform.k);
      });

    svg.call(zoom);

    // Double-click reset
    svg.on('dblclick.zoom', null);
    svg.on('dblclick', function () {
      svg.transition().duration(750).call(zoom.transform, d3.zoomIdentity);
    });

    // Load world data
    loadWorld();
  }

  // ── Load World ────────────────────────────────────────────
  function loadWorld() {
    d3.json(WORLD_URL).then(function (world) {
      const countries = topojson.feature(world, world.objects.countries);

      mapGroup.selectAll('.rm-country')
        .data(countries.features)
        .join('path')
        .attr('class', 'rm-country')
        .attr('d', path)
        .attr('fill', '#2a3344')
        .attr('stroke', '#3b4560')
        .attr('stroke-width', 0.4)
        .on('click', function (event, d) {
          clickZoomCountry(event, d);
        })
        .on('mouseover', function () {
          d3.select(this).attr('fill', '#3a4560');
        })
        .on('mouseout', function () {
          d3.select(this).attr('fill', '#2a3344');
        });

      // Load initial data
      fetchData(currentPeriod);
    }).catch(function (err) {
      console.error('Failed to load world atlas:', err);
      mapWrap.innerHTML = '<p style="color:#ef4444;text-align:center;padding:40px;">Failed to load world map data.</p>';
    });
  }

  // ── Click-to-zoom Country ─────────────────────────────────
  function clickZoomCountry(event, d) {
    event.stopPropagation();
    const bounds = path.bounds(d);
    const dx = bounds[1][0] - bounds[0][0];
    const dy = bounds[1][1] - bounds[0][1];
    const x = (bounds[0][0] + bounds[1][0]) / 2;
    const y = (bounds[0][1] + bounds[1][1]) / 2;
    const scale = Math.min(8, 0.9 / Math.max(dx / width, dy / height));

    svg.transition().duration(750).call(
      zoom.transform,
      d3.zoomIdentity.translate(width / 2, height / 2).scale(scale).translate(-x, -y)
    );
  }

  // ── Tooltip ───────────────────────────────────────────────
  const tooltip = d3.select('body').append('div')
    .attr('class', 'rm-tooltip')
    .style('position', 'absolute')
    .style('display', 'none')
    .style('background', 'rgba(0,0,0,.88)')
    .style('color', '#fff')
    .style('padding', '8px 14px')
    .style('border-radius', '8px')
    .style('font-size', '13px')
    .style('pointer-events', 'none')
    .style('z-index', '10000')
    .style('box-shadow', '0 4px 12px rgba(0,0,0,.3)')
    .style('backdrop-filter', 'blur(4px)');

  // ── Fetch & Render Data ───────────────────────────────────
  function fetchData(period) {
    fetch(API_URL + '?period=' + period)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        citiesData = data.cities || [];
        totalEl.textContent = Number(data.total_visitors || 0).toLocaleString() + ' visitors';
        renderDots();
        renderTopCities();
      })
      .catch(function (err) {
        console.error('Reader map API error:', err);
      });
  }

  // ── Render Dots ───────────────────────────────────────────
  function renderDots() {
    // Get current zoom level
    const currentTransform = d3.zoomTransform(svg.node());
    const k = currentTransform.k;

    // Remove existing dots
    mapGroup.selectAll('.rm-dot, .rm-pulse').remove();

    // Pulse rings (for 1000+ cities)
    mapGroup.selectAll('.rm-pulse')
      .data(citiesData.filter(function (d) { return d.visitors >= 1000; }))
      .join('circle')
      .attr('class', 'rm-pulse')
      .attr('cx', function (d) { var p = projection([d.lng, d.lat]); return p ? p[0] : -999; })
      .attr('cy', function (d) { var p = projection([d.lng, d.lat]); return p ? p[1] : -999; })
      .attr('r', function (d) { return (dotRadius(d.visitors) + 4) / k; })
      .attr('fill', 'none')
      .attr('stroke', '#ef4444')
      .attr('stroke-width', 1.5 / k)
      .attr('opacity', 0.6)
      .style('animation', 'rm-pulse-anim 2s ease-in-out infinite');

    // City dots
    mapGroup.selectAll('.rm-dot')
      .data(citiesData)
      .join('circle')
      .attr('class', 'rm-dot')
      .attr('cx', function (d) { var p = projection([d.lng, d.lat]); return p ? p[0] : -999; })
      .attr('cy', function (d) { var p = projection([d.lng, d.lat]); return p ? p[1] : -999; })
      .attr('r', 0)
      .attr('fill', function (d) { return dotColor(d.visitors); })
      .attr('opacity', 0.85)
      .attr('filter', function (d) {
        var g = dotGlow(d.visitors);
        if (g === 'none') return null;
        return 'url(#glow-' + g + ')';
      })
      .style('cursor', 'pointer')
      .on('mouseover', function (event, d) {
        tooltip.style('display', 'block')
          .html(
            '<strong>' + d.city + '</strong>, ' + d.country + '<br>' +
            '<span style="font-size:15px;font-weight:700;">' + Number(d.visitors).toLocaleString() + '</span> visitors ' +
            '<span style="opacity:.6;">(' + d.percentage + '%)</span>'
          );
        d3.select(this).attr('opacity', 1).attr('stroke', '#fff').attr('stroke-width', 1.5 / k);
      })
      .on('mousemove', function (event) {
        tooltip.style('left', (event.pageX + 12) + 'px').style('top', (event.pageY - 30) + 'px');
      })
      .on('mouseout', function () {
        tooltip.style('display', 'none');
        d3.select(this).attr('opacity', 0.85).attr('stroke', 'none');
      })
      .transition()
      .duration(500)
      .attr('r', function (d) { return dotRadius(d.visitors) / k; });
  }

  // ── Render Top Cities List ────────────────────────────────
  function renderTopCities() {
    if (!topList) return;

    var html = '';
    var top8 = citiesData.slice(0, 8);

    top8.forEach(function (c) {
      var color = dotColor(c.visitors);
      html += '<div class="rm-city-row">' +
        '<span class="rm-city-dot" style="background:' + color + ';"></span>' +
        '<span class="rm-city-name">' + c.city + '</span>' +
        '<span class="rm-city-count">' + Number(c.visitors).toLocaleString() + '</span>' +
        '<span class="rm-city-pct">' + c.percentage + '%</span>' +
        '</div>';
    });

    if (top8.length === 0) {
      html = '<p style="color:#888;font-size:13px;text-align:center;padding:12px;">No geo data yet. Visitors will appear after they browse the site.</p>';
    }

    topList.innerHTML = html;
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
      svg.transition().duration(300).call(zoom.scaleBy, 1.5);
    });
  }
  if (zoomOutBtn) {
    zoomOutBtn.addEventListener('click', function () {
      svg.transition().duration(300).call(zoom.scaleBy, 0.67);
    });
  }
  if (resetBtn) {
    resetBtn.addEventListener('click', function () {
      svg.transition().duration(750).call(zoom.transform, d3.zoomIdentity);
    });
  }

  // ── Responsive ────────────────────────────────────────────
  var resizeTimer;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
      mapWrap.innerHTML = '';
      initMap();
    }, 300);
  });

  // ── Live Ping Flash ──────────────────────────────────────
  // Called by dashboard polling to flash new reader pings on the map
  function flashPings(pings) {
    if (!mapGroup || !projection || !pings || !pings.length) return;

    var pingGroup = mapGroup.selectAll('.live-ping-group').data([0]);
    pingGroup = pingGroup.enter().append('g').attr('class', 'live-ping-group').merge(pingGroup);

    pings.forEach(function (p) {
      if (!p.lat || !p.lng) return;
      var coords = projection([+p.lng, +p.lat]);
      if (!coords) return;

      // Flash ring — expands and fades out
      pingGroup.append('circle')
        .attr('cx', coords[0])
        .attr('cy', coords[1])
        .attr('r', 4)
        .attr('fill', 'none')
        .attr('stroke', '#22c55e')
        .attr('stroke-width', 2)
        .attr('opacity', 1)
        .transition()
        .duration(1500)
        .ease(d3.easeCubicOut)
        .attr('r', 28)
        .attr('opacity', 0)
        .attr('stroke-width', 0.5)
        .remove();

      // Solid dot — appears then fades
      pingGroup.append('circle')
        .attr('cx', coords[0])
        .attr('cy', coords[1])
        .attr('r', 3)
        .attr('fill', '#22c55e')
        .attr('opacity', 1)
        .transition()
        .delay(800)
        .duration(2000)
        .attr('opacity', 0)
        .remove();
    });
  }

  // Expose API for external access
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
