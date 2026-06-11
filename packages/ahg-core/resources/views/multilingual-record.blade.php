{{--
  heratio#1211 "every museum for everyone - universal multilingual access".
  Public, read-only page: read a catalogue record's key metadata in your own
  language, on demand. The original is shown first and is ALWAYS authoritative;
  the translation is machine-generated, clearly labelled, and never written back
  to the catalogue. International / jurisdiction-neutral; many languages offered.

  Reading experience:
    - A target-language picker sourced from MultilingualRecordService::languages()
      (operator i18n_languages -> lang/*.json -> jurisdiction-neutral default).
      Afrikaans is ordered ahead of Dutch by the service (project rule).
    - Changing the picker re-translates via the record.translate.ajax endpoint
      (no full page reload) and swaps the displayed fields in place.
    - Original and translation are shown SIDE BY SIDE on wide screens and STACKED
      on narrow screens, so a reader can compare. Field labels stay in the UI
      language.
    - On a gateway error the original stays visible with an inline notice; an
      unsupported language falls back to the service default with a notice. Never
      a 500.

  Extends the public 1-column layout. Two-segment route -> safe from the
  single-segment /{slug} archival-record catch-all.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za
  Licensed under the GNU Affero General Public License v3 or later.
--}}
@extends('theme::layouts.1col')
@section('title', __('Read this record in your language'))

@section('content')
@php
  // Map the server-side pre-translation (no-JS / shareable deep link) by field key.
  $trByKey = [];
  if (!empty($translation['fields'])) {
    foreach ($translation['fields'] as $tf) { $trByKey[$tf['key']] = $tf; }
  }
  $hasServerTranslation = !empty($translation['fields'])
    && collect($translation['fields'])->contains('is_translated', true);
  $serverNotice = $translation['notice'] ?? null;
  $serverLangLabel = $translation['language'] ?? '';
@endphp
<div class="container py-4" style="max-width:1100px">
  <header class="mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
      <div>
        <h1 class="h3 mb-1"><i class="fas fa-globe me-2 text-muted"></i>{{ __('Read this record in your language') }}</h1>
        <p class="text-muted mb-0">{{ __('Choose a language to read this record\'s key metadata translated on demand, side by side with the original. The original catalogue text is always shown and is authoritative.') }}</p>
      </div>
      {{-- Compact, remembered reading-language control (heratio#1211 preference
           layer). Persists the choice for next time; on this page it drives the
           in-place re-translation via the ahg:reading-language event below. --}}
      <div class="text-md-end">
        @include('ahg-core::partials.reading-language-picker', [
          'rlpLanguages' => $languages,
          'rlpSelected'  => $selectedLang,
          'rlpSource'    => $sourceLang,
          'rlpRedirect'  => route('record.translate', ['idOrSlug' => $idOrSlug], false) . ($selectedLang !== '' ? ('?lang=' . urlencode($selectedLang)) : ''),
          'rlpLabel'     => __('Reading language'),
        ])
        @if(!empty($preferredLang))
          <div class="form-text mt-1">
            <i class="fas fa-bookmark me-1"></i>{{ __('Remembered for your next visit.') }}
          </div>
        @endif
      </div>
    </div>
  </header>

  <div class="alert alert-info d-flex align-items-start" role="note">
    <i class="fas fa-info-circle me-2 mt-1"></i>
    <div>{{ __('Translations are machine-generated for reading convenience only. They may contain errors and vary in quality by language. The original is the authoritative version and should be used for citation. Translations are not saved to the catalogue.') }}</div>
  </div>

  @if(empty($fields))
    <div class="alert alert-secondary">{{ __('This record has no descriptive metadata to display.') }}</div>
  @else
    {{-- Language picker. Source: MultilingualRecordService::languages(). --}}
    <form id="mlForm" method="GET" action="{{ route('record.translate', ['idOrSlug' => $idOrSlug]) }}" class="mb-3">
      <label for="mlLang" class="form-label fw-semibold">{{ __('Read in') }}:</label>
      <div class="input-group">
        <span class="input-group-text"><i class="fas fa-language"></i></span>
        <select name="lang" id="mlLang" class="form-select" aria-label="{{ __('Target language') }}">
          <option value="">{{ __('Original only') }}</option>
          @foreach($languages as $l)
            <option value="{{ $l['code'] }}" @selected($selectedLang === $l['code'])>{{ $l['label'] }} ({{ $l['code'] }})</option>
          @endforeach
        </select>
        {{-- Submit kept for the no-JS path; JS re-translates on change. --}}
        <button class="btn btn-primary" type="submit" id="mlBtn">
          <i class="fas fa-language me-1"></i>{{ __('Translate') }}
        </button>
      </div>
      <div class="form-text">
        {{ __('Source language of this record') }}:
        <span class="badge bg-light text-dark border">{{ $sourceLang }}</span>
        <span class="ms-2"><i class="fas fa-server me-1"></i>{{ __('Translation is powered by the AHG AI gateway.') }}</span>
      </div>
    </form>

    {{-- Non-fatal notice surface (unsupported language fallback, etc.). --}}
    <div id="mlNotice" class="alert alert-warning py-2 d-flex align-items-start {{ $serverNotice ? '' : 'd-none' }}" role="alert">
      <i class="fas fa-exclamation-triangle me-2 mt-1"></i>
      <div id="mlNoticeText">{{ $serverNotice ? __($serverNotice) : '' }}</div>
    </div>

    {{-- Inline error surface (gateway failure). The original stays visible. --}}
    <div id="mlError" class="alert alert-danger py-2 d-flex align-items-start d-none" role="alert">
      <i class="fas fa-triangle-exclamation me-2 mt-1"></i>
      <div>{{ __('The translation service could not be reached just now. The original text is shown below; please try again shortly.') }}</div>
    </div>

    {{-- Machine-translation banner (shown only when a translation is on screen). --}}
    <div id="mlBanner" class="alert alert-warning d-flex align-items-start {{ $hasServerTranslation ? '' : 'd-none' }}" role="note">
      <i class="fas fa-robot me-2 mt-1"></i>
      <div>
        <strong>{{ __('Machine translation') }}</strong>
        <span id="mlBannerLang">{{ $serverLangLabel ? ('(' . $serverLangLabel . ')') : '' }}</span>
        - {{ __('AI-translated; may contain errors. Consult the original for citation.') }}
        <button type="button" id="mlShowOriginal" class="btn btn-sm btn-outline-secondary ms-2">
          <i class="fas fa-undo me-1"></i>{{ __('Show original only') }}
        </button>
      </div>
    </div>

    <div id="mlSpinner" class="text-center text-muted py-4 d-none">
      <div class="spinner-border" role="status"></div>
      <div class="mt-2 small">{{ __('Translating...') }}</div>
    </div>

    {{-- Fields: side-by-side (original | translation) on wide screens, stacked on narrow. --}}
    <div id="mlFields">
      @foreach($fields as $f)
        @php $tf = $trByKey[$f['key']] ?? null; @endphp
        <section class="card mb-3" data-field="{{ $f['key'] }}">
          <div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3">{{ __($f['label']) }}</h2>
            <div class="row g-3">
              {{-- Original column. On narrow screens it stacks full width. --}}
              <div class="ml-col-original col-12 {{ ($tf && $tf['is_translated']) ? 'col-md-6' : '' }}">
                <div class="small text-muted text-uppercase mb-1">
                  <i class="fas fa-landmark me-1"></i>{{ __('Original') }}
                  <span class="badge bg-light text-dark border ms-1">{{ $sourceLang }}</span>
                </div>
                <div class="ml-original" style="line-height:1.7; white-space:pre-line">{{ $f['original'] }}</div>
              </div>
              {{-- Translated column, hidden until a translation is on screen. --}}
              <div class="ml-col-translated col-12 col-md-6 border-start {{ ($tf && $tf['is_translated']) ? '' : 'd-none' }}">
                <div class="small text-muted text-uppercase mb-1">
                  <i class="fas fa-language me-1"></i>{{ __('Translation') }}
                  <span class="ml-tr-lang badge bg-warning-subtle text-dark border ms-1">{{ $tf['lang'] ?? ($translation['lang'] ?? '') }}</span>
                </div>
                <div class="ml-translated" style="line-height:1.7; white-space:pre-line">{{ $tf ? $tf['translated'] : '' }}</div>
              </div>
            </div>
          </div>
        </section>
      @endforeach
    </div>
  @endif
</div>

<script nonce="{{ $cspNonce ?? '' }}">
(function () {
  var form = document.getElementById('mlForm');
  if (!form) { return; }
  var sel = document.getElementById('mlLang');
  var btn = document.getElementById('mlBtn');
  var spin = document.getElementById('mlSpinner');
  var banner = document.getElementById('mlBanner');
  var bannerLang = document.getElementById('mlBannerLang');
  var notice = document.getElementById('mlNotice');
  var noticeText = document.getElementById('mlNoticeText');
  var errBox = document.getElementById('mlError');
  var showOrig = document.getElementById('mlShowOriginal');
  var objectId = {{ (int) $objectId }};
  var ajaxUrl = '{{ route('record.translate.ajax') }}';
  var showUrl = '{{ route('record.translate', ['idOrSlug' => $idOrSlug]) }}';
  var csrf = '{{ csrf_token() }}';
  var prefUrl = '{{ route('reading-language.set') }}';
  var mtLabel = '{{ __('Machine translation') }}';
  var inFlight = 0;
  var syncing = false; // guard so a programmatic sync doesn't re-loop events

  // Persist the chosen reading language (1-year cookie + session) so a returning
  // visitor's record opens already translated. Best-effort: a failure here never
  // blocks the in-place translation the user already sees.
  function persistPreference(lang) {
    try {
      fetch(prefUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ lang: lang })
      }).catch(function () {});
    } catch (e) {}
  }

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

  // Collapse every translation column; show original full-width.
  function showOriginalOnly() {
    document.querySelectorAll('#mlFields section[data-field]').forEach(function (sec) {
      var origCol = sec.querySelector('.ml-col-original');
      var trCol = sec.querySelector('.ml-col-translated');
      if (trCol) { trCol.classList.add('d-none'); }
      if (origCol) { origCol.classList.remove('col-md-6'); }
    });
    setHidden(banner, true);
  }

  // Render a translate() contract into the side-by-side layout.
  function applyTranslation(r) {
    var byKey = {};
    (r.fields || []).forEach(function (tf) { byKey[tf.key] = tf; });
    var anyTranslated = false;
    var langCode = txt(r.lang);

    document.querySelectorAll('#mlFields section[data-field]').forEach(function (sec) {
      var key = sec.getAttribute('data-field');
      var tf = byKey[key];
      var origCol = sec.querySelector('.ml-col-original');
      var trCol = sec.querySelector('.ml-col-translated');
      var trDiv = sec.querySelector('.ml-translated');
      var trLangBadge = sec.querySelector('.ml-tr-lang');
      if (!trCol || !trDiv) { return; }

      if (tf && tf.is_translated) {
        trDiv.textContent = txt(tf.translated);
        if (trLangBadge) { trLangBadge.textContent = txt(tf.lang || langCode); }
        trCol.classList.remove('d-none');
        if (origCol) { origCol.classList.add('col-md-6'); }
        anyTranslated = true;
      } else {
        trCol.classList.add('d-none');
        if (origCol) { origCol.classList.remove('col-md-6'); }
      }
    });

    // Banner + per-record notice from the service contract.
    if (bannerLang) { bannerLang.textContent = r.language ? ('(' + txt(r.language) + ')') : ''; }
    setHidden(banner, !anyTranslated);
    showNotice(r.notice || '');
    return anyTranslated;
  }

  if (showOrig) {
    showOrig.addEventListener('click', function () {
      if (sel) { sel.value = ''; }
      showOriginalOnly();
      showNotice('');
      persistPreference(''); // "show original only" also forgets the preference
      try { history.replaceState(null, '', showUrl); } catch (e) {}
    });
  }

  function translateTo(lang) {
    setHidden(errBox, true);
    // Reflect the choice in the URL so the page is shareable / refresh-safe.
    try {
      history.replaceState(null, '', showUrl + (lang ? ('?lang=' + encodeURIComponent(lang)) : ''));
    } catch (e) {}

    if (!lang) { showOriginalOnly(); showNotice(''); return; }

    var ticket = ++inFlight;
    setHidden(spin, false);
    if (btn) { btn.disabled = true; }

    fetch(ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ object_id: objectId, lang: lang })
    })
      .then(function (res) {
        if (!res.ok) { throw new Error('http ' + res.status); }
        return res.json();
      })
      .then(function (r) {
        if (ticket !== inFlight) { return; } // a newer request superseded this one
        r = r || { fields: [] };
        var translated = applyTranslation(r);
        // Gateway round-tripped but nothing came back translated -> keep original,
        // tell the reader (not an error: same-language or MT had nothing to add).
        if (!translated && (r.provider === 'original') && lang) {
          showNotice('{{ __('No translation was available for this selection; showing the original.') }}');
        }
      })
      .catch(function () {
        if (ticket !== inFlight) { return; }
        // Graceful: original stays on screen, inline error shown, never a 500.
        showOriginalOnly();
        setHidden(errBox, false);
      })
      .finally(function () {
        if (ticket === inFlight) { setHidden(spin, true); if (btn) { btn.disabled = false; } }
      });
  }

  // Re-translate on picker change (no full reload), and remember the choice so
  // the next visit opens in this language.
  if (sel) {
    sel.addEventListener('change', function () {
      var lang = (sel.value || '').trim();
      translateTo(lang);
      if (!syncing) { persistPreference(lang); }
    });
  }

  // No-JS fallback path also works via GET submit; intercept for the async path.
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    translateTo((sel.value || '').trim());
  });

  // The compact header "Reading language" picker (partials/reading-language-
  // picker) persists the choice itself and emits ahg:reading-language. Mirror it
  // into the main picker + re-translate in place, without double-persisting.
  document.addEventListener('ahg:reading-language', function (e) {
    var lang = (e && e.detail && typeof e.detail.lang === 'string') ? e.detail.lang.trim() : '';
    if (sel && sel.value !== lang) {
      syncing = true;
      sel.value = lang;
      syncing = false;
    }
    translateTo(lang);
  });
})();
</script>
@endsection
