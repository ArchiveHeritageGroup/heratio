@extends('theme::layouts.1col')
@section('title', 'Content Insights')
@section('body-class', 'admin heritage')

@php
$contentData = (array)($contentData ?? []);
$topContent = (array)($contentData['top_content'] ?? []);
$lowPerforming = (array)($contentData['low_performing'] ?? []);
$qualityIssues = (array)($contentData['quality_issues'] ?? []);
$summary = (array)($contentData['summary'] ?? ['total_items'=>0,'total_views'=>0,'total_downloads'=>0,'avg_ctr'=>0]);
@endphp

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._admin-sidebar')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-chart-bar me-2"></i>{{ __('Content Insights') }}</h1>

    <div class="row g-4 mb-4">
      @foreach([['total_items','Total Items'],['total_views','Total Views (30d)'],['total_downloads','Downloads (30d)'],['avg_ctr','Avg Click-Through']] as [$key,$label])
      <div class="col-md-3"><div class="card border-0 shadow-sm text-center"><div class="card-body"><h3 class="mb-0">{{ $key==='avg_ctr' ? ($summary[$key] ?? 0).'%' : number_format($summary[$key] ?? 0) }}</h3><small class="text-muted">{{ $label }}</small></div></div></div>
      @endforeach
    </div>

    <div class="row g-4 mb-4">
      <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-trophy me-2"></i>{{ __('Top Performing') }}</h5></div>
          <div class="card-body p-0">
            @if(!empty($topContent))
            <div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>{{ __('Item') }}</th><th class="text-center">{{ __('Views') }}</th><th class="text-center">{{ __('Downloads') }}</th></tr></thead><tbody>
              @foreach(array_slice($topContent,0,10) as $item)<tr><td>{{ mb_strimwidth($item->title ?? $item->slug ?? 'Item',0,35,'...') }}</td><td class="text-center">{{ number_format($item->view_count ?? 0) }}</td><td class="text-center">{{ number_format($item->download_count ?? 0) }}</td></tr>@endforeach
            </tbody></table></div>
            @else<p class="text-muted text-center py-4">No data available.</p>@endif
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-eye-slash me-2"></i>{{ __('Needs Attention') }}</h5></div>
          <div class="card-body p-0">
            @if(!empty($lowPerforming))
            <div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>{{ __('Item') }}</th><th class="text-center">{{ __('Views') }}</th><th>{{ __('Issue') }}</th></tr></thead><tbody>
              @foreach(array_slice($lowPerforming,0,10) as $item)<tr><td>{{ mb_strimwidth($item->title ?? '',0,30,'...') }}</td><td class="text-center">{{ number_format($item->view_count ?? 0) }}</td><td><span class="badge bg-warning">{{ $item->issue ?? 'Low visibility' }}</span></td></tr>@endforeach
            </tbody></table></div>
            @else<p class="text-muted text-center py-4">All content performing well!</p>@endif
          </div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>{{ __('Quality Issues') }}</h5>
        <span class="badge bg-warning text-dark">{{ count($qualityIssues) }} items</span>
      </div>
      <div class="card-body p-0">
        @if(!empty($qualityIssues))
        <div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>{{ __('Item') }}</th><th>{{ __('Issue Type') }}</th><th>{{ __('Details') }}</th><th></th></tr></thead><tbody>
          @foreach($qualityIssues as $issue)
          @php $color = ['missing_description'=>'warning','no_digital_object'=>'info','poor_metadata'=>'danger','broken_links'=>'danger'][$issue->issue_type ?? ''] ?? 'secondary'; @endphp
          <tr><td>{{ mb_strimwidth($issue->title ?? '',0,40,'...') }}</td><td><span class="badge bg-{{ $color }}">{{ ucwords(str_replace('_',' ',$issue->issue_type ?? '')) }}</span></td><td><small class="text-muted">{{ $issue->details ?? '-' }}</small></td><td><a href="#" class="btn btn-sm btn-outline-primary"><i class="fas fa-pencil-alt"></i></a></td></tr>
          @endforeach
        </tbody></table></div>
        @else<div class="text-center text-muted py-4"><i class="fas fa-check-circle fs-1 text-success mb-3 d-block"></i><p>No quality issues detected.</p></div>@endif
      </div>
    </div>
  </div>
</div>
@endsection
