{{--
  Language-revival corpus - PUBLIC transcription / correction / translation
  contribution form for one item (north-star heratio#1208: "a culture you can
  talk to").

  A community member viewing a PUBLISHED item in a heritage language can read its
  in-language text and lodge a transcription, a correction, a translation or a
  note. The contribution lands for moderation, not straight to public. Already-
  approved community contributions on this item are shown above the form.
  Respectful, jurisdiction-neutral framing; the contributor is credited only on
  consent. Empty-states throughout; never 500.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', __('Contribute a transcription'))

@section('content')
<div class="container-fluid py-4">

    {{-- Breadcrumb back to the language-revival directory --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item"><a href="{{ route('language-corpus.index') }}">{{ __('Language revival') }}</a></li>
            @if($context && !empty($context['culture']))
                <li class="breadcrumb-item">
                    <a href="{{ route('language-corpus.show', ['culture' => $context['culture']]) }}">{{ $context['culture_label'] ?? $context['culture'] }}</a>
                </li>
            @endif
            <li class="breadcrumb-item active" aria-current="page">{{ __('Contribute') }}</li>
        </ol>
    </nav>

    @if(session('success'))
        <div class="alert alert-success" role="alert">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    @if($context === null)
        {{-- Item not resolvable / not published --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-circle-question fa-3x text-muted mb-3"></i>
                <h1 class="h4">{{ __('This item is not available for contributions') }}</h1>
                <p class="text-muted mb-3 mx-auto" style="max-width: 42rem;">
                    {{ __('The item could not be found, or it is not published. Contributions can only be made on published items in the collection.') }}
                </p>
                <a href="{{ route('language-corpus.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>{{ __('Back to language revival') }}
                </a>
            </div>
        </div>
    @else
        {{-- Hero / item context --}}
        <div class="p-4 p-md-5 mb-4 rounded-3 bg-dark text-white">
            <div class="d-flex align-items-center flex-wrap mb-2">
                <i class="fas fa-feather-pointed fa-lg me-3"></i>
                <h1 class="h3 mb-0">{{ __('Help transcribe this item') }}</h1>
                @if(!empty($context['culture']))
                    <span class="badge text-bg-light text-dark text-uppercase ms-3">{{ $context['culture'] }}</span>
                @endif
            </div>
            <p class="mb-0 text-white-50">
                @if(!empty($context['slug']))
                    <a href="{{ url('/'.$context['slug']) }}" class="link-light">{{ $context['title'] }}</a>
                @else
                    {{ $context['title'] }}
                @endif
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

        <div class="row g-4">
            {{-- The item's in-language text, read-only, so the contributor has the source --}}
            <div class="col-12 col-lg-5">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white">
                        <i class="fas fa-file-lines me-1 text-muted"></i>{{ __('The item') }}
                        @if(!empty($context['culture_label']))
                            <span class="text-muted small">- {{ $context['culture_label'] }}</span>
                        @endif
                    </div>
                    <div class="card-body">
                        @if(!empty($context['text']))
                            <p class="small mb-0" style="white-space: pre-line;">{{ $context['text'] }}</p>
                        @else
                            <p class="text-muted small mb-0">{{ __('This item has no text on record yet. Your transcription will be the first.') }}</p>
                        @endif
                    </div>
                    @if(!empty($context['slug']))
                        <div class="card-footer bg-white">
                            <a href="{{ url('/'.$context['slug']) }}" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-up-right-from-square me-1"></i>{{ __('Open the full record') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- The contribution form --}}
            <div class="col-12 col-lg-7">
                @if(!$available)
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-database fa-3x text-muted mb-3"></i>
                            <h2 class="h5">{{ __('Contributions are not available right now') }}</h2>
                            <p class="text-muted mb-0">{{ __('The contributions store has not been installed yet. It is created automatically on the next application boot.') }}</p>
                        </div>
                    </div>
                @else
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <i class="fas fa-plus me-1"></i>{{ __('Add a contribution') }}
                        </div>
                        <div class="card-body">
                            <p class="small text-muted">
                                {{ __('Add a transcription, a correction, a translation or a note. Contributions are reviewed before they appear, so the record stays trustworthy.') }}
                            </p>
                            <form method="POST" action="{{ route('language-transcribe.contribute', ['item' => $itemId]) }}">
                                @csrf
                                <div class="mb-3">
                                    <label for="ltc-type" class="form-label small">{{ __('Type of contribution') }} <span class="text-danger">*</span></label>
                                    <select class="form-select @error('contribution_type') is-invalid @enderror" id="ltc-type" name="contribution_type" required>
                                        @foreach($types as $key => $meta)
                                            <option value="{{ $key }}" {{ old('contribution_type', 'transcription') === $key ? 'selected' : '' }}>
                                                {{ __($meta['label']) }} - {{ __($meta['blurb']) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('contribution_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="mb-3">
                                    <label for="ltc-body" class="form-label small">{{ __('Your text') }} <span class="text-danger">*</span></label>
                                    <textarea class="form-control @error('body') is-invalid @enderror" id="ltc-body" name="body" rows="8" maxlength="60000" required placeholder="{{ __('Type the transcription, correction, translation or note here...') }}">{{ old('body') }}</textarea>
                                    @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label for="ltc-source" class="form-label small">{{ __('Source or attribution (optional)') }}</label>
                                        <input type="text" class="form-control" id="ltc-source" name="source" maxlength="512" value="{{ old('source') }}">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label for="ltc-name" class="form-label small">{{ __('Your name (optional)') }}</label>
                                        <input type="text" class="form-control" id="ltc-name" name="contributor_name" maxlength="255" value="{{ old('contributor_name') }}">
                                    </div>
                                </div>

                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="checkbox" value="1" id="ltc-consent" name="credit_consent" {{ old('credit_consent') ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="ltc-consent">
                                        {{ __('Credit me by name for this contribution. If unticked, your contribution appears anonymously.') }}
                                    </label>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-paper-plane me-1"></i>{{ __('Submit for review') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Already-approved community contributions on this item --}}
        <section class="mt-5">
            <h2 class="h4 border-bottom pb-2 mb-3">
                <i class="fas fa-users me-2 text-muted"></i>{{ __('Community contributions') }}
            </h2>
            @if(empty($approved))
                <p class="text-muted small mb-0">{{ __('No approved contributions on this item yet. Be the first to contribute above.') }}</p>
            @else
                <div class="row g-3">
                    @foreach($approved as $c)
                        <div class="col-12 col-lg-6">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                    <span class="small fw-semibold">
                                        <i class="fas {{ $c['type_meta']['icon'] ?? 'fa-comment' }} me-1 text-muted"></i>{{ __($c['type_meta']['label'] ?? $c['contribution_type']) }}
                                    </span>
                                    <span class="badge text-bg-light border text-uppercase">{{ $c['culture'] }}</span>
                                </div>
                                <div class="card-body">
                                    <p class="small mb-2" style="white-space: pre-line;">{{ \Illuminate\Support\Str::limit($c['body'], 600) }}</p>
                                    @if(!empty($c['source']))
                                        <p class="small text-muted mb-1"><i class="fas fa-book-open me-1"></i>{{ $c['source'] }}</p>
                                    @endif
                                    <p class="small text-muted mb-0">
                                        <i class="fas fa-user me-1"></i>{{ $c['contributor_name'] ?: __('Anonymous contributor') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

</div>
@endsection
