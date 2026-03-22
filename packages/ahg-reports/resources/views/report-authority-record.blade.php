@extends('theme::layouts.1col')
@section('title', 'Authority Record Report')
@section('body-class', 'admin reports')

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-reports::_menu')
  </div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-users me-2"></i>Authority Record Report</h1>
      <div>
        <a href="{{ route('reports.select') }}" class="btn btn-sm atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back to Reports</a>
        @if(!empty($results))
          <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-sm atom-btn-outline-success ms-1"><i class="fas fa-file-csv me-1"></i>CSV</a>
        @endif
      </div>
    </div>

    {{-- Filter --}}
    <div class="card mb-3">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-filter me-2"></i>Filter Options</div>
      <div class="card-body">
        <form method="get" class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Culture</label>
            <select name="culture" class="form-select form-select-sm">
              <option value="en" {{ request('culture','en')=='en'?'selected':'' }}>English</option>
              <option value="af" {{ request('culture')=='af'?'selected':'' }}>Afrikaans</option>
              <option value="fr" {{ request('culture')=='fr'?'selected':'' }}>French</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Date Start</label>
            <input type="date" name="dateStart" class="form-control form-control-sm" value="{{ request('dateStart') }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">Date End</label>
            <input type="date" name="dateEnd" class="form-control form-control-sm" value="{{ request('dateEnd') }}">
          </div>
          <div class="col-md-3 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i>Search</button>
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
                @foreach($columns ?? ['ID','Identifier','Title','Created','Updated'] as $col)
                  <th>{{ $col }}</th>
                @endforeach
              </tr>
            </thead>
            <tbody>
              @forelse($results ?? [] as $row)
              <tr>
                @foreach((array)$row as $val)
                  <td>{{ Str::limit($val, 80) ?: '-' }}</td>
                @endforeach
              </tr>
              @empty
              <tr><td colspan="{{ count($columns ?? ['ID','Identifier','Title','Created','Updated']) }}" class="text-center text-muted py-3">No results found</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    @if(isset($results) && method_exists($results, 'links'))
      <div class="mt-3">{{ $results->withQueryString()->links() }}</div>
    @endif
  </div>
</div>
@endsection