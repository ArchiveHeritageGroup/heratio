{{-- Stories Section Partial --}}
<section class="heritage-stories-section py-5">
  <div class="container">
    <h2 class="text-center mb-4"><i class="fas fa-book-open me-2"></i>Featured Stories</h2>
    <div class="row">
      @forelse($stories ?? [] as $story)
      <div class="col-md-4 mb-3">
        <div class="card h-100">
          <div class="card-body">
            <h5>{{ $story->title ?? '' }}</h5>
            <p class="text-muted">{{ Str::limit($story->content ?? '', 120) }}</p>
          </div>
        </div>
      </div>
      @empty
      <div class="col-12 text-center text-muted">No stories published yet</div>
      @endforelse
    </div>
  </div>
</section>