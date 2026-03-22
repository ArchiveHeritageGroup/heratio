{{-- Landing page widget partial — a single settings card tile --}}
<div class="col-lg-4 col-md-6 mb-4">
  <a href="{{ $widgetUrl ?? '#' }}" class="text-decoration-none">
    <div class="card h-100 shadow-sm settings-tile {{ isset($widgetBorder) ? 'border-' . $widgetBorder : '' }}">
      <div class="card-body text-center py-4">
        <div class="mb-3"><i class="fas {{ $widgetIcon ?? 'fa-cog' }} fa-3x text-{{ $widgetColor ?? 'primary' }}"></i></div>
        <h5 class="card-title text-dark">{{ $widgetLabel ?? 'Settings' }}</h5>
        <p class="card-text text-muted small">{{ $widgetDescription ?? '' }}</p>
      </div>
      <div class="card-footer bg-white border-0 text-center pb-4">
        <span class="btn atom-btn-white"><i class="fas fa-cog"></i> {{ $widgetBtnText ?? 'Configure' }}</span>
      </div>
    </div>
  </a>
</div>
