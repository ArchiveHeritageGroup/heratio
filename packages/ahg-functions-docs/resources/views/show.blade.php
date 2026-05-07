@extends('theme::layouts.1col')

@section('title', ($summary['label'] ?? ucfirst($kind)) . ' - ' . __('Function & Route Catalogues'))
@section('body-class', 'functions-docs show')

@section('content')
<div class="row">
  {{-- TOC sidebar --}}
  <div class="col-lg-3 col-md-4 mb-4">
    <div class="sticky-top" style="top: 1rem;">
      <a href="{{ route('functionsDocs.index') }}" class="d-block mb-3 small">
        <i class="bi bi-arrow-left me-1"></i>{{ __('All catalogues') }}
      </a>

      <h6 class="text-uppercase text-muted mb-2">{{ $summary['label'] ?? ucfirst($kind) }}</h6>
      <p class="small text-muted mb-3">
        <span class="badge bg-secondary">{{ number_format($summary['section_count']) }} {{ \Illuminate\Support\Str::plural($summary['group_label'], $summary['section_count']) }}</span>
        @if($rendered['filtered'] > 0 && $filter !== '')
          <span class="badge bg-warning text-dark">{{ number_format($rendered['filtered']) }} {{ __('matched') }}</span>
        @endif
      </p>

      <form method="get" action="{{ route('functionsDocs.show', ['kind' => $kind]) }}" class="mb-3">
        <div class="input-group input-group-sm">
          <input type="text" name="q" class="form-control" placeholder="{{ __('Filter by') }} {{ $summary['group_label'] }}..." value="{{ $filter }}" autocomplete="off">
          <button type="submit" class="btn atom-btn-white"><i class="bi bi-search"></i></button>
          @if($filter !== '')
            <a href="{{ route('functionsDocs.show', ['kind' => $kind]) }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
          @endif
        </div>
      </form>

      @if($summary['paginate'] && $rendered['pages'] > 1)
        <h6 class="text-uppercase text-muted mb-2 mt-3">{{ __('Pages') }}</h6>
        <p class="small text-muted mb-2">
          {{ __('Page') }} {{ $rendered['page'] }} {{ __('of') }} {{ $rendered['pages'] }}
        </p>
        <nav aria-label="{{ __('Page navigation') }}">
          <ul class="pagination pagination-sm flex-wrap">
            @php
              $totalPages = $rendered['pages'];
              $current    = $rendered['page'];
              $window     = 2;
              $start      = max(1, $current - $window);
              $end        = min($totalPages, $current + $window);
              $pageQuery  = function ($p) use ($kind, $filter) {
                $params = ['kind' => $kind, 'page' => $p];
                if ($filter !== '') $params['q'] = $filter;
                return route('functionsDocs.show', $params);
              };
            @endphp

            @if($current > 1)
              <li class="page-item"><a class="page-link" href="{{ $pageQuery(1) }}">&laquo;</a></li>
              <li class="page-item"><a class="page-link" href="{{ $pageQuery($current - 1) }}">&lsaquo;</a></li>
            @endif

            @if($start > 1)
              <li class="page-item disabled"><span class="page-link">...</span></li>
            @endif

            @for($p = $start; $p <= $end; $p++)
              <li class="page-item {{ $p === $current ? 'active' : '' }}">
                <a class="page-link" href="{{ $pageQuery($p) }}">{{ $p }}</a>
              </li>
            @endfor

            @if($end < $totalPages)
              <li class="page-item disabled"><span class="page-link">...</span></li>
            @endif

            @if($current < $totalPages)
              <li class="page-item"><a class="page-link" href="{{ $pageQuery($current + 1) }}">&rsaquo;</a></li>
              <li class="page-item"><a class="page-link" href="{{ $pageQuery($totalPages) }}">&raquo;</a></li>
            @endif
          </ul>
        </nav>
      @endif

      @if(!empty($rendered['toc']))
        <h6 class="text-uppercase text-muted mb-2 mt-3">{{ __('On this page') }}</h6>
        <nav style="max-height: 60vh; overflow-y: auto;">
          <ul class="nav flex-column small">
            @foreach($rendered['toc'] as $entry)
              <li class="nav-item">
                <a class="nav-link py-1" href="#{{ $entry['anchor'] }}" style="word-break: break-all;">
                  {{ $entry['title'] }}
                </a>
              </li>
            @endforeach
          </ul>
        </nav>
      @endif

      <div class="mt-3 pt-3 border-top small text-muted">
        <div class="mb-1"><i class="bi bi-file-earmark-code me-1"></i><code>{{ basename($summary['path']) }}</code></div>
        <div class="mb-1"><i class="bi bi-hdd me-1"></i>{{ number_format($summary['byte_size'] / 1024, 0) }} KB</div>
        <div><i class="bi bi-clock me-1"></i>{{ \Carbon\Carbon::createFromTimestamp($summary['mtime'])->diffForHumans() }}</div>
      </div>
    </div>
  </div>

  {{-- Main content --}}
  <div class="col-lg-9 col-md-8">
    <div class="d-flex align-items-center mb-3">
      <i class="bi {{ $summary['icon'] }} fs-2 me-2 text-primary"></i>
      <h1 class="h3 mb-0">{{ $summary['label'] }}</h1>
    </div>

    @if($rendered['intro_html'] !== '' && $rendered['page'] === 1 && $filter === '')
      <div class="card bg-light mb-4">
        <div class="card-body">
          <div class="markdown-body">{!! $rendered['intro_html'] !!}</div>
        </div>
      </div>
    @endif

    @if($filter !== '' && $rendered['filtered'] === 0)
      <div class="alert alert-warning">
        {{ __('No') }} {{ $summary['group_label'] }} {{ __('matches') }} <code>{{ $filter }}</code>.
      </div>
    @endif

    @foreach($rendered['sections'] as $section)
      <article id="{{ $section['anchor'] }}" class="card mb-3 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <h2 class="h5 mb-0" style="word-break: break-all;">{{ $section['title'] }}</h2>
          <a href="#{{ $section['anchor'] }}" class="text-muted small text-decoration-none" title="{{ __('Anchor link') }}">
            <i class="bi bi-link-45deg"></i>
          </a>
        </div>
        <div class="card-body">
          <div class="markdown-body">{!! $section['body_html'] !!}</div>
        </div>
      </article>
    @endforeach

    {{-- Bottom pagination mirror --}}
    @if($summary['paginate'] && $rendered['pages'] > 1)
      <nav aria-label="{{ __('Page navigation') }}" class="mt-4">
        <ul class="pagination justify-content-center flex-wrap">
          @php
            $totalPages = $rendered['pages'];
            $current    = $rendered['page'];
            $pageQuery  = function ($p) use ($kind, $filter) {
              $params = ['kind' => $kind, 'page' => $p];
              if ($filter !== '') $params['q'] = $filter;
              return route('functionsDocs.show', $params);
            };
          @endphp
          @if($current > 1)
            <li class="page-item"><a class="page-link" href="{{ $pageQuery($current - 1) }}">&lsaquo; {{ __('Previous') }}</a></li>
          @endif
          <li class="page-item disabled"><span class="page-link">{{ __('Page') }} {{ $current }} / {{ $totalPages }}</span></li>
          @if($current < $totalPages)
            <li class="page-item"><a class="page-link" href="{{ $pageQuery($current + 1) }}">{{ __('Next') }} &rsaquo;</a></li>
          @endif
        </ul>
      </nav>
    @endif
  </div>
</div>

@push('head')
  @if($rendered['page'] > 1)
    <link rel="prev" href="{{ route('functionsDocs.show', ['kind' => $kind, 'page' => $rendered['page'] - 1] + ($filter !== '' ? ['q' => $filter] : [])) }}">
  @endif
  @if($rendered['page'] < $rendered['pages'])
    <link rel="next" href="{{ route('functionsDocs.show', ['kind' => $kind, 'page' => $rendered['page'] + 1] + ($filter !== '' ? ['q' => $filter] : [])) }}">
  @endif
@endpush

<style>
  .functions-docs.show .markdown-body code {
    background: rgba(0, 0, 0, 0.06);
    padding: 0.1em 0.35em;
    border-radius: 3px;
    font-size: 0.875em;
  }
  .functions-docs.show .markdown-body pre {
    background: #f7f7f9;
    padding: 0.75rem;
    border-radius: 4px;
    overflow-x: auto;
  }
  .functions-docs.show .markdown-body h2 { font-size: 1.15rem; margin-top: 1rem; }
  .functions-docs.show .markdown-body h3 { font-size: 1rem; margin-top: 0.75rem; }
  .functions-docs.show .markdown-body p { margin-bottom: 0.5rem; }
  .functions-docs.show .markdown-body ul,
  .functions-docs.show .markdown-body ol { margin-bottom: 0.5rem; }
</style>
@endsection
