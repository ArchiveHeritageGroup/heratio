{{-- Partial: GLAM advanced search --}}
<div class="card mb-3"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-search-plus me-2"></i>Advanced Search</div><div class="card-body">
  <form method="GET" action="{{ route('glam.browse') }}">
    <div class="row g-2">
      <div class="col-md-6 mb-2"><input type="text" class="form-control" name="query" value="{{ request('query') }}" placeholder="Search..."></div>
      <div class="col-md-3 mb-2"><select class="form-select" name="type"><option value="">All Types</option></select></div>
      <div class="col-md-3 mb-2"><select class="form-select" name="repository"><option value="">All Repositories</option></select></div>
    </div>
    <div class="d-flex gap-2 mt-2"><button type="submit" class="btn atom-btn-white"><i class="fas fa-search me-1"></i>Search</button><a href="{{ route('glam.browse') }}" class="btn atom-btn-white">Reset</a></div>
  </form>
</div></div>
