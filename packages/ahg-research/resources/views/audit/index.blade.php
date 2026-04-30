{{-- Audit Trail Index - Migrated from AtoM: audit/indexSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'Audit Trail')

@section('content')
<h1><i class="fas fa-history text-primary me-2"></i>Audit Trail</h1>

<div class="row mb-4">
  <div class="col-md-2">
    <div class="card text-center bg-light"><div class="card-body py-2"><h4 class="mb-0 text-primary">{{ number_format($stats['total'] ?? 0) }}</h4><small class="text-muted">Total</small></div></div>
  </div>
  <div class="col-md-2">
    <div class="card text-center bg-light"><div class="card-body py-2"><h4 class="mb-0 text-info">{{ number_format($stats['today'] ?? 0) }}</h4><small class="text-muted">Today</small></div></div>
  </div>
  <div class="col-md-2">
    <div class="card text-center bg-light"><div class="card-body py-2"><h4 class="mb-0 text-success">{{ number_format($stats['creates'] ?? 0) }}</h4><small class="text-muted">Creates</small></div></div>
  </div>
  <div class="col-md-2">
    <div class="card text-center bg-light"><div class="card-body py-2"><h4 class="mb-0 text-warning">{{ number_format($stats['updates'] ?? 0) }}</h4><small class="text-muted">Updates</small></div></div>
  </div>
  <div class="col-md-2">
    <div class="card text-center bg-light"><div class="card-body py-2"><h4 class="mb-0 text-danger">{{ number_format($stats['deletes'] ?? 0) }}</h4><small class="text-muted">Deletes</small></div></div>
  </div>
  <div class="col-md-2">
    <a href="{{ route('audit.index', array_merge(request()->only(['table','from_date','to_date']), ['export' => 'csv'])) }}" class="btn btn-outline-secondary w-100 h-100 d-flex align-items-center justify-content-center">
      <i class="fas fa-download me-1"></i>Export CSV
    </a>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header"><i class="fas fa-filter me-2"></i>Filters</div>
  <div class="card-body">
    <form method="get" class="row g-3">
      <div class="col-md-2">
        <label class="form-label">Table <span class="badge bg-secondary ms-1">Optional</span></label>
        <select name="table" class="form-select form-select-sm">
          <option value="">{{ __('All Tables') }}</option>
          @foreach($tables ?? [] as $table)
            <option value="{{ $table }}" {{ request('table') === $table ? 'selected' : '' }}>{{ $table }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Action <span class="badge bg-secondary ms-1">Optional</span></label>
        <select name="form_action" class="form-select form-select-sm">
          <option value="">{{ __('All Actions') }}</option>
          <option value="create" {{ request('form_action') === 'create' ? 'selected' : '' }}>{{ __('Create') }}</option>
          <option value="update" {{ request('form_action') === 'update' ? 'selected' : '' }}>{{ __('Update') }}</option>
          <option value="delete" {{ request('form_action') === 'delete' ? 'selected' : '' }}>{{ __('Delete') }}</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">From Date <span class="badge bg-secondary ms-1">Optional</span></label>
        <input type="date" name="from_date" class="form-control form-control-sm" value="{{ request('from_date') }}">
      </div>
      <div class="col-md-2">
        <label class="form-label">To Date <span class="badge bg-secondary ms-1">Optional</span></label>
        <input type="date" name="to_date" class="form-control form-control-sm" value="{{ request('to_date') }}">
      </div>
      <div class="col-md-2">
        <label class="form-label">Search <span class="badge bg-secondary ms-1">Optional</span></label>
        <input type="text" name="q" class="form-control form-control-sm" value="{{ request('q') }}" placeholder="{{ __('Search...') }}">
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn atom-btn-white btn-sm me-2"><i class="fas fa-search"></i> Filter</button>
        <a href="{{ route('audit.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i></a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-list me-2"></i>Audit Log</span>
    <span class="badge bg-secondary">{{ number_format($totalCount ?? 0) }} entries</span>
  </div>
  <div class="card-body p-0">
    @if(empty($logs))
      <div class="text-center py-5 text-muted">
        <i class="fas fa-inbox fa-3x mb-3"></i>
        <p>No audit entries found</p>
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0">
          <thead class="table-light">
            <tr><th>{{ __('Date/Time') }}</th><th>{{ __('User') }}</th><th>{{ __('Action') }}</th><th>{{ __('Table') }}</th><th>{{ __('Record') }}</th><th>{{ __('Changes') }}</th><th></th></tr>
          </thead>
          <tbody>
            @foreach($logs as $log)
              @php
                $class = 'secondary';
                if ($log->action === 'create') $class = 'success';
                elseif ($log->action === 'update') $class = 'warning';
                elseif ($log->action === 'delete') $class = 'danger';
              @endphp
              <tr>
                <td class="small">{{ date('Y-m-d H:i:s', strtotime($log->created_at)) }}</td>
                <td>{{ e($log->user_name ?? 'System') }}</td>
                <td><span class="badge bg-{{ $class }}">{{ ucfirst($log->action) }}</span></td>
                <td><code>{{ $log->table_name }}</code></td>
                <td><code>#{{ $log->record_id }}</code></td>
                <td class="small">
                  @if($log->field_name)
                    <strong>{{ $log->field_name }}:</strong>
                    <span class="text-danger">{{ e(Str::limit($log->old_value ?? '', 20)) }}</span>
                    <i class="fas fa-arrow-right mx-1 text-muted"></i>
                    <span class="text-success">{{ e(Str::limit($log->new_value ?? '', 20)) }}</span>
                  @else
                    {{ e($log->action_description ?? '') }}
                  @endif
                </td>
                <td><a href="{{ route('audit.view', $log->id) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
  @if(($totalPages ?? 1) > 1)
    <div class="card-footer">
      <nav>
        <ul class="pagination pagination-sm justify-content-center mb-0">
          @for($i = max(1, ($currentPage ?? 1) - 2); $i <= min($totalPages, ($currentPage ?? 1) + 2); $i++)
            <li class="page-item {{ $i === ($currentPage ?? 1) ? 'active' : '' }}">
              <a class="page-link" href="{{ route('audit.index', array_merge(request()->only(['table','form_action','from_date','to_date','q']), ['page' => $i])) }}">{{ $i }}</a>
            </li>
          @endfor
        </ul>
      </nav>
    </div>
  @endif
</div>

<div class="mt-3">
  <a href="{{ route('settings.ahgSettings') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back to Settings</a>
</div>
@endsection
