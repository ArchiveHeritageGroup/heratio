@extends('theme::layouts.1col')
@section('title', 'Audit Archival Description')
@section('body-class', 'admin reports')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-reports::_menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-file-alt me-2"></i>Audit Archival Description</h1>
      <div>
        <a href="{{ route('reports.dashboard') }}" class="btn btn-sm atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back</a>
      </div>
    </div>

    @if(session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Filters --}}
    <div class="card mb-3">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-filter me-2"></i>Filters</div>
      <div class="card-body">
        <form method="get" class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Date Start <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="date" name="dateStart" class="form-control form-control-sm" value="{{ request('dateStart') }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">Date End <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="date" name="dateEnd" class="form-control form-control-sm" value="{{ request('dateEnd') }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">Per Page <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="limit" class="form-select form-select-sm">
              <option value="25" {{ request('limit',25)==25?'selected':'' }}>25</option>
              <option value="50" {{ request('limit')==50?'selected':'' }}>50</option>
              <option value="100" {{ request('limit')==100?'selected':'' }}>100</option>
            </select>
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn atom-btn-white btn-sm"><i class="fas fa-search me-1"></i>Search</button>
          </div>
        </form>
      </div>
    </div>

    {{-- Results --}}
    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-sm table-striped mb-0">
            <thead>
              <tr style="background:var(--ahg-primary);color:#fff">
                <th>Audit ID</th>
                <th>User</th>
                <th>Action</th>
                <th>Date/Time</th>
                <th>Record</th>
                <th>Table</th>
                <th>Details</th>
              </tr>
            </thead>
            <tbody>
              @forelse($records ?? [] as $item)
              <tr>
                <td>{{ $item->id ?? '' }}</td>
                <td>{{ $item->username ?? '' }}</td>
                <td>
                  @if(($item->action ?? '') === 'insert')
                    <span class="badge bg-success">Insert</span>
                  @elseif(($item->action ?? '') === 'update')
                    <span class="badge bg-warning text-dark">Update</span>
                  @elseif(($item->action ?? '') === 'delete')
                    <span class="badge bg-danger">Delete</span>
                  @else
                    <span class="badge bg-secondary">{{ ucfirst($item->action ?? '-') }}</span>
                  @endif
                </td>
                <td><small>{{ $item->action_date_time ?? $item->created_at ?? '' }}</small></td>
                <td>{{ $item->record_id ?? '' }}</td>
                <td><code>{{ $item->db_table ?? '' }}</code></td>
                <td><small class="text-muted">{{ Str::limit($item->db_query ?? '', 80) }}</small></td>
              </tr>
              @empty
              <tr><td colspan="7" class="text-center text-muted py-3">No audit records found</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Pagination --}}
    @if(isset($records) && method_exists($records, 'links'))
      <div class="mt-3">{{ $records->withQueryString()->links() }}</div>
    @endif
  </div>
</div>
@endsection