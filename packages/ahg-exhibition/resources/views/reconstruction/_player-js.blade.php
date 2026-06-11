{{--
  heratio#1219 - reconstruction montage player (vanilla JS, no libs, no CDN).

  Drives both montage modes off the SAME stage rows:
    - Assembly: layers are toggled .is-shown and STAY; CSS applies each layer's
      per-stage --layer-opacity so the structure accretes from fragments to whole.
    - Time-lapse: only the current layer carries .is-current; CSS fades everything
      else to 0, so one dated state cross-fades into the next. A range scrubber
      seeks by stage, labelled from each stage's date_display.
  The mode toggle re-renders the current frame in the new mode live. Autoplays on
  load; respects prefers-reduced-motion by jumping to the finished structure.

  Receives $stages (the playable, presented stage rows) from the including view.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3.0 or later.
--}}
@php
  $reconJsStages = collect($stages)->map(fn ($s) => [
      'caption' => $s->caption,
      'body' => $s->body,
      'date_display' => $s->date_display,
      'opacity' => $s->opacity,
  ])->all();
@endphp
window.__RECON_STAGES__ = {!! json_encode($reconJsStages, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
(function () {
  'use strict';

  var STAGE_DATA = window.__RECON_STAGES__ || [];
  var REDUCED = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var montage = document.getElementById('reconMontage');
  var stageEl = document.getElementById('reconStage');
  if (!montage || !stageEl) { return; }

  var layers = Array.prototype.slice.call(stageEl.querySelectorAll('.recon-layer'));
  var captionEl = document.getElementById('reconCaption');
  var captionDate = document.getElementById('reconCaptionDate');
  var captionText = document.getElementById('reconCaptionText');
  var progressBar = document.getElementById('reconProgressBar');
  var playPauseBtn = document.getElementById('reconPlayPause');
  var replayBtn = document.getElementById('reconReplay');
  var toggle = document.getElementById('reconToggle');
  var modeBtns = toggle ? Array.prototype.slice.call(toggle.querySelectorAll('.recon-mode-btn')) : [];
  var scrub = document.getElementById('reconScrub');
  var scrubLabel = document.getElementById('reconScrubLabel');
  var cta = document.getElementById('reconCta');

  var n = layers.length;
  if (n === 0) { return; }

  var STEP_MS = 1600;
  var mode = montage.getAttribute('data-default-mode') === 'timelapse' ? 'timelapse' : 'assembly';
  var current = -1;     // index of the last-revealed / current stage
  var playing = false;
  var timer = null;

  function stageMeta(i) {
    return STAGE_DATA[i] || {};
  }

  function setCaption(i) {
    if (!captionEl) { return; }
    var m = stageMeta(i);
    if (captionDate) { captionDate.textContent = m.date_display || ''; }
    if (captionText) { captionText.textContent = m.caption || ''; }
    var hasText = (m.date_display || m.caption);
    captionEl.classList.toggle('is-visible', !!hasText);
  }

  function setProgress(i) {
    if (!progressBar) { return; }
    var pct = n <= 1 ? 100 : Math.round(((i + 1) / n) * 100);
    progressBar.style.width = pct + '%';
  }

  function syncScrub(i) {
    if (!scrub) { return; }
    scrub.value = String(i);
    if (scrubLabel) { scrubLabel.textContent = (stageMeta(i).date_display || ''); }
  }

  // Render the montage up to and including index i, honouring the current mode.
  function render(i) {
    for (var k = 0; k < n; k++) {
      var el = layers[k];
      if (mode === 'assembly') {
        // Layers stack and STAY; opacity is the per-stage translucency (CSS var).
        el.classList.toggle('is-shown', k <= i);
        el.classList.remove('is-current');
      } else {
        // Time-lapse: only the current state is visible; it cross-fades.
        el.classList.toggle('is-current', k === i);
        el.classList.remove('is-shown');
      }
    }
    current = i;
    setCaption(i);
    setProgress(i);
    syncScrub(i);
  }

  function revealCta() {
    if (cta) { cta.classList.add('is-revealed'); }
  }

  function hideCta() {
    if (cta) { cta.classList.remove('is-revealed'); }
  }

  function atEnd() {
    return current >= n - 1;
  }

  function showFinished() {
    // Reduced-motion / no-animation end-state: full structure, CTA shown.
    if (mode === 'assembly') {
      for (var k = 0; k < n; k++) {
        layers[k].classList.add('is-shown');
        layers[k].classList.remove('is-current');
      }
      current = n - 1;
    } else {
      render(n - 1);
    }
    setCaption(n - 1);
    setProgress(n - 1);
    syncScrub(n - 1);
    revealCta();
  }

  function stop() {
    playing = false;
    if (timer) { clearTimeout(timer); timer = null; }
    updatePlayPause();
  }

  function tick() {
    if (!playing) { return; }
    if (atEnd()) {
      stop();
      revealCta();
      return;
    }
    render(current + 1);
    if (atEnd()) {
      stop();
      revealCta();
      return;
    }
    timer = setTimeout(tick, STEP_MS);
  }

  function play() {
    if (atEnd()) { restart(true); return; }
    playing = true;
    hideCta();
    updatePlayPause();
    timer = setTimeout(tick, STEP_MS);
  }

  function pause() {
    stop();
  }

  function restart(autostart) {
    stop();
    hideCta();
    render(0);
    if (autostart) { play(); }
  }

  function updatePlayPause() {
    if (!playPauseBtn) { return; }
    var icon = playPauseBtn.querySelector('i');
    if (icon) {
      icon.className = playing ? 'fas fa-pause' : 'fas fa-play';
    }
    playPauseBtn.setAttribute('aria-label', playing ? 'Pause' : 'Play');
  }

  function applyMode(next) {
    if (next !== 'assembly' && next !== 'timelapse') { return; }
    mode = next;
    montage.setAttribute('data-mode', mode);
    modeBtns.forEach(function (b) {
      var on = b.getAttribute('data-mode') === mode;
      b.classList.toggle('active', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    // Re-render the current frame in the new mode; if we were finished, stay finished.
    if (REDUCED) {
      showFinished();
    } else {
      var keep = current < 0 ? 0 : current;
      render(keep);
      if (atEnd()) { revealCta(); }
    }
  }

  // Wire controls.
  modeBtns.forEach(function (b) {
    b.addEventListener('click', function () {
      applyMode(b.getAttribute('data-mode'));
    });
  });

  if (playPauseBtn) {
    playPauseBtn.addEventListener('click', function () {
      if (playing) { pause(); } else { play(); }
    });
  }

  if (replayBtn) {
    replayBtn.addEventListener('click', function () { restart(true); });
  }

  if (scrub) {
    scrub.addEventListener('input', function () {
      pause();
      var i = parseInt(scrub.value, 10);
      if (isNaN(i)) { i = 0; }
      render(i);
      if (atEnd()) { revealCta(); } else { hideCta(); }
    });
  }

  // Initial paint.
  applyMode(mode);
  if (REDUCED) {
    showFinished();
  } else {
    render(0);
    play();
  }
})();
