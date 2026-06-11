{{--
  Public "Language coverage" dashboard (heratio#1211, universal multilingual access).

  A read-only, visitor-facing snapshot of which languages this collection's catalogue
  can be read in, and how much of it: a hero line, headline stat cards, and simple
  inline CSS bar breakdowns (descriptions by language, authority records by language,
  vocabulary terms by language). No charting library and no CDN - the bars are plain
  divs reusing the same _breakdown partial the "collection at a glance" page uses.

  Framing is an open INVITATION ("help us reach more readers"), not a deficiency
  report. A small on-demand panel lets a visitor paste a record id and read its key
  metadata machine-translated into a chosen language, via the SANCTIONED AHG gateway
  (POST /language-coverage/translate) - always clearly labelled "machine translation
  via the AHG gateway - not an official translation". The panel degrades gracefully:
  if the gateway is unavailable it shows the original text, flagged.

  Every figure comes from LanguageCoverageService via LanguageCoverageController and
  is already null-safe. A zero total (or a service error) renders the calm "still
  being catalogued" empty-state - never a 500.

  Jurisdiction-neutral, internationalised copy. Afrikaans leads Dutch in the
  breakdowns (handled in the service).

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za
  Licensed under the GNU Affero General Public License v3 or later.

  Extends the public 1-column layout.
--}}
@extends('theme::layouts.1col')
@section('title', __('Languages you can read this collection in'))

@php
    // Defensive locals: the controller always passes these, but coalesce so a stale
    // render context can never trip an undefined-variable warning.
    $total = (int) ($total ?? 0);
    $primary = $primary ?? null;
    $languageCount = (int) ($languageCount ?? 0);
    $descriptions = $descriptions ?? [];
    $actors = $actors ?? [];
    $terms = $terms ?? [];
    $hasRecordTranslate = (bool) ($hasRecordTranslate ?? false);
    $isEmpty = ($total <= 0 && empty($descriptions) && empty($actors) && empty($terms));

    $fmt = fn ($n) => number_format((int) ($n ?? 0));
    $fmtPct = fn ($p) => rtrim(rtrim(number_format((float) ($p ?? 0), 1), '0'), '.');

    // Adapt the description rows into the shape the _breakdown partial expects
    // (label / count / pct / url). We do not deep-link these into browse (there is
    // no language facet wired up), so url stays null and the rows render as text.
    $descRows = [];
    foreach ($descriptions as $d) {
        $descRows[] = [
            'label' => ($d['label'] ?? '') . ' (' . ($d['code'] ?? '') . ')',
            'count' => $d['titled'] ?? 0,
            'pct'   => $d['pct'] ?? 0,
            'url'   => null,
        ];
    }
    $actorRows = [];
    foreach ($actors as $a) {
        $actorRows[] = [
            'label' => ($a['label'] ?? '') . ' (' . ($a['code'] ?? '') . ')',
            'count' => $a['count'] ?? 0,
            'pct'   => $a['pct'] ?? 0,
            'url'   => null,
        ];
    }
    $termRows = [];
    foreach ($terms as $t) {
        $termRows[] = [
            'label' => ($t['label'] ?? '') . ' (' . ($t['code'] ?? '') . ')',
            'count' => $t['count'] ?? 0,
            'pct'   => $t['pct'] ?? 0,
            'url'   => null,
        ];
    }
@endphp

@section('content')
<div class="container py-4" style="max-width:1040px">

  <header class="mb-4 text-center">
    <h1 class="mb-2">
      <i class="fas fa-language me-2 text-muted"></i>{{ __('Languages you can read this collection in') }}
    </h1>
    <p class="lead text-muted mb-0" style="max-width:780px;margin:0 auto">
      {{ __('Every museum, for everyone. This page shows how much of the catalogue is written in each language today - and invites you to help us reach more readers in more languages.') }}
    </p>
  </header>

  @if($isEmpty)
    {{-- Empty-state: nothing published / described yet, or a service-level error. --}}
    <div class="text-center py-5">
      <div class="mb-3">
        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light"
              style="width:5rem;height:5rem">
          <i class="fas fa-globe fs-1 text-primary"></i>
        </span>
      </div>
      <h2 class="h4">{{ __('The collection is still being catalogued') }}</h2>
      <p class="text-muted" style="max-width:560px;margin:0 auto">
        {{ __('There is nothing to show here just yet. As records are described and published, this page will fill with the languages the collection can be read in. Please check back soon.') }}
      </p>
    </div>
  @else

    {{-- ----------------------------------------------------------------- --}}
    {{-- Headline stat cards.                                              --}}
    {{-- ----------------------------------------------------------------- --}}
    <div class="row row-cols-2 row-cols-md-4 g-3 mb-4 text-center">
      @php
        $stats = [
          ['icon' => 'fas fa-folder-open', 'value' => $total,                                   'label' => __('Published descriptions')],
          ['icon' => 'fas fa-language',    'value' => $languageCount,                            'label' => __('Languages present')],
          ['icon' => 'fas fa-star',        'value' => $primary['label'] ?? __('-'),              'label' => __('Primary language'), 'text' => true],
          ['icon' => 'fas fa-percentage',  'value' => $fmtPct($primary['pct'] ?? 0) . '%',       'label' => __('Of records in it'), 'text' => true],
        ];
      @endphp
      @foreach($stats as $stat)
        <div class="col">
          <div class="card h-100 shadow-sm border-0 bg-light">
            <div class="card-body py-3">
              <i class="{{ $stat['icon'] }} fs-4 text-primary mb-2 d-block"></i>
              <div class="fw-bold" style="font-size:1.35rem;line-height:1.1">
                {{ !empty($stat['text']) ? $stat['value'] : $fmt($stat['value']) }}
              </div>
              <div class="small text-muted">{{ $stat['label'] }}</div>
            </div>
          </div>
        </div>
      @endforeach
    </div>

    {{-- "Help us translate" invitation banner. --}}
    <div class="alert alert-info d-flex align-items-start gap-2 mb-4" role="note">
      <i class="fas fa-hands-helping mt-1"></i>
      <div>
        <strong>{{ __('Help us reach more readers.') }}</strong>
        {{ __('Most of this catalogue is written in one or two languages. If you can contribute translations - or you represent a community whose language is under-served here - we would love to hear from you. Below you can also try an instant machine translation of any single record.') }}
      </div>
    </div>

    <div class="row g-4">
      {{-- Descriptions by language. --}}
      @if(!empty($descRows))
        <div class="col-12 col-lg-6">
          @include('ahg-core::collection-overview._breakdown', [
            'title' => __('Archival descriptions by language'),
            'icon'  => 'fas fa-folder-open',
            'rows'  => $descRows,
            'total' => $total,
            'barClass' => 'bg-primary',
            'note'  => __('Published records that carry a title in each language. Percentages are of all published descriptions.'),
          ])
        </div>
      @endif

      {{-- Authority records by language. --}}
      @if(!empty($actorRows))
        <div class="col-12 col-lg-6">
          @include('ahg-core::collection-overview._breakdown', [
            'title' => __('People & organisations by language'),
            'icon'  => 'fas fa-users',
            'rows'  => $actorRows,
            'total' => 0,
            'barClass' => 'bg-info',
            'note'  => __('Authority records with a name recorded in each language. Bars are relative to the best-covered language.'),
          ])
        </div>
      @endif

      {{-- Vocabulary terms by language. --}}
      @if(!empty($termRows))
        <div class="col-12 col-lg-6">
          @include('ahg-core::collection-overview._breakdown', [
            'title' => __('Subjects & places by language'),
            'icon'  => 'fas fa-tags',
            'rows'  => $termRows,
            'total' => 0,
            'barClass' => 'bg-secondary',
            'note'  => __('Controlled-vocabulary terms with a label in each language. Bars are relative to the best-covered language.'),
          ])
        </div>
      @endif

      {{-- ----------------------------------------------------------- --}}
      {{-- On-demand machine-translation panel.                        --}}
      {{-- ----------------------------------------------------------- --}}
      <div class="col-12 col-lg-6">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <h2 class="h5 card-title mb-3">
              <i class="fas fa-robot me-2 text-muted"></i>{{ __('Read any record in your language') }}
            </h2>
            <p class="small text-muted">
              {{ __('Enter a published record id and pick a language to see its key metadata translated on demand.') }}
            </p>

            <form id="lc-translate-form" class="row g-2 align-items-end"
                  method="post" action="{{ route('language-coverage.translate') }}">
              @csrf
              <div class="col-12 col-sm-5">
                <label for="lc-object-id" class="form-label small mb-1">{{ __('Record id') }}</label>
                <input type="number" min="1" step="1" class="form-control form-control-sm"
                       id="lc-object-id" name="object_id" placeholder="123" required>
              </div>
              <div class="col-8 col-sm-5">
                <label for="lc-lang" class="form-label small mb-1">{{ __('Language') }}</label>
                <select id="lc-lang" name="lang" class="form-select form-select-sm" required>
                  @php
                    // Offer the languages actually present, then a neutral default set.
                    $offered = [];
                    foreach ($descriptions as $d) { $offered[$d['code']] = $d['label']; }
                    foreach (['en'=>'English','af'=>'Afrikaans','fr'=>'Français','es'=>'Español','pt'=>'Português','de'=>'Deutsch','sw'=>'Kiswahili','ar'=>'العربية','zh'=>'中文'] as $c=>$l) {
                        $offered[$c] = $offered[$c] ?? $l;
                    }
                  @endphp
                  @foreach($offered as $code => $label)
                    <option value="{{ $code }}">{{ $label }} ({{ $code }})</option>
                  @endforeach
                </select>
              </div>
              <div class="col-4 col-sm-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                  <i class="fas fa-language"></i>
                </button>
              </div>
            </form>

            {{-- Result region (filled by JS; hidden until used). --}}
            <div id="lc-translate-result" class="mt-3" hidden>
              <div class="alert alert-warning small py-2 px-3 mb-2" role="note">
                <i class="fas fa-info-circle me-1"></i>
                <span id="lc-disclaimer">{{ __('Machine translation via the AHG gateway - not an official translation. The original text remains authoritative.') }}</span>
              </div>
              <div id="lc-translate-fields"></div>
            </div>
            <div id="lc-translate-error" class="mt-3 small text-muted" hidden></div>
          </div>
        </div>
      </div>

    </div>{{-- /.row --}}

    {{-- Footer: provenance + invitation to the per-record reader. --}}
    <div class="text-center mt-4">
      @if($hasRecordTranslate)
        <p class="small text-muted mb-2">
          {{ __('Every published record also has its own "read in your language" page.') }}
        </p>
      @endif
      @if(!empty($generatedAt))
        <p class="small text-muted mb-0">
          {{ __('Snapshot taken :when.', ['when' => $generatedAt]) }}
        </p>
      @endif
    </div>

  @endif

</div>

{{-- Progressive enhancement: AJAX submit of the translate panel. Without JS the
     form still posts and returns JSON (graceful, if plain). With JS we render the
     fields inline, ALWAYS showing the machine-translation disclaimer. --}}
<script>
(function () {
  var form = document.getElementById('lc-translate-form');
  if (!form) return;
  var resultBox = document.getElementById('lc-translate-result');
  var fieldsBox = document.getElementById('lc-translate-fields');
  var discBox   = document.getElementById('lc-disclaimer');
  var errBox    = document.getElementById('lc-translate-error');

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
    });
  }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    errBox.hidden = true;
    var fd = new FormData(form);
    var btn = form.querySelector('button[type="submit"]');
    if (btn) btn.disabled = true;

    fetch(form.action, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      body: fd
    }).then(function (r) {
      if (r.status === 404) { throw new Error('not_found'); }
      return r.json();
    }).then(function (data) {
      if (btn) btn.disabled = false;
      if (data && data.disclaimer && discBox) { discBox.textContent = data.disclaimer; }
      var rows = (data && data.fields) || [];
      if (!rows.length) {
        errBox.textContent = @json(__('This record has no public metadata to translate, or it is not published.'));
        errBox.hidden = false;
        resultBox.hidden = true;
        return;
      }
      var html = '';
      rows.forEach(function (f) {
        var tr = f.is_translated;
        html += '<div class="mb-2">'
              + '<div class="fw-semibold small">' + esc(f.label) + '</div>'
              + '<div class="small">' + esc(f.translated) + '</div>'
              + (tr ? '' : '<div class="small text-muted fst-italic">' + @json(__('(shown in the original - no machine translation available)')) + '</div>')
              + '</div>';
      });
      fieldsBox.innerHTML = html;
      resultBox.hidden = false;
    }).catch(function (e) {
      if (btn) btn.disabled = false;
      resultBox.hidden = true;
      errBox.textContent = (e && e.message === 'not_found')
        ? @json(__('That record could not be found, or it is not published.'))
        : @json(__('Translation is unavailable right now. Please try again later.'));
      errBox.hidden = false;
    });
  });
})();
</script>
@endsection
