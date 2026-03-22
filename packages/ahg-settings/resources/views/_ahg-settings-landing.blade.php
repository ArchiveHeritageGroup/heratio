{{-- AHG Settings Landing partial — settings tile grid --}}
<div class="row">
  @foreach($sections ?? [] as $key => $section)
    <div class="col-lg-4 col-md-6 mb-4">
      <a href="{{ url('/admin/settings/' . ($section['route'] ?? $key)) }}" class="text-decoration-none">
        <div class="card h-100 shadow-sm settings-tile">
          <div class="card-body text-center py-4">
            <div class="mb-3"><i class="fas {{ $section['icon'] ?? 'fa-cog' }} fa-3x text-primary"></i></div>
            <h5 class="card-title text-dark">{{ $section['label'] ?? ucfirst($key) }}</h5>
            <p class="card-text text-muted small">{{ $section['description'] ?? '' }}</p>
          </div>
          <div class="card-footer bg-white border-0 text-center pb-4">
            <span class="btn atom-btn-white"><i class="fas fa-cog"></i> Configure</span>
          </div>
        </div>
      </a>
    </div>
  @endforeach
</div>
