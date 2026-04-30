{{--
  Archival Description Report — full ISAD(G) field dump with column toggles
  Cloned from AtoM ahgReportsPlugin reportInformationObjectSuccess.blade.php

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Browse Archival Description')
@section('body-class', 'admin reports')

@section('sidebar')
<section class="card mb-3">
  <div class="card-header"><h6 class="mb-0">{{ __('Filter options') }}</h6></div>
  <div class="card-body">
    <form method="get" action="{{ route('reports.descriptions') }}">
      <div class="mb-3">
        <label class="form-label">{{ __('Level of description') }}</label>
        <select name="level" class="form-select form-select-sm">
          <option value="">{{ __('All levels') }}</option>
          @foreach($levels as $l)
            <option value="{{ $l->id }}" {{ ($params['level'] ?? '') == $l->id ? 'selected' : '' }}>{{ e($l->name) }}</option>
          @endforeach
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">{{ __('Publication status') }}</label>
        <select name="publicationStatus" class="form-select form-select-sm">
          <option value="">{{ __('All') }}</option>
          <option value="159" {{ ($params['publicationStatus'] ?? '') == '159' ? 'selected' : '' }}>{{ __('Draft') }}</option>
          <option value="160" {{ ($params['publicationStatus'] ?? '') == '160' ? 'selected' : '' }}>{{ __('Published') }}</option>
        </select>
      </div>
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
      <button type="button" onclick="exportTableToCSV()" class="btn btn-outline-secondary btn-sm w-100">{{ __('Export to CSV') }}</button>
    </form>
  </div>
</section>
@endsection

@section('title-block')
<h1>{{ __('Browse Archival Description') }}</h1>
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
    $columns = ['Identifier','Title','Alt Title','Extent','Archival History','Acquisition','Scope','Appraisal','Accruals','Arrangement','Access','Reproduction','Physical','Finding Aids','Originals','Copies','Related','Institution','Rules','Sources','Revision','Culture','Repository','Created'];
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
          <th>{{ __('Title') }}</th>
          <th>{{ __('Alternate Title') }}</th>
          <th>{{ __('Extent And Medium') }}</th>
          <th>{{ __('Archival History') }}</th>
          <th>{{ __('Acquisition') }}</th>
          <th>{{ __('Scope And Content') }}</th>
          <th>{{ __('Appraisal') }}</th>
          <th>{{ __('Accruals') }}</th>
          <th>{{ __('Arrangement') }}</th>
          <th>{{ __('Access Conditions') }}</th>
          <th>{{ __('Reproduction Conditions') }}</th>
          <th>{{ __('Physical Characteristics') }}</th>
          <th>{{ __('Finding Aids') }}</th>
          <th>{{ __('Location Of Originals') }}</th>
          <th>{{ __('Location Of Copies') }}</th>
          <th>{{ __('Related Units') }}</th>
          <th>{{ __('Institution Responsible') }}</th>
          <th>{{ __('Rules') }}</th>
          <th>{{ __('Sources') }}</th>
          <th>{{ __('Revision History') }}</th>
          <th>{{ __('Culture') }}</th>
          <th>{{ __('Repository') }}</th>
          <th>{{ __('Created') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($results as $item)
        <tr>
          <td>@if($item->identifier)<a href="{{ url('/' . ($item->identifier ?? $item->id)) }}">{{ $item->identifier }}</a>@else - @endif</td>
          <td>{!! $item->title ?? '-' !!}</td>
          <td>{!! $item->alternate_title ?? '-' !!}</td>
          <td>{!! $item->extent_and_medium ?? '-' !!}</td>
          <td>{!! $item->archival_history ?? '-' !!}</td>
          <td>{!! $item->acquisition ?? '-' !!}</td>
          <td>{!! $item->scope_and_content ?? '-' !!}</td>
          <td>{!! $item->appraisal ?? '-' !!}</td>
          <td>{!! $item->accruals ?? '-' !!}</td>
          <td>{!! $item->arrangement ?? '-' !!}</td>
          <td>{!! $item->access_conditions ?? '-' !!}</td>
          <td>{!! $item->reproduction_conditions ?? '-' !!}</td>
          <td>{!! $item->physical_characteristics ?? '-' !!}</td>
          <td>{!! $item->finding_aids ?? '-' !!}</td>
          <td>{!! $item->location_of_originals ?? '-' !!}</td>
          <td>{!! $item->location_of_copies ?? '-' !!}</td>
          <td>{!! $item->related_units_of_description ?? '-' !!}</td>
          <td>{!! $item->institution_responsible_identifier ?? '-' !!}</td>
          <td>{!! $item->rules ?? '-' !!}</td>
          <td>{!! $item->sources ?? '-' !!}</td>
          <td>{!! $item->revision_history ?? '-' !!}</td>
          <td>{!! $item->culture ?? '-' !!}</td>
          <td>{{ $item->repository_name ?? '-' }}</td>
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
    if (cell) {
      cell.style.display = cell.style.display === 'none' ? '' : 'none';
    }
  }
}

function exportTableToCSV() {
  var table = document.getElementById('reportTable');
  var csv = [];
  var rows = table.querySelectorAll('tr');
  for (var i = 0; i < rows.length; i++) {
    var row = [];
    var cols = rows[i].querySelectorAll('td, th');
    for (var j = 0; j < cols.length; j++) {
      if (cols[j].style.display !== 'none') {
        var text = cols[j].innerText.replace(/"/g, '""');
        row.push('"' + text + '"');
      }
    }
    csv.push(row.join(','));
  }
  var csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
  var downloadLink = document.createElement('a');
  downloadLink.download = 'report_' + new Date().getTime() + '.csv';
  downloadLink.href = window.URL.createObjectURL(csvFile);
  downloadLink.style.display = 'none';
  document.body.appendChild(downloadLink);
  downloadLink.click();
  document.body.removeChild(downloadLink);
}
</script>
@endsection
