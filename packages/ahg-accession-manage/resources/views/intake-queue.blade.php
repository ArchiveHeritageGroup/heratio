@extends('theme::layouts.1col')

@section('title', 'Accession intake queue')
@section('body-class', 'browse accession intake-queue')

@section('title-block')
  <h1>Accession intake queue</h1>
@endsection

@section('before-content')
  <div class="d-flex flex-wrap gap-2 mb-3">
    <div class="d-flex flex-wrap gap-2 ms-auto">
      <a href="{{ route('accession.browse') }}" class="btn btn-sm atom-btn-white">Back to browse</a>
      <a href="{{ route('accession.create') }}" class="btn btn-sm atom-btn-white">Add new</a>
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
            <th>Status</th>
            <th>Priority</th>
            <th>Updated</th>
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
              <td>{{ e($row->status_name ?? '') }}</td>
              <td>{{ e($row->priority_name ?? '') }}</td>
              <td>{{ $row->updated_at ? \Carbon\Carbon::parse($row->updated_at)->format('Y-m-d') : '' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    {{ $rows->links() }}
  @else
    <div class="alert alert-info">No accessions in the intake queue.</div>
  @endif
@endsection
