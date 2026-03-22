<section class="card mb-3">
  <div class="card-body">
    @php include_component('repository', 'logo', ['resource' => $resource]); @endphp

    <form class="mb-3" role="search" aria-label="@php echo sfConfig::get('app_ui_label_institutionSearchHoldings'); @endphp" action="@php echo url_for('@glam_browse'); @endphp">
      <input type="hidden" name="repos" value="@php echo $resource->id; @endphp">
      <label for="institution-search-query" class="h5 mb-2 form-label">@php echo sfConfig::get('app_ui_label_institutionSearchHoldings'); @endphp</label>
      <div class="input-group">
        <input type="text" class="form-control" id="institution-search-query" name="query" value="@php echo $sf_request->query; @endphp" placeholder="{{ __('Search') }}" required>
        <button class="btn atom-btn-white" type="submit" aria-label={{ __('Search') }}>
          <i aria-hidden="true" class="fas fa-search"></i>
        </button>
      </div>
    </form>

    @php echo get_component('menu', 'browseMenuInstitution', ['sf_cache_key' => 'dominion-b5'.$sf_user->getCulture().$sf_user->getUserID()]); @endphp
  </div>
</section>
