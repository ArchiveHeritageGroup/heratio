{{--
  On-site AR companion (heratio#1191, first slice).

  A standalone, mobile-first page a visitor opens on their phone IN the physical
  gallery (via a QR code / short URL). Single column, big tap-friendly cards,
  works one-handed. Shows the room's twin-sourced object info (from accessibleTour)
  and embeds the grounded room AI docent (reuses the public ask-room endpoint).

  This slice is a 2D companion. Geo / marker AR anchoring (camera passthrough,
  placing cards in 3D space from the twin's object placements) is the NEXT slice -
  see docs/reference/onsite-companion.md. No admin chrome on purpose: this is the
  visitor-in-gallery surface, not an editor.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3.0 or later.
--}}
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex">
  <title>{{ $space->name }} - {{ __('Gallery companion') }}</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style nonce="{{ $cspNonce ?? '' }}">
    :root { --c-bg:#0f1115; --c-card:#1b1f27; --c-line:#2c313c; --c-accent:#4ea1ff; --c-text:#f2f4f8; --c-muted:#9aa3b2; }
    * { box-sizing: border-box; }
    html, body { margin:0; padding:0; background:var(--c-bg); color:var(--c-text);
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; font-size: 18px; line-height: 1.45; }
    body { padding: max(env(safe-area-inset-top), 0px) 0 calc(env(safe-area-inset-bottom) + 1rem); }
    .wrap { max-width: 640px; margin: 0 auto; padding: 0 1rem; }
    header.companion-top { position: sticky; top: 0; z-index: 5; background: rgba(15,17,21,.96);
      backdrop-filter: blur(6px); border-bottom: 1px solid var(--c-line); padding: .85rem 0; }
    header.companion-top h1 { font-size: 1.35rem; margin: 0; line-height: 1.2; }
    header.companion-top .sub { color: var(--c-muted); font-size: .85rem; margin-top: .2rem; }
    .hint { display:flex; gap:.5rem; align-items:flex-start; background:#1a2330; border:1px solid #2a3a52;
      color:#cfe2ff; border-radius:.75rem; padding:.65rem .8rem; margin:1rem 0; font-size:.85rem; }
    .hint i { color:var(--c-accent); font-size:1.1rem; flex:0 0 auto; }
    .card { background:var(--c-card); border:1px solid var(--c-line); border-radius:1rem;
      overflow:hidden; margin: 0 0 1rem; }
    .card .thumb { width:100%; aspect-ratio: 4/3; object-fit: cover; display:block; background:#000; }
    .card .body { padding: 1rem 1.1rem 1.15rem; }
    .card .room { color:var(--c-accent); font-size:.8rem; text-transform:uppercase; letter-spacing:.04em;
      display:flex; align-items:center; gap:.4rem; margin-bottom:.35rem; }
    .card h2 { font-size:1.25rem; margin:.1rem 0 .5rem; }
    .card p.desc { color:#dde3ec; margin:0 0 .9rem; }
    .actions { display:flex; flex-wrap:wrap; gap:.6rem; }
    .btn { -webkit-appearance:none; appearance:none; border:0; cursor:pointer; text-decoration:none;
      display:inline-flex; align-items:center; gap:.5rem; font-size:1rem; font-weight:600;
      padding:.8rem 1.1rem; border-radius:.85rem; min-height:48px; line-height:1; }
    .btn-accent { background:var(--c-accent); color:#06182f; }
    .btn-ghost { background:transparent; color:var(--c-text); border:1px solid var(--c-line); }
    .btn:active { transform: translateY(1px); }
    .empty { text-align:center; color:var(--c-muted); padding:2.5rem 1rem; }
    /* Docent panel */
    .docent { background:var(--c-card); border:1px solid var(--c-line); border-radius:1rem; padding:1.1rem; margin:0 0 1rem; }
    .docent h2 { font-size:1.15rem; margin:0 0 .25rem; display:flex; align-items:center; gap:.5rem; }
    .docent .lead { color:var(--c-muted); font-size:.9rem; margin:0 0 .8rem; }
    .chips { display:flex; flex-wrap:wrap; gap:.5rem; margin:0 0 .85rem; }
    .chip { background:#222a36; border:1px solid var(--c-line); color:#cfe2ff; border-radius:999px;
      padding:.55rem .85rem; font-size:.85rem; cursor:pointer; min-height:42px; }
    .ask-row { display:flex; gap:.5rem; }
    .ask-row input { flex:1 1 auto; min-width:0; background:#11151c; border:1px solid var(--c-line);
      color:var(--c-text); border-radius:.85rem; padding:.85rem .9rem; font-size:1rem; min-height:48px; }
    .ask-row input:focus { outline:2px solid var(--c-accent); }
    .answer { white-space:pre-wrap; background:#11151c; border:1px solid var(--c-line); border-radius:.85rem;
      padding:.9rem; margin-top:.85rem; font-size:1rem; }
    .answer[hidden] { display:none; }
    .spin { display:inline-block; width:1.05em; height:1.05em; border:2px solid currentColor;
      border-right-color:transparent; border-radius:50%; animation: spin .7s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    footer.companion-foot { color:var(--c-muted); font-size:.8rem; text-align:center; padding:1.25rem 1rem 0; }
    footer.companion-foot a { color:var(--c-accent); }
  </style>
</head>
<body>
  <header class="companion-top">
    <div class="wrap">
      <h1><i class="bi bi-phone" aria-hidden="true"></i> {{ $space->name }}</h1>
      <div class="sub">{{ __('Gallery companion') }} &middot; {{ __('point your phone, tap an object to learn more') }}</div>
    </div>
  </header>

  <main class="wrap">
    <div class="hint">
      <i class="bi bi-info-circle" aria-hidden="true"></i>
      <span>{{ __('You are viewing the live digital twin of this room. Live camera AR (objects anchored where they physically stand) is coming soon - for now, scroll the objects in this room and ask the docent anything.') }}</span>
    </div>

    {{-- Room AI docent: reuses the public ask-room endpoint (gateway-grounded). --}}
    <section class="docent" id="docent" aria-labelledby="docent-h">
      <h2 id="docent-h"><i class="bi bi-robot" aria-hidden="true"></i> {{ __('Ask the docent') }}</h2>
      <p class="lead">{{ __('Grounded only in the objects on display in this room.') }}</p>
      @if(!empty($questions))
        <div class="chips" id="chips" role="list">
          @foreach($questions as $q)
            <button type="button" class="chip" role="listitem" data-q="{{ $q }}">{{ $q }}</button>
          @endforeach
        </div>
      @endif
      <div class="ask-row">
        <input type="text" id="askInput" inputmode="text" autocomplete="off"
               placeholder="{{ __('Type your question...') }}" aria-label="{{ __('Ask the docent a question') }}" maxlength="300">
        <button type="button" class="btn btn-accent" id="askBtn"><i class="bi bi-send" aria-hidden="true"></i></button>
      </div>
      <div class="answer" id="answer" hidden aria-live="polite"></div>
    </section>

    @if(empty($stops))
      <div class="empty">
        <i class="bi bi-box-seam" style="font-size:2rem;display:block;margin-bottom:.5rem;" aria-hidden="true"></i>
        {{ __('No objects are placed in this room yet.') }}
      </div>
    @else
      @foreach($stops as $i => $s)
        <article class="card">
          @if($s['thumb_url'])
            <img class="thumb" src="{{ $s['thumb_url'] }}" loading="lazy"
                 alt="{{ $s['title'] }}">
          @endif
          <div class="body">
            @if($s['room'])
              <div class="room"><i class="bi bi-door-open" aria-hidden="true"></i>{{ $s['room'] }}</div>
            @endif
            <h2>{{ __('Stop') }} {{ $i + 1 }}: {{ $s['title'] }}</h2>
            <p class="desc">{{ $s['description'] ?: __('No description is recorded for this object.') }}</p>
            <div class="actions">
              @if($s['slug'])
                <a class="btn btn-ghost" href="/{{ $s['slug'] }}"><i class="bi bi-card-text" aria-hidden="true"></i>{{ __('Full record') }}</a>
              @endif
              <button type="button" class="btn btn-ghost ask-about" data-title="{{ $s['title'] }}">
                <i class="bi bi-chat-dots" aria-hidden="true"></i>{{ __('Ask about this') }}
              </button>
            </div>
          </div>
        </article>
      @endforeach
    @endif

    <footer class="companion-foot">
      <a href="{{ route('exhibition-space.walkthrough', ['slug' => $space->slug]) }}">{{ __('Open the 3D walkthrough') }}</a>
      &middot; {{ __('On-site companion (preview)') }}
    </footer>
  </main>

  <script nonce="{{ $cspNonce ?? '' }}">
  (function () {
    var askUrl = @json(route('exhibition-space.ask-room', ['slug' => $space->slug]));
    var input  = document.getElementById('askInput');
    var btn    = document.getElementById('askBtn');
    var out    = document.getElementById('answer');
    var busy   = false;

    function show(text) { out.hidden = false; out.textContent = text; out.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }

    function ask(q) {
      q = (q || '').trim();
      if (!q || busy) { return; }
      busy = true;
      btn.disabled = true;
      out.hidden = false;
      out.innerHTML = '<span class="spin" aria-hidden="true"></span> ' + @json(__('Thinking...'));
      fetch(askUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d && d.ok && d.answer) { show(d.answer); }
          else { show(@json(__('Sorry, the docent could not answer that right now.'))); }
        })
        .catch(function () { show(@json(__('Sorry, the docent is unavailable right now.'))); })
        .finally(function () { busy = false; btn.disabled = false; });
    }

    btn.addEventListener('click', function () { ask(input.value); });
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); ask(input.value); } });

    var chips = document.getElementById('chips');
    if (chips) {
      chips.addEventListener('click', function (e) {
        var c = e.target.closest('.chip');
        if (!c) { return; }
        input.value = c.getAttribute('data-q') || '';
        ask(input.value);
      });
    }

    Array.prototype.forEach.call(document.querySelectorAll('.ask-about'), function (b) {
      b.addEventListener('click', function () {
        var t = b.getAttribute('data-title') || '';
        var q = @json(__('Tell me about')) + ' "' + t + '".';
        input.value = q;
        document.getElementById('docent').scrollIntoView({ behavior: 'smooth', block: 'start' });
        ask(q);
      });
    });
  })();
  </script>
</body>
</html>
