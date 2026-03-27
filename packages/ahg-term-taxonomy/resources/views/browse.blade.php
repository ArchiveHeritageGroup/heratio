@extends('theme::layouts.2col')

@section('title', $taxonomyName ? $taxonomyName . ' - Terms' : 'Terms')
@section('body-class', 'taxonomy index')

@section('sidebar')
  <h2 class="d-grid">
    <button class="btn btn-lg atom-btn-white text-wrap" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-treeview" aria-expanded="true" aria-controls="collapse-treeview">
      Browse {{ $taxonomyName ?? 'Terms' }}:
    </button>
  </h2>
  <div class="collapse show" id="collapse-treeview">
    <div class="tab-content mb-3" id="treeview-content">
      <div class="tab-pane fade show active" id="treeview" role="tabpanel" aria-labelledby="treeview-tab">
        <ul class="list-group rounded-0">
          @foreach($treeTerms as $tt)
            <li class="list-group-item" data-content="{{ $tt->name }}">
              <span class="text text-truncate"><a href="{{ route('term.show', $tt->slug) }}" title="{{ $tt->name }}">{{ $tt->name }}</a></span>
            </li>
          @endforeach
        </ul>
      </div>
    </div>
  </div>
@endsection

@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x {{ $icon ?? 'fa-tag' }} me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0" aria-describedby="heading-label">
        @if($pager->getNbResults())
          Showing {{ number_format($pager->getNbResults()) }} results
        @else
          No results found
        @endif
      </h1>
      <span class="small" id="heading-label">{{ $taxonomyName ?? 'Terms' }}</span>
    </div>
  </div>
@endsection

@section('before-content')

    {{-- Search bar with field selector + sort (matching AtoM exactly) --}}
    <div class="d-flex flex-wrap gap-2 mb-3">
      <form id="inline-search" method="get" action="{{ route('term.browse') }}" class="d-flex" role="search" aria-label="{{ $taxonomyName }}">
        <input type="hidden" name="taxonomy" value="{{ $taxonomyId }}">
        <div class="input-group flex-nowrap">
          {{-- Search field selector --}}
          <button class="btn btn-sm atom-btn-white dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
            <i class="fas fa-cog" aria-hidden="true"></i>
            <span class="visually-hidden">Search options</span>
          </button>
          <div class="dropdown-menu mt-2">
            <div class="px-3 py-2">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="subqueryField" id="qf-all" value="allLabels" {{ (request('subqueryField', 'allLabels') === 'allLabels') ? 'checked' : '' }}>
                <label class="form-check-label" for="qf-all">All labels</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="subqueryField" id="qf-preferred" value="preferredLabel" {{ request('subqueryField') === 'preferredLabel' ? 'checked' : '' }}>
                <label class="form-check-label" for="qf-preferred">Preferred label</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="subqueryField" id="qf-usefor" value="useForLabels" {{ request('subqueryField') === 'useForLabels' ? 'checked' : '' }}>
                <label class="form-check-label" for="qf-usefor">"Use for" labels</label>
              </div>
            </div>
          </div>
          {{-- Search input --}}
          <input class="form-control form-control-sm" type="search" name="subquery" value="{{ request('subquery') }}" placeholder="Search {{ strtolower($taxonomyName ?? 'terms') }}" aria-label="Search {{ strtolower($taxonomyName ?? 'terms') }}">
          {{-- Reset button (only when search active) --}}
          @if(request('subquery'))
            <a href="{{ route('term.browse', ['taxonomy' => $taxonomyId]) }}" class="btn btn-sm atom-btn-white d-flex align-items-center" role="button">
              <i class="fas fa-undo" aria-hidden="true"></i>
              <span class="visually-hidden">Reset search</span>
            </a>
          @endif
          {{-- Search submit --}}
          <button class="btn btn-sm atom-btn-white" type="submit">
            <i class="fas fa-search" aria-hidden="true"></i>
            <span class="visually-hidden">Search</span>
          </button>
        </div>
      </form>

      <div class="d-flex flex-wrap gap-2 ms-auto">
        @php $activeSort = request('sort', 'alphabetic'); @endphp
        <div class="dropdown d-inline-block">
          <button class="btn btn-sm atom-btn-white dropdown-toggle text-wrap" type="button" id="sort-button" data-bs-toggle="dropdown" aria-expanded="false">
            Sort by: {{ $sortOptions[$activeSort] ?? 'Name' }}
          </button>
          <ul class="dropdown-menu dropdown-menu-end mt-2" aria-labelledby="sort-button">
            @foreach($sortOptions as $key => $label)
              <li><a class="dropdown-item {{ $activeSort === $key ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => $key, 'page' => 1]) }}">{{ $label }}</a></li>
            @endforeach
          </ul>
        </div>
      </div>
    </div>
@endsection

@section('content')

    @if($pager->getNbResults())
      @php
        $hasAnyIoCount = collect($enrichedResults)->contains(fn($d) => ($d['ioCount'] ?? 0) > 0);
        $hasAnyActorCount = collect($enrichedResults)->contains(fn($d) => ($d['actorCount'] ?? 0) > 0);
      @endphp
      <div class="table-responsive mb-3">
        <table class="table table-bordered mb-0">
          <thead>
            <tr>
              <th>{{ $taxonomyName ?? 'Term' }} term</th>
              <th>Scope note</th>
              @if($hasAnyIoCount)
                <th>{{ config('app.ui_label_informationobject', 'Archival description') }} count</th>
              @endif
              @if($hasAnyActorCount)
                <th>{{ config('app.ui_label_actor', 'Authority record') }} count</th>
              @endif
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
                @if($hasAnyIoCount)
                  <td>{{ $doc['ioCount'] ?? 0 }}</td>
                @endif
                @if($hasAnyActorCount)
                  <td>{{ $doc['actorCount'] ?? 0 }}</td>
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
      <a href="{{ route('term.create', ['taxonomy' => $taxonomyId]) }}" class="btn atom-btn-outline-light">Add new</a>
    </section>
  @endauth
@endsection
