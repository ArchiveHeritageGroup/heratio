@extends('theme::layouts.1col')

@section('title', $term->name ?? 'Term')
@section('body-class', 'view term')

@section('content')
  <div class="row">
    {{-- Main content --}}
    <div class="col-md-9">

      <div class="multiline-header d-flex align-items-center mb-3">
        <i class="fas fa-3x {{ $icon ?? 'fa-tag' }} me-3" aria-hidden="true"></i>
        <div class="d-flex flex-column">
          <h1 class="mb-0">{{ $term->name }}</h1>
          <span class="small text-muted">{{ $taxonomyName ?? 'Term' }}</span>
        </div>
      </div>

      @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif

      {{-- Elements area --}}
      <section class="border-bottom mb-3">
        <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
          <a class="text-decoration-none text-white" href="#">Elements area</a>
        </h2>
        <div class="py-2">
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Taxonomy</h3>
            <div class="col-9 p-2">
              <a href="{{ route('term.browse', ['taxonomy' => $term->taxonomy_id]) }}">{{ $taxonomyName }}</a>
            </div>
          </div>

          @if(!empty($useFor))
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Use for</h3>
              <div class="col-9 p-2">{{ implode(', ', $useFor) }}</div>
            </div>
          @endif

          @if($scopeNote && $scopeNote->content)
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Scope note(s)</h3>
              <div class="col-9 p-2">{!! nl2br(e($scopeNote->content)) !!}</div>
            </div>
          @endif

          @if($term->code ?? null)
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Code</h3>
              <div class="col-9 p-2"><code>{{ $term->code }}</code></div>
            </div>
          @endif
        </div>
      </section>

      {{-- Related descriptions --}}
      <section class="mb-3">
        <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
          <a class="text-decoration-none text-white" href="#">{{ $term->name }} - Related archival descriptions</a>
        </h2>

        <div class="d-flex justify-content-between align-items-center py-2">
          <span>Showing {{ number_format($totalRelated) }} results</span>
          <div class="d-flex gap-2">
            <a href="{{ request()->fullUrlWithQuery(['sort' => 'lastUpdated']) }}" class="btn btn-sm {{ $sort === 'lastUpdated' ? 'btn-primary' : 'btn-outline-secondary' }}">Date modified</a>
            <a href="{{ request()->fullUrlWithQuery(['sort' => 'alphabetic']) }}" class="btn btn-sm {{ $sort === 'alphabetic' ? 'btn-primary' : 'btn-outline-secondary' }}">Title</a>
          </div>
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
          <p class="text-muted py-2">No related archival descriptions.</p>
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

    {{-- Right sidebar --}}
    <div class="col-md-3">
      <div class="card mb-3">
        <div class="card-body">
          <p><strong>Results:</strong> {{ number_format($relatedDescriptionsCount) }}</p>

          @if($broaderTerm)
            <p>
              <strong>Broader term:</strong><br>
              <a href="{{ route('term.show', $broaderTerm->slug) }}">{{ $broaderTerm->name }}</a>
            </p>
          @endif

          <p><strong>Narrower terms:</strong> {{ $narrowerCount }}</p>
        </div>
      </div>
    </div>
  </div>
@endsection
