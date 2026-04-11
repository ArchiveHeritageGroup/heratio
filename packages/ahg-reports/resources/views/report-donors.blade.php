{{--
  Donor Report — browse donors with contact info
  Cloned from AtoM ahgReportsPlugin reportDonorSuccess.blade.php

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Browse Donor Report')
@section('body-class', 'admin reports')

@section('sidebar')
<section class="card mb-3">
  <div class="card-header"><h6 class="mb-0">Filter options</h6></div>
  <div class="card-body">
    <form method="get" action="{{ route('reports.donors') }}">
      <div class="mb-3">
        <label class="form-label">Date start</label>
        <input type="date" name="dateStart" class="form-control form-control-sm" value="{{ $params['dateStart'] ?? '' }}">
      </div>
      <div class="mb-3">
        <label class="form-label">Date end</label>
        <input type="date" name="dateEnd" class="form-control form-control-sm" value="{{ $params['dateEnd'] ?? '' }}">
      </div>
      <div class="mb-3">
        <label class="form-label">Results per page</label>
        <select name="limit" class="form-select form-select-sm">
          @foreach([25, 50, 100, 500] as $lim)
            <option value="{{ $lim }}" {{ ($params['limit'] ?? 20) == $lim ? 'selected' : '' }}>{{ $lim }}</option>
          @endforeach
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-sm w-100 mb-2"><i class="fas fa-search me-1"></i>Search</button>
      <button type="button" onclick="exportTableToCSV()" class="btn btn-success btn-sm w-100">
        <i class="fas fa-download me-1"></i>Export CSV
      </button>
    </form>
  </div>
</section>
@endsection

@section('title-block')
<div class="d-flex justify-content-between align-items-center">
  <h1>Browse Donor Report</h1>
  <a href="{{ route('reports.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>
@endsection

@section('content')
@if(isset($results) && count($results) > 0)
  <div class="alert alert-info">Found {{ number_format($total) }} results</div>

  <div class="table-responsive">
    <table id="reportTable" class="table table-bordered table-striped table-sm">
      <thead class="table-dark">
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Telephone</th>
          <th>City</th>
          <th>Created</th>
          <th>Updated</th>
        </tr>
      </thead>
      <tbody>
        @foreach($results as $item)
        <tr>
          <td>{{ $item->authorized_form_of_name ?? '-' }}</td>
          <td>{{ $item->email ?? '-' }}</td>
          <td>{{ $item->telephone ?? '-' }}</td>
          <td>{{ $item->city ?? '-' }}</td>
          <td>{{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->format('Y-m-d') : '-' }}</td>
          <td>{{ $item->updated_at ? \Carbon\Carbon::parse($item->updated_at)->format('Y-m-d') : '-' }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @include('ahg-reports::_pagination')
@else
  <div class="alert alert-warning">No results found. Use the filter options to search for donors.</div>
@endif

<script>
function exportTableToCSV() {
  var table = document.getElementById('reportTable');
  var csv = [];
  var rows = table.querySelectorAll('tr');
  for (var i = 0; i < rows.length; i++) {
    var row = [], cols = rows[i].querySelectorAll('td, th');
    for (var j = 0; j < cols.length; j++) {
      if (cols[j].style.display !== 'none') { row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"'); }
    }
    csv.push(row.join(','));
  }
  var blob = new Blob([csv.join('\n')], {type: 'text/csv'});
  var link = document.createElement('a');
  link.download = 'donor_report_' + new Date().getTime() + '.csv';
  link.href = URL.createObjectURL(blob);
  link.style.display = 'none'; document.body.appendChild(link); link.click(); document.body.removeChild(link);
}
</script>
@endsection
