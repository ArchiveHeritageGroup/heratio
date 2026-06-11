{{--
  Union catalogue - public cross-member search surface (#1203 first slice).

  Search across all opt-in participating institutions from one place,
  respecting each institution's sharing settings. Full-width public layout.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio (AGPL-3.0-or-later).
--}}
@extends('theme::layouts.1col')

@section('title', __('Union catalogue'))

@section('content')
<div class="container-fluid py-3">
    <div class="mb-4">
        <h4 class="mb-1">
            <i class="bi bi-diagram-3 me-2"></i>{{ __('Union catalogue') }}
        </h4>
        <p class="text-muted mb-0">
            {{ __('Search across participating institutions from one place. Each institution controls what it shares.') }}
        </p>
    </div>

    <form method="GET" action="{{ route('union.catalogue') }}" class="mb-4">
        <div class="input-group">
            <input type="text" name="q" value="{{ $q }}" class="form-control"
                   placeholder="{{ __('Search titles, repositories, dates...') }}"
                   aria-label="{{ __('Search the union catalogue') }}">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-search me-1"></i>{{ __('Search') }}
            </button>
            <a href="{{ route('union.catalogue.json', array_filter(['q' => $q])) }}"
               class="btn btn-outline-secondary" target="_blank" rel="noopener">
                <i class="bi bi-braces me-1"></i>{{ __('JSON') }}
            </a>
        </div>
    </form>

    @if ($result['memberCount'] === 0)
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-1"></i>
            {{ __('No participating collections yet. When institutions opt in, their shared records appear here.') }}
        </div>
    @elseif ($result['total'] === 0)
        <div class="alert alert-secondary">
            <i class="bi bi-search me-1"></i>
            {{ $q !== ''
                ? __('No matches for your search across the participating collections.')
                : __('No shared records are available yet.') }}
        </div>
    @else
        <p class="text-muted">
            {{ trans_choice('{1}:count result|[2,*]:count results', $result['total'], ['count' => number_format($result['total'])]) }}
            {{ __('across') }} {{ $result['memberCount'] }}
            {{ trans_choice('{1}participating institution|[2,*]participating institutions', $result['memberCount']) }}.
        </p>

        @foreach ($result['groups'] as $group)
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center">
                    <i class="bi bi-building me-2"></i>
                    <strong>{{ $group['member']->name }}</strong>
                    @if (! empty($group['member']->base_url))
                        <a href="{{ $group['member']->base_url }}" target="_blank" rel="noopener"
                           class="ms-2 small text-decoration-none">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                    @endif
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>{{ __('Title') }}</th>
                                <th>{{ __('Level') }}</th>
                                <th>{{ __('Dates') }}</th>
                                <th>{{ __('Repository') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($group['rows'] as $row)
                                <tr>
                                    <td>
                                        @if (! empty($row->url))
                                            <a href="{{ $row->url }}" target="_blank" rel="noopener">
                                                {{ $row->title ?: __('(untitled)') }}
                                            </a>
                                        @else
                                            {{ $row->title ?: __('(untitled)') }}
                                        @endif
                                    </td>
                                    <td class="small text-muted">{{ $row->level ?: '-' }}</td>
                                    <td class="small text-muted">{{ $row->dates ?: '-' }}</td>
                                    <td class="small text-muted">{{ $row->repository ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        @if ($result['lastPage'] > 1)
            <nav aria-label="{{ __('Union catalogue pages') }}">
                <ul class="pagination">
                    @php $prev = max(1, $result['page'] - 1); $next = min($result['lastPage'], $result['page'] + 1); @endphp
                    <li class="page-item {{ $result['page'] <= 1 ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ route('union.catalogue', array_filter(['q' => $q, 'page' => $prev])) }}">
                            {{ __('Previous') }}
                        </a>
                    </li>
                    <li class="page-item disabled">
                        <span class="page-link">
                            {{ __('Page') }} {{ $result['page'] }} / {{ $result['lastPage'] }}
                        </span>
                    </li>
                    <li class="page-item {{ $result['page'] >= $result['lastPage'] ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ route('union.catalogue', array_filter(['q' => $q, 'page' => $next])) }}">
                            {{ __('Next') }}
                        </a>
                    </li>
                </ul>
            </nav>
        @endif
    @endif
</div>
@endsection
