@extends('theme::layouts.1col')
@section('title', 'Search Insights')
@section('body-class', 'admin heritage')

@php
$popularQueries = (array)($popularQueries ?? []);
$zeroResultQueries = (array)($zeroResultQueries ?? []);
$trendingQueries = (array)($trendingQueries ?? []);
$conversion = (array)($conversion ?? []);
$patterns = (array)($patterns ?? []);
@endphp

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._admin-sidebar')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-search me-2"></i>{{ __('Search Insights') }}</h1>

    <div class="row g-4 mb-4">
      @foreach([['total_searches','Total Searches'],['result_rate','Result Rate'],['conversion_rate','Conversion Rate'],['avg_clicks','Avg Clicks/Search']] as [$key,$label])
      <div class="col-md-3"><div class="card border-0 shadow-sm text-center"><div class="card-body"><h3 class="mb-0 {{ $key==='conversion_rate'?'text-success':'' }}">{{ $conversion[$key] ?? 0 }}{{ in_array($key,['result_rate','conversion_rate'])?'%':'' }}</h3><small class="text-muted">{{ $label }}</small></div></div></div>
      @endforeach
    </div>

    <div class="row g-4 mb-4">
      <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-fire me-2"></i>{{ __('Popular Queries') }}</h5></div>
          <div class="card-body p-0">
            @if(!empty($popularQueries))
            <div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>{{ __('Query') }}</th><th class="text-center">{{ __('Searches') }}</th><th class="text-center">{{ __('Clicks') }}</th></tr></thead><tbody>
              @foreach(array_slice($popularQueries,0,10) as $query)<tr><td>{{ $query->query_text ?? '' }}</td><td class="text-center">{{ number_format($query->search_count ?? 0) }}</td><td class="text-center">{{ number_format($query->total_clicks ?? 0) }}</td></tr>@endforeach
            </tbody></table></div>
            @else<p class="text-muted text-center py-4">No data available.</p>@endif
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-exclamation-circle me-2"></i>{{ __('Zero Result Queries') }}</h5></div>
          <div class="card-body p-0">
            @if(!empty($zeroResultQueries))
            <div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>{{ __('Query') }}</th><th class="text-center">{{ __('Count') }}</th><th>{{ __('Last Searched') }}</th></tr></thead><tbody>
              @foreach(array_slice($zeroResultQueries,0,10) as $query)<tr><td>{{ $query->query_text ?? '' }}</td><td class="text-center">{{ number_format($query->search_count ?? 0) }}</td><td><small class="text-muted">{{ date('M d', strtotime($query->last_searched ?? 'now')) }}</small></td></tr>@endforeach
            </tbody></table></div>
            @else<p class="text-muted text-center py-4">No zero-result queries.</p>@endif
          </div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>{{ __('Trending Queries') }}</h5></div>
      <div class="card-body">
        @if(!empty($trendingQueries))
        <div class="row">@foreach($trendingQueries as $trend)<div class="col-md-6 col-lg-4 mb-3"><div class="d-flex justify-content-between align-items-center p-2 bg-light rounded"><span>{{ $trend['query'] ?? '' }}</span><span class="badge bg-success">+{{ $trend['growth_percent'] ?? 0 }}%</span></div></div>@endforeach</div>
        @else<p class="text-muted text-center">No trending queries this week.</p>@endif
      </div>
    </div>

    @if(!empty($patterns['by_day_of_week'] ?? []))
    <div class="card border-0 shadow-sm">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-clock me-2"></i>{{ __('Search Patterns by Day') }}</h5></div>
      <div class="card-body">
        @foreach((array)($patterns['by_day_of_week'] ?? []) as $day => $count)
        <div class="d-flex justify-content-between mb-1"><span>{{ $day }}</span><span class="badge bg-secondary">{{ number_format($count) }}</span></div>
        @endforeach
      </div>
    </div>
    @endif
  </div>
</div>
@endsection
