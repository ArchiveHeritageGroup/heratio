{{--
  Image alt-text curation worklist (admin). The actionable companion to the
  read-only /admin/accessibility coverage report: that report surfaced that
  published image surrogates carry essentially no genuine alternative text (the
  catalogue has no dedicated alt-text column). Here a cataloguer or contributor
  authors a real, human-authored text alternative for each published image into the
  image_alt_text side table (WCAG 2.1 - 1.1.1 Non-text Content).

  Lang-aware (international; Afrikaans is a first-class working language). Read +
  one write (the inline save form posts to alt-text.save). Empty / unavailable
  states render calmly, never a 500.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems.
  Licensed under the GNU Affero General Public License v3 or later.
--}}
@extends('theme::layouts.1col')
@section('title', __('Image alt-text curation'))

@section('content')
@php
  $available = $available ?? false;
  $coverage = $coverage ?? ['total' => 0, 'with' => 0, 'pct' => 0.0, 'lang' => 'en'];
  $worklist = $worklist ?? ['rows' => [], 'page' => 1, 'per_page' => 25, 'total_missing' => 0, 'last_page' => 1, 'lang' => 'en'];
  $lang = $lang ?? ($coverage['lang'] ?? 'en');
  $maxAltLen = (int) ($maxAltLen ?? 2000);
  // Optional AI assist (a DRAFT suggestion only; never auto-saved). Shown only when
  // the gateway vision path is configured; otherwise the manual curation is unchanged.
  $aiEnabled = (bool) ($aiEnabled ?? false);

  $covTotal = (int) ($coverage['total'] ?? 0);
  $covWith = (int) ($coverage['with'] ?? 0);
  $covPct = (float) ($coverage['pct'] ?? 0);

  $rows = $worklist['rows'] ?? [];
  $page = (int) ($worklist['page'] ?? 1);
  $lastPage = (int) ($worklist['last_page'] ?? 1);
  $totalMissing = (int) ($worklist['total_missing'] ?? 0);

  // A small, neutral, international set of common working languages. The free-text
  // box below lets a curator switch to any other BCP-47-ish code. Afrikaans leads
  // the non-English options as a first-class working language.
  $commonLangs = ['en' => 'English', 'af' => 'Afrikaans', 'fr' => 'Francais', 'pt' => 'Portugues', 'de' => 'Deutsch', 'es' => 'Espanol', 'nl' => 'Nederlands'];

  $covClass = match (true) {
      $covPct >= 95.0 => 'text-success',
      $covPct >= 40.0 => 'text-warning',
      $covPct > 0.0   => 'text-warning',
      default         => 'text-danger',
  };
  $covBar = match (true) {
      $covPct >= 95.0 => 'bg-success',
      $covPct >= 40.0 => 'bg-warning',
      default         => 'bg-danger',
  };
@endphp

<div class="container-fluid py-3">

  <div class="d-flex flex-wrap align-items-baseline gap-2 mb-1">
    <h1 class="h4 mb-0"><i class="fas fa-image me-2 text-primary"></i>{{ __('Image alt-text curation') }}</h1>
    <span class="text-muted small">{{ __('Author a text alternative for every published image') }}</span>
    <span class="ms-auto"></span>
    @if(Route::has('accessibility.index'))
      <a href="{{ route('accessibility.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-universal-access me-1"></i>{{ __('Accessibility report') }}
      </a>
    @endif
  </div>
  <p class="text-muted small mb-2" style="max-width:900px">
    {{ __('Screen-reader users rely on a text alternative to understand an image (WCAG 2.1 - 1.1.1 Non-text Content). This worklist lists published images that still have no curated alternative text in the working language, so you can add one. Alternative text is authored by people here, and stored separately from the embedded caption.') }}
    @if($aiEnabled)
      {{ __('To speed things up you may ask the AI vision model (via the AHG gateway) for a draft description; it is a starting point only - you review, correct, and save it. Nothing is ever saved automatically.') }}
    @endif
  </p>

  {{-- Flash messages from the save action --}}
  @if(session('status'))
    <div class="alert alert-success py-2 small mb-3"><i class="fas fa-circle-check me-1"></i>{{ session('status') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-warning py-2 small mb-3"><i class="fas fa-triangle-exclamation me-1"></i>{{ session('error') }}</div>
  @endif

  @if(! $available)
    {{-- Feature not available on this install (missing store table / DB). --}}
    <div class="card border-0 shadow-sm">
      <div class="card-body text-center py-5">
        <div class="display-6 text-muted mb-2"><i class="fas fa-image"></i></div>
        <h2 class="h5">{{ __('Alt-text curation is not available yet') }}</h2>
        <p class="text-muted mb-0" style="max-width:620px;margin:0 auto">
          {{ __('The alternative-text store could not be reached on this instance. Once the catalogue and the alt-text store are in place, this worklist will let you author a text alternative for every published image.') }}
        </p>
      </div>
    </div>
  @else

    {{-- Working language + coverage --}}
    <div class="row g-3 mb-3">
      <div class="col-12 col-lg-5">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted small text-uppercase mb-1">{{ __('Curated alt-text coverage') }}</div>
            <div class="d-flex align-items-baseline gap-2">
              <div class="display-6 mb-0 {{ $covClass }}">{{ number_format($covWith) }}</div>
              <div class="text-muted">{{ __('of') }} {{ number_format($covTotal) }}</div>
              <div class="ms-auto fs-5 fw-semibold {{ $covClass }}">{{ rtrim(rtrim(number_format($covPct, 1), '0'), '.') }}%</div>
            </div>
            <div class="progress mt-1" style="height:8px" role="progressbar"
                 aria-valuenow="{{ $covWith }}" aria-valuemin="0" aria-valuemax="{{ max(1, $covTotal) }}"
                 aria-label="{{ __('Curated alt-text coverage') }}">
              <div class="progress-bar {{ $covBar }}" style="width: {{ max(0, min(100, $covPct)) }}%"></div>
            </div>
            <p class="text-muted small mb-0 mt-2">
              {{ __('Published image surrogates that carry a genuine, human-authored text alternative in the working language (:lang).', ['lang' => $lang]) }}
            </p>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-7">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted small text-uppercase mb-2">{{ __('Working language') }}</div>
            <form method="GET" action="{{ route('alt-text.index') }}" class="row g-2 align-items-end">
              <div class="col-auto">
                <label for="altLangSelect" class="form-label small mb-1">{{ __('Choose a language') }}</label>
                <select id="altLangSelect" name="lang" class="form-select form-select-sm" onchange="this.form.submit()">
                  @php $known = false; @endphp
                  @foreach($commonLangs as $code => $label)
                    <option value="{{ $code }}" @selected($lang === $code)>{{ $label }} ({{ $code }})</option>
                    @php $known = $known || ($lang === $code); @endphp
                  @endforeach
                  @unless($known)
                    <option value="{{ $lang }}" selected>{{ $lang }}</option>
                  @endunless
                </select>
              </div>
              <div class="col-auto">
                <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Switch') }}</button></noscript>
              </div>
            </form>
            <p class="text-muted small mb-0 mt-2">
              {{ __('Alternative text is authored per language, so the same image can carry a description in English, Afrikaans, and any other language. Switching the language shows the images still missing alt text in that language.') }}
            </p>
          </div>
        </div>
      </div>
    </div>

    {{-- Worklist: published images missing alt text in this language --}}
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white d-flex flex-wrap align-items-baseline gap-2">
        <span class="fw-semibold"><i class="fas fa-list-check me-2 text-muted"></i>{{ __('Images still needing alternative text') }}</span>
        @if($totalMissing > 0)
          <span class="ms-auto text-muted small">{{ number_format($totalMissing) }} {{ __('to do in :lang', ['lang' => $lang]) }}</span>
        @endif
      </div>
      <div class="card-body">
        @if(empty($rows))
          {{-- Empty state: either nothing to do, or no published images at all. --}}
          <div class="text-center py-5">
            <div class="display-6 text-success mb-2"><i class="fas fa-circle-check"></i></div>
            <h2 class="h5">{{ __('Nothing to curate here') }}</h2>
            <p class="text-muted mb-0" style="max-width:620px;margin:0 auto">
              @if($covTotal > 0)
                {{ __('Every published image now carries a curated text alternative in this language. Switch the working language above to curate another language, or revisit as new images are published.') }}
              @else
                {{ __('There are no published image surrogates to curate yet. When images are published, they will appear here so you can author a text alternative for each.') }}
              @endif
            </p>
          </div>
        @else
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th style="width:34%">{{ __('Image') }}</th>
                  <th>{{ __('Alternative text') }} <span class="text-muted fw-normal">({{ $lang }})</span></th>
                </tr>
              </thead>
              <tbody>
                @foreach($rows as $row)
                  @php
                    $doId = (int) ($row['digital_object_id'] ?? 0);
                    $title = trim((string) ($row['title'] ?? ''));
                    $slug = $row['slug'] ?? null;
                    $name = trim((string) ($row['name'] ?? ''));
                    $caption = trim((string) ($row['caption'] ?? ''));
                    $display = $title !== '' ? $title : ($name !== '' ? $name : (__('Digital object').' #'.$doId));
                  @endphp
                  <tr>
                    <td>
                      <div class="fw-semibold">
                        @if($slug)
                          <a href="{{ url('/'.$slug) }}" target="_blank" rel="noopener">{{ $display }}</a>
                        @else
                          {{ $display }}
                        @endif
                      </div>
                      @if($name !== '')
                        <div class="text-muted small"><i class="fas fa-file-image me-1"></i>{{ $name }}</div>
                      @endif
                      @if($caption !== '')
                        <div class="text-muted small mt-1">
                          <span class="fw-semibold">{{ __('Embedded caption (fallback)') }}:</span>
                          {{ \Illuminate\Support\Str::limit($caption, 160) }}
                        </div>
                      @endif
                    </td>
                    <td>
                      <form method="POST" action="{{ route('alt-text.save') }}" class="d-flex flex-column gap-2">
                        @csrf
                        <input type="hidden" name="digital_object_id" value="{{ $doId }}">
                        <input type="hidden" name="lang" value="{{ $lang }}">
                        <input type="hidden" name="page" value="{{ $page }}">
                        <label class="visually-hidden" for="alt-{{ $doId }}">{{ __('Alternative text for :name', ['name' => $display]) }}</label>
                        {{-- AI-draft notice: hidden until a suggestion is fetched. Honest framing -
                             the draft is AI-generated and must be reviewed + edited by a person, who
                             is the author of the saved text. --}}
                        <div id="ai-note-{{ $doId }}" class="alert alert-info py-1 px-2 small mb-0 d-none" role="status">
                          <i class="fas fa-robot me-1"></i><strong>{{ __('AI-suggested - review and edit before saving') }}.</strong>
                          {{ __('This draft was generated from the image by an AI vision model via the AHG gateway. You are the author: check it for accuracy, correct anything wrong, and remove anything the model guessed.') }}
                        </div>
                        <textarea id="alt-{{ $doId }}" name="alt_text" class="form-control form-control-sm"
                                  rows="2" maxlength="{{ $maxAltLen }}"
                                  placeholder="{{ $caption !== '' ? __('Describe the image (you can adapt the embedded caption above)') : __('Describe what the image shows, for someone who cannot see it') }}"></textarea>
                        <div class="d-flex align-items-center flex-wrap gap-2">
                          <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-floppy-disk me-1"></i>{{ __('Save') }}</button>
                          @if($aiEnabled)
                            <button type="button" class="btn btn-sm btn-outline-secondary js-alt-suggest"
                                    data-do-id="{{ $doId }}" data-target="alt-{{ $doId }}" data-note="ai-note-{{ $doId }}"
                                    title="{{ __('Generate a draft description from the image - you review and edit it before saving') }}">
                              <i class="fas fa-wand-magic-sparkles me-1"></i>{{ __('Suggest alt text') }}
                            </button>
                            <span class="js-alt-suggest-msg text-muted small" data-for="{{ $doId }}" aria-live="polite"></span>
                          @endif
                          <span class="text-muted small">{{ __('Max :n characters', ['n' => number_format($maxAltLen)]) }}</span>
                        </div>
                      </form>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          {{-- Pagination (bounded, page links preserve the working language) --}}
          @if($lastPage > 1)
            <nav aria-label="{{ __('Worklist pages') }}" class="mt-2">
              <ul class="pagination pagination-sm mb-0">
                <li class="page-item {{ $page <= 1 ? 'disabled' : '' }}">
                  <a class="page-link" href="{{ route('alt-text.index', ['lang' => $lang, 'page' => max(1, $page - 1)]) }}">{{ __('Previous') }}</a>
                </li>
                <li class="page-item disabled"><span class="page-link">{{ __('Page :p of :n', ['p' => $page, 'n' => $lastPage]) }}</span></li>
                <li class="page-item {{ $page >= $lastPage ? 'disabled' : '' }}">
                  <a class="page-link" href="{{ route('alt-text.index', ['lang' => $lang, 'page' => min($lastPage, $page + 1)]) }}">{{ __('Next') }}</a>
                </li>
              </ul>
            </nav>
          @endif
        @endif
      </div>
    </div>

    <p class="text-muted small mb-0 mt-3">
      <i class="fas fa-circle-info me-1"></i>{{ __('Curated entries are counted directly by the accessibility report. The embedded IPTC/XMP caption is only used as a fallback signal where no curated alternative text exists.') }}
    </p>

  @endif

</div>

@if($aiEnabled)
{{-- AI alt-text suggestion: fetch a DRAFT from the gateway vision model and drop it
     into the matching textarea. The draft is NEVER auto-saved - it only fills the box,
     reveals the "AI-suggested - review and edit before saving" notice, and leaves the
     curator to edit + submit the existing save form. Resilient: a non-ok response or a
     network error shows a calm inline message and leaves the textarea untouched. --}}
<script>
(function () {
  if (window.__ahgAltSuggestWired) { return; }
  window.__ahgAltSuggestWired = true;

  var endpoint = @json(route('alt-text.suggest'));
  var token = document.querySelector('meta[name="csrf-token"]');
  token = token ? token.getAttribute('content') : '';

  function msgEl(doId) {
    return document.querySelector('.js-alt-suggest-msg[data-for="' + doId + '"]');
  }

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('.js-alt-suggest');
    if (!btn) { return; }
    ev.preventDefault();

    var doId = btn.getAttribute('data-do-id');
    var ta = document.getElementById(btn.getAttribute('data-target'));
    var note = document.getElementById(btn.getAttribute('data-note'));
    var msg = msgEl(doId);
    if (!ta) { return; }

    btn.disabled = true;
    var original = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>' + @json(__('Thinking...'));
    if (msg) { msg.textContent = ''; }

    var body = new URLSearchParams();
    body.set('digital_object_id', doId);
    body.set('lang', @json($lang));

    fetch(endpoint, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': token,
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/x-www-form-urlencoded',
        'Accept': 'application/json'
      },
      body: body.toString(),
      credentials: 'same-origin'
    })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (data) {
      if (data && data.ok && data.draft) {
        ta.value = data.draft;
        ta.focus();
        if (note) { note.classList.remove('d-none'); }
        if (msg) { msg.textContent = @json(__('Draft inserted - please review and edit.')); }
      } else {
        var reason = (data && data.reason) ? data.reason : @json(__('Suggestion unavailable right now.'));
        if (msg) { msg.textContent = reason; }
      }
    })
    .catch(function () {
      if (msg) { msg.textContent = @json(__('Suggestion unavailable right now.')); }
    })
    .finally(function () {
      btn.disabled = false;
      btn.innerHTML = original;
    });
  });
})();
</script>
@endif
@endsection
