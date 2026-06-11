{{--
  Language-revival corpus - PUBLIC directory (north-star heratio#1208:
  "a culture you can talk to").

  A dignified, read-only directory of every language the collection holds, with
  how much of the collection is described in each and how many terms carry a label
  in it. Each language links to its own revival page. Respectful, jurisdiction-
  neutral framing: heritage and endangered languages are living and owned by their
  communities. Empty-state when the catalogue holds nothing yet. International,
  Afrikaans first-class.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', __('Language revival'))

@section('content')
<div class="container-fluid py-4">

    {{-- Hero --}}
    <div class="p-4 p-md-5 mb-4 rounded-3 bg-dark text-white">
        <div class="d-flex align-items-center mb-2">
            <i class="fas fa-language fa-lg me-3"></i>
            <h1 class="h2 mb-0">{{ __('Language revival') }}</h1>
        </div>
        <p class="lead mb-1">
            {{ __('The collection as a living resource for heritage and endangered languages.') }}
        </p>
        <p class="mb-0 text-white-50">
            {{ __('Every language the collection holds is gathered here. For each one you can explore the records described in it, the place-names and subject terms that carry a label in it, any transcriptions, and a community glossary - a resource for speakers, learners and researchers.') }}
        </p>
    </div>

    {{-- Standing, respectful framing --}}
    <div class="alert alert-info d-flex align-items-start mb-4" role="alert">
        <i class="fas fa-circle-info fa-lg me-3 mt-1"></i>
        <div>
            <strong>{{ __('Heritage languages are living and belong to their communities.') }}</strong>
            <p class="mb-0 small">{{ $disclaimer }}</p>
        </div>
    </div>

    @if(empty($languages))
        {{-- Empty-state --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                <h2 class="h4">{{ __('No languages to show yet') }}</h2>
                <p class="text-muted mb-0 mx-auto" style="max-width: 42rem;">
                    {{ __('When the catalogue holds published records described in a language, or terms that carry a label in it, that language will appear here as a revival resource.') }}
                </p>
            </div>
        </div>
    @else
        <p class="text-muted small mb-3">
            <i class="fas fa-layer-group me-1"></i>
            {{ trans_choice('{1}:count language in the collection.|[2,*]:count languages in the collection.', count($languages), ['count' => count($languages)]) }}
        </p>

        <div class="row g-4">
            @foreach($languages as $lang)
                <div class="col-12 col-md-6 col-lg-4">
                    <a href="{{ route('language-corpus.show', ['culture' => $lang['code']]) }}"
                       class="card h-100 shadow-sm text-decoration-none text-reset">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h2 class="h5 mb-0">{{ $lang['label'] }}</h2>
                                <span class="badge text-bg-light border text-uppercase">{{ $lang['code'] }}</span>
                            </div>
                            <div class="d-flex flex-wrap gap-3 small text-muted">
                                <span>
                                    <i class="fas fa-folder-open me-1"></i>
                                    {{ trans_choice('{1}:count record|[2,*]:count records', $lang['records'], ['count' => $lang['records']]) }}
                                </span>
                                <span>
                                    <i class="fas fa-tags me-1"></i>
                                    {{ trans_choice('{1}:count term|[2,*]:count terms', $lang['terms'], ['count' => $lang['terms']]) }}
                                </span>
                            </div>
                        </div>
                        <div class="card-footer bg-white d-flex justify-content-end">
                            <span class="small text-primary">
                                {{ __('Explore') }} <i class="fas fa-arrow-right ms-1"></i>
                            </span>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection
