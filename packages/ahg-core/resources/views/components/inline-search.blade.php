<form id="inline-search" method="get" action="{{ request()->url() }}" role="search" aria-label="{{ $landmarkLabel ?? 'Search' }}">
  @foreach(request()->except(['subquery', 'page']) as $key => $value)
    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
  @endforeach
  <div class="input-group flex-nowrap">
    <input class="form-control form-control-sm" type="search" name="subquery"
           value="{{ request('subquery') }}"
           placeholder="{{ $label ?? 'Search...' }}"
           aria-label="{{ $label ?? 'Search' }}">
    <button class="btn btn-sm atom-btn-white" type="submit">
      <i class="fas fa-search" aria-hidden="true"></i>
      <span class="visually-hidden">Search</span>
    </button>
    @if(request('subquery'))
      <a href="{{ request()->fullUrlWithoutQuery('subquery') }}" class="btn btn-sm atom-btn-white" title="Clear search" role="button">
        <i class="fas fa-undo" aria-hidden="true"></i>
        <span class="visually-hidden">Reset search</span>
      </a>
    @endif
  </div>
</form>
