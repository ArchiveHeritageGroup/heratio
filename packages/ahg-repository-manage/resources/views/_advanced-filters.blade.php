{{-- Partial: Repository advanced filters --}}
<div class="card mb-3"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-filter me-2"></i>Advanced Filters</div><div class="card-body">
  <form method="GET" action="{{ route('repository.browse') }}">
    <div class="row g-2">
      <div class="col-md-4 mb-2"><label class="form-label small">Type</label><select class="form-select form-select-sm" name="type"><option value="">All</option></select></div>
      <div class="col-md-4 mb-2"><label class="form-label small">Region</label><select class="form-select form-select-sm" name="region"><option value="">All</option></select></div>
      <div class="col-md-4 mb-2"><label class="form-label small">Status</label><select class="form-select form-select-sm" name="status"><option value="">All</option></select></div>
    </div>
    <button type="submit" class="btn btn-sm atom-btn-white mt-2"><i class="fas fa-search me-1"></i>Apply</button>
  </form>
</div></div>
