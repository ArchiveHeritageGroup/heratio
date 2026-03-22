@extends('theme::layouts.1col')

@section('title', 'Accession valuation report')
@section('body-class', 'browse accession valuation-report')

@section('title-block')
  <h1>Accession valuation report</h1>
@endsection

@section('before-content')
  <div class="d-flex flex-wrap gap-2 mb-3">
    <div class="d-flex flex-wrap gap-2 ms-auto">
      <a href="{{ route('accession.browse') }}" class="btn btn-sm atom-btn-white">Back to browse</a>
    </div>
  </div>
@endsection

@section('content')
  @if($rows->count())
    <div class="table-responsive mb-3">
      <table class="table table-bordered table-hover mb-0">
        <thead>
          <tr>
            <th>Identifier</th>
            <th>Title</th>
            <th>Date</th>
            <th>Acquisition type</th>
            <th>Extent</th>
            <th>Appraisal</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rows as $row)
            <tr>
              <td>
                @if($row->slug)
                  <a href="{{ route('accession.show', $row->slug) }}">{{ e($row->identifier) }}</a>
                @else
                  {{ e($row->identifier) }}
                @endif
              </td>
              <td>{{ e($row->title ?? '') }}</td>
              <td>{{ $row->date ?? '' }}</td>
              <td>{{ e($row->acquisition_type ?? '') }}</td>
              <td>{{ e($row->received_extent_units ?? '') }}</td>
              <td>{{ Str::limit(strip_tags($row->appraisal ?? ''), 120) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    {{ $rows->links() }}
  @else
    <div class="alert alert-info">No accession records found.</div>
  @endif
@endsection
