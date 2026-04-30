@extends('theme::layouts.1col')
@section('title', 'Audit Trail')
@section('body-class', 'admin heritage')

@php
$logs = $historyData['logs'] ?? [];
$total = $historyData['total'] ?? 0;
@endphp

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-heritage-manage::partials._admin-sidebar')
    <div class="mt-4">
      <h6 class="text-muted mb-3">{{ __('Quick Filters') }}</h6>
      <div class="list-group">
        <a href="?" class="list-group-item list-group-item-action {{ !request('action_type') ? 'active' : '' }}">All Actions</a>
        @foreach(['create','update','delete'] as $at)
        <a href="?action_type={{ $at }}" class="list-group-item list-group-item-action {{ request('action_type')===$at ? 'active' : '' }}">{{ ucfirst($at) }}s</a>
        @endforeach
      </div>
    </div>
  </div>
  <div class="col-md-9">
    <h1><i class="fas fa-history me-2"></i>Audit Trail</h1>

    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <form method="get" class="row g-3">
          <div class="col-md-4"><label class="form-label">Search <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" name="search" value="{{ request('search','') }}" placeholder="{{ __('User, object, or action...') }}"></div>
          <div class="col-md-3"><label class="form-label">From Date <span class="badge bg-secondary ms-1">Optional</span></label><input type="date" class="form-control" name="date_from" value="{{ request('date_from','') }}"></div>
          <div class="col-md-3"><label class="form-label">To Date <span class="badge bg-secondary ms-1">Optional</span></label><input type="date" class="form-control" name="date_to" value="{{ request('date_to','') }}"></div>
          <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn atom-btn-secondary w-100"><i class="fas fa-search me-1"></i>Filter</button></div>
        </form>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0">{{ __('Audit Log') }}</h5><span class="badge bg-secondary">{{ number_format($total) }} entries</span>
      </div>
      <div class="card-body p-0">
        @if(empty($logs))
        <div class="text-center text-muted py-5"><i class="fas fa-inbox fs-1 mb-3 d-block"></i><p>No audit log entries found.</p></div>
        @else
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th style="width:140px">{{ __('Timestamp') }}</th><th>{{ __('User') }}</th><th>{{ __('Action') }}</th><th>{{ __('Object') }}</th><th>{{ __('Changes') }}</th><th></th></tr></thead>
            <tbody>
              @foreach($logs as $log)
              @php $color = ['create'=>'success','update'=>'primary','delete'=>'danger','view'=>'info','approve'=>'success','deny'=>'danger'][$log->action] ?? 'secondary'; @endphp
              <tr>
                <td><small class="text-muted">{{ date('M d, Y', strtotime($log->created_at)) }}</small><br><small>{{ date('H:i:s', strtotime($log->created_at)) }}</small></td>
                <td><strong>{{ $log->username ?? 'System' }}</strong>@if($log->ip_address)<br><small class="text-muted">{{ $log->ip_address }}</small>@endif</td>
                <td><span class="badge bg-{{ $color }}">{{ ucfirst($log->action) }}</span></td>
                <td>@if($log->object_id){{ mb_strimwidth($log->object_title ?? "Object #{$log->object_id}", 0, 40, '...') }}@else -@endif</td>
                <td>@if($log->field_name)<small><strong>{{ $log->field_name }}</strong>: @if($log->old_value)<span class="text-danger text-decoration-line-through">{{ mb_strimwidth($log->old_value, 0, 20, '...') }}</span>@endif &rarr; <span class="text-success">{{ mb_strimwidth($log->new_value ?? '', 0, 20, '...') }}</span></small>@else -@endif</td>
                <td><button type="button" class="btn btn-sm atom-btn-white"><i class="fas fa-eye"></i></button></td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
