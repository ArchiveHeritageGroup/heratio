{{--
  Cite this record - the human "Cite this" page (CitationController::show).

  Shows a formatted reference for ONE published archival record, with a
  copy-to-clipboard button for the plain reference and for each machine format
  (BibTeX, RIS, CSL-JSON, simple Dublin Core), plus a direct download link per
  format. Researchers drop the reference straight into a reference manager.

  Read-only, published records only. Every URL is built with url() in the
  controller, so no host is hardcoded. Honest: a field with no value is simply
  not shown (no fabricated authors or dates).

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za
  Licensed under the GNU Affero General Public License v3 or later.

  Extends the public 1-column theme layout (Bootstrap 5 central theme).
--}}
@extends('theme::layouts.1col')
@section('title', __('Cite this record').' - '.$rec['title'])

@section('content')
<div class="container py-4" style="max-width:880px">

  <nav class="mb-3" aria-label="breadcrumb">
    <a href="{{ $urls['record'] }}" class="text-decoration-none small">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back to the record') }}
    </a>
  </nav>

  <header class="mb-4">
    <span class="badge bg-light text-muted border mb-2">
      <i class="fas fa-quote-right me-1"></i>{{ __('Cite this record') }}
    </span>
    <h1 class="h3 mb-1">{{ $rec['title'] }}</h1>
    @if(!empty($rec['identifier']))
      <div class="text-muted small">{{ $rec['identifier'] }}</div>
    @endif
  </header>

  {{-- Formatted reference --------------------------------------------------- --}}
  <section class="mb-4">
    <label class="form-label fw-semibold">{{ __('Reference') }}</label>
    <div class="card">
      <div class="card-body d-flex align-items-start gap-3">
        <p class="mb-0 flex-grow-1" id="cite-plain" style="line-height:1.6">{{ $plain }}</p>
        <button type="button" class="btn btn-sm btn-outline-secondary flex-shrink-0"
                data-copy-target="cite-plain" title="{{ __('Copy reference') }}">
          <i class="far fa-copy me-1"></i>{{ __('Copy') }}
        </button>
      </div>
    </div>
    <p class="text-muted small mt-2 mb-0">
      {{ __('A neutral archival reference. For a house style (Chicago, APA, MLA, etc.), download a machine format below and let your reference manager apply the style.') }}
    </p>
  </section>

  {{-- Machine formats -------------------------------------------------------- --}}
  <section>
    <h2 class="h5 mb-3">{{ __('Download for a reference manager') }}</h2>

    @php
      $formats = [
        ['key' => 'bibtex', 'label' => 'BibTeX', 'ext' => '.bib',  'url' => $urls['bib'],
         'hint' => __('LaTeX / BibLaTeX, JabRef, Zotero, Mendeley'), 'body' => $bibtex, 'id' => 'fmt-bibtex'],
        ['key' => 'ris',    'label' => 'RIS',    'ext' => '.ris',  'url' => $urls['ris'],
         'hint' => __('EndNote, Zotero, Mendeley, RefWorks'), 'body' => $ris, 'id' => 'fmt-ris'],
        ['key' => 'csl',    'label' => 'CSL-JSON','ext' => '.json', 'url' => $urls['json'],
         'hint' => __('citeproc, Zotero, pandoc'), 'body' => $csl, 'id' => 'fmt-csl'],
        ['key' => 'dc',     'label' => 'Dublin Core', 'ext' => '.dc.xml', 'url' => $urls['dc'],
         'hint' => __('OAI-DC / simple Dublin Core XML'), 'body' => $dc, 'id' => 'fmt-dc'],
      ];
    @endphp

    <div class="accordion" id="citeFormats">
      @foreach($formats as $i => $f)
        <div class="accordion-item">
          <h3 class="accordion-header" id="head-{{ $f['key'] }}">
            <button class="accordion-button {{ $i === 0 ? '' : 'collapsed' }}" type="button"
                    data-bs-toggle="collapse" data-bs-target="#body-{{ $f['key'] }}"
                    aria-expanded="{{ $i === 0 ? 'true' : 'false' }}" aria-controls="body-{{ $f['key'] }}">
              <span class="fw-semibold">{{ $f['label'] }}</span>
              <span class="text-muted small ms-2">{{ $f['ext'] }} &middot; {{ $f['hint'] }}</span>
            </button>
          </h3>
          <div id="body-{{ $f['key'] }}" class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}"
               aria-labelledby="head-{{ $f['key'] }}" data-bs-parent="#citeFormats">
            <div class="accordion-body">
              <div class="d-flex gap-2 mb-2">
                <a href="{{ $f['url'] }}" class="btn btn-sm btn-primary" download>
                  <i class="fas fa-download me-1"></i>{{ __('Download') }} {{ $f['ext'] }}
                </a>
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        data-copy-target="{{ $f['id'] }}">
                  <i class="far fa-copy me-1"></i>{{ __('Copy') }}
                </button>
              </div>
              <pre class="bg-light border rounded p-3 mb-0" style="white-space:pre-wrap;word-break:break-word"><code id="{{ $f['id'] }}">{{ $f['body'] }}</code></pre>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </section>

</div>

@push('js')
<script>
  // Copy-to-clipboard for the reference and each machine format. No dependency;
  // falls back to a textarea+execCommand on older / non-secure-context browsers.
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-copy-target]');
    if (!btn) return;
    var el = document.getElementById(btn.getAttribute('data-copy-target'));
    if (!el) return;
    var text = el.innerText;

    var done = function () {
      var original = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-check me-1"></i>{{ __('Copied') }}';
      setTimeout(function () { btn.innerHTML = original; }, 1500);
    };

    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done).catch(function () { fallback(text, done); });
    } else {
      fallback(text, done);
    }

    function fallback(t, cb) {
      var ta = document.createElement('textarea');
      ta.value = t;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand('copy'); cb(); } catch (e) {}
      document.body.removeChild(ta);
    }
  });
</script>
@endpush
@endsection
