@extends('theme::layouts.1col')
@section('title', 'User Activity Report')
@section('body-class', 'admin reports')

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-reports::_menu')
    <div class="card mb-3">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-filter me-2"></i>Filters</div>
      <div class="card-body">
        <form method="get" action="{{ route('reports.activity') }}">
          <div class="mb-3">
            <label class="form-label">Date start <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="date" name="dateStart" class="form-control form-control-sm" value="{{ $params['dateStart'] ?? '' }}">
          </div>
          <div class="mb-3">
            <label class="form-label">Date end <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="date" name="dateEnd" class="form-control form-control-sm" value="{{ $params['dateEnd'] ?? '' }}">
          </div>
          <div class="mb-3">
            <label class="form-label">User <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="actionUser" class="form-select form-select-sm">
              <option value="">{{ __('All users') }}</option>
              @foreach($users as $u)
                <option value="{{ $u }}" {{ ($params['actionUser'] ?? '') === $u ? 'selected' : '' }}>{{ $u }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Action <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="userAction" class="form-select form-select-sm">
              <option value="">{{ __('All actions') }}</option>
              @foreach(['create', 'update', 'delete'] as $a)
                <option value="{{ $a }}" {{ ($params['userAction'] ?? '') === $a ? 'selected' : '' }}>{{ ucfirst($a) }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Results per page <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="limit" class="form-select form-select-sm">
              @foreach([10, 20, 50, 100] as $l)
                <option value="{{ $l }}" {{ ($params['limit'] ?? 20) == $l ? 'selected' : '' }}>{{ $l }}</option>
              @endforeach
            </select>
          </div>
          <button type="submit" class="btn atom-btn-outline-light btn-sm w-100"><i class="fas fa-search me-1"></i>Filter</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-history me-2"></i>User Activity</h1>
      <span class="badge bg-primary fs-6">{{ number_format($total) }} results</span>
    </div>
    @if(!$auditTable)
      <div class="alert alert-warning">No audit log table found.</div>
    @else
      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm">
          <thead>
            <tr>
            <th>{{ __('Date') }}</th><th>{{ __('User') }}</th><th>{{ __('Action') }}</th>
            @if($auditTable === 'ahg_audit_log')
              <th>{{ __('Entity type') }}</th><th>{{ __('Entity') }}</th>
            @else
              <th>{{ __('Table') }}</th><th>{{ __('Record') }}</th>
            @endif
            </tr>
          </thead>
          <tbody>
            @forelse($results as $row)
              <tr>
                <td>{{ $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('Y-m-d H:i') : '' }}</td>
                <td>{{ $row->username ?? '' }}</td>
                <td>
                  @php $badge = match($row->action ?? '') { 'create' => 'bg-success', 'update' => 'bg-primary', 'delete' => 'bg-danger', default => 'bg-secondary' }; @endphp
                  <span class="badge {{ $badge }}">{{ $row->action ?? '' }}</span>
                </td>
                @if($auditTable === 'ahg_audit_log')
                  <td>{{ $row->entity_type ?? '' }}</td>
                  <td>{{ $row->entity_title ?? '' }}</td>
                @else
                  <td>{{ $row->table_name ?? '' }}</td>
                  <td>{{ $row->record_id ?? '' }}</td>
                @endif
              </tr>
            @empty
              <tr><td colspan="5" class="text-muted text-center">No results</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @include('ahg-reports::_pagination')
    @endif
  </div>
</div>
@endsection
