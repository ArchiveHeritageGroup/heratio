@extends('theme::layouts.1col')
@section('title', 'Donor Report')
@section('body-class', 'admin reports')

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-reports::_menu')
    @include('ahg-reports::_filters', ['action' => route('reports.donors')])
  </div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-hand-holding-heart me-2"></i>Donor Report</h1>
      <div>
        <span class="badge bg-primary fs-6">{{ number_format($total) }} results</span>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-sm atom-btn-outline-success ms-2"><i class="fas fa-file-csv me-1"></i>CSV</a>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-bordered table-striped table-sm">
        <thead>
        <tbody>
          @forelse($results as $row)
            <tr>
              <td>{{ $row->id }}</td>
              <td>{{ $row->authorized_form_of_name ?? '' }}</td>
              <td>{{ $row->email ?? '' }}</td>
              <td>{{ $row->telephone ?? '' }}</td>
              <td>{{ $row->city ?? '' }}</td>
              <td>{{ $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('Y-m-d') : '' }}</td>
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
