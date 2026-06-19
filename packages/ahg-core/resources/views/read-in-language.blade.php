{{--
  read-in-language - Heratio ahg-core (heratio#1211)

  Public, standalone "READ THIS RECORD IN YOUR LANGUAGE" page. Shows a PUBLISHED
  record's original title + descriptive metadata, and a language picker that
  distinguishes cultures with an OFFICIAL existing translation (human-authored,
  preferred, authoritative) from those available only via on-demand machine
  translation through the AHG AI gateway.

  Policy enforced by the controller / service:
    - A real information_object_i18n row for the chosen culture is shown labelled
      "OFFICIAL TRANSLATION" (no gateway call).
    - Otherwise the gateway machine translation is shown labelled "Machine
      translation via the AHG gateway - not an official translation. The original
      remains authoritative."
    - The original is always shown first and is always authoritative. Nothing is
      written back to the catalogue. On gateway failure the original stays visible
      with a calm notice; the picker keeps working. Never a 500.

  Extends the public 1-column layout. Two-segment route -> safe from the
  single-segment /{slug} archival-record catch-all. International /
  jurisdiction-neutral; Afrikaans is ordered ahead of Dutch by the service.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za
  Licensed under the GNU Affero General Public License v3 or later.
--}}
@extends('theme::layouts.1col')
@section('title', __('Read this record in your language'))

@section('content')
@php
  // Normalise the official-culture list to base subtags for quick lookup in the
  // picker (so af / af_ZA both flag as official).
  $officialSet = collect($officialCultures ?? [])->map(fn ($c) => strtolower(explode('-', explode('_', (string) $c)[0])[0]))->filter()->unique()->all();

  // Map any server-side pre-render by field key, and work out whether it is an
  // official translation or a machine translation (drives the banner choice).
  $renderByKey = [];
  if (!empty($rendering['fields'])) {
    foreach ($rendering['fields'] as $rf) { $renderByKey[$rf['key']] = $rf; }
  }
  $renderIsOfficial = !empty($rendering['is_official']);
  $renderHasTranslation = !empty($rendering['fields'])
    && collect($rendering['fields'])->contains('is_translated', true);
  $renderLangLabel = $rendering['language'] ?? '';
  $renderNotice = $rendering['notice'] ?? null;
@endphp
<div class="container py-4" style="max-width:1000px">

  <nav aria-label="{{ __('breadcrumb') }}" class="small mb-2">
    <a href="{{ url('/read/'.$idOrSlug) }}" class="text-decoration-none">
      <i class="fas fa-globe me-1"></i>{{ __('Read this record in your language') }}
    </a>
  </nav>

  <header class="mb-3">
    <h1 class="h3 mb-1">{{ $title !== '' ? $title : __('This record') }}</h1>
    <p class="text-muted mb-0">
      {{ __('Choose a language to read this record\'s key metadata. Where a professional translation already exists it is shown as the official version; otherwise it is translated on demand.') }}
    </p>
  </header>

  {{-- Language picker. Official cultures are flagged; the rest are MT-only. The
       <select> works WITHOUT JavaScript (a plain GET submit re-renders the page);
       JS upgrades it to an in-place fetch against the translate endpoint. --}}
  <form id="rlForm" method="GET" action="{{ url('/read/'.$idOrSlug) }}" class="card mb-4 border-0 shadow-sm">
    <div class="card-body">
      <label for="rlLang" class="form-label fw-semibold mb-2">
        <i class="fas fa-language me-1"></i>{{ __('Read in') }}
      </label>
      <div class="input-group">
        <select name="lang" id="rlLang" class="form-select" aria-label="{{ __('Target language') }}">
          <option value="">{{ __('Original') }} ({{ $sourceLang }})</option>
          @foreach($languages as $l)
            @php $isOfficial = in_array($l['code'], $officialSet, true) && $l['code'] !== $sourceLang; @endphp
            <option value="{{ $l['code'] }}" @selected($selectedLang === $l['code'])>
              {{ $l['label'] }} ({{ $l['code'] }})@if($isOfficial) - {{ __('official') }}@endif
            </option>
          @endforeach
        </select>
        <button class="btn btn-primary" type="submit" id="rlBtn">
          <i class="fas fa-language me-1"></i>{{ __('Read') }}
        </button>
      </div>
      <div class="form-text d-flex flex-wrap align-items-center gap-2 mt-2">
        <span><i class="fas fa-landmark me-1"></i>{{ __('Source language') }}:
          <span class="badge bg-light text-dark border">{{ $sourceLang }}</span></span>
        @if(!empty($officialSet))
          <span><i class="fas fa-certificate me-1 text-success"></i>{{ __('Languages marked "official" have a human-authored translation.') }}</span>
        @endif
        <span><i class="fas fa-server me-1"></i>{{ __('Other languages are translated on demand by the AHG AI gateway.') }}</span>
      </div>
    </div>
  </form>

  {{-- Non-fatal notice (unsupported language fallback, etc.). --}}
  <div id="rlNotice" class="alert alert-warning py-2 d-flex align-items-start {{ $renderNotice ? '' : 'd-none' }}" role="alert">
    <i class="fas fa-exclamation-triangle me-2 mt-1"></i>
    <div id="rlNoticeText">{{ $renderNotice ? __($renderNotice) : '' }}</div>
  </div>

  {{-- Gateway-unavailable notice. The original stays visible. --}}
  <div id="rlError" class="alert alert-secondary py-2 d-flex align-items-start d-none" role="alert">
    <i class="fas fa-triangle-exclamation me-2 mt-1"></i>
    <div>{{ __('The translation service could not be reached just now, so the original text is shown. Please try again shortly.') }}</div>
  </div>

  {{-- OFFICIAL-translation banner (green) - shown when the catalogue holds a real
       human-authored translation for the chosen language. --}}
  <div id="rlOfficialBanner" class="alert alert-success d-flex align-items-start {{ ($renderIsOfficial && $renderHasTranslation) ? '' : 'd-none' }}" role="note">
    <i class="fas fa-certificate me-2 mt-1"></i>
    <div>
      <strong>{{ __('Official translation') }}</strong>
      <span id="rlOfficialLang">{{ ($renderIsOfficial && $renderLangLabel) ? ('(' . $renderLangLabel . ')') : '' }}</span>
      - {{ __('This is a human-authored translation held in the catalogue.') }}
    </div>
  </div>

  {{-- MACHINE-translation banner (amber) with the MANDATORY verbatim disclaimer. --}}
  <div id="rlMtBanner" class="alert alert-warning d-flex align-items-start {{ (!$renderIsOfficial && $renderHasTranslation) ? '' : 'd-none' }}" role="note">
    <i class="fas fa-robot me-2 mt-1"></i>
    <div>
      <strong id="rlMtLang">{{ (!$renderIsOfficial && $renderLangLabel) ? $renderLangLabel : '' }}</strong>
      <span class="d-block">{{ $mtDisclaimer }}</span>
    </div>
  </div>

  <div id="rlSpinner" class="text-center text-muted py-4 d-none">
    <div class="spinner-border" role="status"></div>
    <div class="mt-2 small">{{ __('Translating...') }}</div>
  </div>

  @if(empty($fields))
    {{-- Empty-state: published record with no descriptive metadata. --}}
    <div class="alert alert-secondary d-flex align-items-start" role="note">
      <i class="fas fa-circle-info me-2 mt-1"></i>
      <div>{{ __('This record has no descriptive metadata to display yet.') }}</div>
    </div>
  @else
    {{-- The record's fields. Each shows the chosen language's text (original until a
         language is picked), with the original available alongside for reference. --}}
    <div id="rlFields">
      @foreach($fields as $f)
        @php $rf = $renderByKey[$f['key']] ?? null; $showTranslated = $rf && $rf['is_translated']; @endphp
        <section class="card mb-3" data-field="{{ $f['key'] }}">
          <div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-2">{{ __($f['label']) }}</h2>

            {{-- Primary reading text: the chosen language when picked, else original. --}}
            <div class="rl-display" style="line-height:1.7; white-space:pre-line">{{ $showTranslated ? $rf['translated'] : $f['original'] }}</div>

            {{-- Original-text reference, collapsed by default, shown only once a
                 translation is on screen. --}}
            <details class="rl-orig-wrap mt-2 {{ $showTranslated ? '' : 'd-none' }}">
              <summary class="small text-muted">
                <i class="fas fa-landmark me-1"></i>{{ __('Show original') }} ({{ $sourceLang }})
              </summary>
              <div class="rl-original text-muted mt-1" style="line-height:1.7; white-space:pre-line">{{ $f['original'] }}</div>
            </details>
          </div>
        </section>
      @endforeach
    </div>
  @endif

  <p class="text-muted small mt-4">
    <i class="fas fa-shield-halved me-1"></i>{{ __('Translations are provided for reading convenience only and are never saved to the catalogue. The original text is the authoritative version and should be used for citation.') }}
  </p>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
(function () {
  var form = document.getElementById('rlForm');
  if (!form) { return; }
  var sel = document.getElementById('rlLang');
  var btn = document.getElementById('rlBtn');
  var spin = document.getElementById('rlSpinner');
  var officialBanner = document.getElementById('rlOfficialBanner');
  var officialLang = document.getElementById('rlOfficialLang');
  var mtBanner = document.getElementById('rlMtBanner');
  var mtLang = document.getElementById('rlMtLang');
  var notice = document.getElementById('rlNotice');
  var noticeText = document.getElementById('rlNoticeText');
  var errBox = document.getElementById('rlError');
  var idOrSlug = @json($idOrSlug);
  var sourceLang = @json($sourceLang);
  var translateUrl = @json(url('/read/'.$idOrSlug.'/translate'));
  var showUrl = @json(url('/read/'.$idOrSlug));
  var csrf = '{{ csrf_token() }}';
  var inFlight = 0;

  function txt(s) { return (s == null ? '' : String(s)); }
  function setHidden(el, hidden) {
    if (!el) { return; }
    if (hidden) { el.classList.add('d-none'); } else { el.classList.remove('d-none'); }
  }
  function showNotice(msg) {
    if (!notice) { return; }
    if (msg) { if (noticeText) { noticeText.textContent = txt(msg); } setHidden(notice, false); }
    else { setHidden(notice, true); }
  }

  // Collapse to original-only across all fields.
  function showOriginalOnly() {
    document.querySelectorAll('#rlFields section[data-field]').forEach(function (sec) {
      var disp = sec.querySelector('.rl-display');
      var orig = sec.querySelector('.rl-original');
      var wrap = sec.querySelector('.rl-orig-wrap');
      if (disp && orig) { disp.textContent = orig.textContent; }
      if (wrap) { wrap.classList.add('d-none'); }
    });
    setHidden(officialBanner, true);
    setHidden(mtBanner, true);
  }

  // Render a read() contract into the page (official OR machine translation).
  function applyRendering(r) {
    var byKey = {};
    (r.fields || []).forEach(function (rf) { byKey[rf.key] = rf; });
    var any = false;

    document.querySelectorAll('#rlFields section[data-field]').forEach(function (sec) {
      var key = sec.getAttribute('data-field');
      var rf = byKey[key];
      var disp = sec.querySelector('.rl-display');
      var orig = sec.querySelector('.rl-original');
      var wrap = sec.querySelector('.rl-orig-wrap');
      if (!disp) { return; }

      if (rf && rf.is_translated) {
        disp.textContent = txt(rf.translated);
        if (orig && typeof rf.original === 'string') { orig.textContent = rf.original; }
        if (wrap) { wrap.classList.remove('d-none'); }
        any = true;
      } else {
        if (orig) { disp.textContent = orig.textContent; }
        if (wrap) { wrap.classList.add('d-none'); }
      }
    });

    // Pick the correct banner: green official vs amber machine translation.
    var isOfficial = !!r.is_official;
    if (any && isOfficial) {
      if (officialLang) { officialLang.textContent = r.language ? ('(' + txt(r.language) + ')') : ''; }
      setHidden(officialBanner, false);
      setHidden(mtBanner, true);
    } else if (any) {
      if (mtLang) { mtLang.textContent = r.language ? txt(r.language) : ''; }
      setHidden(mtBanner, false);
      setHidden(officialBanner, true);
    } else {
      setHidden(officialBanner, true);
      setHidden(mtBanner, true);
    }

    showNotice(r.notice || '');
    return any;
  }

  function renderTo(lang) {
    setHidden(errBox, true);
    try {
      history.replaceState(null, '', showUrl + (lang ? ('?lang=' + encodeURIComponent(lang)) : ''));
    } catch (e) {}

    if (!lang || lang === sourceLang) { showOriginalOnly(); showNotice(''); return; }

    var ticket = ++inFlight;
    setHidden(spin, false);
    if (btn) { btn.disabled = true; }

    fetch(translateUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ lang: lang })
    })
      .then(function (res) { if (!res.ok) { throw new Error('http ' + res.status); } return res.json(); })
      .then(function (r) {
        if (ticket !== inFlight) { return; }
        r = r || { fields: [] };
        var any = applyRendering(r);
        if (!any && lang) {
          showNotice('{{ __('No translation was available for this selection; showing the original.') }}');
        }
      })
      .catch(function () {
        if (ticket !== inFlight) { return; }
        showOriginalOnly();
        setHidden(errBox, false); // original stays visible, calm notice, never a 500
      })
      .finally(function () {
        if (ticket === inFlight) { setHidden(spin, true); if (btn) { btn.disabled = false; } }
      });
  }

  if (sel) {
    sel.addEventListener('change', function () { renderTo((sel.value || '').trim()); });
  }
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    renderTo((sel.value || '').trim());
  });
})();
</script>
@endsection
