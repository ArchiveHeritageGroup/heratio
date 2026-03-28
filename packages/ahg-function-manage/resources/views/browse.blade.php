@extends('theme::layouts.1col')

@section('title', 'Browse functions')
@section('body-class', 'functionManage browse')

@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-tools me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0" aria-describedby="heading-label">
        @if($pager->getNbResults())
          Showing {{ number_format($pager->getNbResults()) }} results
        @else
          No results found
        @endif
      </h1>
      <span class="small" id="heading-label">Function</span>
    </div>
  </div>
@endsection

@section('before-content')
  <div class="d-flex flex-wrap gap-2 mb-3">
    @include('ahg-core::components.inline-search', [
        'label' => 'Search functions',
        'landmarkLabel' => 'Function',
    ])

    <div class="d-flex flex-wrap gap-2 ms-auto">
      @include('ahg-core::components.sort-pickers', [
          'options' => ['lastUpdated' => 'Date modified', 'alphabetic' => 'Name', 'identifier' => 'Identifier'],
          'default' => 'alphabetic',
      ])

    </div>
  </div>
@endsection

@section('content')
  @if($pager->getNbResults())
    <div class="table-responsive mb-3">
      <table class="table table-bordered mb-0">
        <thead>
          <tr>
            <th>Name</th>
            <th>Classification</th>
            <th>Dates</th>
            @if(request('sort', 'alphabetic') === 'alphabetic')
              <th>Type</th>
            @else
              <th>Updated</th>
            @endif
          </tr>
        </thead>
        <tbody>
          @foreach($enrichedResults as $doc)
            <tr>
              <td>
                <a href="{{ route('function.show', $doc['slug']) }}" title="{{ $doc['name'] ?: '[Untitled]' }}">
                  {{ $doc['name'] ?: '[Untitled]' }}
                </a>
              </td>
              <td>{{ $doc['classification'] ?? '' }}</td>
              <td>{{ $doc['dates'] ?? '' }}</td>
              @if(request('sort', 'alphabetic') === 'alphabetic')
                <td>{{ $doc['type_name'] ?? '' }}</td>
              @else
                <td>{{ !empty($doc['updated_at']) ? \Carbon\Carbon::parse($doc['updated_at'])->format('F j, Y g:i A') : '' }}</td>
              @endif
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endsection

@section('after-content')
  @include('ahg-core::components.pager', ['pager' => $pager])

  @auth
    <section class="actions mb-3">
      <a href="{{ route('function.create') }}" class="btn atom-btn-outline-light">Add new</a>
    </section>
  @endauth
@endsection
