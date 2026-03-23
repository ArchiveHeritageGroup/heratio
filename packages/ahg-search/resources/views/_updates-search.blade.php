{{-- This partial is now integrated into description-updates.blade.php as an accordion filter. --}}
{{-- Kept for backward compatibility if included elsewhere. --}}
<form id="inline-search" method="get" action="{{ route('search.descriptionUpdates') }}" role="search" aria-label="{{ __('Search') }}">
  <div class="input-group flex-nowrap">
    <input
      class="form-control form-control-sm"
      type="search"
      name="query"
      value="{{ request('query') }}"
      placeholder="{{ __('Search') }}"
      aria-label="{{ __('Search') }}">
    <button class="btn btn-sm atom-btn-white" type="submit">
      <i class="fas fa-search" aria-hidden="true"></i>
      <span class="visually-hidden">{{ __('Search') }}</span>
    </button>
  </div>
</form>
