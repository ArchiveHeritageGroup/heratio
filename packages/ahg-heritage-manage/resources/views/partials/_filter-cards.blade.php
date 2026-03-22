{{-- Filter Cards Partial --}}
<div class="row heritage-filter-cards mb-4">
  @foreach($filterOptions ?? [] as $filter)
  <div class="col-md-3 mb-3">
    <div class="card h-100 border-primary">
      <div class="card-body text-center">
        <i class="fas {{ $filter['icon'] ?? 'fa-filter' }} fa-2x text-primary mb-2"></i>
        <h6>{{ $filter['label'] ?? '' }}</h6>
        <span class="badge bg-primary">{{ count($filter['values'] ?? []) }}</span>
      </div>
    </div>
  </div>
  @endforeach
</div>