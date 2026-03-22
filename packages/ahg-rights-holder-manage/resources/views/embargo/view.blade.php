@extends('theme::layouts.1col')

@section('title', 'Embargo Details')
@section('body-class', 'embargo view')

@section('title-block')
  <h1 class="mb-0">Embargo Details</h1>
@endsection

@section('content')
@php
  $status = $embargo['status'] ?? 'active';
  $statusColors = ['active' => 'danger', 'expired' => 'secondary', 'lifted' => 'success', 'pending' => 'warning'];
@endphp

<div class="card mb-4">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <h4 class="mb-0">Embargo Information
      <span class="badge bg-{{ $statusColors[$status] ?? 'secondary' }} float-end">{{ ucfirst($status) }}</span>
    </h4>
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-6">
        <dl>
          <dt>Object</dt>
          <dd>#{{ $embargo['object_id'] ?? '' }}</dd>
          <dt>Type</dt>
          <dd>{{ ucfirst(str_replace('_', ' ', $embargo['embargo_type'] ?? 'full')) }}</dd>
          <dt>Start Date</dt>
          <dd>{{ $embargo['start_date'] ?? '-' }}</dd>
          <dt>End Date</dt>
          <dd>
            @if($embargo['is_perpetual'] ?? false)
              <span class="text-danger">Perpetual</span>
            @elseif($embargo['end_date'] ?? null)
              {{ $embargo['end_date'] }}
            @else
              -
            @endif
          </dd>
        </dl>
      </div>
      <div class="col-md-6">
        <dl>
          @if(!empty($embargo['reason']))
            <dt>Reason</dt>
            <dd>{{ $embargo['reason'] }}</dd>
          @endif
          @if(!empty($embargo['public_message']))
            <dt>Public Message</dt>
            <dd>{{ $embargo['public_message'] }}</dd>
          @endif
          @if(!empty($embargo['notes']))
            <dt>Internal Notes</dt>
            <dd>{!! nl2br(e($embargo['notes'])) !!}</dd>
          @endif
        </dl>
      </div>
    </div>

    @if($status === 'lifted')
      <div class="alert alert-success">
        <strong>This embargo was lifted</strong>
        @if($embargo['lifted_at'] ?? null)
          on {{ date('Y-m-d H:i', strtotime($embargo['lifted_at'])) }}
        @endif
        @if($embargo['lift_reason'] ?? null)
          <br>Reason: {{ $embargo['lift_reason'] }}
        @endif
      </div>
    @endif
  </div>
</div>

{{-- Exceptions --}}
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <h4 class="mb-0">Exceptions</h4>
  </div>
  <div class="card-body">
    @if(!empty($embargo['exceptions']))
      <table class="table table-sm">
        <thead>
          <tr><th>Type</th><th>Details</th><th>Valid Period</th></tr>
        </thead>
        <tbody>
          @foreach($embargo['exceptions'] as $exception)
            <tr>
              <td>{{ ucfirst($exception['exception_type'] ?? '') }}</td>
              <td>
                @if(($exception['exception_type'] ?? '') === 'ip_range')
                  {{ $exception['ip_range_start'] ?? '' }} - {{ $exception['ip_range_end'] ?? '' }}
                @elseif($exception['exception_id'] ?? null)
                  #{{ $exception['exception_id'] }}
                @endif
              </td>
              <td>
                @if(($exception['valid_from'] ?? null) || ($exception['valid_until'] ?? null))
                  {{ $exception['valid_from'] ?? '...' }} - {{ $exception['valid_until'] ?? '...' }}
                @else
                  Always
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @else
      <p class="text-muted">No exceptions defined.</p>
    @endif
  </div>
</div>

{{-- Audit Log --}}
<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <h4 class="mb-0">Audit Log</h4>
  </div>
  <div class="card-body">
    @if(!empty($embargo['audit_log']))
      <table class="table table-sm">
        <thead>
          <tr><th>Date</th><th>Action</th><th>User</th><th>IP Address</th></tr>
        </thead>
        <tbody>
          @foreach($embargo['audit_log'] as $log)
            <tr>
              <td>{{ date('Y-m-d H:i', strtotime($log['created_at'])) }}</td>
              <td>{{ ucfirst(str_replace('_', ' ', $log['action'])) }}</td>
              <td>{{ $log['user_id'] ? '#' . $log['user_id'] : '-' }}</td>
              <td>{{ $log['ip_address'] ?? '-' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @else
      <p class="text-muted">No audit log entries.</p>
    @endif
  </div>
</div>

@endsection

@section('after-content')
  @auth
    <ul class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;">
      @if($status === 'active')
        <li><a href="{{ route('embargo.liftForm', $embargo['id']) }}" class="btn atom-btn-white"><i class="fas fa-unlock me-1"></i> Lift Embargo</a></li>
      @endif
      <li><a href="{{ route('embargo.index') }}" class="btn atom-btn-outline-light">Back to Embargoes</a></li>
    </ul>
  @endauth
@endsection
