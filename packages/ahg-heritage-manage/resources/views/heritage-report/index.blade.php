@extends('theme::layouts.1col')
@section('title', 'Heritage Reports')
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-chart-bar me-2"></i>Heritage Reports</h1>
      <div>
        @if(!empty($items))
          <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-sm atom-btn-outline-success"><i class="fas fa-file-csv me-1"></i>CSV</a>
          <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="btn btn-sm btn-outline-danger ms-1"><i class="fas fa-file-pdf me-1"></i>PDF</a>
        @endif
      </div>
    </div>
    <p class="text-muted">Heritage asset reporting dashboard.</p>

    {{-- Filter --}}
    <div class="card mb-3">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-filter me-2"></i>Filters</div>
      <div class="card-body">
        <form method="get" class="row g-3">
          <div class="col-md-3">
            <label class="form-label">From <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">To <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
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
            <button type="submit" class="btn atom-btn-white btn-sm"><i class="fas fa-filter me-1"></i>Filter</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-chart-bar me-2"></i>Heritage Reports</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-sm table-striped mb-0">
            <thead><tr>
              @foreach($columns ?? ['ID','Name','Class','Value','Status','Date'] as $col)
                <th>{{ $col }}</th>
              @endforeach
            </tr></thead>
            <tbody>
              @forelse($items ?? [] as $item)
              <tr>@foreach((array)$item as $val)<td>{{ Str::limit($val, 80) ?: '-' }}</td>@endforeach</tr>
              @empty
              <tr><td colspan="{{ count($columns ?? ['ID','Name','Class','Value','Status','Date']) }}" class="text-center text-muted py-3">No records found</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    @if(isset($items) && method_exists($items, 'links'))
      <div class="mt-3">{{ $items->withQueryString()->links() }}</div>
    @endif
  </div>
</div>
@endsection