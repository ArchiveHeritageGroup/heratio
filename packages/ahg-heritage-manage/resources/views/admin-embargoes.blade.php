@extends('theme::layouts.1col')
@section('title', 'Embargo Management')
@section('body-class', 'admin heritage')

@php
$embargoes = $embargoData['embargoes'] ?? [];
$total = $embargoData['total'] ?? 0;
$expiringEmbargoes = $expiringEmbargoes ?? [];
@endphp

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-heritage-manage::partials._admin-sidebar')

    <div class="card border-0 shadow-sm mt-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h6 class="mb-0">Statistics</h6></div>
      <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
          <span>Active</span>
          <span class="badge bg-danger">{{ $stats['active'] ?? 0 }}</span>
        </div>
        <div class="d-flex justify-content-between">
          <span>Expiring Soon</span>
          <span class="badge bg-warning">{{ $stats['expiring_soon'] ?? 0 }}</span>
        </div>
      </div>
    </div>

    @if(!empty($expiringEmbargoes))
    <div class="card border-0 shadow-sm mt-4">
      <div class="card-header bg-warning bg-opacity-10">
        <h6 class="mb-0 text-warning"><i class="fas fa-clock me-2"></i>Expiring Soon</h6>
      </div>
      <ul class="list-group list-group-flush">
        @foreach(array_slice((array)$expiringEmbargoes, 0, 5) as $exp)
        <li class="list-group-item d-flex justify-content-between align-items-center">
          <span>{{ $exp->title ?? $exp->slug ?? 'Item' }}</span>
          <small class="text-muted">{{ date('M d', strtotime($exp->end_date)) }}</small>
        </li>
        @endforeach
      </ul>
    </div>
    @endif
  </div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-lock me-2"></i>Embargo Management</h1>
    </div>

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0">Active Embargoes</h5>
        <span class="badge bg-danger">{{ number_format($total) }} active</span>
      </div>
      <div class="card-body p-0">
        @if(empty($embargoes))
        <div class="text-center text-muted py-5">
          <i class="fas fa-unlock fs-1 mb-3 d-block"></i>
          <p>No active embargoes.</p>
        </div>
        @else
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr><th>Item</th><th>Type</th><th>End Date</th><th>Auto-Release</th><th></th></tr>
            </thead>
            <tbody>
              @foreach($embargoes as $embargo)
              @php
                $typeColors = ['full' => 'danger', 'digital_only' => 'warning', 'metadata_hidden' => 'info'];
                $color = $typeColors[$embargo->embargo_type] ?? 'secondary';
              @endphp
              <tr>
                <td>{{ $embargo->title ?? $embargo->slug ?? 'Item' }}</td>
                <td><span class="badge bg-{{ $color }}">{{ ucfirst(str_replace('_', ' ', $embargo->embargo_type)) }}</span></td>
                <td>{{ $embargo->end_date ? date('Y-m-d', strtotime($embargo->end_date)) : 'Indefinite' }}</td>
                <td>@if($embargo->auto_release)<i class="fas fa-check-circle text-success"></i>@else<i class="fas fa-times-circle text-muted"></i>@endif</td>
                <td class="text-end">
                  <form method="post" class="d-inline" onsubmit="return confirm('Remove this embargo?');" action="{{ route('heritage.admin-embargoes') }}">
                    @csrf
                    <input type="hidden" name="form_action" value="remove">
                    <input type="hidden" name="embargo_id" value="{{ $embargo->id }}">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-unlock"></i></button>
                  </form>
                </td>
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
