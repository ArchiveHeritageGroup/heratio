@extends('theme::layouts.1col')
@section('title', 'Search Results')
@section('body-class', 'browse')
@section('content')
@php
$requestParams = request()->all();
$params = array_merge(['query' => '*', 'sort' => '_score'], $requestParams);
$layout = $params['layout'] ?? 'card';
@endphp

<div class="container-fluid">
    <div class="row">
        <!-- Facets Sidebar -->
        <div class="col-lg-3 col-md-4">
            <div class="facets-sidebar sticky-top" style="top: 20px;">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>{{ __('Filter Results') }}</h5>
                    </div>
                    <div class="card-body">
                        <!-- Search within results -->
                        <form method="get" class="mb-4">
                            <div class="input-group">
                                <input type="text" name="query" class="form-control"
                                       placeholder="{{ __('Search...') }}"
                                       value="{{ e($params['query'] !== '*' ? $params['query'] : '') }}">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>

                        @if(isset($adapter) && isset($results['aggregations']))
                        {!! $adapter->renderFacets($results['aggregations'] ?? []) !!}
                        @endif

                        @if(!empty($params['object_type']) || !empty($params['media_type']))
                        <div class="mt-3">
                            <a href="{{ route('displaySearch.search') }}" class="btn btn-outline-secondary btn-sm w-100">
                                <i class="fas fa-times me-1"></i>{{ __('Clear All Filters') }}
                            </a>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Results -->
        <div class="col-lg-9 col-md-8">
            <!-- Results Header -->
            <div class="results-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0">
                        @if($params['query'] !== '*')
                        Search: "{{ e($params['query']) }}"
                        @else
                        Browse All
                        @endif
                    </h4>
                    <small class="text-muted">{{ number_format($results['total'] ?? 0) }} results</small>
                </div>

                <div class="d-flex gap-2">
                    <!-- Layout Switcher -->
                    <div class="btn-group btn-group-sm">
                        <a href="?{{ http_build_query(array_merge($requestParams, ['layout' => 'card'])) }}"
                           class="btn btn-{{ $layout === 'card' ? 'primary' : 'outline-secondary' }}" title="{{ __('Cards') }}">
                            <i class="fas fa-th-large"></i>
                        </a>
                        <a href="?{{ http_build_query(array_merge($requestParams, ['layout' => 'grid'])) }}"
                           class="btn btn-{{ $layout === 'grid' ? 'primary' : 'outline-secondary' }}" title="{{ __('Grid') }}">
                            <i class="fas fa-th"></i>
                        </a>
                        <a href="?{{ http_build_query(array_merge($requestParams, ['layout' => 'list'])) }}"
                           class="btn btn-{{ $layout === 'list' ? 'primary' : 'outline-secondary' }}" title="{{ __('List') }}">
                            <i class="fas fa-list"></i>
                        </a>
                    </div>

                    <!-- Sort -->
                    <select class="form-select form-select-sm" style="width: auto;" onchange="location=this.value">
                        <option value="?{{ http_build_query(array_merge($requestParams, ['sort' => '_score'])) }}" {{ $params['sort'] === '_score' ? 'selected' : '' }}>Relevance</option>
                        <option value="?{{ http_build_query(array_merge($requestParams, ['sort' => 'title_asc'])) }}" {{ $params['sort'] === 'title_asc' ? 'selected' : '' }}>Title A-Z</option>
                        <option value="?{{ http_build_query(array_merge($requestParams, ['sort' => 'title_desc'])) }}" {{ $params['sort'] === 'title_desc' ? 'selected' : '' }}>Title Z-A</option>
                        <option value="?{{ http_build_query(array_merge($requestParams, ['sort' => 'date_desc'])) }}" {{ $params['sort'] === 'date_desc' ? 'selected' : '' }}>Date Newest</option>
                        <option value="?{{ http_build_query(array_merge($requestParams, ['sort' => 'date_asc'])) }}" {{ $params['sort'] === 'date_asc' ? 'selected' : '' }}>Date Oldest</option>
                    </select>
                </div>
            </div>

            <!-- Results Grid -->
            @if(!empty($results['hits']))
                @if(isset($adapter))
                {!! $adapter->renderResults($results, $layout) !!}
                @endif

                <!-- Pagination -->
                @php
                $total = $results['total'] ?? 0;
                $from = $results['from'] ?? 0;
                $size = $results['size'] ?? 10;
                $pages = $size > 0 ? ceil($total / $size) : 1;
                $currentPage = $size > 0 ? floor($from / $size) + 1 : 1;
                @endphp
                @if($pages > 1)
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        @if($currentPage > 1)
                        <li class="page-item">
                            <a class="page-link" href="?{{ http_build_query(array_merge($requestParams, ['from' => ($currentPage - 2) * $size])) }}">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        @endif

                        @php
                        $start = max(1, $currentPage - 2);
                        $end = min($pages, $currentPage + 2);
                        @endphp
                        @for($i = $start; $i <= $end; $i++)
                        <li class="page-item {{ $i === $currentPage ? 'active' : '' }}">
                            <a class="page-link" href="?{{ http_build_query(array_merge($requestParams, ['from' => ($i - 1) * $size])) }}">
                                {{ $i }}
                            </a>
                        </li>
                        @endfor

                        @if($currentPage < $pages)
                        <li class="page-item">
                            <a class="page-link" href="?{{ http_build_query(array_merge($requestParams, ['from' => $currentPage * $size])) }}">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                        @endif
                    </ul>
                </nav>
                @endif
            @else
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    {{ __('No results found. Try adjusting your filters or search terms.') }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
