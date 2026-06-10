{{-- heratio#1194 Accessible tour: semantic, keyboard-navigable, TTS-narrated alternative to the 3D walkthrough. --}}
@extends('theme::layouts.1col')

@section('title', __('Accessible tour').' - '.$space->name)
@section('body-class', 'exhibition accessible-tour')

@section('content')
<a href="#axtour-main" class="visually-hidden-focusable btn btn-primary m-2">{{ __('Skip to the tour') }}</a>

<div class="d-flex flex-wrap align-items-baseline mb-2 gap-2">
  <h1 class="mb-0 flex-grow-1"><i class="fas fa-universal-access me-2" aria-hidden="true"></i>{{ $space->name }} &mdash; {{ __('Accessible tour') }}</h1>
  @include('ahg-exhibition::exhibition-space._nav-actions', ['space' => $space, 'current' => 'accessible'])
</div>
<p class="text-muted">{{ __('A described, keyboard-navigable tour. Use Next and Previous (or the N and P keys) to move between stops, and Play to hear each one read aloud.') }}</p>

<div id="axtour">
  <div class="d-flex flex-wrap gap-2 mb-3 align-items-center" role="toolbar" aria-label="{{ __('Tour controls') }}">
    <button type="button" id="ax-prev" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i>{{ __('Previous') }}</button>
    <button type="button" id="ax-next" class="btn btn-outline-primary">{{ __('Next') }}<i class="fas fa-arrow-right ms-1" aria-hidden="true"></i></button>
    <button type="button" id="ax-play" class="btn btn-success" hidden><i class="fas fa-play me-1" aria-hidden="true"></i>{{ __('Play narration') }}</button>
    <button type="button" id="ax-stop" class="btn btn-outline-secondary" hidden><i class="fas fa-stop me-1" aria-hidden="true"></i>{{ __('Stop') }}</button>
    <div class="vr d-none d-sm-block"></div>
    <button type="button" id="ax-contrast" class="btn btn-outline-dark" aria-pressed="false"><i class="fas fa-circle-half-stroke me-1" aria-hidden="true"></i>{{ __('High contrast') }}</button>
    <button type="button" id="ax-large" class="btn btn-outline-dark" aria-pressed="false"><i class="fas fa-text-height me-1" aria-hidden="true"></i>{{ __('Larger text') }}</button>
  </div>

  <p id="ax-status" class="fw-bold" role="status" aria-live="polite"></p>

  <main id="axtour-main" tabindex="-1">
    @if(empty($stops))
      <div class="alert alert-info">{{ __('This exhibition has no objects placed yet, so there is nothing to narrate.') }}</div>
    @else
      <ol class="list-unstyled ax-stops" aria-label="{{ __('Tour stops') }}">
        @foreach($stops as $i => $s)
          <li>
            <section class="card mb-3 ax-stop" id="ax-stop-{{ $i }}" data-index="{{ $i }}"
                     data-narration="{{ $s['narration'] }}" tabindex="-1" aria-labelledby="ax-h-{{ $i }}">
              <div class="card-body">
                <h2 class="h5" id="ax-h-{{ $i }}">{{ __('Stop') }} {{ $i + 1 }} {{ __('of') }} {{ count($stops) }}: {{ $s['title'] }}</h2>
                @if($s['room'])<p class="text-muted mb-2"><i class="fas fa-door-open me-1" aria-hidden="true"></i>{{ $s['room'] }}</p>@endif
                <div class="row g-3">
                  @if($s['thumb_url'])
                    <div class="col-sm-4">
                      <img src="{{ $s['thumb_url'] }}" class="img-fluid rounded"
                           alt="{{ $s['title'] }}{{ $s['description'] ? ' — '.\Illuminate\Support\Str::limit($s['description'], 200) : '' }}">
                    </div>
                  @endif
                  <div class="col-sm-{{ $s['thumb_url'] ? 8 : 12 }}">
                    <p>{{ $s['description'] ?: __('No description is recorded for this object.') }}</p>
                    <div class="d-flex flex-wrap gap-2">
                      <button type="button" class="btn btn-sm btn-success ax-play-one" data-index="{{ $i }}" hidden>
                        <i class="fas fa-play me-1" aria-hidden="true"></i>{{ __('Play') }}
                      </button>
                      @if($s['slug'])
                        <a href="/{{ $s['slug'] }}" class="btn btn-sm btn-outline-secondary">{{ __('View full record') }}</a>
                      @endif
                    </div>
                  </div>
                </div>
              </div>
            </section>
          </li>
        @endforeach
      </ol>
    @endif
  </main>
</div>

<style nonce="{{ $cspNonce ?? '' }}">
  #axtour .ax-stop.ax-current { outline: 3px solid var(--bs-primary); outline-offset: 2px; }
  #axtour.ax-contrast { background:#000; color:#fff; padding:.5rem; }
  #axtour.ax-contrast .card { background:#000; color:#fff; border-color:#fff; }
  #axtour.ax-contrast .text-muted { color:#ffe98a !important; }
  #axtour.ax-contrast a, #axtour.ax-contrast .btn-outline-secondary { color:#ffe98a; border-color:#ffe98a; }
  #axtour.ax-large { font-size: 1.25rem; }
  #axtour.ax-large .h5 { font-size: 1.6rem; }
</style>
<script nonce="{{ $cspNonce ?? '' }}">
(function () {
  var wrap = document.getElementById('axtour');
  var stops = Array.prototype.slice.call(document.querySelectorAll('.ax-stop'));
  var statusEl = document.getElementById('ax-status');
  var cur = 0, autoplay = false;
  var synth = ('speechSynthesis' in window) ? window.speechSynthesis : null;

  // TTS controls only appear when the browser can speak.
  if (synth) {
    ['ax-play', 'ax-stop'].forEach(function (id) { document.getElementById(id).hidden = false; });
    document.querySelectorAll('.ax-play-one').forEach(function (b) { b.hidden = false; });
  }

  function announce(i) {
    if (!stops.length) { return; }
    var h = stops[i].querySelector('h2');
    statusEl.textContent = h ? h.textContent : '';
  }
  function goTo(i, focus) {
    if (!stops.length) { return; }
    cur = Math.max(0, Math.min(stops.length - 1, i));
    stops.forEach(function (s, idx) { s.classList.toggle('ax-current', idx === cur); });
    stops[cur].scrollIntoView({ behavior: 'smooth', block: 'center' });
    if (focus !== false) { stops[cur].focus(); }
    announce(cur);
  }
  function speak(i, chain) {
    if (!synth) { return; }
    synth.cancel();
    var text = stops[i].getAttribute('data-narration') || '';
    var u = new SpeechSynthesisUtterance(text);
    u.rate = 0.98;
    u.onend = function () {
      if (chain && autoplay && cur < stops.length - 1) { goTo(cur + 1, false); speak(cur, true); }
      else { autoplay = false; }
    };
    synth.speak(u);
  }

  document.getElementById('ax-prev').addEventListener('click', function () { goTo(cur - 1); });
  document.getElementById('ax-next').addEventListener('click', function () { goTo(cur + 1); });
  var playBtn = document.getElementById('ax-play');
  if (playBtn) { playBtn.addEventListener('click', function () { autoplay = true; goTo(cur, false); speak(cur, true); }); }
  var stopBtn = document.getElementById('ax-stop');
  if (stopBtn) { stopBtn.addEventListener('click', function () { autoplay = false; if (synth) { synth.cancel(); } }); }
  document.querySelectorAll('.ax-play-one').forEach(function (b) {
    b.addEventListener('click', function () { autoplay = false; var i = +b.getAttribute('data-index'); goTo(i, false); speak(i, false); });
  });

  // Keyboard: N / P (and arrows) move between stops from anywhere on the page.
  document.addEventListener('keydown', function (e) {
    if (/^(INPUT|TEXTAREA|SELECT)$/.test((e.target.tagName || '')) || e.metaKey || e.ctrlKey || e.altKey) { return; }
    var k = e.key.toLowerCase();
    if (k === 'n' || k === 'arrowright') { e.preventDefault(); goTo(cur + 1); }
    else if (k === 'p' || k === 'arrowleft') { e.preventDefault(); goTo(cur - 1); }
  });

  // Preferences persist across visits.
  function bindToggle(btnId, cls, key) {
    var btn = document.getElementById(btnId);
    var on = localStorage.getItem(key) === '1';
    if (on) { wrap.classList.add(cls); btn.setAttribute('aria-pressed', 'true'); btn.classList.add('active'); }
    btn.addEventListener('click', function () {
      var now = wrap.classList.toggle(cls);
      btn.setAttribute('aria-pressed', now ? 'true' : 'false');
      btn.classList.toggle('active', now);
      localStorage.setItem(key, now ? '1' : '0');
    });
  }
  bindToggle('ax-contrast', 'ax-contrast', 'axtour_contrast');
  bindToggle('ax-large', 'ax-large', 'axtour_large');

  if (stops.length) { goTo(0, false); }
})();
</script>
@endsection
