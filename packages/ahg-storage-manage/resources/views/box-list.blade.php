@extends('theme::layouts.1col')
@section('title', 'Box List')
@section('body-class', 'browse')
@section('content')

  <div id="preview-message" class="mb-3 d-print-none">
    Print preview
    @if(isset($storage))
      <a href="{{ route('physicalobject.show', $storage->slug) }}">Close</a>
    @else
      <a href="{{ route('physicalobject.browse') }}">Close</a>
    @endif
  </div>

  <h1 class="do-print">{{ config('app.ui_label_physicalobject', 'Physical storage') }}</h1>

  @if(isset($storage))
    <h1 class="label">{{ $storage->name ?? '[Untitled]' }}</h1>
  @endif

  @if(isset($rows) && count($rows))
    <div class="table-responsive">
      <table class="table table-bordered sticky-enabled mb-0">
        <thead>
          <tr>
            <th>Reference code</th>
            <th>Title</th>
            <th>Date(s)</th>
            <th>Part of</th>
            <th>Conditions governing access</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rows as $idx => $row)
            <tr class="{{ $idx % 2 === 0 ? 'even' : 'odd' }}">
              <td>{{ $row->reference_code ?? '' }}</td>
              <td>{{ $row->title ?? '[Untitled]' }}</td>
              <td>
                @if(!empty($row->dates))
                  <ul class="m-0 ps-3">
                    @foreach($row->dates as $date)
                      <li>{{ $date->date_display ?? '' }} ({{ $date->type_name ?? '' }})</li>
                    @endforeach
                  </ul>
                @endif
              </td>
              <td>{{ $row->part_of ?? '' }}</td>
              <td>{{ $row->access_conditions ?? 'None' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div id="result-count" class="mt-2">
      Showing {{ count($rows) }} results
    </div>
  @else
    <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No records found.</div>
  @endif
@endsection
