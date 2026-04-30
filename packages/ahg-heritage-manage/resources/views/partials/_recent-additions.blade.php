{{-- Recent Additions Partial --}}
<section class="heritage-recent-additions py-5">
  <div class="container">
    <h2 class="text-center mb-4"><i class="fas fa-clock me-2"></i>{{ __('Recently Added') }}</h2>
    <div class="row">
      @forelse($recentItems ?? [] as $item)
      <div class="col-md-3 mb-3">
        <div class="card h-100">
          @if($item->thumb_child_path ?? null)
            <img src="/uploads/{{ ltrim($item->thumb_child_path, '/') }}/{{ $item->thumb_child_name ?? '' }}" class="card-img-top" alt="" style="height:150px;object-fit:cover">
          @else
            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height:150px"><i class="fas fa-image fa-3x text-muted"></i></div>
          @endif
          <div class="card-body">
            <h6 class="card-title"><a href="{{ route('informationobject.show', $item->slug ?? $item->id) }}">{{ Str::limit($item->title ?? 'Untitled', 50) }}</a></h6>
          </div>
        </div>
      </div>
      @empty
      <div class="col-12 text-center text-muted">No recent additions</div>
      @endforelse
    </div>
  </div>
</section>