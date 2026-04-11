{{--
  Physical Storage Report — browse physical storage objects
  Cloned from AtoM ahgReportsPlugin reportPhysicalStorageSuccess.blade.php

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Browse Physical Storage Report')
@section('body-class', 'admin reports')

@section('sidebar')
<section class="card mb-3">
  <div class="card-body">
    <a href="{{ route('reports.dashboard') }}" class="btn btn-outline-secondary btn-sm w-100">
      <i class="fas fa-arrow-left me-1"></i>Back to Reports
    </a>
  </div>
</section>
<section class="card mb-3">
  <div class="card-header"><h6 class="mb-0">Filter options</h6></div>
  <div class="card-body">
    <form method="get" action="{{ route('reports.storage') }}">
      <div class="mb-3">
        <label class="form-label">Date start</label>
        <input type="date" name="dateStart" class="form-control form-control-sm" value="{{ $params['dateStart'] ?? '' }}">
      </div>
      <div class="mb-3">
        <label class="form-label">Date end</label>
        <input type="date" name="dateEnd" class="form-control form-control-sm" value="{{ $params['dateEnd'] ?? '' }}">
      </div>
      <div class="mb-3">
        <label class="form-label">Date of</label>
        <select name="dateOf" class="form-select form-select-sm">
          <option value="created_at" {{ ($params['dateOf'] ?? 'created_at') === 'created_at' ? 'selected' : '' }}>Creation date</option>
          <option value="updated_at" {{ ($params['dateOf'] ?? '') === 'updated_at' ? 'selected' : '' }}>Modification date</option>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">Results per page</label>
        <select name="limit" class="form-select form-select-sm">
          @foreach([10, 20, 50, 100] as $lim)
            <option value="{{ $lim }}" {{ ($params['limit'] ?? 20) == $lim ? 'selected' : '' }}>{{ $lim }}</option>
          @endforeach
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-sm w-100 mb-2">Search</button>
      <button type="button" onclick="exportTableToCSV()" class="btn btn-outline-secondary btn-sm w-100">
        <i class="fas fa-download me-1"></i>Export CSV
      </button>
    </form>
  </div>
</section>
@endsection

@section('title-block')
<h1>Browse Physical Storage Report</h1>
@endsection

@section('content')
@if(isset($results) && count($results) > 0)
  <div class="alert alert-info">Found {{ number_format($total) }} results</div>

  <div class="mb-3" style="font-size:0.85rem">
    <strong>Show/Hide Columns:</strong><br>
    @php $columns = ['Name','Location','Type','Culture','Created']; @endphp
    @foreach($columns as $i => $col)
      <label><input type="checkbox" onclick="toggleColumn({{ $i }})" checked> {{ $col }}</label>
    @endforeach
  </div>

  <div class="table-responsive" style="max-height:600px;overflow:auto">
    <table id="reportTable" class="table table-bordered table-striped table-sm">
      <thead>
        <tr>
          <th>Name</th>
          <th>Location</th>
          <th>Type</th>
          <th>Culture</th>
          <th>Created</th>
        </tr>
      </thead>
      <tbody>
        @foreach($results as $item)
        <tr>
          <td>@if($item->name)<a href="{{ url('/physicalobject/' . $item->id) }}">{{ $item->name }}</a>@else - @endif</td>
          <td>{!! $item->location ?? '-' !!}</td>
          <td>{{ $item->type_name ?? '-' }}</td>
          <td>{{ $item->culture ?? '-' }}</td>
          <td>{{ $item->created_at ?? '-' }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @include('ahg-reports::_pagination')
@else
  <div class="alert alert-warning">No results found.</div>
@endif

<script>
function toggleColumn(colNum) {
  var table = document.getElementById('reportTable');
  var rows = table.getElementsByTagName('tr');
  for (var i = 0; i < rows.length; i++) {
    var cell = rows[i].cells[colNum];
    if (cell) { cell.style.display = cell.style.display === 'none' ? '' : 'none'; }
  }
}
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
  link.download = 'physical_storage_report_' + new Date().getTime() + '.csv';
  link.href = URL.createObjectURL(blob);
  link.style.display = 'none'; document.body.appendChild(link); link.click(); document.body.removeChild(link);
}
</script>
@endsection
