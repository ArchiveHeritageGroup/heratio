{{--
  One theme - public discovery detail (heratio#1210)

  One subject term: its label, optional scope note, the total published-record
  count, and a paginated, bounded list of the published records filed under it.
  Each record links straight to its show page; a "browse all in this theme" link
  drops into the canonical GLAM browse with the subject facet applied. Read-only;
  published records only; empty list never 500s. International, jurisdiction-
  neutral copy.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', __('Theme').': '.($theme['label'] ?? __('Theme')))

@section('content')
@php
    $label = $theme['label'] ?? __('Theme');
    $total = (int) ($theme['total'] ?? 0);
    $records = $theme['records'] ?? [];
    $page = (int) ($theme['page'] ?? 1);
    $lastPage = (int) ($theme['last_page'] ?? 1);
    $termId = (int) ($theme['term_id'] ?? 0);
    $browseUrl = $theme['browse_url'] ?? url('/glam/browse?subject='.$termId);
@endphp
<div class="container-fluid py-4">

    {{-- Breadcrumb back to the themes landing --}}
    <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('themes.index') }}" class="text-decoration-none">
                    <i class="fas fa-shapes me-1"></i>{{ __('Explore by theme') }}
                </a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">{{ $label }}</li>
        </ol>
    </nav>

    {{-- Hero --}}
    <div class="p-4 p-md-5 mb-4 rounded-3 bg-dark text-white">
        <div class="text-uppercase small text-white-50 fw-semibold mb-1">
            <i class="fas fa-tag me-1"></i>{{ __('Theme') }}
        </div>
        <h1 class="h2 mb-2">{{ $label }}</h1>
        <p class="lead mb-2">
            {{ trans_choice('{1}:count published record under this theme.|[2,*]:count published records under this theme.', $total, ['count' => number_format($total)]) }}
        </p>
        @if(!empty($theme['note']))
            <p class="mb-0 text-white-50">{{ \Illuminate\Support\Str::limit($theme['note'], 400) }}</p>
        @endif
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <p class="text-muted small mb-0">
            <i class="fas fa-circle-info me-1"></i>
            @if($lastPage > 1)
                {{ __('Showing page :page of :last.', ['page' => $page, 'last' => $lastPage]) }}
            @else
                {{ __('Showing all records in this theme.') }}
            @endif
        </p>
        <a href="{{ $browseUrl }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-up-right-from-square me-1"></i>{{ __('Browse all in this theme') }}
        </a>
    </div>

    @if(empty($records))
        {{-- Empty-state (a published theme can still be empty on a far page) --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                <h2 class="h4">{{ __('No records to show on this page') }}</h2>
                <p class="text-muted mb-3">{{ __('There are no published records to show here.') }}</p>
                <a href="{{ route('themes.show', ['termId' => $termId]) }}" class="btn btn-outline-primary btn-sm">
                    {{ __('Back to the first page') }}
                </a>
            </div>
        </div>
    @else
        <div class="list-group shadow-sm mb-4">
            @foreach($records as $rec)
                <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
                    <div class="text-truncate me-2">
                        @if(!empty($rec['slug']))
                            <a href="{{ url('/'.$rec['slug']) }}" class="text-decoration-none">{{ $rec['title'] }}</a>
                        @else
                            <span>{{ $rec['title'] }}</span>
                        @endif
                    </div>
                    @if(!empty($rec['slug']))
                        <a href="{{ url('/'.$rec['slug']) }}" class="btn btn-sm btn-outline-secondary flex-shrink-0">
                            {{ __('Open') }} <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Bounded pagination - only renders when there is more than one page --}}
        @if($lastPage > 1)
            @php
                $windowStart = max(1, $page - 2);
                $windowEnd = min($lastPage, $page + 2);
            @endphp
            <nav aria-label="{{ __('Theme record pages') }}">
                <ul class="pagination justify-content-center flex-wrap">
                    <li class="page-item {{ $page <= 1 ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ $page <= 1 ? '#' : route('themes.show', ['termId' => $termId, 'page' => $page - 1]) }}" rel="prev">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    @if($windowStart > 1)
                        <li class="page-item"><a class="page-link" href="{{ route('themes.show', ['termId' => $termId, 'page' => 1]) }}">1</a></li>
                        @if($windowStart > 2)
                            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                        @endif
                    @endif
                    @for($p = $windowStart; $p <= $windowEnd; $p++)
                        <li class="page-item {{ $p === $page ? 'active' : '' }}">
                            <a class="page-link" href="{{ route('themes.show', ['termId' => $termId, 'page' => $p]) }}">{{ $p }}</a>
                        </li>
                    @endfor
                    @if($windowEnd < $lastPage)
                        @if($windowEnd < $lastPage - 1)
                            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                        @endif
                        <li class="page-item"><a class="page-link" href="{{ route('themes.show', ['termId' => $termId, 'page' => $lastPage]) }}">{{ $lastPage }}</a></li>
                    @endif
                    <li class="page-item {{ $page >= $lastPage ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ $page >= $lastPage ? '#' : route('themes.show', ['termId' => $termId, 'page' => $page + 1]) }}" rel="next">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        @endif

        <p class="text-muted small mt-2 mb-0">
            <i class="fas fa-circle-info me-1"></i>
            {{ __('Only published records appear under a theme. Counts and listings update automatically as the collection grows.') }}
        </p>
    @endif

</div>
@endsection
