@extends('theme::layouts.1col')

@section('title', 'Browse ' . mb_strtolower(config('app.ui_label_accession', 'Accession')) . 's')
@section('body-class', 'browse accession')

@section('title-block')
  <h1>Browse {{ mb_strtolower(config('app.ui_label_accession', 'Accession')) }}s</h1>
@endsection

@section('before-content')
  <div class="d-flex flex-wrap gap-2 mb-3">
    @include('ahg-core::components.inline-search', [
        'label' => 'Search accessions',
        'landmarkLabel' => 'Accession',
    ])

    <div class="d-flex flex-wrap gap-2 ms-auto">
      @include('ahg-core::components.sort-pickers', [
          'options' => $sortOptions,
          'default' => 'lastUpdated',
      ])
    </div>
  </div>
@endsection

@section('content')
  <div class="table-responsive mb-3">
    <table class="table table-bordered mb-0">
      <thead>
        <tr>
          <th>Accession number</th>
          <th>Title</th>
          <th>Acquisition date</th>
          <th>Acquisition type</th>
          <th>Resource type</th>
          <th>Status</th>
          <th>Priority</th>
          @if(request('sort') === 'lastUpdated')
            <th>Updated</th>
          @endif
        </tr>
      </thead>
      <tbody>
        @foreach($pager->getResults() as $doc)
          <tr>
            <td class="w-20">
              <a href="{{ route('accession.show', $doc['slug']) }}">
                {{ $doc['identifier'] ?: '—' }}
              </a>
            </td>
            <td>
              <a href="{{ route('accession.show', $doc['slug']) }}">
                {{ $doc['name'] ?: '[Untitled]' }}
              </a>
            </td>
            <td class="w-20">
              {{ $doc['accession_date'] ? \Carbon\Carbon::parse($doc['accession_date'])->format('Y-m-d') : '' }}
            </td>
            <td>
              @if(!empty($doc['acquisition_type_id']) && isset($termNames[$doc['acquisition_type_id']]))
                {{ $termNames[$doc['acquisition_type_id']] }}
              @endif
            </td>
            <td>
              @if(!empty($doc['resource_type_id']) && isset($termNames[$doc['resource_type_id']]))
                {{ $termNames[$doc['resource_type_id']] }}
              @endif
            </td>
            <td>
              @if(!empty($doc['processing_status_id']) && isset($termNames[$doc['processing_status_id']]))
                <span class="badge bg-info text-dark">{{ $termNames[$doc['processing_status_id']] }}</span>
              @endif
            </td>
            <td>
              @if(!empty($doc['processing_priority_id']) && isset($termNames[$doc['processing_priority_id']]))
                <span class="badge bg-warning text-dark">{{ $termNames[$doc['processing_priority_id']] }}</span>
              @endif
            </td>
            @if(request('sort') === 'lastUpdated')
              <td class="w-20">
                {{ $doc['updated_at'] ? \Carbon\Carbon::parse($doc['updated_at'])->format('Y-m-d H:i') : '' }}
              </td>
            @endif
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endsection

@section('after-content')
  @include('ahg-core::components.pager', ['pager' => $pager])

  <section class="actions mb-3 d-flex flex-wrap gap-2">
    <a href="{{ route('accession.create') }}" class="btn atom-btn-outline-light">Add new</a>
    <a href="{{ route('accession.export-csv') }}" class="btn atom-btn-outline-light"><i class="fas fa-download me-1"></i>Export CSV</a>
  </section>
@endsection
