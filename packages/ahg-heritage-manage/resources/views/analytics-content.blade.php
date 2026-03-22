@extends('theme::layouts.1col')
@section('title', 'Content Analytics')
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._admin-sidebar')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-chart-area me-2"></i>Content Analytics</h1>
      <div>
        <select class="form-select form-select-sm d-inline-block" style="width:auto" onchange="window.location.search='?days='+this.value">
          <option value="7" {{ request('days',30)==7?'selected':'' }}>Last 7 days</option>
          <option value="30" {{ request('days',30)==30?'selected':'' }}>Last 30 days</option>
          <option value="90" {{ request('days')==90?'selected':'' }}>Last 90 days</option>
        </select>
      </div>
    </div>
    <p class="text-muted">Track content growth, quality metrics, and coverage.</p>

    <div class="row mb-4">
      @foreach($stats ?? [] as $key => $value)
      <div class="col-md-3 mb-3">
        <div class="card border-primary h-100">
          <div class="card-body text-center">
            <h3 class="mb-0">{{ number_format($value) }}</h3>
            <small class="text-muted">{{ ucwords(str_replace('_', ' ', $key)) }}</small>
          </div>
        </div>
      </div>
      @endforeach
    </div>

    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-chart-area me-2"></i>Content Analytics Details</div>
      <div class="card-body">
        @if(!empty($records))
        <div class="table-responsive">
          <table class="table table-sm table-striped mb-0">
            <thead><tr>
              @foreach(array_keys((array)($records[0] ?? [])) as $col)
                <th>{{ ucwords(str_replace('_', ' ', $col)) }}</th>
              @endforeach
            </tr></thead>
            <tbody>
              @foreach($records as $row)
              <tr>@foreach((array)$row as $val)<td>{{ Str::limit($val, 60) ?: '-' }}</td>@endforeach</tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @else
        <p class="text-muted text-center py-4">No data for this period.</p>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection