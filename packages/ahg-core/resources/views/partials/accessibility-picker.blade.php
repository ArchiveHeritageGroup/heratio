{{--
  accessibility-picker - Heratio ahg-core (heratio#1211)

  A compact, reusable "Reading comfort" control that lets any anonymous visitor
  turn on a small set of accessibility preferences and have them remembered
  (1-year cookie + session) via the accessibility.preferences.set endpoint. Safe
  to @include from any public view.

  Three on/off preferences (validated server-side against a fixed set):
    - high-contrast
    - larger-text
    - reduced-motion

  The AccessibilityPreferences middleware applies the matching <body> classes
  (a11y-high-contrast / a11y-larger-text / a11y-reduced-motion) on every page,
  so the theme can style them. This control only sets the preference.

  Accessible by construction: a real <fieldset>/<legend>, every checkbox has a
  <label>, the control is fully keyboard-operable, and it works WITHOUT
  JavaScript - it is a real <form method="POST"> whose submit persists the
  choice and redirects back. When JS is present the inline script persists on
  change via a CSRF-nonce'd fetch and dispatches an 'ahg:a11y' event so the
  classes apply instantly with no reload.

  Optional @include data (all have safe defaults):
    $apRedirect - same-app path to return to after a no-JS submit. Optional;
                  defaults to the current request path+query.
    $apLabel    - the fieldset legend. Optional.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za
  Licensed under the GNU Affero General Public License v3 or later.
--}}
@php
  // The supported preferences + their human labels. The token set is the single
  // source of truth in the middleware; we mirror it here for the labels only.
  $apPrefs = [
    'high-contrast' => __('High contrast'),
    'larger-text' => __('Larger text'),
    'reduced-motion' => __('Reduce motion'),
  ];

  // Currently-active tokens, resolved from session+cookie by the middleware and
  // shared as $ahgA11yActive. Default to none so a fresh visitor sees all off.
  $apActive = is_array($ahgA11yActive ?? null) ? $ahgA11yActive : [];

  $apReturn = $apRedirect ?? (request()->getRequestUri() ?: '/');
  $apId = 'ap_' . substr(md5(uniqid('', true)), 0, 8);
  $apLegend = $apLabel ?? __('Reading comfort');
@endphp
<form method="POST"
      action="{{ route('accessibility.preferences.set') }}"
      class="ahg-accessibility-picker"
      data-ap="{{ $apId }}"
      data-set-url="{{ route('accessibility.preferences.set') }}">
  @csrf
  <input type="hidden" name="redirect" value="{{ $apReturn }}">
  <fieldset class="border-0 p-0 m-0">
    <legend class="form-label mb-2 small fw-semibold">
      <i class="fas fa-universal-access me-1" aria-hidden="true"></i>{{ $apLegend }}
    </legend>
    <div class="d-flex flex-wrap gap-3">
      @foreach($apPrefs as $token => $label)
        <div class="form-check">
          <input class="form-check-input ap-toggle" type="checkbox"
                 name="prefs[]" value="{{ $token }}"
                 id="{{ $apId }}_{{ $token }}"
                 @checked(in_array($token, $apActive, true))>
          <label class="form-check-label small" for="{{ $apId }}_{{ $token }}">{{ $label }}</label>
        </div>
      @endforeach
    </div>
    {{-- Submit kept for the no-JS path; JS persists on change and suppresses it. --}}
    <noscript>
      <button type="submit" class="btn btn-sm btn-primary mt-2">{{ __('Apply') }}</button>
    </noscript>
  </fieldset>
</form>
<script nonce="{{ $cspNonce ?? '' }}">
(function () {
  var form = document.querySelector('form[data-ap="{{ $apId }}"]');
  if (!form) { return; }
  var setUrl = form.getAttribute('data-set-url') || '';
  var tokenEl = form.querySelector('input[name="_token"]');
  var csrf = tokenEl ? tokenEl.value : '';
  var toggles = form.querySelectorAll('.ap-toggle');

  function chosen() {
    var out = [];
    toggles.forEach(function (t) { if (t.checked) { out.push(t.value); } });
    return out;
  }

  function persist(list) {
    return fetch(setUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ prefs: list.join(',') })
    }).then(function (res) {
      if (!res.ok) { throw new Error('http ' + res.status); }
      return res.json();
    });
  }

  toggles.forEach(function (t) {
    t.addEventListener('change', function () {
      var list = chosen();
      persist(list).then(function (r) {
        // Apply instantly (no reload) by handing the resolved classes to the
        // always-on applier injected by the AccessibilityPreferences middleware.
        var classes = (r && Array.isArray(r.classes)) ? r.classes : [];
        try {
          document.dispatchEvent(new CustomEvent('ahg:a11y', {
            bubbles: true, detail: { classes: classes }
          }));
        } catch (e) {}
      }).catch(function () {
        // Graceful: no-JS path still persists + redirects.
        try { form.submit(); } catch (e) {}
      });
    });
  });

  // Intercept the (rare) explicit submit so it stays on-page when JS is present.
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    persist(chosen()).then(function (r) {
      var classes = (r && Array.isArray(r.classes)) ? r.classes : [];
      try {
        document.dispatchEvent(new CustomEvent('ahg:a11y', {
          bubbles: true, detail: { classes: classes }
        }));
      } catch (e) {}
    }).catch(function () { try { form.submit(); } catch (e) {} });
  });
})();
</script>
