{{--
  Repository Report — full ISDIAH field dump with column toggles
  Cloned from AtoM ahgReportsPlugin reportRepositorySuccess.blade.php

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Browse Repository Report')
@section('body-class', 'admin reports')

@section('sidebar')
<section class="card mb-3">
  <div class="card-header"><h6 class="mb-0">{{ __('Filter options') }}</h6></div>
  <div class="card-body">
    <form method="get" action="{{ route('reports.repositories') }}">
      <div class="mb-3">
        <label class="form-label">{{ __('Date start') }}</label>
        <input type="date" name="dateStart" class="form-control form-control-sm" value="{{ $params['dateStart'] ?? '' }}">
      </div>
      <div class="mb-3">
        <label class="form-label">{{ __('Date end') }}</label>
        <input type="date" name="dateEnd" class="form-control form-control-sm" value="{{ $params['dateEnd'] ?? '' }}">
      </div>
      <div class="mb-3">
        <label class="form-label">{{ __('Date of') }}</label>
        <select name="dateOf" class="form-select form-select-sm">
          <option value="created_at" {{ ($params['dateOf'] ?? 'created_at') === 'created_at' ? 'selected' : '' }}>{{ __('Creation date') }}</option>
          <option value="updated_at" {{ ($params['dateOf'] ?? '') === 'updated_at' ? 'selected' : '' }}>{{ __('Modification date') }}</option>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">{{ __('Results per page') }}</label>
        <select name="limit" class="form-select form-select-sm">
          @foreach([10, 20, 50, 100] as $lim)
            <option value="{{ $lim }}" {{ ($params['limit'] ?? 20) == $lim ? 'selected' : '' }}>{{ $lim }}</option>
          @endforeach
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-sm w-100 mb-2">{{ __('Search') }}</button>
      <button type="button" onclick="exportTableToCSV()" class="btn btn-outline-secondary btn-sm w-100">
        <i class="fas fa-download me-1"></i>Export CSV
      </button>
    </form>
  </div>
</section>
@endsection

@section('title-block')
<h1>{{ __('Browse Repository Report') }}</h1>
<div class="mb-3">
  <a href="{{ route('reports.dashboard') }}" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left me-1"></i>Back to Reports
  </a>
</div>
@endsection

@section('content')
@if(isset($results) && count($results) > 0)
  <div class="alert alert-info">
    Found {{ number_format($total) }} results
  </div>

  <div class="mb-3" style="font-size:0.85rem">
    <strong>Show/Hide Columns:</strong><br>
    @php
    $columns = ['Identifier','Name','Desc Status','Desc Detail','Desc ID','Geocultural','Collecting','Buildings','Holdings','Finding Aids','Opening Times','Access','Disabled Access','Research','Reproduction','Public Facilities','Institution ID','Rules','Sources','Revision','Created'];
    @endphp
    @foreach($columns as $i => $col)
      <label><input type="checkbox" onclick="toggleColumn({{ $i }})" checked> {{ $col }}</label>
    @endforeach
  </div>

  <div class="table-responsive" style="max-height:600px;overflow:auto">
    <table id="reportTable" class="table table-bordered table-striped table-sm">
      <thead>
        <tr>
          <th>{{ __('Identifier') }}</th>
          <th>{{ __('Name') }}</th>
          <th>{{ __('Description Status') }}</th>
          <th>{{ __('Description Detail') }}</th>
          <th>{{ __('Description Identifier') }}</th>
          <th>{{ __('Geocultural Context') }}</th>
          <th>{{ __('Collecting Policies') }}</th>
          <th>{{ __('Buildings') }}</th>
          <th>{{ __('Holdings') }}</th>
          <th>{{ __('Finding Aids') }}</th>
          <th>{{ __('Opening Times') }}</th>
          <th>{{ __('Access Conditions') }}</th>
          <th>{{ __('Disabled Access') }}</th>
          <th>{{ __('Research Services') }}</th>
          <th>{{ __('Reproduction Services') }}</th>
          <th>{{ __('Public Facilities') }}</th>
          <th>{{ __('Description Institution Identifier') }}</th>
          <th>{{ __('Description Rules') }}</th>
          <th>{{ __('Description Sources') }}</th>
          <th>{{ __('Description Revision History') }}</th>
          <th>{{ __('Created') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($results as $item)
        <tr>
          <td>@if($item->identifier)<a href="{{ url('/repository/' . $item->id) }}">{{ $item->identifier }}</a>@else - @endif</td>
          <td>{!! $item->authorized_form_of_name ?? '-' !!}</td>
          <td>{{ $item->desc_status_id ?? '-' }}</td>
          <td>{{ $item->desc_detail_id ?? '-' }}</td>
          <td>{!! $item->desc_identifier ?? '-' !!}</td>
          <td>{!! $item->geocultural_context ?? '-' !!}</td>
          <td>{!! $item->collecting_policies ?? '-' !!}</td>
          <td>{!! $item->buildings ?? '-' !!}</td>
          <td>{!! $item->holdings ?? '-' !!}</td>
          <td>{!! $item->finding_aids ?? '-' !!}</td>
          <td>{!! $item->opening_times ?? '-' !!}</td>
          <td>{!! $item->access_conditions ?? '-' !!}</td>
          <td>{!! $item->disabled_access ?? '-' !!}</td>
          <td>{!! $item->research_services ?? '-' !!}</td>
          <td>{!! $item->reproduction_services ?? '-' !!}</td>
          <td>{!! $item->public_facilities ?? '-' !!}</td>
          <td>{!! $item->desc_institution_identifier ?? '-' !!}</td>
          <td>{!! $item->desc_rules ?? '-' !!}</td>
          <td>{!! $item->desc_sources ?? '-' !!}</td>
          <td>{!! $item->desc_revision_history ?? '-' !!}</td>
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
      if (cols[j].style.display !== 'none') {
        row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
      }
    }
    csv.push(row.join(','));
  }
  var blob = new Blob([csv.join('\n')], {type: 'text/csv'});
  var link = document.createElement('a');
  link.download = 'repository_report_' + new Date().getTime() + '.csv';
  link.href = URL.createObjectURL(blob);
  link.style.display = 'none';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}
</script>
@endsection
