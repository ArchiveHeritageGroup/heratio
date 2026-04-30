@extends('theme::layouts.1col')
@section('title', 'POPIA/Privacy Flags')
@section('body-class', 'admin heritage')

@php
$flags = $flagData['flags'] ?? [];
$total = $flagData['total'] ?? 0;
@endphp

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-heritage-manage::partials._admin-sidebar')
    <div class="card border-0 shadow-sm mt-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h6 class="mb-0">{{ __('Statistics') }}</h6></div>
      <div class="card-body">
        <div class="d-flex justify-content-between mb-2"><span>{{ __('Unresolved') }}</span><span class="badge bg-warning">{{ $stats['unresolved'] ?? 0 }}</span></div>
        <div class="d-flex justify-content-between mb-2"><span>{{ __('Critical') }}</span><span class="badge bg-danger">{{ $stats['critical'] ?? 0 }}</span></div>
        <div class="d-flex justify-content-between mb-2"><span>{{ __('High') }}</span><span class="badge bg-warning">{{ $stats['high'] ?? 0 }}</span></div>
        <div class="d-flex justify-content-between"><span>{{ __('Resolved (This Month)') }}</span><span class="badge bg-success">{{ $stats['resolved_this_month'] ?? 0 }}</span></div>
      </div>
    </div>
  </div>
  <div class="col-md-9">
    <h1><i class="fas fa-user-shield me-2"></i>{{ __('POPIA/Privacy Flags') }}</h1>

    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <form method="get" class="row g-3">
          <div class="col-md-4">
            <select class="form-select" name="severity">
              <option value="">{{ __('All Severities') }}</option>
              @foreach(['critical','high','medium','low'] as $sev)
              <option value="{{ $sev }}" {{ request('severity') === $sev ? 'selected' : '' }}>{{ ucfirst($sev) }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4">
            <select class="form-select" name="flag_type">
              <option value="">{{ __('All Types') }}</option>
              <option value="personal_info">{{ __('Personal Information') }}</option>
              <option value="sensitive">{{ __('Sensitive Data') }}</option>
              <option value="children">{{ __("Children's Data") }}</option>
              <option value="health">{{ __('Health Information') }}</option>
            </select>
          </div>
          <div class="col-md-4"><button type="submit" class="btn atom-btn-secondary w-100">{{ __('Filter') }}</button></div>
        </form>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0">{{ __('Unresolved Flags') }}</h5>
        <span class="badge bg-warning text-dark">{{ number_format($total) }} flags</span>
      </div>
      <div class="card-body p-0">
        @if(empty($flags))
        <div class="text-center text-muted py-5"><i class="fas fa-shield-alt fs-1 mb-3 d-block text-success"></i><p>No unresolved privacy flags.</p></div>
        @else
        <div class="list-group list-group-flush">
          @foreach($flags as $flag)
          @php $color = ['critical'=>'danger','high'=>'warning','medium'=>'info','low'=>'secondary'][$flag->severity] ?? 'secondary'; @endphp
          <div class="list-group-item">
            <div class="row align-items-center">
              <div class="col-md-1"><span class="badge bg-{{ $color }} text-uppercase">{{ $flag->severity }}</span></div>
              <div class="col-md-5">{{ $flag->object_title ?? 'Item' }}<br><small class="text-muted">{{ ucfirst(str_replace('_',' ',$flag->flag_type)) }}</small></div>
              <div class="col-md-4">@if($flag->description)<small>{{ substr($flag->description,0,100) }}</small>@endif</div>
              <div class="col-md-2 text-end">
                <button class="btn btn-sm atom-btn-outline-success" data-bs-toggle="modal" data-bs-target="#resolveModal" data-flag-id="{{ $flag->id }}"><i class="fas fa-check me-1"></i>{{ __('Resolve') }}</button>
              </div>
            </div>
          </div>
          @endforeach
        </div>
        @endif
      </div>
    </div>

    <div class="modal fade" id="resolveModal" tabindex="-1">
      <div class="modal-dialog"><div class="modal-content">
        <form method="post" action="{{ route('heritage.admin-popia') }}">@csrf
          <div class="modal-header bg-success text-white"><h5 class="modal-title">{{ __('Resolve Privacy Flag') }}</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <input type="hidden" name="form_action" value="resolve"><input type="hidden" name="flag_id" id="resolve_flag_id">
            <div class="mb-3"><label for="resolution_notes" class="form-label">{{ __('Resolution Notes') }}</label><textarea class="form-control" name="resolution_notes" id="resolution_notes" rows="3" placeholder="{{ __('Describe what action was taken...') }}"></textarea></div>
          </div>
          <div class="modal-footer"><button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn atom-btn-outline-success">{{ __('Mark as Resolved') }}</button></div>
        </form>
      </div></div>
    </div>
    <script>document.getElementById('resolveModal')?.addEventListener('show.bs.modal',function(e){document.getElementById('resolve_flag_id').value=e.relatedTarget.getAttribute('data-flag-id');});</script>
  </div>
</div>
@endsection
