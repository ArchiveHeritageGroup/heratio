{{--
  Language-revival corpus - PUBLIC per-language page (north-star heratio#1208:
  "a culture you can talk to").

  For one chosen language / culture, this READ-ONLY page surfaces what the
  collection holds in or about it: published records described in it, place-names
  and subject terms that carry a label in it, any transcriptions (full-text in the
  language), and the approved community glossary. A public contribution form lets
  speakers add a glossary term (which lands for moderation, not straight to
  public). Each transcription offers an OPTIONAL on-demand machine translation via
  the AHG gateway, clearly labelled as unofficial. Respectful, jurisdiction-neutral
  framing. Empty-states throughout; never 500.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', __('Language revival') . ' - ' . $label)

@section('content')
<div class="container-fluid py-4">

    {{-- Breadcrumb back to the directory --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item"><a href="{{ route('language-corpus.index') }}">{{ __('Language revival') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $label }}</li>
        </ol>
    </nav>

    {{-- Hero --}}
    <div class="p-4 p-md-5 mb-4 rounded-3 bg-dark text-white">
        <div class="d-flex align-items-center mb-2">
            <i class="fas fa-language fa-lg me-3"></i>
            <h1 class="h2 mb-0">{{ $label }}</h1>
            <span class="badge text-bg-light text-dark text-uppercase ms-3">{{ $culture }}</span>
        </div>
        <p class="mb-0 text-white-50">
            {{ __('What the collection holds in and about this language - a resource for speakers, learners and researchers.') }}
        </p>
    </div>

    {{-- Standing, respectful framing --}}
    <div class="alert alert-info d-flex align-items-start mb-4" role="alert">
        <i class="fas fa-circle-info fa-lg me-3 mt-1"></i>
        <div>
            <strong>{{ __('This language is living and belongs to its community of speakers.') }}</strong>
            <p class="mb-0 small">{{ $disclaimer }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success" role="alert">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    @unless($hasAnything)
        {{-- Whole-page empty-state --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body text-center py-5">
                <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                <h2 class="h4">{{ __('Nothing published in this language yet') }}</h2>
                <p class="text-muted mb-3 mx-auto" style="max-width: 42rem;">
                    {{ __('The collection does not yet hold published records described in this language, or terms that carry a label in it. You can still help build the community glossary below.') }}
                </p>
                <a href="{{ route('language-corpus.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>{{ __('Back to all languages') }}
                </a>
            </div>
        </div>
    @endunless

    {{-- Records described in this language --}}
    <section class="mb-5">
        <h2 class="h4 border-bottom pb-2 mb-3">
            <i class="fas fa-folder-open me-2 text-muted"></i>{{ __('Records described in :lang', ['lang' => $label]) }}
        </h2>
        @if(empty($records))
            <p class="text-muted small mb-0">{{ __('No published records are described in this language yet.') }}</p>
        @else
            <div class="row g-3">
                @foreach($records as $r)
                    <div class="col-12 col-lg-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <h3 class="h6 mb-1">
                                    @if(!empty($r['slug']))
                                        <a href="{{ url('/'.$r['slug']) }}" class="text-decoration-none">{{ $r['title'] }}</a>
                                    @else
                                        {{ $r['title'] }}
                                    @endif
                                </h3>
                                @if(!empty($r['snippet']))
                                    <p class="small text-muted mb-0">{{ $r['snippet'] }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Place-names + subject terms (word-list) --}}
    <section class="mb-5">
        <h2 class="h4 border-bottom pb-2 mb-3">
            <i class="fas fa-tags me-2 text-muted"></i>{{ __('Words from the collection') }}
        </h2>
        <p class="small text-muted mb-3">
            {{ __('Place-names and subject terms drawn from the catalogue that carry a label in this language - a starting word-list, not a definitive vocabulary.') }}
        </p>
        <div class="row g-4">
            <div class="col-12 col-md-6">
                <h3 class="h6 text-uppercase text-muted"><i class="fas fa-map-location-dot me-1"></i>{{ __('Place-names') }}</h3>
                @if(empty($places))
                    <p class="text-muted small mb-0">{{ __('No place-names carry a label in this language yet.') }}</p>
                @else
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($places as $p)
                            @if(!empty($p['slug']))
                                <a href="{{ url('/'.$p['slug']) }}" class="badge rounded-pill text-bg-light border text-decoration-none">{{ $p['name'] }}</a>
                            @else
                                <span class="badge rounded-pill text-bg-light border">{{ $p['name'] }}</span>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="col-12 col-md-6">
                <h3 class="h6 text-uppercase text-muted"><i class="fas fa-hashtag me-1"></i>{{ __('Subject terms') }}</h3>
                @if(empty($subjects))
                    <p class="text-muted small mb-0">{{ __('No subject terms carry a label in this language yet.') }}</p>
                @else
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($subjects as $s)
                            @if(!empty($s['slug']))
                                <a href="{{ url('/'.$s['slug']) }}" class="badge rounded-pill text-bg-light border text-decoration-none">{{ $s['name'] }}</a>
                            @else
                                <span class="badge rounded-pill text-bg-light border">{{ $s['name'] }}</span>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Transcriptions / full-text, with optional gateway translation --}}
    <section class="mb-5">
        <h2 class="h4 border-bottom pb-2 mb-3">
            <i class="fas fa-file-lines me-2 text-muted"></i>{{ __('Transcriptions and full text') }}
        </h2>
        @if(empty($transcriptions))
            <p class="text-muted small mb-0">{{ __('No full-text passages in this language are published yet.') }}</p>
        @else
            <p class="small text-muted mb-3">
                <i class="fas fa-circle-info me-1"></i>{{ $mtLabel }}
            </p>
            @foreach($transcriptions as $i => $t)
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center gap-2">
                        <h3 class="h6 mb-0">
                            @if(!empty($t['slug']))
                                <a href="{{ url('/'.$t['slug']) }}" class="text-decoration-none">{{ $t['title'] }}</a>
                            @else
                                {{ $t['title'] }}
                            @endif
                        </h3>
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary js-lc-translate"
                                data-text="{{ $i }}"
                                data-target="en">
                            <i class="fas fa-wand-magic-sparkles me-1"></i>{{ __('Translate to English (machine)') }}
                        </button>
                    </div>
                    <div class="card-body">
                        <p class="small mb-0" id="lc-src-{{ $i }}" style="white-space: pre-line;">{{ $t['text'] }}</p>
                        <div class="mt-3 border-top pt-3 d-none" id="lc-out-wrap-{{ $i }}">
                            <div class="text-uppercase text-muted small fw-semibold mb-1">
                                <i class="fas fa-language me-1"></i>{{ __('Machine translation') }}
                            </div>
                            <p class="small mb-1" id="lc-out-{{ $i }}"></p>
                            <p class="small text-muted fst-italic mb-0" id="lc-label-{{ $i }}">{{ $mtLabel }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </section>

    {{-- Community glossary --}}
    <section class="mb-5">
        <h2 class="h4 border-bottom pb-2 mb-3">
            <i class="fas fa-book me-2 text-muted"></i>{{ __('Community glossary') }}
        </h2>
        <p class="small text-muted mb-3">
            {{ __('Words and meanings contributed by the community and reviewed before they appear. A shared starting point, not a definitive dictionary.') }}
        </p>
        @if(empty($glossary))
            <p class="text-muted small">{{ __('No approved glossary entries yet. Be the first to contribute below.') }}</p>
        @else
            <div class="row g-3 mb-3">
                @foreach($glossary as $g)
                    <div class="col-12 col-md-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <h3 class="h6 mb-1">{{ $g['term'] }}</h3>
                                <p class="small mb-2">{{ $g['meaning'] }}</p>
                                @if(!empty($g['usage_example']))
                                    <p class="small text-muted fst-italic mb-1">
                                        <i class="fas fa-quote-left me-1"></i>{{ $g['usage_example'] }}
                                    </p>
                                @endif
                                @if(!empty($g['source']))
                                    <p class="small text-muted mb-0"><i class="fas fa-book-open me-1"></i>{{ $g['source'] }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Contribution form --}}
        @if($glossaryAvailable)
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <i class="fas fa-plus me-1"></i>{{ __('Contribute a word') }}
                </div>
                <div class="card-body">
                    <p class="small text-muted">
                        {{ __('Add a word in :lang and its meaning. Contributions are reviewed before they appear, so the glossary stays trustworthy.', ['lang' => $label]) }}
                    </p>
                    <form method="POST" action="{{ route('language-corpus.contribute', ['culture' => $culture]) }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="lc-term" class="form-label small">{{ __('Word or term') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('term') is-invalid @enderror" id="lc-term" name="term" maxlength="512" value="{{ old('term') }}" required>
                                @error('term')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="lc-contrib" class="form-label small">{{ __('Your name (optional)') }}</label>
                                <input type="text" class="form-control" id="lc-contrib" name="contributor_name" maxlength="255" value="{{ old('contributor_name') }}">
                            </div>
                            <div class="col-12">
                                <label for="lc-meaning" class="form-label small">{{ __('Meaning') }} <span class="text-danger">*</span></label>
                                <textarea class="form-control @error('meaning') is-invalid @enderror" id="lc-meaning" name="meaning" rows="2" maxlength="20000" required>{{ old('meaning') }}</textarea>
                                @error('meaning')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="lc-usage" class="form-label small">{{ __('Example of use (optional)') }}</label>
                                <textarea class="form-control" id="lc-usage" name="usage_example" rows="2" maxlength="20000">{{ old('usage_example') }}</textarea>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="lc-source" class="form-label small">{{ __('Source or attribution (optional)') }}</label>
                                <input type="text" class="form-control" id="lc-source" name="source" maxlength="512" value="{{ old('source') }}">
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-paper-plane me-1"></i>{{ __('Submit for review') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @else
            <p class="text-muted small">{{ __('The community glossary is not available right now.') }}</p>
        @endif
    </section>

</div>

{{-- Inline, dependency-free translate widget. POSTs the source text to the
     gateway-backed endpoint and renders the labelled machine translation. Soft:
     any failure shows an "unavailable" note and leaves the original intact. --}}
<script>
(function () {
    var endpoint = @json(route('language-corpus.translate', ['culture' => $culture]));
    var token = document.querySelector('meta[name="csrf-token"]');
    token = token ? token.getAttribute('content') : '';

    document.querySelectorAll('.js-lc-translate').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var i = btn.getAttribute('data-text');
            var target = btn.getAttribute('data-target') || 'en';
            var src = document.getElementById('lc-src-' + i);
            var wrap = document.getElementById('lc-out-wrap-' + i);
            var out = document.getElementById('lc-out-' + i);
            var label = document.getElementById('lc-label-' + i);
            if (!src || !wrap || !out) { return; }

            btn.disabled = true;
            var original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>' + @json(__('Translating...'));

            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify({ text: src.textContent, target: target })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                wrap.classList.remove('d-none');
                if (data && data.ok) {
                    out.textContent = data.translation || '';
                    if (label && data.label) { label.textContent = data.label; }
                } else {
                    out.textContent = (data && data.message) ? data.message : @json(__('Machine translation is not available right now.'));
                }
            })
            .catch(function () {
                wrap.classList.remove('d-none');
                out.textContent = @json(__('Machine translation is not available right now.'));
            })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = original;
            });
        });
    });
})();
</script>
@endsection
