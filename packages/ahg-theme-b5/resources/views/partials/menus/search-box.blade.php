<form class="d-flex my-2 my-lg-0 ms-lg-3" action="{{ url('/search') }}" method="get" role="search" style="max-width:300px;">
  <div class="input-group input-group-sm">
    <input type="text" class="form-control" name="query" placeholder="Search..." aria-label="Search" value="{{ request('query') }}">
    <button class="btn atom-btn-secondary" type="submit">
      <i class="fas fa-search" aria-hidden="true"></i>
      <span class="visually-hidden">Search</span>
    </button>
  </div>
</form>
