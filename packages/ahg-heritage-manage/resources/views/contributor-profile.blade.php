@extends('theme::layouts.1col')
@section('title', 'Contributor Profile')
@section('body-class', 'heritage')

@php
$profile = (array)($profile ?? []);
$contributor = $profile['contributor'] ?? [];
$badges = $profile['badges'] ?? [];
$recentContributions = $profile['recent_contributions'] ?? [];
$statsByType = $profile['stats_by_type'] ?? [];
@endphp

@section('content')
<div class="row">
  <div class="col-md-4">
    <!-- Profile Card -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body text-center">
        @if(!empty($contributor['avatar_url']))<img src="{{ $contributor['avatar_url'] }}" class="rounded-circle mb-3" width="100" height="100" alt="{{ __('Avatar') }}">
        @else<div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:100px;height:100px;"><i class="fas fa-user display-3" style="color:var(--ahg-primary)"></i></div>@endif
        <h4 class="mb-1">{{ $contributor['display_name'] ?? 'Contributor' }}</h4>
        <span class="badge bg-{{ match($contributor['trust_level'] ?? 'new') { 'expert'=>'primary','trusted'=>'success','contributor'=>'info',default=>'secondary' } }} mb-2">{{ ucfirst($contributor['trust_level'] ?? 'new') }} Contributor</span>
        @if(!empty($contributor['bio']))<p class="text-muted small mt-3 mb-0">{!! nl2br(e($contributor['bio'])) !!}</p>@endif
        <p class="text-muted small mt-3 mb-0">Member since {{ date('F Y', strtotime($contributor['created_at'] ?? 'now')) }}</p>
      </div>
    </div>

    <!-- Stats -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>{{ __('Statistics') }}</h6></div>
      <div class="card-body">
        <div class="row g-3 text-center">
          <div class="col-4"><div class="h4 mb-0" style="color:var(--ahg-primary)">{{ number_format($contributor['approved_contributions'] ?? 0) }}</div><small class="text-muted">{{ __('Approved') }}</small></div>
          <div class="col-4"><div class="h4 text-success mb-0">{{ number_format($contributor['points'] ?? 0) }}</div><small class="text-muted">{{ __('Points') }}</small></div>
          <div class="col-4"><div class="h4 text-info mb-0">{{ count($badges) }}</div><small class="text-muted">{{ __('Badges') }}</small></div>
        </div>
      </div>
    </div>

    @if(!empty($badges))
    <div class="card border-0 shadow-sm">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h6 class="mb-0"><i class="fas fa-award me-2"></i>{{ __('Badges Earned') }}</h6></div>
      <ul class="list-group list-group-flush">
        @foreach($badges as $badge)
        <li class="list-group-item d-flex align-items-center"><i class="fas {{ $badge['icon'] ?? 'fa-award' }} fs-4 text-{{ $badge['color'] ?? 'primary' }} me-3"></i><div><strong>{{ $badge['name'] }}</strong><div class="small text-muted">{{ $badge['description'] ?? '' }}</div></div></li>
        @endforeach
      </ul>
    </div>
    @endif
  </div>
  <div class="col-md-8">
    <h1><i class="fas fa-user me-2"></i>{{ __('Contributor Profile') }}</h1>

    @if(!empty($statsByType))
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">{{ __('Contributions by Type') }}</h5></div>
      <div class="card-body">
        <div class="row g-3">
          @foreach($statsByType as $stat)
          <div class="col-md-4 col-6"><div class="d-flex align-items-center"><i class="fas {{ $stat['icon'] }} fs-3 me-3" style="color:var(--ahg-primary)"></i><div><div class="h5 mb-0">{{ number_format($stat['count']) }}</div><small class="text-muted">{{ $stat['name'] }}</small></div></div></div>
          @endforeach
        </div>
      </div>
    </div>
    @endif

    <div class="card border-0 shadow-sm">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">{{ __('Recent Contributions') }}</h5></div>
      @if(empty($recentContributions))
      <div class="card-body text-center text-muted py-5"><i class="fas fa-inbox display-4 mb-3 d-block"></i><p>No approved contributions yet.</p></div>
      @else
      <div class="list-group list-group-flush">
        @foreach($recentContributions as $contrib)
        <a href="{{ route('informationobject.show', $contrib['item_slug'] ?? '#') }}" class="list-group-item list-group-item-action">
          <div class="d-flex align-items-center">
            <i class="fas {{ $contrib['type_icon'] }} fs-4 me-3" style="color:var(--ahg-primary)"></i>
            <div class="flex-grow-1"><h6 class="mb-0">{{ $contrib['item_title'] ?? 'Untitled' }}</h6><small class="text-muted">{!! $contrib['type_name'] !!} &middot; {{ date('M d, Y', strtotime($contrib['created_at'])) }}</small></div>
            <i class="fas fa-chevron-right text-muted"></i>
          </div>
        </a>
        @endforeach
      </div>
      @endif
    </div>

    <div class="mt-4"><a href="{{ route('heritage.leaderboard') }}" class="btn atom-btn-white"><i class="fas fa-trophy me-1"></i>{{ __('View Leaderboard') }}</a></div>
  </div>
</div>
@endsection
