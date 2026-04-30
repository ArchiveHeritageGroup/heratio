{{-- Block: Search Box (migrated from ahgLandingPagePlugin) --}}
@php
$placeholder = $config['placeholder'] ?? 'Search the archive...';
$showAdvanced = $config['show_advanced'] ?? true;
$style = $config['style'] ?? 'default';
$inputClass = 'form-control';
if ($style === 'large') {
    $inputClass .= ' form-control-lg';
}
@endphp

<div class="search-box-block {{ $style === 'large' ? 'py-4' : '' }}">
  <form action="{{ route('search.index') }}" method="get">
    <div class="{{ $style === 'large' ? 'input-group input-group-lg' : 'input-group' }}">
      <input type="text" name="query" class="{{ $inputClass }}"
             placeholder="{{ e($placeholder) }}"
             aria-label="{{ __('Search') }}">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-search"></i>
        @if ($style !== 'minimal')
          <span class="d-none d-md-inline ms-1">{{ __('Search') }}</span>
        @endif
      </button>
    </div>

    @if ($showAdvanced)
      <div class="text-{{ $style === 'large' ? 'center' : 'end' }} mt-2">
        <a href="{{ route('search.advanced') }}" class="small">
          <i class="bi bi-sliders"></i> {{ __('Advanced search') }}
        </a>
      </div>
    @endif
  </form>
</div>
