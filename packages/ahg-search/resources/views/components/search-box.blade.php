{{--
  Reusable search box component for inclusion in theme header or any layout.
  Usage: @include('ahg-search::components.search-box')
--}}
<form action="{{ route('search') }}" method="get" class="d-flex" role="search">
  <div class="input-group">
    <input
      type="text"
      name="q"
      class="form-control"
      placeholder="Search..."
      value="{{ request('q') }}"
      autocomplete="off"
      data-autocomplete-url="{{ route('search.autocomplete') }}"
      aria-label="Search"
    >
    <button class="btn btn-outline-light" type="submit" aria-label="Search">
      <i class="fas fa-search" aria-hidden="true"></i>
    </button>
  </div>
</form>
