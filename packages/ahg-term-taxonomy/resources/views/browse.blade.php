@extends('theme::layouts.1col')

@section('title', $taxonomyName ? $taxonomyName . ' - Terms' : 'Terms')
@section('body-class', 'browse term')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x {{ $icon ?? 'fa-tag' }} me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">
        @if($pager->getNbResults())
          Showing {{ number_format($pager->getNbResults()) }} results
        @else
          No results found
        @endif
      </h1>
      <span class="small text-muted">{{ $taxonomyName ?? 'Terms' }}</span>
    </div>
  </div>

  <div class="d-flex flex-wrap gap-2 mb-3">
    @include('ahg-core::components.inline-search', [
        'label' => 'Search ' . strtolower($taxonomyName ?? 'terms'),
        'landmarkLabel' => $taxonomyName ?? 'Term',
    ])

    <div class="d-flex flex-wrap gap-2 ms-auto">
      @include('ahg-core::components.sort-pickers', [
          'options' => $sortOptions,
          'default' => 'alphabetic',
      ])
    </div>
  </div>

  @if($pager->getNbResults())
    <div class="table-responsive mb-3">
      <table class="table table-bordered mb-0">
        <thead>
          <tr>
            <th>{{ $taxonomyName ?? 'Term' }} term</th>
            <th>Scope note</th>
            <th>Archival description count</th>
          </tr>
        </thead>
        <tbody>
          @foreach($enrichedResults as $doc)
            <tr>
              <td>
                <a href="{{ route('term.show', $doc['slug']) }}">
                  {{ $doc['name'] ?: '[Untitled]' }}
                </a>
                @if(($doc['descendantCount'] ?? 0) > 0)
                  <span class="text-muted">({{ $doc['descendantCount'] }})</span>
                @endif
                @if(!empty($doc['useFor']))
                  <p class="small text-muted mb-0">
                    Use for: {{ implode(', ', $doc['useFor']) }}
                  </p>
                @endif
              </td>
              <td>
                @if(!empty($doc['scopeNotes']))
                  @if(count($doc['scopeNotes']) === 1)
                    {{ $doc['scopeNotes'][0] }}
                  @else
                    <ul class="mb-0 ps-3">
                      @foreach($doc['scopeNotes'] as $note)
                        <li>{{ $note }}</li>
                      @endforeach
                    </ul>
                  @endif
                @endif
              </td>
              <td>{{ $doc['ioCount'] ?? 0 }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  @include('ahg-core::components.pager', ['pager' => $pager])

  @auth
    <section class="actions mb-3">
      <a href="{{ route('term.create', ['taxonomy' => $taxonomyId]) }}" class="btn atom-btn-outline-light">Add new</a>
    </section>
  @endauth
@endsection
