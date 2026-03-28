@extends('ahg-theme-b5::layout')

@section('title', 'Discovery')

@section('content')
<div class="container-fluid mt-3">
  <h1><i class="fas fa-compass"></i> Discovery</h1>

  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" class="row g-2" id="discoveryForm">
        <div class="col-md-7">
          <div class="position-relative">
            <input type="text" name="q" id="discoverySearch" class="form-control form-control-lg"
                   value="{{ e($query ?? '') }}" placeholder="Search across all records..." autocomplete="off" autofocus>
            <div id="suggestDropdown" class="dropdown-menu w-100" style="display:none;"></div>
          </div>
        </div>
        <div class="col-md-3">
          <select name="type" class="form-select form-select-lg">
            <option value="all" {{ ($type ?? '') === 'all' ? 'selected' : '' }}>All types</option>
            <option value="information_object" {{ ($type ?? '') === 'information_object' ? 'selected' : '' }}>Archival descriptions</option>
            <option value="actor" {{ ($type ?? '') === 'actor' ? 'selected' : '' }}>Authority records</option>
            <option value="repository" {{ ($type ?? '') === 'repository' ? 'selected' : '' }}>Repositories</option>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary btn-lg w-100"><i class="fas fa-search"></i> Search</button>
        </div>
      </form>
    </div>
  </div>

  @if(!empty($query))
  <div class="row">
    {{-- Type facets --}}
    @if(!empty($counts))
    <div class="col-md-3">
      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Entity Type</h6></div>
        <div class="list-group list-group-flush">
          <a href="?q={{ urlencode($query) }}&type=all" class="list-group-item list-group-item-action d-flex justify-content-between {{ ($type ?? '') === 'all' ? 'active' : '' }}">
            All <span class="badge bg-secondary">{{ array_sum($counts) }}</span>
          </a>
          <a href="?q={{ urlencode($query) }}&type=information_object" class="list-group-item list-group-item-action d-flex justify-content-between {{ ($type ?? '') === 'information_object' ? 'active' : '' }}">
            Archival descriptions <span class="badge bg-secondary">{{ $counts['information_object'] ?? 0 }}</span>
          </a>
          <a href="?q={{ urlencode($query) }}&type=actor" class="list-group-item list-group-item-action d-flex justify-content-between {{ ($type ?? '') === 'actor' ? 'active' : '' }}">
            Authority records <span class="badge bg-secondary">{{ $counts['actor'] ?? 0 }}</span>
          </a>
          <a href="?q={{ urlencode($query) }}&type=repository" class="list-group-item list-group-item-action d-flex justify-content-between {{ ($type ?? '') === 'repository' ? 'active' : '' }}">
            Repositories <span class="badge bg-secondary">{{ $counts['repository'] ?? 0 }}</span>
          </a>
        </div>
      </div>
    </div>
    @endif

    {{-- Results --}}
    <div class="{{ !empty($counts) ? 'col-md-9' : 'col-12' }}">
      <p class="text-muted">{{ $total }} result(s) for "{{ e($query) }}"</p>

      @forelse($results as $result)
      <div class="card mb-2">
        <div class="card-body p-3">
          <div class="d-flex justify-content-between">
            <div>
              <h6 class="mb-1">
                @if(!empty($result->slug))
                  <a href="/{{ $result->slug }}">{{ e($result->label ?? $result->title ?? 'Untitled') }}</a>
                @else
                  {{ e($result->label ?? $result->title ?? 'Untitled') }}
                @endif
              </h6>
              <span class="badge bg-info">{{ e($result->entity_type ?? '') }}</span>
              @if(!empty($result->identifier))
                <span class="badge bg-secondary">{{ e($result->identifier) }}</span>
              @endif
            </div>
          </div>
        </div>
      </div>
      @empty
      <div class="alert alert-info">No results found.</div>
      @endforelse

      @if(($totalPages ?? 1) > 1)
      <nav class="mt-3">
        <ul class="pagination">
          @if($page > 1)
            <li class="page-item"><a class="page-link" href="?{{ http_build_query(['q' => $query, 'type' => $type, 'page' => $page - 1]) }}">Prev</a></li>
          @endif
          @for($i = max(1, $page - 3); $i <= min($totalPages, $page + 3); $i++)
            <li class="page-item {{ $i == $page ? 'active' : '' }}"><a class="page-link" href="?{{ http_build_query(['q' => $query, 'type' => $type, 'page' => $i]) }}">{{ $i }}</a></li>
          @endfor
          @if($page < $totalPages)
            <li class="page-item"><a class="page-link" href="?{{ http_build_query(['q' => $query, 'type' => $type, 'page' => $page + 1]) }}">Next</a></li>
          @endif
        </ul>
      </nav>
      @endif
    </div>
  </div>
  @endif
</div>

@push('scripts')
<script>
let debounce;
const searchInput = document.getElementById('discoverySearch');
const dropdown = document.getElementById('suggestDropdown');

searchInput?.addEventListener('input', function() {
  clearTimeout(debounce);
  if (this.value.length < 2) { dropdown.style.display = 'none'; return; }
  debounce = setTimeout(() => {
    fetch('/admin/discovery/suggest?q=' + encodeURIComponent(this.value))
      .then(r => r.json())
      .then(data => {
        if (!data.length) { dropdown.style.display = 'none'; return; }
        dropdown.innerHTML = data.map(item =>
          '<a class="dropdown-item" href="/' + (item.slug || '#') + '">' +
          '<small class="text-muted">' + item.type + '</small> ' + item.label + '</a>'
        ).join('');
        dropdown.style.display = 'block';
      });
  }, 300);
});

document.addEventListener('click', function(e) {
  if (!searchInput?.contains(e.target)) dropdown.style.display = 'none';
});
</script>
@endpush
@endsection
