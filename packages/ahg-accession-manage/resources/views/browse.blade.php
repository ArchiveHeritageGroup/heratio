@extends('theme::layouts.1col')

@section('title', 'Browse accessions')
@section('body-class', 'browse accession')

@section('title-block')
  <h1>Browse accessions</h1>
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

  <section class="actions mb-3">
    <a href="{{ route('accession.create') }}" class="btn atom-btn-outline-light">Add new</a>
    @auth
      @if(Route::has('accession.export-csv'))
        <a href="{{ route('accession.export-csv', request()->query()) }}" class="btn atom-btn-outline-light">Export CSV</a>
      @endif
    @endauth
  </section>
@endsection
