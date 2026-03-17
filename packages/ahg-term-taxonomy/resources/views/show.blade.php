@extends('theme::layouts.1col')

@section('title', $term->name ?? 'Term')
@section('body-class', 'view term')

@section('content')
  <div class="row">
    {{-- Main content --}}
    <div class="col-md-9">

      {{-- Title --}}
      <h1>{{ $term->name }}</h1>

      {{-- Prev/Next navigation --}}
      <div class="d-flex justify-content-between mb-2">
        @if($prevTerm)
          <a href="{{ route('term.show', $prevTerm->slug) }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-chevron-left me-1"></i>{{ Str::limit($prevTerm->name, 30) }}
          </a>
        @else
          <span></span>
        @endif
        @if($nextTerm)
          <a href="{{ route('term.show', $nextTerm->slug) }}" class="btn btn-sm btn-outline-secondary">
            {{ Str::limit($nextTerm->name, 30) }}<i class="fas fa-chevron-right ms-1"></i>
          </a>
        @else
          <span></span>
        @endif
      </div>

      {{-- Breadcrumb --}}
      @if($breadcrumb->isNotEmpty())
        <nav aria-label="breadcrumb" class="mb-3">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('term.browse', ['taxonomy' => $term->taxonomy_id]) }}">{{ $taxonomyName }}</a></li>
            @foreach($breadcrumb as $ancestor)
              <li class="breadcrumb-item"><a href="{{ route('term.show', $ancestor->slug) }}">{{ $ancestor->name }}</a></li>
            @endforeach
            <li class="breadcrumb-item active">{{ $term->name }}</li>
          </ol>
        </nav>
      @endif

      @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif

      {{-- ===== Elements area ===== --}}
      <section class="border-bottom mb-3">
        <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
          <a class="text-decoration-none text-white" href="#">Elements area</a>
          @auth
            <a href="{{ route('term.edit', $term->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit"><i class="fas fa-pencil-alt"></i></a>
          @endauth
        </h2>
        <div>
          {{-- Taxonomy --}}
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Taxonomy</h3>
            <div class="col-9 p-2">
              <a href="{{ route('term.browse', ['taxonomy' => $term->taxonomy_id]) }}">{{ $taxonomyName }}</a>
            </div>
          </div>

          {{-- Code (with Google Map for Places) --}}
          @if($term->code ?? null)
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Code</h3>
              <div class="col-9 p-2">
                <code>{{ $term->code }}</code>
                @if($mapApiKey && $term->taxonomy_id == 42)
                  <img src="https://maps.googleapis.com/maps/api/staticmap?zoom=13&size=300x300&sensor=false&key={{ $mapApiKey }}&center={{ urlencode($term->code) }}" class="img-thumbnail d-block mt-2" alt="Map of {{ $term->name }}">
                @endif
              </div>
            </div>
          @endif

          {{-- Scope note(s) --}}
          @if($scopeNote && $scopeNote->content)
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Scope note(s)</h3>
              <div class="col-9 p-2">{!! nl2br(e($scopeNote->content)) !!}</div>
            </div>
          @endif

          {{-- Source note(s) --}}
          @if(!empty($sourceNotes))
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Source note(s)</h3>
              <div class="col-9 p-2">
                @foreach($sourceNotes as $note)
                  <p class="mb-1">{{ $note }}</p>
                @endforeach
              </div>
            </div>
          @endif

          {{-- Display note(s) --}}
          @if(!empty($displayNotes))
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Display note(s)</h3>
              <div class="col-9 p-2">
                @foreach($displayNotes as $note)
                  <p class="mb-1">{{ $note }}</p>
                @endforeach
              </div>
            </div>
          @endif

          {{-- Hierarchical terms (BT / NT) --}}
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Hierarchical terms</h3>
            <div class="col-9 p-2">
              @if($broaderTerm)
                <div class="mb-1">
                  {{ $term->name }}: <strong>BT</strong> <a href="{{ route('term.show', $broaderTerm->slug) }}">{{ $broaderTerm->name }}</a>
                </div>
              @endif
              @if($narrowerTerms->isNotEmpty())
                @foreach($narrowerTerms as $nt)
                  <div class="mb-1">
                    {{ $term->name }}: <strong>NT</strong> <a href="{{ route('term.show', $nt->slug) }}">{{ $nt->name }}</a>
                  </div>
                @endforeach
              @endif
              @if(!$broaderTerm && $narrowerTerms->isEmpty())
                <span class="text-muted">None</span>
              @endif
            </div>
          </div>

          {{-- Equivalent terms (UF) --}}
          @if(!empty($useFor))
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Equivalent terms</h3>
              <div class="col-9 p-2">
                @foreach($useFor as $uf)
                  <div class="mb-1">{{ $term->name }}: <strong>UF</strong> {{ $uf }}</div>
                @endforeach
              </div>
            </div>
          @endif

          {{-- Converse term --}}
          @if($converseTerm)
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Converse term</h3>
              <div class="col-9 p-2">
                <a href="{{ route('term.show', $converseTerm->slug) }}">{{ $converseTerm->name }}</a>
              </div>
            </div>
          @endif

          {{-- Associated terms (RT) --}}
          @if($associatedTerms->isNotEmpty())
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Associated terms</h3>
              <div class="col-9 p-2">
                @foreach($associatedTerms as $rt)
                  <div class="mb-1">{{ $term->name }}: <strong>RT</strong> <a href="{{ route('term.show', $rt->slug) }}">{{ $rt->name }}</a></div>
                @endforeach
              </div>
            </div>
          @endif
        </div>
      </section>

      {{-- ===== Related archival descriptions ===== --}}
      <section class="mb-3">
        <h1>
          {{ number_format($totalRelated) }} Archival description results for {{ $term->name }}
        </h1>

        <div class="d-flex flex-wrap gap-2 ms-auto mb-3">
          @foreach(['lastUpdated' => 'Date modified', 'alphabetic' => 'Title', 'referenceCode' => 'Reference code', 'date' => 'Start date'] as $sortKey => $sortLabel)
            <a href="{{ request()->fullUrlWithQuery(['sort' => $sortKey, 'page' => 1]) }}" class="btn btn-sm {{ $sort === $sortKey ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $sortLabel }}</a>
          @endforeach
        </div>

        @if($relatedDescriptions->isNotEmpty())
          <div class="table-responsive mb-3">
            <table class="table table-bordered mb-0">
              <thead>
                <tr>
                  <th>Title</th>
                  <th>Identifier</th>
                  <th>Date modified</th>
                </tr>
              </thead>
              <tbody>
                @foreach($relatedDescriptions as $desc)
                  <tr>
                    <td><a href="{{ url('/' . $desc->slug) }}">{{ $desc->title ?: '[Untitled]' }}</a></td>
                    <td><code>{{ $desc->identifier ?? '' }}</code></td>
                    <td>{{ $desc->updated_at ? \Carbon\Carbon::parse($desc->updated_at)->format('Y-m-d') : '' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          @if($lastPage > 1)
            <nav>
              <ul class="pagination pagination-sm justify-content-center">
                <li class="page-item {{ $page <= 1 ? 'disabled' : '' }}">
                  <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}">Previous</a>
                </li>
                @for($i = max(1, $page - 2); $i <= min($lastPage, $page + 2); $i++)
                  <li class="page-item {{ $i == $page ? 'active' : '' }}">
                    <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $i]) }}">{{ $i }}</a>
                  </li>
                @endfor
                <li class="page-item {{ $page >= $lastPage ? 'disabled' : '' }}">
                  <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}">Next</a>
                </li>
              </ul>
            </nav>
          @endif
        @else
          <p class="text-muted">No related archival descriptions.</p>
        @endif
      </section>

      {{-- Action buttons --}}
      @auth
        <ul class="actions mb-3 nav gap-2">
          <li><a href="{{ route('term.edit', $term->slug) }}" class="btn atom-btn-outline-light">Edit</a></li>
          <li><a href="{{ route('term.confirmDelete', $term->slug) }}" class="btn atom-btn-outline-danger">Delete</a></li>
          <li><a href="{{ route('term.create', ['taxonomy' => $term->taxonomy_id]) }}" class="btn atom-btn-outline-light">Add new</a></li>
        </ul>
      @endauth
    </div>

    {{-- ===== Right sidebar ===== --}}
    <div class="col-md-3">
      <div class="card mb-3">
        <div class="card-body">
          <h4 class="h5 mb-2">Results</h4>
          <ul class="list-unstyled"><li>{{ number_format($relatedDescriptionsCount) }}</li></ul>

          @if($broaderTerm)
            <h4 class="h5 mb-2">Broader term</h4>
            <ul class="list-unstyled">
              <li><a href="{{ route('term.show', $broaderTerm->slug) }}">{{ $broaderTerm->name }}</a></li>
            </ul>
          @endif

          <h4 class="h5 mb-2">No. narrower terms</h4>
          <ul class="list-unstyled"><li>{{ $narrowerCount }}</li></ul>

          @if($associatedTerms->isNotEmpty())
            <h4 class="h5 mb-2">Related terms</h4>
            <ul class="list-unstyled">
              @foreach($associatedTerms as $rt)
                <li><a href="{{ route('term.show', $rt->slug) }}">{{ $rt->name }}</a></li>
              @endforeach
            </ul>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
