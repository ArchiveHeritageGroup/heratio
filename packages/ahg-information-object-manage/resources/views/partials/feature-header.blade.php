{{-- Reusable feature page header with back link --}}
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="mb-1"><i class="{{ $icon ?? 'fas fa-cog' }} me-2"></i>{{ $featureTitle }}</h4>
    <p class="text-muted mb-0">{{ $featureDescription ?? '' }}</p>
    @if(isset($io))
      <nav aria-label="breadcrumb" class="mt-2">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="{{ route('informationobject.show', $io->slug) }}">{{ $io->title }}</a></li>
          <li class="breadcrumb-item active">{{ $featureTitle }}</li>
        </ol>
      </nav>
    @endif
  </div>
  @if(isset($io))
    <a href="{{ route('informationobject.show', $io->slug) }}" class="btn atom-btn-white">
      <i class="fas fa-arrow-left me-1"></i> Back
    </a>
  @endif
</div>
