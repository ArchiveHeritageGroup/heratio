{{-- Security Audit Log - Migrated from AtoM: ahgSecurityClearancePlugin/modules/securityClearance/templates/auditSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'Security Audit Log')

@section('content')

<h1><i class="fas fa-history"></i> Security Audit Log</h1>

{{-- Filters --}}
<div class="card mb-4">
  <div class="card-body">
    <form method="get" action="">
      <div class="row">
        <div class="col-md-2">
          <label class="form-label">{{ __('Date From') }}</label>
          <input type="date" name="date_from" class="form-control" value="{{ e($filters['date_from'] ?? '') }}">
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ __('Date To') }}</label>
          <input type="date" name="date_to" class="form-control" value="{{ e($filters['date_to'] ?? '') }}">
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ __('Action') }}</label>
          <select name="form_action" class="form-select">
            <option value="">{{ __('All') }}</option>
            <option value="view" {{ (($filters['form_action'] ?? '') === 'view') ? 'selected' : '' }}>{{ __('View') }}</option>
            <option value="download" {{ (($filters['form_action'] ?? '') === 'download') ? 'selected' : '' }}>{{ __('Download') }}</option>
            <option value="print" {{ (($filters['form_action'] ?? '') === 'print') ? 'selected' : '' }}>{{ __('Print') }}</option>
            <option value="classify" {{ (($filters['form_action'] ?? '') === 'classify') ? 'selected' : '' }}>{{ __('Classify') }}</option>
            <option value="access_denied" {{ (($filters['form_action'] ?? '') === 'access_denied') ? 'selected' : '' }}>{{ __('Denied') }}</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ __('Result') }}</label>
          <select name="access_granted" class="form-select">
            <option value="">{{ __('All') }}</option>
            <option value="granted" {{ (($filters['access_granted'] ?? '') === 'granted') ? 'selected' : '' }}>{{ __('Granted') }}</option>
            <option value="denied" {{ (($filters['access_granted'] ?? '') === 'denied') ? 'selected' : '' }}>{{ __('Denied') }}</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ __('Classification') }}</label>
          <select name="classification_id" class="form-select">
            <option value="">{{ __('All') }}</option>
            @foreach($classifications ?? [] as $c)
            <option value="{{ $c->id }}" {{ ($c->id == ($filters['classification_id'] ?? '')) ? 'selected' : '' }}>
              {{ e($c->name) }}
            </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button type="submit" class="btn btn-primary w-100">{{ __('Filter') }}</button>
        </div>
      </div>
    </form>
  </div>
</div>

{{-- Export --}}
<div class="mb-3">
  <a href="{{ route('acl.security-report') }}?export=csv&date_from={{ urlencode($filters['date_from'] ?? '') }}&date_to={{ urlencode($filters['date_to'] ?? '') }}"
     class="btn btn-success">
    <i class="fas fa-download"></i> Export CSV
  </a>
</div>

{{-- Log Table --}}
<div class="card">
  <div class="card-body">
    <table class="table table-striped table-sm">
      <thead>
        <tr>
          <th>{{ __('Date/Time') }}</th>
          <th>{{ __('User') }}</th>
          <th>{{ __('Object') }}</th>
          <th>{{ __('Classification') }}</th>
          <th>{{ __('Action') }}</th>
          <th>{{ __('Result') }}</th>
          <th>{{ __('IP Address') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($logs ?? [] as $log)
        <tr class="{{ ($log->access_granted ?? true) ? '' : 'table-danger' }}">
          <td>{{ date('Y-m-d H:i:s', strtotime($log->created_at)) }}</td>
          <td>
            <a href="{{ route('acl.user-security', ['id' => $log->user_id]) }}">{{ e($log->username ?? '') }}</a>
          </td>
          <td>
            @if($log->object_id ?? null)
              <a href="{{ route('acl.object-view', ['id' => $log->object_id]) }}">{{ e($log->object_title ?? 'ID: ' . $log->object_id) }}</a>
            @else
              -
            @endif
          </td>
          <td>{{ e($log->classification_name ?? '-') }}</td>
          <td>
            <span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $log->action ?? '')) }}</span>
          </td>
          <td>
            @if($log->access_granted ?? false)
              <span class="badge bg-success">Granted</span>
            @else
              <span class="badge bg-danger">Denied</span>
              @if($log->denial_reason ?? null)
                <br><small class="text-muted">{{ e($log->denial_reason) }}</small>
              @endif
            @endif
          </td>
          <td><small>{{ e($log->ip_address ?? '') }}</small></td>
        </tr>
        @endforeach
      </tbody>
    </table>

    @if(empty($logs) || (is_countable($logs) && count($logs) === 0))
    <p class="text-muted text-center">No audit entries found.</p>
    @endif
  </div>
</div>

@endsection
