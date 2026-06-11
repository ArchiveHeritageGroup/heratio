{{--
  reading-language-picker - Heratio ahg-core (heratio#1211)

  A compact, reusable "Reading language: [picker]" control that persists the
  visitor's chosen reading language (1-year cookie + session) via the
  reading-language.set endpoint, and is safe to @include from any public view.

  Universal multilingual access, preference layer: a returning visitor sets their
  reading language ONCE and records open already translated into it. International
  / jurisdiction-neutral. Machine translation via the AHG AI gateway is clearly
  labelled. No catalogue writes; original text always stays authoritative.

  Progressive enhancement: it is a real <form method="POST"> that works WITHOUT
  JavaScript (submitting persists the choice and redirects back with the new
  ?lang= applied). When JS is available the inline script intercepts the change,
  persists via a CSRF/nonce'd fetch, and (on the translate page) lets that page's
  own picker drive the in-place re-translation - so we do not reload.

  Optional @include data (all have safe defaults):
    $rlpLanguages  - array<int,{code,label}>; defaults to
                     MultilingualRecordService::languages($rlpSourceCulture).
    $rlpSelected   - currently active reading-language code (string); defaults to
                     the persisted preference.
    $rlpSource     - the record's source culture (string), so the picker can
                     offer the authoritative original too. Optional.
    $rlpRedirect   - same-app path to return to after a no-JS submit. Optional;
                     defaults to the current request path+query.
    $rlpLabel      - the control's leading label. Optional.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za
  Licensed under the GNU Affero General Public License v3 or later.
--}}
@php
  $rlpSourceCulture = $rlpSource ?? null;
  $rlpSvc = app(\AhgCore\Services\MultilingualRecordService::class);

  // Languages: explicit list, else build from the service (same source the page
  // picker uses). Never a hardcoded list.
  $rlpLangs = $rlpLanguages ?? $rlpSvc->languages($rlpSourceCulture);

  // Current selection: explicit, else the persisted, validated preference.
  $rlpCurrent = isset($rlpSelected) && is_string($rlpSelected)
    ? trim($rlpSelected)
    : \AhgCore\Controllers\ReadingLanguageController::current(request(), $rlpSvc, $rlpSourceCulture);

  // No-JS return target: explicit, else the current path+query (safe, same-app).
  $rlpReturn = $rlpRedirect ?? (request()->getRequestUri() ?: '/');

  $rlpId = 'rlp_' . substr(md5(uniqid('', true)), 0, 8);
  $rlpHeading = $rlpLabel ?? __('Reading language');
@endphp
<form method="POST"
      action="{{ route('reading-language.set') }}"
      class="ahg-reading-language-picker d-inline-flex align-items-center gap-2 flex-wrap"
      data-rlp="{{ $rlpId }}"
      data-set-url="{{ route('reading-language.set') }}">
  @csrf
  <input type="hidden" name="redirect" value="{{ $rlpReturn }}">
  <label for="{{ $rlpId }}_sel" class="form-label mb-0 small fw-semibold text-nowrap">
    <i class="fas fa-language me-1" aria-hidden="true"></i>{{ $rlpHeading }}:
  </label>
  <select name="lang" id="{{ $rlpId }}_sel"
          class="form-select form-select-sm w-auto rlp-select"
          aria-label="{{ __('Preferred reading language') }}">
    <option value="">{{ __('Original only') }}</option>
    @foreach($rlpLangs as $rlpL)
      <option value="{{ $rlpL['code'] }}" @selected($rlpCurrent === $rlpL['code'])>{{ $rlpL['label'] }} ({{ $rlpL['code'] }})</option>
    @endforeach
  </select>
  {{-- Submit kept for the no-JS path; JS persists on change and suppresses it. --}}
  <noscript><button type="submit" class="btn btn-sm btn-primary">{{ __('Remember') }}</button></noscript>
  <span class="small text-muted d-inline-flex align-items-center">
    <i class="fas fa-server me-1" aria-hidden="true"></i>{{ __('Machine translation via the AHG AI gateway. Original stays authoritative.') }}
  </span>
</form>
<script nonce="{{ $cspNonce ?? '' }}">
(function () {
  var form = document.querySelector('form[data-rlp="{{ $rlpId }}"]');
  if (!form) { return; }
  var sel = form.querySelector('.rlp-select');
  var setUrl = form.getAttribute('data-set-url') || '';
  var tokenEl = form.querySelector('input[name="_token"]');
  var csrf = tokenEl ? tokenEl.value : '';

  // Persist the choice without a reload. On success, dispatch a DOM event so a
  // host page (e.g. the translate page) can re-translate in place; pages that do
  // not listen simply keep the new preference for next load. On any failure we
  // fall back to a normal form submit (the no-JS path), so the choice is never
  // silently lost.
  function persist(lang) {
    return fetch(setUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ lang: lang })
    }).then(function (res) {
      if (!res.ok) { throw new Error('http ' + res.status); }
      return res.json();
    });
  }

  if (sel) {
    sel.addEventListener('change', function () {
      var lang = (sel.value || '').trim();
      persist(lang).then(function (r) {
        var code = (r && typeof r.lang === 'string') ? r.lang : lang;
        try {
          form.dispatchEvent(new CustomEvent('ahg:reading-language', {
            bubbles: true,
            detail: { lang: code, cleared: !!(r && r.cleared) }
          }));
        } catch (e) {}
      }).catch(function () {
        // Graceful: no-JS path still persists + redirects.
        try { form.submit(); } catch (e) {}
      });
    });
  }

  // Intercept the (rare) explicit submit so it stays on-page when JS is present.
  form.addEventListener('submit', function (e) {
    if (!sel) { return; }
    e.preventDefault();
    sel.dispatchEvent(new Event('change'));
  });
})();
</script>
