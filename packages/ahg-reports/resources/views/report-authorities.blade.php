{{--
  Authority Record Report — browse actor records with filters
  Cloned from AtoM ahgReportsPlugin reportAuthorityRecordSuccess.blade.php

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Browse Authority Record/Actor Report')
@section('body-class', 'admin reports')

@section('sidebar')
<section class="card mb-3">
  <div class="card-header"><h6 class="mb-0">{{ __('Filter options') }}</h6></div>
  <div class="card-body">
    <form method="get" action="{{ route('reports.authorities') }}">
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
        <label class="form-label">{{ __('Entity type') }}</label>
        <select name="entityType" class="form-select form-select-sm">
          <option value="">{{ __('All types') }}</option>
          @foreach($entityTypes as $t)
            <option value="{{ $t->id }}" {{ ($params['entityType'] ?? '') == $t->id ? 'selected' : '' }}>{{ e($t->name) }}</option>
          @endforeach
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
<h1>{{ __('Browse Authority Record/Actor Report') }}</h1>
<div class="mb-3">
  <a href="{{ route('reports.dashboard') }}" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left me-1"></i>Back to Reports
  </a>
</div>
@endsection

@section('content')
@if(!empty($results) && count($results) > 0)
  <div class="alert alert-info">
    Showing {{ count($results) }} of {{ number_format($total) }} results (Page {{ $page }} of {{ $lastPage }})
  </div>

  <table id="reportTable" class="table table-striped table-sm">
    <thead>
      <tr>
        <th>{{ __('Name') }}</th>
        <th>{{ __('Type') }}</th>
        <th>{{ __('Dates') }}</th>
        <th>{{ __('Created') }}</th>
        <th>{{ __('Updated') }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach($results as $result)
      <tr>
        <td>
          @if(!empty($result->slug))
            <a href="{{ url('/actor/' . $result->slug) }}">{{ $result->authorized_form_of_name ?? 'N/A' }}</a>
          @else
            {{ $result->authorized_form_of_name ?? 'N/A' }}
          @endif
        </td>
        <td>{{ $result->entity_type_name ?? 'N/A' }}</td>
        <td>{{ $result->dates_of_existence ?? '' }}</td>
        <td>{{ $result->created_at ? \Carbon\Carbon::parse($result->created_at)->format('Y-m-d') : 'N/A' }}</td>
        <td>{{ $result->updated_at ? \Carbon\Carbon::parse($result->updated_at)->format('Y-m-d') : 'N/A' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  @include('ahg-reports::_pagination')
@else
  <div class="alert alert-warning">No results found. Adjust your search criteria.</div>
@endif

<script>
function exportTableToCSV() {
  var table = document.getElementById('reportTable');
  if (!table) { alert('No data to export'); return; }
  var csv = [];
  var rows = table.querySelectorAll('tr');
  for (var i = 0; i < rows.length; i++) {
    var row = [], cols = rows[i].querySelectorAll('td, th');
    for (var j = 0; j < cols.length; j++) {
      var text = cols[j].innerText.replace(/"/g, '""');
      row.push('"' + text + '"');
    }
    csv.push(row.join(','));
  }
  var blob = new Blob(['\ufeff' + csv.join('\n')], {type: 'text/csv;charset=utf-8;'});
  var link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = 'authority_report_{{ date('Y-m-d') }}.csv';
  link.click();
}
</script>
@endsection
