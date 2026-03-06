<form class="d-inline-block" action="{{ request()->url() }}" method="GET">
  @foreach(request()->except(['subquery', 'page']) as $key => $value)
    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
  @endforeach
  <div class="input-group">
    <input type="text" class="form-control" name="subquery"
           value="{{ request('subquery') }}"
           placeholder="{{ $label ?? 'Search...' }}"
           aria-label="{{ $landmarkLabel ?? 'Search' }}">
    <button class="btn atom-btn-secondary" type="submit">
      <i class="fas fa-search" aria-hidden="true"></i>
    </button>
    @if(request('subquery'))
      <a href="{{ request()->fullUrlWithoutQuery('subquery') }}" class="btn atom-btn-white" title="Clear search">
        <i class="fas fa-times" aria-hidden="true"></i>
      </a>
    @endif
  </div>
</form>
