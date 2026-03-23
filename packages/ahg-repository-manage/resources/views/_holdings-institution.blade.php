@php
  $institutionSearchLabel = \AhgCore\Services\SettingHelper::get('ui_label_institutionSearchHoldings', 'Search holdings');
@endphp

<section class="card mb-3">
  <div class="card-body">
    @include('ahg-repository-manage::_logo', ['resource' => $resource])

    <form class="mb-3" role="search" aria-label="{{ $institutionSearchLabel }}" action="{{ route('informationobject.browse') }}">
      <input type="hidden" name="repos" value="{{ $resource->id }}">
      <label for="institution-search-query" class="h5 mb-2 form-label">{{ $institutionSearchLabel }}</label>
      <div class="input-group">
        <input type="text" class="form-control" id="institution-search-query" name="query" value="{{ request('query') }}" placeholder="{{ __('Search') }}" required>
        <button class="btn atom-btn-white" type="submit" aria-label="{{ __('Search') }}">
          <i aria-hidden="true" class="fas fa-search"></i>
        </button>
      </div>
    </form>

    @if(View::exists('ahg-menu-manage::_browse-menu-institution'))
      @include('ahg-menu-manage::_browse-menu-institution')
    @endif
  </div>
</section>
