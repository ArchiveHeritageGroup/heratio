@extends('theme::layouts.1col')

@section('title', $term->name ?? 'Term')
@section('body-class', 'view term')

@section('content')
  <div class="row">
    {{-- LEFT SIDEBAR --}}
    <div class="col-md-3">
      {{-- Term hierarchy treeview --}}
      <div class="card mb-3">
        <div class="card-header py-2"><i class="fas fa-sitemap me-1"></i> Hierarchy</div>
        <div class="card-body p-2" style="max-height:400px;overflow-y:auto;">
          @if($broaderTerm)
            <div class="ms-0 mb-1">
              <i class="fas fa-chevron-right text-muted me-1" style="font-size:.6em;"></i>
              <a href="{{ route('term.show', $broaderTerm->slug) }}" class="small">{{ $broaderTerm->name }}</a>
            </div>
          @endif
          <div class="ms-3 mb-1">
            <i class="fas fa-caret-down text-primary me-1"></i>
            <strong class="small">{{ $term->name }}</strong>
          </div>
          @if($narrowerTerms->isNotEmpty())
            @foreach($narrowerTerms->take(20) as $nt)
              <div class="ms-5 mb-1">
                <i class="fas fa-chevron-right text-muted me-1" style="font-size:.6em;"></i>
                <a href="{{ route('term.show', $nt->slug) }}" class="small">{{ $nt->name }}</a>
              </div>
            @endforeach
            @if($narrowerTerms->count() > 20)
              <div class="ms-5 text-muted small">... and {{ $narrowerTerms->count() - 20 }} more</div>
            @endif
          @endif
        </div>
      </div>

      {{-- Facets: Narrow your results --}}
      @if($totalRelated > 0)
        <h2 class="d-grid">
          <button class="btn btn-lg atom-btn-white collapsed text-wrap" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-aggregations" aria-expanded="false">
            Narrow your results by:
          </button>
        </h2>
        <div class="collapse" id="collapse-aggregations">
          {{-- These would ideally come from Elasticsearch aggregations. For now, show links to filtered browse. --}}
          @if($term->taxonomy_id != 42)
            <div class="card mb-2"><div class="card-header py-1 small">Places</div>
              <div class="card-body p-2 small"><a href="{{ route('term.browse', ['taxonomy' => 42]) }}">Browse places</a></div>
            </div>
          @endif
          @if($term->taxonomy_id != 35)
            <div class="card mb-2"><div class="card-header py-1 small">Subjects</div>
              <div class="card-body p-2 small"><a href="{{ route('term.browse', ['taxonomy' => 35]) }}">Browse subjects</a></div>
            </div>
          @endif
          @if($term->taxonomy_id != 78)
            <div class="card mb-2"><div class="card-header py-1 small">Genre</div>
              <div class="card-body p-2 small"><a href="{{ route('term.browse', ['taxonomy' => 78]) }}">Browse genres</a></div>
            </div>
          @endif
        </div>
      @endif
    </div>

    {{-- MAIN CONTENT --}}
    <div class="col-md-6">

      {{-- Title --}}
      <h1>{{ $term->name }}</h1>

      {{-- #3 Navigate Related tabs --}}
      <nav>
        <ul class="nav nav-pills mb-3 d-flex gap-2">
          <li class="nav-item">
            <a class="btn atom-btn-white active-primary text-wrap active" href="{{ route('term.show', $term->slug) }}">
              Related Archival descriptions ({{ number_format($relatedDescriptionsCount) }})
            </a>
          </li>
          <li class="nav-item">
            <a class="btn atom-btn-white active-primary text-wrap" href="{{ route('term.show', ['slug' => $term->slug, 'view' => 'actors']) }}">
              Related Authority records ({{ number_format($relatedActorCount) }})
            </a>
          </li>
        </ul>
      </nav>

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
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Taxonomy</h3>
            <div class="col-9 p-2"><a href="{{ route('term.browse', ['taxonomy' => $term->taxonomy_id]) }}">{{ $taxonomyName }}</a></div>
          </div>
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
          @if($scopeNote && $scopeNote->content)
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Scope note(s)</h3>
              <div class="col-9 p-2">{!! nl2br(e($scopeNote->content)) !!}</div>
            </div>
          @endif
          @if(!empty($sourceNotes))
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Source note(s)</h3>
              <div class="col-9 p-2">@foreach($sourceNotes as $note)<p class="mb-1">{{ $note }}</p>@endforeach</div>
            </div>
          @endif
          @if(!empty($displayNotes))
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Display note(s)</h3>
              <div class="col-9 p-2">@foreach($displayNotes as $note)<p class="mb-1">{{ $note }}</p>@endforeach</div>
            </div>
          @endif
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Hierarchical terms</h3>
            <div class="col-9 p-2">
              @if($broaderTerm)
                <div class="mb-1">{{ $term->name }}: <strong>BT</strong> <a href="{{ route('term.show', $broaderTerm->slug) }}">{{ $broaderTerm->name }}</a></div>
              @endif
              @foreach($narrowerTerms as $nt)
                <div class="mb-1">{{ $term->name }}: <strong>NT</strong> <a href="{{ route('term.show', $nt->slug) }}">{{ $nt->name }}</a></div>
              @endforeach
            </div>
          </div>
          @if(!empty($useFor))
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Equivalent terms</h3>
              <div class="col-9 p-2">@foreach($useFor as $uf)<div class="mb-1">{{ $term->name }}: <strong>UF</strong> {{ $uf }}</div>@endforeach</div>
            </div>
          @endif
          @if($converseTerm)
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Converse term</h3>
              <div class="col-9 p-2"><a href="{{ route('term.show', $converseTerm->slug) }}">{{ $converseTerm->name }}</a></div>
            </div>
          @endif
          @if($associatedTerms->isNotEmpty())
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Associated terms</h3>
              <div class="col-9 p-2">@foreach($associatedTerms as $rt)<div class="mb-1">{{ $term->name }}: <strong>RT</strong> <a href="{{ route('term.show', $rt->slug) }}">{{ $rt->name }}</a></div>@endforeach</div>
            </div>
          @endif
        </div>
      </section>

      {{-- Action buttons --}}
      @auth
        <ul class="actions mb-3 nav gap-2">
          <li><a href="{{ route('term.edit', $term->slug) }}" class="btn atom-btn-outline-light">Edit</a></li>
          <li><a href="{{ route('term.confirmDelete', $term->slug) }}" class="btn atom-btn-outline-danger">Delete</a></li>
          <li><a href="{{ route('term.create', ['taxonomy' => $term->taxonomy_id, 'parent' => $term->slug]) }}" class="btn atom-btn-outline-light">Add new</a></li>
        </ul>
      @endauth

      {{-- ===== Related archival descriptions ===== --}}
      <h1>{{ number_format($totalRelated) }} Archival description results for {{ $term->name }}</h1>

      {{-- #22 Only direct filter --}}
      @if($onlyDirect)
        <div class="d-flex flex-wrap gap-2 mb-2">
          @php $removeParams = request()->except(['onlyDirect', 'page']); @endphp
          <a href="{{ route('term.show', array_merge(['slug' => $term->slug], $removeParams)) }}" class="btn btn-sm atom-btn-white filter-tag d-flex align-items-center">
            <span class="visually-hidden">Remove filter:</span>
            <span class="text-truncate">Only results directly related</span>
            <i class="fas fa-times ms-2"></i>
          </a>
        </div>
      @elseif($directCount < $totalRelated && $directCount > 0)
        <div class="d-grid d-sm-flex gap-2 align-items-center p-3 border-bottom mb-2">
          {{ number_format($directCount) }} results directly related
          <a class="btn btn-sm atom-btn-white ms-auto text-wrap" href="{{ request()->fullUrlWithQuery(['onlyDirect' => 1, 'page' => 1]) }}">
            <i class="fas fa-search me-1"></i>Exclude narrower terms
          </a>
        </div>
      @endif

      {{-- Sort options --}}
      <div class="d-flex flex-wrap gap-2 ms-auto mb-3">
        @foreach(['lastUpdated' => 'Date modified', 'alphabetic' => 'Title', 'referenceCode' => 'Reference code', 'date' => 'Start date'] as $sortKey => $sortLabel)
          <a href="{{ request()->fullUrlWithQuery(['sort' => $sortKey, 'page' => 1]) }}" class="btn btn-sm {{ $sort === $sortKey ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $sortLabel }}</a>
        @endforeach
      </div>

      {{-- Results table --}}
      @if($relatedDescriptions->isNotEmpty())
        <div class="table-responsive mb-3">
          <table class="table table-bordered mb-0">
            <thead><tr><th>Title</th><th>Identifier</th><th>Date modified</th></tr></thead>
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
          <nav><ul class="pagination pagination-sm justify-content-center">
            <li class="page-item {{ $page <= 1 ? 'disabled' : '' }}"><a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}">Previous</a></li>
            @for($i = max(1, $page - 2); $i <= min($lastPage, $page + 2); $i++)
              <li class="page-item {{ $i == $page ? 'active' : '' }}"><a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $i]) }}">{{ $i }}</a></li>
            @endfor
            <li class="page-item {{ $page >= $lastPage ? 'disabled' : '' }}"><a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}">Next</a></li>
          </ul></nav>
        @endif
      @else
        <p class="text-muted">No related archival descriptions.</p>
      @endif
    </div>

    {{-- RIGHT SIDEBAR --}}
    <div class="col-md-3">
      {{-- #34-35 SKOS Import/Export --}}
      @auth
        <h4 class="h5 mb-2">Import</h4>
        <ul class="list-unstyled">
          <li><a href="{{ url('/term/import/skos?taxonomy=' . $term->taxonomy_id) }}"><i class="fas fa-download me-1"></i>SKOS</a></li>
        </ul>
      @endauth
      <h4 class="h5 mb-2">Export</h4>
      <ul class="list-unstyled">
        <li><a href="{{ url('/term/export/skos?taxonomy=' . $term->taxonomy_id) }}"><i class="fas fa-upload me-1"></i>SKOS</a></li>
      </ul>

      <h4 class="h5 mb-2">Results</h4>
      <ul class="list-unstyled"><li>{{ number_format($relatedDescriptionsCount) }}</li></ul>

      @if($broaderTerm)
        <h4 class="h5 mb-2">Broader term</h4>
        <ul class="list-unstyled"><li><a href="{{ route('term.show', $broaderTerm->slug) }}">{{ $broaderTerm->name }}</a></li></ul>
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
@endsection
