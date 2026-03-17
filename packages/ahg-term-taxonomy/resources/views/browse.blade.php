@extends('theme::layouts.1col')

@section('title', $taxonomyName ? $taxonomyName . ' - Terms' : 'Terms')
@section('body-class', 'browse term')

@section('content')
<div class="row">
  {{-- LEFT SIDEBAR: Treeview --}}
  <div class="col-md-3">
    <h2 class="d-grid">
      <button class="btn btn-lg atom-btn-white text-wrap" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-treeview" aria-expanded="true">
        Browse {{ $taxonomyName ?? 'Terms' }}:
      </button>
    </h2>
    <div class="collapse show" id="collapse-treeview">
      <ul class="list-group rounded-0" style="max-height:500px;overflow-y:auto;">
        @foreach($treeTerms as $tt)
          <a href="{{ route('term.show', $tt->slug) }}" class="list-group-item list-group-item-action py-2" style="white-space:normal;">
            {{ $tt->name }}
          </a>
        @endforeach
      </ul>
    </div>
  </div>

  {{-- MAIN CONTENT --}}
  <div class="col-md-9">
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

    {{-- Search bar with field selector + sort --}}
    <div class="d-flex flex-wrap gap-2 mb-3">
      <form method="get" action="{{ route('term.browse') }}" class="d-flex">
        <input type="hidden" name="taxonomy" value="{{ $taxonomyId }}">
        <div class="input-group input-group-sm">
          <button class="btn atom-btn-white dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
            <i class="fas fa-cog"></i>
          </button>
          <div class="dropdown-menu mt-2">
            <div class="px-3 py-2">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="queryField" id="qf-all" value="allLabels" {{ (request('queryField', 'allLabels') === 'allLabels') ? 'checked' : '' }}>
                <label class="form-check-label" for="qf-all">All labels (boosted)</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="queryField" id="qf-preferred" value="preferredLabel" {{ request('queryField') === 'preferredLabel' ? 'checked' : '' }}>
                <label class="form-check-label" for="qf-preferred">Preferred label</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="queryField" id="qf-usefor" value="useForLabels" {{ request('queryField') === 'useForLabels' ? 'checked' : '' }}>
                <label class="form-check-label" for="qf-usefor">"Use for" labels</label>
              </div>
            </div>
          </div>
          <input type="text" class="form-control" name="subquery" placeholder="Search {{ strtolower($taxonomyName ?? 'terms') }}..." value="{{ request('subquery') }}">
          <button class="btn atom-btn-white" type="submit"><i class="fas fa-search"></i></button>
        </div>
      </form>

      <div class="d-flex flex-wrap gap-2 ms-auto align-items-center">
        @include('ahg-core::components.sort-pickers', [
            'options' => $sortOptions,
            'default' => 'alphabetic',
        ])
        @php $currentDir = request('dir', 'asc'); @endphp
        <a href="{{ request()->fullUrlWithQuery(['dir' => $currentDir === 'asc' ? 'desc' : 'asc', 'page' => 1]) }}" class="btn btn-sm atom-btn-white" title="{{ $currentDir === 'asc' ? 'Ascending' : 'Descending' }}">
          <i class="fas fa-sort-alpha-{{ $currentDir === 'asc' ? 'down' : 'up' }}"></i>
        </a>
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
              <th>Authority record count</th>
            </tr>
          </thead>
          <tbody>
            @foreach($enrichedResults as $doc)
              <tr>
                <td>
                  <a href="{{ route('term.show', $doc['slug']) }}" @if($doc['isProtected'] ?? false) class="text-muted" @endif>
                    {{ $doc['name'] ?: '[Untitled]' }}
                  </a>
                  @if($doc['isProtected'] ?? false)
                    <i class="fas fa-lock text-muted ms-1" style="font-size:.7em;" title="Protected term"></i>
                  @endif
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
                <td>{{ $doc['actorCount'] ?? 0 }}</td>
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
  </div>
</div>
@endsection
