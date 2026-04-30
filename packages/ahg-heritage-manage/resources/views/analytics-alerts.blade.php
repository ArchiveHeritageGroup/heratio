@extends('theme::layouts.1col')
@section('title', 'Alerts & Notifications')
@section('body-class', 'admin heritage')

@php
$alerts = $alertData['alerts'] ?? [];
$stats = $alertData['stats'] ?? [];
@endphp

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-heritage-manage::partials._admin-sidebar')
    <div class="mt-4">
      <h6 class="text-muted mb-3">{{ __('Filter by Severity') }}</h6>
      <div class="list-group">
        <a href="?" class="list-group-item list-group-item-action {{ !request('severity')?'active':'' }}">All Alerts</a>
        <a href="?severity=critical" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ request('severity')==='critical'?'active':'' }}">Critical <span class="badge bg-danger">{{ $stats['critical'] ?? 0 }}</span></a>
        <a href="?severity=warning" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ request('severity')==='warning'?'active':'' }}">Warning <span class="badge bg-warning">{{ $stats['warning'] ?? 0 }}</span></a>
        <a href="?severity=info" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ request('severity')==='info'?'active':'' }}">Info <span class="badge bg-info">{{ $stats['info'] ?? 0 }}</span></a>
      </div>
    </div>
    <div class="card border-0 shadow-sm mt-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h6 class="mb-0">{{ __('Alert Types') }}</h6></div>
      <div class="card-body">
        @foreach(['content_quality'=>['fas fa-file-alt','Content Quality'],'access_request'=>['fas fa-key','Access Requests'],'system'=>['fas fa-cog','System'],'security'=>['fas fa-shield-alt','Security'],'performance'=>['fas fa-tachometer-alt','Performance']] as $type => [$icon,$label])
        <a href="?type={{ $type }}" class="d-flex align-items-center text-decoration-none text-dark mb-2"><i class="{{ $icon }} me-2"></i><span>{{ $label }}</span></a>
        @endforeach
      </div>
    </div>
  </div>
  <div class="col-md-9">
    <h1><i class="fas fa-bell me-2"></i>Alerts & Notifications</h1>

    <div class="row g-4 mb-4">
      @foreach([['critical','Critical','danger','fas fa-exclamation-circle'],['warning','Warnings','warning','fas fa-exclamation-triangle'],['info','Info','info','fas fa-info-circle']] as [$key,$label,$color,$icon])
      <div class="col-md-4">
        <div class="card border-0 shadow-sm border-start border-{{ $color }} border-4">
          <div class="card-body d-flex justify-content-between align-items-center">
            <div><h3 class="mb-0 text-{{ $color }}">{{ $stats[$key] ?? 0 }}</h3><small class="text-muted">{{ $label }}</small></div>
            <i class="{{ $icon }} fs-1 text-{{ $color }} opacity-25"></i>
          </div>
        </div>
      </div>
      @endforeach
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0">{{ __('Active Alerts') }}</h5>
        <button type="button" class="btn btn-sm btn-outline-light" onclick="if(confirm('Dismiss all info-level alerts?'))location.reload()"><i class="fas fa-check-double me-1"></i>Dismiss All Read</button>
      </div>
      <div class="card-body p-0">
        @if(empty($alerts))
        <div class="text-center text-muted py-5"><i class="fas fa-bell-slash fs-1 mb-3 d-block"></i><p>No alerts at this time.</p></div>
        @else
        <div class="list-group list-group-flush">
          @foreach($alerts as $alert)
          @php
          $severityColors = ['critical'=>'danger','warning'=>'warning','info'=>'info'];
          $severityIcons = ['critical'=>'exclamation-circle','warning'=>'exclamation-triangle','info'=>'info-circle'];
          $color = $severityColors[$alert->severity] ?? 'secondary';
          $icon = $severityIcons[$alert->severity] ?? 'bell';
          @endphp
          <div class="list-group-item list-group-item-action" id="alert-{{ $alert->id }}">
            <div class="d-flex w-100 justify-content-between align-items-start">
              <div class="d-flex align-items-start">
                <div class="me-3"><span class="badge bg-{{ $color }} rounded-pill p-2"><i class="fas fa-{{ $icon }}"></i></span></div>
                <div>
                  <h6 class="mb-1">{{ $alert->title }}</h6>
                  <p class="mb-1 text-muted">{{ $alert->message ?? '' }}</p>
                  <small class="text-muted"><i class="fas fa-clock me-1"></i>{{ date('M d, Y H:i', strtotime($alert->created_at)) }}@if($alert->alert_type)<span class="ms-2 badge bg-light text-dark">{{ ucwords(str_replace('_',' ',$alert->alert_type)) }}</span>@endif</small>
                </div>
              </div>
              <div class="d-flex gap-2">
                @if($alert->action_url ?? null)<a href="{{ $alert->action_url }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-arrow-right"></i></a>@endif
                <button type="button" class="btn btn-sm btn-outline-secondary" title="{{ __('Dismiss') }}"><i class="fas fa-times"></i></button>
              </div>
            </div>
          </div>
          @endforeach
        </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
