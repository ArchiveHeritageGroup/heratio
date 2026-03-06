<form class="d-flex my-2 my-lg-0 ms-lg-3 flex-grow-1" action="{{ url('/search') }}" method="get" role="search">
  <div class="input-group">
    <input type="text" class="form-control form-control-sm" name="query" placeholder="Search..." aria-label="Search" value="{{ request('query') }}">
    <button class="btn btn-sm atom-btn-secondary" type="submit">
      <i class="fas fa-search" aria-hidden="true"></i>
      <span class="visually-hidden">Search</span>
    </button>
  </div>
</form>
