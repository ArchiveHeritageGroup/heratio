<form class="mb-3" role="search" aria-label="@php echo sfConfig::get('app_ui_label_holdings'); @endphp" action="@php echo url_for('@glam_browse'); @endphp">
  <input type="hidden" name="repos" value="@php echo $resource->id; @endphp">
  <div class="input-group">
    <input type="text" class="form-control" name="query" aria-label="{{ __('Search') }}" placeholder="{{ __('Search') }}">
    <button class="btn atom-btn-white" type="submit" aria-label="{{ __('Search') }}">
      <i aria-hidden="true" class="fas fa-search"></i>
    </button>
  </div>
</form>
