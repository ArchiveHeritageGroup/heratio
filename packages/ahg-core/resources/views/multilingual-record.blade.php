{{--
  heratio#1211 "every museum for everyone - universal multilingual access".
  Public, read-only page: read a catalogue record's key metadata in your own
  language, on demand. The original is shown first and is ALWAYS authoritative;
  the translation is machine-generated, clearly labelled, and never written back
  to the catalogue. International / jurisdiction-neutral; many languages offered.
  Extends the public 1-column layout. Two-segment route -> safe from the
  single-segment /{slug} archival-record catch-all.
--}}
@extends('theme::layouts.1col')
@section('title', __('Read this record in your language'))

@section('content')
<div class="container py-4" style="max-width:880px">
  <header class="mb-3">
    <h1 class="h3 mb-1"><i class="fas fa-globe me-2 text-muted"></i>{{ __('Read this record in your language') }}</h1>
    <p class="text-muted mb-0">{{ __('Choose a language to read this record\'s key metadata translated on demand. The original catalogue text is always shown and is authoritative.') }}</p>
  </header>

  <div class="alert alert-info d-flex align-items-start" role="note">
    <i class="fas fa-info-circle me-2 mt-1"></i>
    <div>{{ __('Translations are machine-generated for reading convenience only. They may contain errors and vary in quality by language. The original is the authoritative version. Translations are not saved to the catalogue.') }}</div>
  </div>

  @if(empty($fields))
    <div class="alert alert-secondary">{{ __('This record has no descriptive metadata to display.') }}</div>
  @else
    {{-- Language picker --}}
    <form id="mlForm" method="GET" action="{{ route('record.translate', ['idOrSlug' => $idOrSlug]) }}" class="mb-4">
      <label for="mlLang" class="form-label fw-semibold">{{ __('Read in') }}:</label>
      <div class="input-group">
        <span class="input-group-text"><i class="fas fa-language"></i></span>
        <select name="lang" id="mlLang" class="form-select">
          <option value="">{{ __('Original') }}</option>
          @foreach($languages as $l)
            <option value="{{ $l['code'] }}" @selected($selectedLang === $l['code'])>{{ $l['label'] }} ({{ $l['code'] }})</option>
          @endforeach
        </select>
        <button class="btn btn-primary" type="submit" id="mlBtn">
          <i class="fas fa-language me-1"></i>{{ __('Translate') }}
        </button>
      </div>
      <div class="form-text">
        {{ __('Source language of this record') }}: <span class="badge bg-light text-dark border">{{ $sourceLang }}</span>
      </div>
    </form>

    {{-- Machine-translation banner (shown only when a translation is on screen) --}}
    <div id="mlBanner" class="alert alert-warning d-flex align-items-start {{ ($translation && !empty($translation['fields']) && collect($translation['fields'])->contains('is_translated', true)) ? '' : 'd-none' }}" role="note">
      <i class="fas fa-robot me-2 mt-1"></i>
      <div>
        <strong>{{ __('Machine translation') }}</strong> &mdash;
        {{ __('the original is authoritative.') }}
        <button type="button" id="mlShowOriginal" class="btn btn-sm btn-outline-secondary ms-2">
          <i class="fas fa-undo me-1"></i>{{ __('Show original') }}
        </button>
      </div>
    </div>

    <div id="mlSpinner" class="text-center text-muted py-4 d-none">
      <div class="spinner-border" role="status"></div>
      <div class="mt-2 small">{{ __('Translating...') }}</div>
    </div>

    {{-- Fields --}}
    <div id="mlFields">
      @php
        // Map object_id-keyed translated rows by field key for server-side render.
        $trByKey = [];
        if ($translation && !empty($translation['fields'])) {
          foreach ($translation['fields'] as $tf) { $trByKey[$tf['key']] = $tf; }
        }
      @endphp
      @foreach($fields as $f)
        @php $tf = $trByKey[$f['key']] ?? null; @endphp
        <section class="card mb-3" data-field="{{ $f['key'] }}">
          <div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-2">{{ __($f['label']) }}</h2>
            <div class="ml-original" style="line-height:1.7; white-space:pre-line">{{ $f['original'] }}</div>
            <div class="ml-translated {{ ($tf && $tf['is_translated']) ? '' : 'd-none' }}" style="line-height:1.7; white-space:pre-line">{{ $tf ? $tf['translated'] : '' }}</div>
            @if($tf && $tf['is_translated'])
              <div class="ml-mt-tag small text-warning mt-1"><i class="fas fa-robot me-1"></i>{{ __('Machine translation') }}</div>
            @endif
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
  var showOrig = document.getElementById('mlShowOriginal');
  var objectId = {{ (int) $objectId }};

  function esc(s) { var d = document.createElement('div'); d.textContent = (s == null ? '' : String(s)); return d.innerHTML; }

  function showOriginalOnly() {
    document.querySelectorAll('#mlFields section[data-field]').forEach(function (sec) {
      var t = sec.querySelector('.ml-translated');
      var tag = sec.querySelector('.ml-mt-tag');
      if (t) { t.classList.add('d-none'); }
      if (tag) { tag.classList.add('d-none'); }
    });
    if (banner) { banner.classList.add('d-none'); }
  }

  function applyTranslation(r) {
    var byKey = {};
    (r.fields || []).forEach(function (tf) { byKey[tf.key] = tf; });
    var anyTranslated = false;

    document.querySelectorAll('#mlFields section[data-field]').forEach(function (sec) {
      var key = sec.getAttribute('data-field');
      var tf = byKey[key];
      var tDiv = sec.querySelector('.ml-translated');
      var tag = sec.querySelector('.ml-mt-tag');
      if (!tDiv) { return; }
      if (tf && tf.is_translated) {
        tDiv.textContent = tf.translated == null ? '' : String(tf.translated);
        tDiv.classList.remove('d-none');
        anyTranslated = true;
        if (!tag) {
          tag = document.createElement('div');
          tag.className = 'ml-mt-tag small text-warning mt-1';
          tag.innerHTML = '<i class="fas fa-robot me-1"></i>' + esc('{{ __('Machine translation') }}');
          tDiv.parentNode.appendChild(tag);
        }
        tag.classList.remove('d-none');
      } else {
        tDiv.classList.add('d-none');
        if (tag) { tag.classList.add('d-none'); }
      }
    });

    if (banner) {
      if (anyTranslated) { banner.classList.remove('d-none'); }
      else { banner.classList.add('d-none'); }
    }
  }

  if (showOrig) { showOrig.addEventListener('click', showOriginalOnly); }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var lang = (sel.value || '').trim();
    // Reflect the choice in the URL so the page is shareable / refresh-safe.
    try {
      var url = '{{ route('record.translate', ['idOrSlug' => $idOrSlug]) }}' + (lang ? ('?lang=' + encodeURIComponent(lang)) : '');
      history.replaceState(null, '', url);
    } catch (err) {}

    if (!lang) { showOriginalOnly(); return; }

    spin.classList.remove('d-none');
    btn.disabled = true;
    fetch('{{ route('record.translate.ajax') }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
      body: JSON.stringify({ object_id: objectId, lang: lang })
    })
      .then(function (res) { return res.json(); })
      .then(function (r) { applyTranslation(r || { fields: [] }); })
      .catch(function () { /* graceful: leave the original on screen */ showOriginalOnly(); })
      .finally(function () { spin.classList.add('d-none'); btn.disabled = false; });
  });
})();
</script>
@endsection
