{{-- Stats Section Partial --}}
<section class="heritage-stats-section py-4 bg-dark text-white">
  <div class="container">
    <div class="row text-center">
      <div class="col-md-3"><h3 class="mb-0">{{ number_format($stats['descriptions'] ?? 0) }}</h3><small>{{ __('Descriptions') }}</small></div>
      <div class="col-md-3"><h3 class="mb-0">{{ number_format($stats['digital_objects'] ?? 0) }}</h3><small>{{ __('Digital Objects') }}</small></div>
      <div class="col-md-3"><h3 class="mb-0">{{ number_format($stats['creators'] ?? 0) }}</h3><small>{{ __('Creators') }}</small></div>
      <div class="col-md-3"><h3 class="mb-0">{{ number_format($stats['repositories'] ?? 0) }}</h3><small>{{ __('Repositories') }}</small></div>
    </div>
  </div>
</section>