@php
  $holdingsLabel = \AhgCore\Services\SettingHelper::get('ui_label_holdings', 'Holdings');
@endphp

<form class="mb-3" role="search" aria-label="{{ $holdingsLabel }}" action="{{ route('informationobject.browse') }}">
  <input type="hidden" name="repos" value="{{ $resource->id }}">
  <div class="input-group">
    <input type="text" class="form-control" name="query" aria-label="{{ __('Search') }}" placeholder="{{ __('Search') }}">
    <button class="btn atom-btn-white" type="submit" aria-label="{{ __('Search') }}">
      <i aria-hidden="true" class="fas fa-search"></i>
    </button>
  </div>
</form>
