{{-- Activity Section Partial --}}
<section class="heritage-activity-section py-5 bg-light">
  <div class="container">
    <h2 class="text-center mb-4"><i class="fas fa-history me-2"></i>{{ __('Recent Activity') }}</h2>
    <div class="row">
      @forelse($recentActivity ?? [] as $activity)
      <div class="col-md-6 mb-3">
        <div class="card h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <strong>{{ $activity->username ?? 'System' }}</strong>
              <small class="text-muted">{{ $activity->created_at ?? '' }}</small>
            </div>
            <p class="mb-0 mt-1 text-muted">{{ $activity->action ?? '' }} - {{ $activity->entity_type ?? '' }}</p>
          </div>
        </div>
      </div>
      @empty
      <div class="col-12 text-center text-muted">No recent activity</div>
      @endforelse
    </div>
  </div>
</section>