<form class="d-flex my-1 my-lg-0 ms-lg-3 flex-grow-1" action="{{ url('/search') }}" method="get" role="search" style="max-width:600px;">
  <div class="input-group input-group-sm">
    <input type="text" class="form-control py-0" name="query" placeholder="Search..." aria-label="Search" value="{{ request('query') }}" style="height:20px;font-size:.75rem;padding:0 6px;">
    <button class="btn atom-btn-secondary py-0 px-2" type="submit" style="height:20px;font-size:.7rem;">
      <i class="fas fa-search" aria-hidden="true"></i>
      <span class="visually-hidden">Search</span>
    </button>
  </div>
</form>
