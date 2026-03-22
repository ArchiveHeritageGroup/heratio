@extends('theme::layouts.1col')
@section('title', 'Physical Storage Report')
@section('body-class', 'admin reports')

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-reports::_menu')
    @include('ahg-reports::_filters', ['action' => route('reports.storage')])
  </div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-box me-2"></i>Physical Storage Report</h1>
      <div>
        <span class="badge bg-primary fs-6">{{ number_format($total) }} results</span>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-sm atom-btn-outline-success ms-2"><i class="fas fa-file-csv me-1"></i>CSV</a>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-bordered table-striped table-sm">
        <thead>
          <tr style="background:var(--ahg-primary);color:#fff">
            <th>#</th><th>Name</th><th>Type</th><th>Location</th><th>Created</th><th>Updated</th>
          </tr>
        </thead>
        <tbody>
          @forelse($results as $row)
            <tr>
              <td>{{ $row->id }}</td>
              <td>{{ $row->name ?? '' }}</td>
              <td>{{ $row->type_name ?? '' }}</td>
              <td>{{ $row->location ?? '' }}</td>
              <td>{{ $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('Y-m-d') : '' }}</td>
              <td>{{ $row->updated_at ? \Carbon\Carbon::parse($row->updated_at)->format('Y-m-d') : '' }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-muted text-center">No results</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @include('ahg-reports::_pagination')
  </div>
</div>
@endsection
