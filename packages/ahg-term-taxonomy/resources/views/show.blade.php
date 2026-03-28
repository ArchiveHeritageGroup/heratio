@extends('theme::layouts.1col')

@section('title', $term->name ?? 'Term')
@section('body-class', 'view term')

@section('content')
  <div class="row">
    {{-- LEFT SIDEBAR --}}
    <div class="col-md-3">

      {{-- Browse taxonomy: collapsible heading with 3 tabs --}}
      <h2 class="d-grid">
        <button class="btn btn-lg atom-btn-white text-wrap" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-treeview" aria-expanded="true">
          Browse {{ $taxonomyName }}:
        </button>
      </h2>

      <div class="collapse show" id="collapse-treeview">
        {{-- Tab navigation --}}
        <ul class="nav nav-tabs border-0" id="treeview-menu" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="treeview-tab" data-bs-toggle="tab" data-bs-target="#treeview-pane" type="button" role="tab">Treeview</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="list-tab" data-bs-toggle="tab" data-bs-target="#list-pane" type="button" role="tab">List</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="search-tab" data-bs-toggle="tab" data-bs-target="#search-pane" type="button" role="tab">Search</button>
          </li>
        </ul>

        <div class="tab-content mb-3" id="treeview-content">

          {{-- TAB 1: Treeview --}}
          <div class="tab-pane fade show active" id="treeview-pane" role="tabpanel">
            <ul class="list-group rounded-0" style="max-height:400px;overflow-y:auto;">
              @if($broaderTerm)
                <a href="{{ route('term.show', $broaderTerm->slug) }}" class="list-group-item list-group-item-action py-2 ps-2" style="white-space:normal;">
                  <i class="fas fa-chevron-right text-muted me-1" style="font-size:.6em;"></i>{{ $broaderTerm->name }}
                </a>
              @endif
              <div class="list-group-item active ps-4">
                <i class="fas fa-caret-down me-1"></i><strong>{{ $term->name }}</strong>
              </div>
              @foreach($narrowerTerms->take(30) as $nt)
                <a href="{{ route('term.show', $nt->slug) }}" class="list-group-item list-group-item-action py-2 ps-5" style="white-space:normal;">
                  <i class="fas fa-chevron-right text-muted me-1" style="font-size:.6em;"></i>{{ $nt->name }}
                </a>
              @endforeach
              @if($narrowerTerms->count() > 30)
                <a href="#" class="list-group-item list-group-item-action text-muted small ps-5" onclick="event.preventDefault();document.getElementById('list-tab').click();">... and {{ $narrowerTerms->count() - 30 }} more</a>
              @endif
            </ul>
          </div>

          {{-- TAB 2: List (alphabetical, paginated) --}}
          <div class="tab-pane fade" id="list-pane" role="tabpanel">
            <div class="list-group list-group-flush rounded-0 border" style="max-height:400px;overflow-y:auto;">
              @foreach($listTerms as $lt)
                <a href="{{ route('term.show', $lt->slug) }}" class="list-group-item list-group-item-action py-2 {{ $lt->id == $term->id ? 'active' : '' }}" style="white-space:normal;word-wrap:break-word;">
                  {{ $lt->name }}
                </a>
              @endforeach
            </div>
            @if($listLastPage > 1)
              <nav class="p-2 bg-white border border-top-0">
                <p class="text-center mb-1 small text-muted">Page {{ $listPage }} of {{ $listLastPage }} ({{ number_format($listTotal) }} terms)</p>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                  <li class="page-item {{ $listPage <= 1 ? 'disabled' : '' }}">
                    <a class="page-link" href="{{ request()->fullUrlWithQuery(['listPage' => $listPage - 1]) }}">Previous</a>
                  </li>
                  <li class="page-item {{ $listPage >= $listLastPage ? 'disabled' : '' }}">
                    <a class="page-link" href="{{ request()->fullUrlWithQuery(['listPage' => $listPage + 1]) }}">Next</a>
                  </li>
                </ul>
              </nav>
            @endif
          </div>

          {{-- TAB 3: Search --}}
          <div class="tab-pane fade" id="search-pane" role="tabpanel">
            <form method="get" role="search" class="p-2 bg-white border" action="{{ route('term.browse', ['taxonomy' => $term->taxonomy_id]) }}">
              <div class="input-group">
                <button class="btn atom-btn-white dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                  <i class="fas fa-cog"></i>
                </button>
                <div class="dropdown-menu mt-2">
                  <div class="px-3 py-2">
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="queryField" id="qf-all" value="allLabels" checked>
                      <label class="form-check-label" for="qf-all">All labels</label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="queryField" id="qf-preferred" value="preferredLabel">
                      <label class="form-check-label" for="qf-preferred">Preferred label</label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="queryField" id="qf-usefor" value="useForLabels">
                      <label class="form-check-label" for="qf-usefor">'Use for' labels</label>
                    </div>
                  </div>
                </div>
                <input type="text" name="subquery" class="form-control" placeholder="Search {{ strtolower($taxonomyName) }}..." required>
                <input type="hidden" name="taxonomy" value="{{ $term->taxonomy_id }}">
                <button class="btn atom-btn-white" type="submit"><i class="fas fa-search"></i></button>
              </div>
            </form>
          </div>

        </div>
      </div>

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
              Related {{ config('app.ui_label_informationobject', 'Archival description') }}s ({{ number_format($relatedDescriptionsCount) }})
            </a>
          </li>
          <li class="nav-item">
            <a class="btn atom-btn-white active-primary text-wrap" href="{{ route('term.show', ['slug' => $term->slug, 'view' => 'actors']) }}">
              Related {{ config('app.ui_label_actor', 'Authority record') }}s ({{ number_format($relatedActorCount) }})
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

      {{-- ===== Elements area ===== --}}
      <section class="border-bottom mb-3">
        <h2 class="h5 mb-0 atom-section-header">
          <div class="d-flex p-3 border-bottom text-primary">
            Elements area
            @auth
              <a href="{{ route('term.edit', $term->slug) }}" class="ms-auto text-primary opacity-75" style="font-size:.75rem;" title="Edit"><i class="fas fa-pencil-alt"></i></a>
            @endauth
          </div>
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

      {{-- Admin area --}}
      @auth
        @if(auth()->user()->is_admin && $term->source_culture)
          <section class="border-bottom mb-3" id="adminArea">
            <h2 class="h5 mb-0 atom-section-header">
              <div class="d-flex p-3 border-bottom text-primary">
                Administration area
              </div>
            </h2>
            <div>
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Source language</h3>
                <div class="col-9 p-2">
                  @php
                    $displayLang = function_exists('locale_get_display_language')
                      ? locale_get_display_language($term->source_culture, app()->getLocale())
                      : $term->source_culture;
                  @endphp
                  {{ $displayLang }}
                </div>
              </div>
            </div>
          </section>
        @endif
      @endauth

      {{-- Action buttons --}}
      @auth
        @php $isAdmin = auth()->user()->is_admin; @endphp
        <ul class="actions mb-3 nav gap-2">
          {{-- Edit: any authenticated user --}}
          <li><a href="{{ route('term.edit', $term->slug) }}" class="btn atom-btn-outline-light">Edit</a></li>
          {{-- Delete: admin only --}}
          @if($isAdmin)
          <li><a href="{{ route('term.confirmDelete', $term->slug) }}" class="btn atom-btn-outline-danger">Delete</a></li>
          @endif
          {{-- Add new: any authenticated user --}}
          <li><a href="{{ route('term.create', ['taxonomy' => $term->taxonomy_id, 'parent' => $term->slug]) }}" class="btn atom-btn-outline-light">Add new</a></li>
        </ul>
      @endauth

      {{-- ===== Related archival descriptions ===== --}}
      <h1>{{ number_format($totalRelated) }} {{ config('app.ui_label_informationobject', 'Archival description') }} results for {{ $term->name }}</h1>

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
          <a href="{{ request()->fullUrlWithQuery(['sort' => $sortKey, 'page' => 1]) }}" class="btn btn-sm {{ $sort === $sortKey ? 'atom-btn-white' : 'atom-btn-white' }}">{{ $sortLabel }}</a>
        @endforeach
      </div>

      {{-- Results table --}}
      @if($relatedDescriptions->isNotEmpty())
        @foreach($relatedDescriptions as $desc)
          <article class="search-result row g-0 p-3 border-bottom">
            <div class="col-12 col-lg-2 pb-2 pb-lg-0 pe-lg-3">
              <a href="{{ url('/' . $desc->slug) }}">
                @if($desc->thumbnail)
                  <img src="{{ $desc->thumbnail }}" alt="{{ $desc->title ?: '' }}" class="img-thumbnail" style="max-height:80px;">
                @else
                  <img src="/generic-icons/blank.png" alt="" class="img-thumbnail" style="max-height:80px;">
                @endif
              </a>
            </div>
            <div class="col-12 col-lg-10 d-flex flex-column gap-1">
              <a href="{{ url('/' . $desc->slug) }}" class="h5 mb-0 text-truncate">{{ $desc->title ?: '[Untitled]' }}</a>
              <div class="d-flex flex-wrap">
                @if($desc->identifier)
                  <span class="text-primary">{{ $desc->identifier }}</span>
                @endif
                @if($desc->levelName ?? null)
                  @if($desc->identifier) <span class="text-muted mx-2">&middot;</span> @endif
                  <span class="text-muted">{{ $desc->levelName }}</span>
                @endif
                @if($desc->dateDisplay ?? null)
                  <span class="text-muted mx-2">&middot;</span>
                  <span class="text-muted">{{ $desc->dateDisplay }}</span>
                @endif
              </div>
              @if($desc->scope_and_content ?? null)
                <span class="text-muted small">{{ Str::limit(strip_tags($desc->scope_and_content), 150) }}</span>
              @endif
              @if($desc->creatorName ?? null)
                <span class="text-muted small"><i class="fas fa-user me-1"></i>{{ $desc->creatorName }}</span>
              @endif
            </div>
          </article>
        @endforeach
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

      {{-- Facet panels: aggregation of related descriptions by taxonomy --}}
      @php
        $facetTaxonomies = [
            ['id' => 12, 'label' => 'Languages'],
            ['id' => 42, 'label' => 'Places'],
            ['id' => 35, 'label' => 'Subjects'],
            ['id' => 78, 'label' => 'Genres'],
            ['id' => 54, 'label' => 'Occupations'],
        ];
      @endphp
      @foreach($facetTaxonomies as $facet)
        @php
          $facetTerms = \Illuminate\Support\Facades\DB::select("
            SELECT ti.name, s.slug, COUNT(*) AS cnt
            FROM object_term_relation otr
            JOIN term t ON t.id = otr.term_id
            JOIN term_i18n ti ON ti.id = t.id AND ti.culture = 'en'
            JOIN taxonomy tx ON tx.id = t.taxonomy_id AND tx.id = ?
            JOIN slug s ON s.object_id = t.id
            JOIN object_term_relation otr2 ON otr2.object_id = otr.object_id
            WHERE otr2.term_id = ?
            GROUP BY ti.name, s.slug
            ORDER BY cnt DESC
            LIMIT 10
          ", [$facet['id'], $term->id]);
        @endphp
        @if(!empty($facetTerms))
          <h4 class="h5 mb-2">{{ $facet['label'] }}</h4>
          <ul class="list-unstyled">
            @foreach($facetTerms as $ft)
              <li><a href="{{ route('term.show', $ft->slug) }}">{{ $ft->name }}</a> <span class="text-muted">({{ $ft->cnt }})</span></li>
            @endforeach
          </ul>
        @endif
      @endforeach
    </div>
  </div>
@endsection
