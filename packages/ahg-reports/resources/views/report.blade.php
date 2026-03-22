@extends('theme::layouts.1col')
@section('title', $reportName ?? 'Report')
@section('body-class', 'admin reports')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-reports::_menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-chart-bar me-2"></i>{{ $reportName ?? 'Report' }}</h1>
      <div class="btn-group">
        @if(!empty($results))
          <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-sm atom-btn-outline-success"><i class="fas fa-file-csv me-1"></i>CSV</a>
          <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="btn btn-sm btn-outline-danger"><i class="fas fa-file-pdf me-1"></i>PDF</a>
        @endif
      </div>
    </div>

    @if(!empty($reportDescription))
      <p class="text-muted">{{ $reportDescription }}</p>
    @endif

    {{-- Filters --}}
    <div class="card mb-3">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-filter me-2"></i>Filters</div>
      <div class="card-body">
        <form method="get" class="row g-3">
          @if(isset($repositories))
          <div class="col-md-3">
            <label class="form-label">Repository <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="repository_id" class="form-select form-select-sm">
              <option value="">All repositories</option>
              @foreach($repositories ?? [] as $repo)
                <option value="{{ $repo->id }}" {{ request('repository_id') == $repo->id ? 'selected' : '' }}>{{ $repo->name }}</option>
              @endforeach
            </select>
          </div>
          @endif
          <div class="col-md-2">
            <label class="form-label">From <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
          </div>
          <div class="col-md-2">
            <label class="form-label">To <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
          </div>
          <div class="col-md-2">
            <label class="form-label">Per Page <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="limit" class="form-select form-select-sm">
              <option value="25" {{ request('limit',25)==25?'selected':'' }}>25</option>
              <option value="50" {{ request('limit')==50?'selected':'' }}>50</option>
              <option value="100" {{ request('limit')==100?'selected':'' }}>100</option>
            </select>
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn atom-btn-white btn-sm"><i class="fas fa-filter me-1"></i>Filter</button>
          </div>
        </form>
      </div>
    </div>

    {{-- Summary --}}
    @if(!empty($summary))
    <div class="row mb-3">
      @foreach($summary as $key => $value)
      <div class="col-md-3 mb-2">
        <div class="card text-center"><div class="card-body py-2">
          <h4 class="mb-0">{{ number_format($value) }}</h4>
          <small class="text-muted">{{ ucwords(str_replace('_', ' ', $key)) }}</small>
        </div></div>
      </div>
      @endforeach
    </div>
    @endif

    {{-- Results Table --}}
    <div class="card">
      <div class="card-body p-0">
        @if(empty($results))
          <div class="alert alert-info m-3"><i class="fas fa-info-circle me-2"></i>No results found. Try adjusting your filters.</div>
        @else
        <div class="table-responsive">
          <table class="table table-bordered table-sm table-striped mb-0">
            <thead>
              <tr>
                @foreach(array_keys((array)($results[0] ?? [])) as $col)
                  <th>{{ ucwords(str_replace('_', ' ', $col)) }}</th>
                @endforeach
              </tr>
            </thead>
            <tbody>
              @foreach($results as $row)
              <tr>
                @foreach((array)$row as $col => $val)
                <td>
                  @if(in_array($col, ['created_at','updated_at','date']))
                    {{ $val ? \Carbon\Carbon::parse($val)->format('Y-m-d H:i') : '-' }}
                  @elseif(in_array($col, ['count','record_count']))
                    {{ number_format($val) }}
                  @else
                    {{ Str::limit($val, 80) ?: '-' }}
                  @endif
                </td>
                @endforeach
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @endif
      </div>
    </div>

    @if(isset($records) && method_exists($records, 'links'))
      <div class="mt-3">{{ $records->withQueryString()->links() }}</div>
    @endif
  </div>
</div>
@endsection