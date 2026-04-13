{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plansailingisystems

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'Semantic Search - Terms')

@section('content')
@php
    $terms = $terms ?? [];
    $sources = $sources ?? [];
    $currentPage = $currentPage ?? 1;
    $perPage = $perPage ?? 25;
    $totalCount = $totalCount ?? (is_countable($terms) ? count($terms) : 0);
    $totalPages = $totalPages ?? max(1, (int) ceil($totalCount / max(1, $perPage)));
    $q = request()->get('q', '');
    $sourceParam = request()->get('source', '');
@endphp

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">
            <a href="{{ route('semantic-search.index') }}" class="text-decoration-none text-muted">
                <i class="fas fa-brain me-2"></i>Semantic Search
            </a>
            <i class="fas fa-chevron-right mx-2 small text-muted"></i>
            Terms
        </h1>
        <a href="{{ route('semantic-search.term.add') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i>Add Term
        </a>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="q" value="{{ $q }}" placeholder="Search terms...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Source</label>
                    <select class="form-select" name="source">
                        <option value="">All Sources</option>
                        @foreach($sources as $source)
                        <option value="{{ $source->source ?? '' }}" {{ $sourceParam === ($source->source ?? '') ? 'selected' : '' }}>
                            {{ ucfirst($source->source ?? '') }} ({{ number_format($source->count ?? 0) }})
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-secondary w-100">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Terms Table --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>
                Showing {{ number_format(($currentPage - 1) * $perPage + 1) }}
                - {{ number_format(min($currentPage * $perPage, $totalCount)) }}
                of {{ number_format($totalCount) }} terms
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Term</th>
                            <th>Source</th>
                            <th>Domain</th>
                            <th>Synonyms</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @if($terms && (is_countable($terms) ? count($terms) : 0) > 0)
                            @foreach($terms as $term)
                            <tr>
                                <td><strong>{{ $term->term ?? '' }}</strong></td>
                                <td>
                                    @php
                                        $src = $term->source ?? '';
                                        $badgeColor = $src === 'local' ? 'secondary' : ($src === 'wordnet' ? 'info' : 'dark');
                                    @endphp
                                    <span class="badge bg-{{ $badgeColor }}">
                                        {{ ucfirst($src) }}
                                    </span>
                                </td>
                                <td><span class="text-muted">{{ $term->domain ?? '-' }}</span></td>
                                <td>
                                    <span class="badge bg-success">{{ $term->synonym_count ?? 0 }}</span>
                                </td>
                                <td class="small text-muted">
                                    {{ !empty($term->created_at) ? \Carbon\Carbon::parse($term->created_at)->format('M j, Y') : '-' }}
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('semantic-search.term.view', ['id' => $term->id ?? 0]) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                    No terms found
                                    <br>
                                    <a href="{{ route('semantic-search.term.add') }}" class="btn btn-primary mt-3">
                                        <i class="fas fa-plus me-1"></i>Add your first term
                                    </a>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
        @if($totalPages > 1)
        <div class="card-footer">
            <nav aria-label="Term pagination">
                <ul class="pagination justify-content-center mb-0">
                    @php
                        $baseParams = [];
                        if ($sourceParam) { $baseParams['source'] = $sourceParam; }
                        if ($q) { $baseParams['q'] = $q; }

                        $showPages = [1];
                        if ($currentPage > 3) { $showPages[] = '...'; }
                        for ($i = max(2, $currentPage - 1); $i <= min($totalPages - 1, $currentPage + 1); $i++) {
                            if (!in_array($i, $showPages)) { $showPages[] = $i; }
                        }
                        if ($currentPage < $totalPages - 2) { $showPages[] = '...'; }
                        if ($totalPages > 1 && !in_array($totalPages, $showPages)) { $showPages[] = $totalPages; }
                    @endphp

                    <li class="page-item {{ $currentPage <= 1 ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ route('semantic-search.terms', array_merge($baseParams, ['page' => $currentPage - 1])) }}">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>

                    @foreach($showPages as $p)
                        @if($p === '...')
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        @else
                            <li class="page-item {{ $p == $currentPage ? 'active' : '' }}">
                                <a class="page-link" href="{{ route('semantic-search.terms', array_merge($baseParams, ['page' => $p])) }}">{{ $p }}</a>
                            </li>
                        @endif
                    @endforeach

                    <li class="page-item {{ $currentPage >= $totalPages ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ route('semantic-search.terms', array_merge($baseParams, ['page' => $currentPage + 1])) }}">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        @endif
    </div>
</div>
@endsection
