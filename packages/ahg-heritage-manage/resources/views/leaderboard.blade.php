@extends('theme::layouts.1col')
@section('title', 'Contributor Leaderboard')
@section('body-class', 'heritage')

@php
$leaderboard = (array)($leaderboard ?? []);
$stats = (array)($stats ?? []);
$period = $period ?? '';
@endphp

@section('content')
<div class="row">
  <div class="col-md-4">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Community Stats</h6></div>
      <div class="card-body">
        <div class="d-flex justify-content-between mb-2"><span>{{ __('Total Contributions') }}</span><strong>{{ number_format($stats['total'] ?? 0) }}</strong></div>
        <div class="d-flex justify-content-between mb-2"><span>{{ __('Approved') }}</span><strong class="text-success">{{ number_format($stats['approved'] ?? 0) }}</strong></div>
        <div class="d-flex justify-content-between mb-2"><span>{{ __('Pending Review') }}</span><strong class="text-warning">{{ number_format($stats['pending'] ?? 0) }}</strong></div>
        <hr>
        <div class="d-flex justify-content-between mb-2"><span>{{ __('This Week') }}</span><strong style="color:var(--ahg-primary)">{{ number_format($stats['this_week'] ?? 0) }}</strong></div>
        <div class="d-flex justify-content-between"><span>{{ __('This Month') }}</span><strong class="text-info">{{ number_format($stats['this_month'] ?? 0) }}</strong></div>
      </div>
    </div>
    @if(!empty($stats['by_type']))
    <div class="card border-0 shadow-sm">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>By Type</h6></div>
      <ul class="list-group list-group-flush">
        @foreach($stats['by_type'] as $type)
        <li class="list-group-item d-flex justify-content-between align-items-center"><span><i class="fas {{ $type['icon'] }} me-2"></i>{{ $type['name'] }}</span><span class="badge bg-primary">{{ number_format($type['total']) }}</span></li>
        @endforeach
      </ul>
    </div>
    @endif
  </div>
  <div class="col-md-8">
    <h1><i class="fas fa-trophy me-2"></i>Contributor Leaderboard</h1>

    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0">{{ __('Top Contributors') }}</h5>
        <div class="btn-group btn-group-sm">
          <a href="{{ route('heritage.leaderboard') }}" class="btn btn-outline-light {{ empty($period)?'active':'' }}">All Time</a>
          <a href="{{ route('heritage.leaderboard', ['period'=>'month']) }}" class="btn btn-outline-light {{ $period==='month'?'active':'' }}">This Month</a>
          <a href="{{ route('heritage.leaderboard', ['period'=>'week']) }}" class="btn btn-outline-light {{ $period==='week'?'active':'' }}">This Week</a>
        </div>
      </div>
      <div class="card-body p-0">
        @if(empty($leaderboard))
        <div class="text-center text-muted py-5"><i class="fas fa-users display-1 mb-3 d-block"></i><p>No contributors yet. Be the first!</p><a href="{{ route('heritage.contributor-register') }}" class="btn atom-btn-secondary"><i class="fas fa-user-plus me-1"></i>{{ __('Join Now') }}</a></div>
        @else
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th width="60">{{ __('Rank') }}</th><th>{{ __('Contributor') }}</th><th class="text-center">{{ __('Level') }}</th><th class="text-center">{{ __('Contributions') }}</th><th class="text-center">{{ __('Points') }}</th><th class="text-center">{{ __('Badges') }}</th></tr></thead>
            <tbody>
              @foreach($leaderboard as $entry)
              <tr>
                <td>@if($entry['rank']===1)&#x1F947;@elseif($entry['rank']===2)&#x1F948;@elseif($entry['rank']===3)&#x1F949;@else<span class="badge bg-secondary">{{ $entry['rank'] }}</span>@endif</td>
                <td><div class="d-flex align-items-center">@if(!empty($entry['avatar_url']))<img src="{{ $entry['avatar_url'] }}" class="rounded-circle me-2" width="32" height="32">@else<div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2" style="width:32px;height:32px"><i class="fas fa-user" style="color:var(--ahg-primary)"></i></div>@endif<strong>{{ $entry['display_name'] }}</strong></div></td>
                <td class="text-center"><span class="badge bg-{{ match($entry['trust_level'] ?? 'new'){'expert'=>'primary','trusted'=>'success','contributor'=>'info',default=>'secondary'} }}">{{ ucfirst($entry['trust_level'] ?? 'new') }}</span></td>
                <td class="text-center"><strong>{{ number_format($entry['approved_contributions'] ?? 0) }}</strong></td>
                <td class="text-center"><strong style="color:var(--ahg-primary)">{{ number_format($entry['points'] ?? 0) }}</strong></td>
                <td class="text-center">@if(($entry['badge_count'] ?? 0) > 0)<span class="badge bg-warning"><i class="fas fa-award"></i> {{ $entry['badge_count'] }}</span>@else<span class="text-muted">-</span>@endif</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @endif
      </div>
    </div>

    <div class="text-center mt-4">
      <p class="text-muted mb-3">Help preserve our heritage and earn recognition!</p>
      <a href="{{ route('heritage.search') }}" class="btn atom-btn-secondary btn-lg"><i class="fas fa-search me-2"></i>{{ __('Find Items to Contribute') }}</a>
    </div>
  </div>
</div>
@endsection
