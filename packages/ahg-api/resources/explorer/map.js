// Collection map (heratio#1567): every record as a point on a 2D map, placed so
// that records sharing subjects, places, names, genres or materials sit near
// each other. Laid out here in the browser, from the same data as the graph,
// so it shows exactly the records the graph may show and needs no server job.
//
// The layout is UMAP's optimisation step (attraction along links, repulsion
// from random samples, the same a/b curve and decaying learning rate) run over
// the graph of records and the values they share; same-story links and, when
// switched on, text neighbours from semantic search add record-to-record links.
// Picking a value pulls its records together and re-forms the map around it.
// Loaded before the explorer's main script: everything below runs on demand.
window.MAP_I18N = {
  en: { Materials: 'Materials', viewGraph: 'Connections', viewMap: 'Collection map', openPoints: 'Collection map', textSim: 'Similar text',
    textTip: 'Records whose text semantic search finds alike', mapNote: 'Each point is a record. Records that share the values switched on above sit together. Pick a value to gather its records.',
    mapPick: 'Highlighted:', clear: 'Clear', unlinked: ':n records share nothing yet and sit around the edge', zoomIn: 'Zoom in', zoomOut: 'Zoom out', fit: 'Fit' },
  af: { Materials: 'Materiale', viewGraph: 'Verbindings', viewMap: 'Versamelingskaart', openPoints: 'Versamelingskaart', textSim: 'Soortgelyke teks',
    textTip: 'Rekords waarvan semantiese soek die teks soortgelyk vind', mapNote: "Elke punt is 'n rekord. Rekords wat die waardes hierbo deel, staan bymekaar. Kies 'n waarde om sy rekords bymekaar te bring.",
    mapPick: 'Uitgelig:', clear: 'Maak skoon', unlinked: ':n rekords deel nog niks nie en staan om die rand', zoomIn: 'Zoem in', zoomOut: 'Zoem uit', fit: 'Pas' },
};

window.MAPVIEW = (() => {
  const startHash = location.hash.slice(1); // read now: the explorer's home screen clears the address's #
  let box, cv, ctx, tip, note, built = false, textOn = true, picked = null, selected = null, hover = -1, raf = 0;
  let n = 0, nodes = [], idx = new Map(), pos = null, linked = null, hubs = [], view = { s: 1, x: 0, y: 0 }, fitted = false;
  const A = 1.577, B = 0.895; // UMAP's curve for min_dist 0.1, spread 1
  const rnd = (seed => () => { seed = (seed + 0x6D2B79F5) | 0; let x = Math.imul(seed ^ (seed >>> 15), 1 | seed);
    x = (x + Math.imul(x ^ (x >>> 7), 61 | x)) ^ x; return ((x ^ (x >>> 14)) >>> 0) / 4294967296; })(1567);
  const active = () => document.body.classList.contains('mapview');

  // Text neighbours: the server sends each record's text vector reduced to a few dimensions.
  let near = null;
  function textNeighbours(k = 6) {
    if (near || !D.vec) return near || [];
    const dim = D.vec.dim, raw = atob(D.vec.q), m = D.vec.ids.length, v = new Int8Array(raw.length);
    for (let i = 0; i < raw.length; i++) v[i] = raw.charCodeAt(i) << 24 >> 24;
    const best = Array.from({ length: m }, () => []);
    const keep = (i, j, s) => { const b = best[i]; if (b.length < k || s > b[b.length - 1][1]) { b.push([j, s]); b.sort((p, q) => q[1] - p[1]); if (b.length > k) b.pop(); } };
    for (let i = 0; i < m; i++) for (let j = i + 1; j < m; j++) {
      let s = 0; for (let c = 0; c < dim; c++) s += v[i * dim + c] * v[j * dim + c];
      s /= 16129; keep(i, j, s); keep(j, i, s);
    }
    near = [];
    best.forEach((b, i) => b.forEach(([j, s]) => { if (i < j || !best[j].some(([x]) => x === i)) near.push([D.vec.ids[i], D.vec.ids[j], Math.max(0, s)]); }));
    return near;
  }

  // Links: record - value (weighted by how telling the value is) and record - record.
  function links() {
    const E = [];
    hubs = D.entities.filter(e => (vis(e) || e.id === picked) && e.recs.length >= 2);
    hubs.forEach((e, h) => { const w = Math.log(1 + n / e.recs.length) * (e.id === picked ? 6 : 1);
      e.recs.forEach(rid => { if (idx.has(rid)) E.push([idx.get(rid), n + h, w]); }); });
    D.records.forEach(r => (r.same || []).forEach(([x]) => { if (idx.has(x) && r.id < x) E.push([idx.get(r.id), idx.get(x), 2]); }));
    if (textOn) textNeighbours().forEach(([a, b, s]) => { if (idx.has(a) && idx.has(b)) E.push([idx.get(a), idx.get(b), 2 * s]); });
    const wmax = Math.max(1e-9, ...E.map(e => e[2]));
    linked = new Uint8Array(n); E.forEach(([a, b]) => { if (a < n) linked[a] = 1; if (b < n) linked[b] = 1; });
    return E.map(([a, b, w]) => [a, b, w / wmax]);
  }

  function layout(epochs) {
    cancelAnimationFrame(raf);
    const E = links(), N = n + hubs.length, old = pos;
    pos = new Float32Array(2 * N);
    for (let i = 0; i < N; i++) {
      const prev = i < n ? i : (old && hubPrev.has(hubs[i - n].id) ? hubPrev.get(hubs[i - n].id) : -1);
      if (old && i < n) { pos[2 * i] = old[2 * i]; pos[2 * i + 1] = old[2 * i + 1]; }
      else if (old && prev >= 0) { pos[2 * i] = old[2 * prev]; pos[2 * i + 1] = old[2 * prev + 1]; }
      else { pos[2 * i] = 20 * rnd() - 10; pos[2 * i + 1] = 20 * rnd() - 10; }
    }
    hubPrev = new Map(hubs.map((e, h) => [e.id, n + h]));
    const clip = g => g > 4 ? 4 : (g < -4 ? -4 : g);
    const per = Math.max(1, Math.floor(250000 / Math.max(1, E.length * 4)));
    let ep = 0;
    const step = () => {
      for (let s = 0; s < per && ep < epochs; s++, ep++) {
        const lr = 1 - ep / epochs;
        for (const [a, b, w] of E) {
          if (rnd() > w) continue;
          let dx = pos[2 * a] - pos[2 * b], dy = pos[2 * a + 1] - pos[2 * b + 1], d2 = dx * dx + dy * dy;
          if (d2 > 0) { const c = (-2 * A * B * Math.pow(d2, B - 1)) / (A * Math.pow(d2, B) + 1);
            const gx = clip(c * dx) * lr, gy = clip(c * dy) * lr; pos[2 * a] += gx; pos[2 * a + 1] += gy; pos[2 * b] -= gx; pos[2 * b + 1] -= gy; }
          for (let k = 0; k < 3; k++) {
            const o = (rnd() * n) | 0; if (o === a) continue;
            dx = pos[2 * a] - pos[2 * o]; dy = pos[2 * a + 1] - pos[2 * o + 1]; d2 = dx * dx + dy * dy;
            const c = d2 > 0 ? (2 * B) / ((0.001 + d2) * (A * Math.pow(d2, B) + 1)) : 0;
            pos[2 * a] += (c ? clip(c * dx) : 4) * lr; pos[2 * a + 1] += (c ? clip(c * dy) : 4) * lr;
          }
        }
      }
      ring(); if (!fitted) { fit(); fitScale = view.s; } draw();
      if (ep < epochs) raf = requestAnimationFrame(step); else fitted = true;
    };
    step();
  }
  let hubPrev = new Map();

  // Records that share nothing go round the edge, so they are still there to find.
  function ring() {
    let r = 1; for (let i = 0; i < n; i++) if (linked[i]) r = Math.max(r, Math.hypot(pos[2 * i], pos[2 * i + 1]));
    const lone = []; for (let i = 0; i < n; i++) if (!linked[i]) lone.push(i);
    lone.forEach((i, k) => { const a = 2 * Math.PI * k / lone.length; pos[2 * i] = 1.12 * r * Math.cos(a); pos[2 * i + 1] = 1.12 * r * Math.sin(a); });
    note.querySelector('.lone').textContent = lone.length ? t('unlinked', { n: fmt(lone.length) }) : '';
  }
  function fit() {
    let x0 = Infinity, x1 = -Infinity, y0 = Infinity, y1 = -Infinity;
    for (let i = 0; i < n; i++) { x0 = Math.min(x0, pos[2 * i]); x1 = Math.max(x1, pos[2 * i]); y0 = Math.min(y0, pos[2 * i + 1]); y1 = Math.max(y1, pos[2 * i + 1]); }
    const W = cv.clientWidth, H = cv.clientHeight, s = 0.9 * Math.min(W / Math.max(1e-6, x1 - x0), H / Math.max(1e-6, y1 - y0));
    view = { s, x: W / 2 - s * (x0 + x1) / 2, y: H / 2 - s * (y0 + y1) / 2 };
  }
  const sx = i => view.x + view.s * pos[2 * i], sy = i => view.y + view.s * pos[2 * i + 1];

  function draw() {
    const W = cv.clientWidth, H = cv.clientHeight, dpr = window.devicePixelRatio || 1;
    if (cv.width !== Math.round(W * dpr) || cv.height !== Math.round(H * dpr)) { cv.width = Math.round(W * dpr); cv.height = Math.round(H * dpr); }
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, W, H);
    const pe = picked && ENT.get(picked), on2 = pe ? new Set(pe.recs) : null, col = pe ? TYPES[pe.type][1] : '#10373E';
    const r0 = Math.max(2.5, Math.min(6, 1.2 + Math.sqrt(view.s) / 2));
    for (let pass = 0; pass < 2; pass++) for (let i = 0; i < n; i++) {
      const hit = on2 && on2.has(nodes[i].id); if ((pass === 1) !== !!hit) continue;
      ctx.globalAlpha = on2 ? (hit ? 0.95 : 0.18) : 0.6; ctx.fillStyle = hit ? col : (on2 ? '#9b9182' : '#10373E');
      ctx.beginPath(); ctx.arc(sx(i), sy(i), hit ? r0 + 1.5 : r0, 0, 7); ctx.fill();
    }
    ctx.globalAlpha = 1;
    const ring1 = (i, c) => { if (i < 0) return; ctx.strokeStyle = c; ctx.lineWidth = 2.5; ctx.beginPath(); ctx.arc(sx(i), sy(i), r0 + 4, 0, 7); ctx.stroke(); };
    ring1(selected !== null && idx.has(selected) ? idx.get(selected) : -1, '#E1B34F'); ring1(hover, '#653332');
    // Value labels at the heart of their records: the busiest first, more as you zoom in.
    const fs = view.s > 0 ? Math.max(1, Math.round(18 * Math.sqrt(view.s / (fitScale || view.s)))) : 18;
    const show = hubs.map((e, h) => [e, n + h]).sort((p, q) => (q[0].id === picked) - (p[0].id === picked) || q[0].recs.length - p[0].recs.length).slice(0, fs);
    ctx.font = '12px Arial, Helvetica, sans-serif'; ctx.textAlign = 'center'; ctx.lineJoin = 'round';
    labelBoxes = [];
    show.forEach(([e, i]) => {
      const x = sx(i), y = sy(i), w = ctx.measureText(e.name).width;
      if (labelBoxes.some(b => Math.abs(b.x - x) < (b.w + w) / 2 + 4 && Math.abs(b.y - y) < 15)) return;
      labelBoxes.push({ x, y, w, id: e.id });
      ctx.font = (e.id === picked ? 'bold 13px' : '12px') + ' Arial, Helvetica, sans-serif';
      ctx.lineWidth = 3.5; ctx.strokeStyle = 'rgba(255,255,255,.9)'; ctx.strokeText(e.name, x, y + 4);
      ctx.fillStyle = TYPES[e.type][1]; ctx.fillText(e.name, x, y + 4);
    });
  }
  let labelBoxes = [], fitScale = 0;

  function at(mx, my) {
    let best = -1, bd = 100; // within 10 px
    for (let i = 0; i < n; i++) { const dx = sx(i) - mx, dy = sy(i) - my, d = dx * dx + dy * dy; if (d < bd) { bd = d; best = i; } }
    return best;
  }
  const labelAt = (mx, my) => labelBoxes.find(b => Math.abs(b.x - mx) < b.w / 2 + 3 && Math.abs(b.y - my) < 9);
  function showTip(i, mx, my) {
    if (i < 0) { tip.style.display = 'none'; return; }
    const r = nodes[i];
    tip.innerHTML = `${r.img ? `<img src="${esc(r.img)}" alt="" onerror="this.remove()">` : ''}<b>${esc(r.title || t('untitled'))}</b><br><small>${ref(r)}</small>`;
    tip.style.display = 'block';
    const W = box.clientWidth; tip.style.left = Math.min(mx + 14, W - 250) + 'px'; tip.style.top = (my + 14) + 'px';
  }

  function build() {
    built = true; nodes = D.records; n = nodes.length; idx = new Map(nodes.map((r, i) => [r.id, i]));
    const st = document.createElement('style');
    st.textContent = `#mapbox { display: none; position: relative; min-height: 0; overflow: hidden; background: #fff; }
      body.mapview #graph, body.mapview #timeline { display: none; } body.mapview #mapbox { display: block; }
      #mapbox canvas { width: 100%; height: 100%; display: block; touch-action: none; cursor: grab; }
      #mapbox .maptip { position: absolute; display: none; pointer-events: none; max-width: 240px; background: #fff; border: 1px solid var(--rule); border-radius: 4px; padding: 6px 8px; font-size: 12px; line-height: 1.35; box-shadow: 0 2px 8px rgba(0,0,0,.15); }
      #mapbox .maptip img { display: block; max-width: 100%; max-height: 140px; margin-bottom: 4px; }
      #mapbox .mapnote { position: absolute; left: 10px; top: 10px; right: 10px; max-width: 520px; font-size: 12px; color: var(--muted); background: rgba(255,255,255,.85); padding: 6px 8px; border-radius: 4px; }
      #mapbox .mapnote button, #mapbox .zoom button { border: 1px solid var(--rule); background: #fff; color: var(--ink); border-radius: 4px; font: inherit; font-size: 12px; padding: 2px 8px; cursor: pointer; }
      #mapbox .zoom { position: absolute; right: 10px; bottom: 10px; display: flex; gap: 4px; }
      @media (max-width: 900px) { #mapbox { height: 62vh; } }`;
    document.head.appendChild(st);
    box = document.createElement('div'); box.id = 'mapbox';
    box.innerHTML = `<canvas aria-label="${esc(t('viewMap'))}"></canvas><div class="maptip"></div>
      <div class="mapnote"><span class="txt">${esc(t('mapNote'))}</span> <span class="pick"></span> <span class="lone"></span></div>
      <div class="zoom"><button type="button" data-z="1.4" title="${esc(t('zoomIn'))}">+</button><button type="button" data-z="0.7" title="${esc(t('zoomOut'))}">-</button><button type="button" data-z="0">${esc(t('fit'))}</button></div>`;
    document.querySelector('main').insertBefore(box, document.getElementById('side'));
    cv = box.querySelector('canvas'); ctx = cv.getContext('2d'); tip = box.querySelector('.maptip'); note = box.querySelector('.mapnote');
    const zoom = (f, mx, my) => { view.x = mx - f * (mx - view.x); view.y = my - f * (my - view.y); view.s *= f; draw(); };
    box.querySelector('.zoom').onclick = ev => { const b = ev.target.closest('[data-z]'); if (!b) return; const z = +b.dataset.z;
      if (z) zoom(z, cv.clientWidth / 2, cv.clientHeight / 2); else { fit(); fitScale = view.s; draw(); } };
    note.querySelector('.pick').onclick = ev => { if (ev.target.closest('button')) pick(null); };
    cv.addEventListener('wheel', ev => { ev.preventDefault(); const b = cv.getBoundingClientRect(); zoom(ev.deltaY < 0 ? 1.2 : 1 / 1.2, ev.clientX - b.left, ev.clientY - b.top); }, { passive: false });
    let drag = null;
    cv.addEventListener('pointerdown', ev => { drag = { x: ev.clientX, y: ev.clientY, vx: view.x, vy: view.y, moved: false }; cv.setPointerCapture(ev.pointerId); });
    cv.addEventListener('pointermove', ev => {
      const b = cv.getBoundingClientRect(), mx = ev.clientX - b.left, my = ev.clientY - b.top;
      if (drag) { const dx = ev.clientX - drag.x, dy = ev.clientY - drag.y; if (Math.abs(dx) + Math.abs(dy) > 3) drag.moved = true;
        if (drag.moved) { view.x = drag.vx + dx; view.y = drag.vy + dy; tip.style.display = 'none'; draw(); return; } }
      const i = at(mx, my); if (i !== hover) { hover = i; draw(); }
      showTip(i, mx, my); cv.style.cursor = i >= 0 || labelAt(mx, my) ? 'pointer' : 'grab';
    });
    cv.addEventListener('pointerleave', () => { hover = -1; tip.style.display = 'none'; draw(); });
    cv.addEventListener('pointerup', ev => {
      const was = drag; drag = null; if (!was || was.moved) return;
      const b = cv.getBoundingClientRect(), mx = ev.clientX - b.left, my = ev.clientY - b.top, i = at(mx, my), lab = labelAt(mx, my);
      if (i >= 0) select(nodes[i].id); else if (lab) pick(lab.id);
    });
    cv.addEventListener('dblclick', ev => { const b = cv.getBoundingClientRect(); zoom(2, ev.clientX - b.left, ev.clientY - b.top); });
    // The chips above choose what drives the layout; text similarity has its own chip here.
    document.getElementById('chips').addEventListener('click', () => { if (active()) setTimeout(() => layout(200), 0); });
    if (D.vec) {
      const el = document.createElement('span'); el.className = 'chip'; el.title = t('textTip'); el.id = 'textchip';
      el.innerHTML = `<span class="dot" style="background:#653332"></span>${esc(t('textSim'))}`;
      el.onclick = () => { textOn = !textOn; el.classList.toggle('off', !textOn); layout(200); };
      document.getElementById('chips').appendChild(el);
    }
    window.addEventListener('resize', () => { if (active()) draw(); });
    fitted = false; layout(400);
  }

  function show(target) {
    document.body.classList.remove('home'); document.body.classList.add('mapview');
    if (!built) build(); else draw();
    document.querySelectorAll('#views button').forEach(b => b.classList.toggle('on', b.dataset.v === 'map'));
    const tc = document.getElementById('textchip'); if (tc) tc.style.display = '';
    if (target && (ENT.has(target) || REC.has(target))) focusMap(target); else { welcome(); hash(); }
  }
  function hide() {
    document.body.classList.remove('mapview');
    document.querySelectorAll('#views button').forEach(b => b.classList.toggle('on', b.dataset.v === 'graph'));
    const tc = document.getElementById('textchip'); if (tc) tc.style.display = 'none';
    cy.resize();
  }
  const hash = () => history.replaceState(null, '', '#map' + (picked ? ':' + encodeURIComponent(picked) : selected ? ':' + encodeURIComponent(selected) : ''));

  function pick(id) {
    picked = id; selected = null;
    note.querySelector('.pick').innerHTML = id ? `${esc(t('mapPick'))} <b>${esc(ENT.get(id).name)}</b> <button type="button">${esc(t('clear'))}</button>` : '';
    if (id) { const e = ENT.get(id), co = new Map();
      e.recs.forEach(rid => REC.get(rid).ents.forEach(k => { if (k !== id && vis(ENT.get(k))) co.set(k, (co.get(k) || []).concat(rid)); }));
      showEntity(e, co); } else welcome();
    layout(250); hash();
  }
  function select(id) {
    selected = id; showRecord(REC.get(id)); draw(); hash();
  }
  function focusMap(id) {
    if (!active()) { show(id); return; }
    if (ENT.has(id)) pick(id); else if (REC.has(id)) {
      select(id); const i = idx.get(id); view.x = cv.clientWidth / 2 - view.s * pos[2 * i]; view.y = cv.clientHeight / 2 - view.s * pos[2 * i + 1]; draw();
    }
  }

  function init() {
    // The view switch sits beside the language switch: the graph of connections, or the map.
    const sw = document.createElement('span'); sw.className = 'lang'; sw.id = 'views'; sw.setAttribute('role', 'group');
    sw.innerHTML = `<button type="button" data-v="graph" class="on">${esc(t('viewGraph'))}</button><button type="button" data-v="map">${esc(t('viewMap'))}</button>`;
    sw.onclick = ev => { const b = ev.target.closest('[data-v]'); if (!b) return;
      if (b.dataset.v === 'map') show(); else { hide(); document.body.classList.remove('home');
        if (current) focus(current, false); else { welcome(); history.replaceState(null, '', location.pathname + location.search); }
        cy.resize(); drawTimeline(); } };
    document.getElementById('lang').after(sw);
    document.getElementById('home').addEventListener('click', ev => {
      if (ev.target.closest('[data-mapview]')) show(); else if (ev.target.id === 'openmap') hide(); });
    document.getElementById('homebtn').addEventListener('click', hide);
    window.addEventListener('hashchange', () => { const h = location.hash.slice(1); if (h.startsWith('map')) show(decodeURIComponent(h.slice(4))); });
    if (startHash.startsWith('map')) show(decodeURIComponent(startHash.slice(4)));
  }

  // push false: the graph redrawing itself (a chip switched, the address changed) - the map has its own handlers.
  return { active, focus: (id, push) => { if (push !== false) focusMap(id); }, init, show, hide };
})();
