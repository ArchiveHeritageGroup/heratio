<div class="help-sidebar sticky-top" style="top: 1rem;">
  <div class="mb-3">
    <form action="{{ route('help.search') }}" method="get" class="input-group input-group-sm">
      <input type="text" name="q" class="form-control" placeholder="Search help..." autocomplete="off">
      <button type="submit" class="btn atom-btn-white"><i class="fas fa-search"></i></button>
    </form>
  </div>

  <h6 class="text-uppercase text-muted mb-2">Categories</h6>
  <ul class="nav flex-column mb-3">
    @foreach($categories as $cat)
      <li class="nav-item">
        <a class="nav-link py-1 d-flex justify-content-between align-items-center"
          href="{{ route('help.category', urlencode($cat['category'])) }}">
          <span>{{ $cat['category'] }}</span>
          <span class="badge bg-secondary rounded-pill">{{ $cat['article_count'] }}</span>
        </a>
      </li>
    @endforeach
  </ul>

  <h6 class="text-uppercase text-muted mb-2">Quick Links</h6>
  <ul class="nav flex-column small">
    <li class="nav-item">
      <a class="nav-link py-1" href="{{ route('help.index') }}">
        <i class="fas fa-home me-1"></i>Help Home
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link py-1" href="{{ route('help.article', 'user-manual') }}">
        <i class="fas fa-book me-1"></i>User Manual
      </a>
    </li>
  </ul>
</div>
