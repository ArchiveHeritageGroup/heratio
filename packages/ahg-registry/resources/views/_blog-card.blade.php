{{-- Cloned from atom-ahg-plugins/ahgRegistryPlugin/modules/registry/templates/_blogCard.php --}}
@php
    $authorTypeBg = [
        'admin' => 'bg-danger',
        'vendor' => 'bg-primary',
        'institution' => 'bg-success',
        'user_group' => 'bg-purple',
    ];
    $at = $item->author_type ?? '';
    $atClass = $authorTypeBg[$at] ?? 'bg-secondary';
    $atStyle = ('user_group' === $at) ? 'background-color:#6f42c1!important;' : '';
    $blogHref = \Illuminate\Support\Facades\Route::has('registry.blogView')
        ? route('registry.blogView', ['slug' => $item->slug ?? ''])
        : url('/registry/blog/' . urlencode($item->slug ?? ''));
@endphp
<div class="col">
  <div class="card h-100">
    @if (!empty($item->featured_image_path))
      <img src="{{ $item->featured_image_path }}" class="card-img-top" alt="" style="height: 160px; object-fit: cover;">
    @else
      <div class="card-img-top d-flex align-items-center justify-content-center" style="height: 100px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <i class="fas fa-newspaper fa-2x text-white opacity-50"></i>
      </div>
    @endif
    <div class="card-body">
      <div class="mb-2">
        <span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $item->category ?? 'news')) }}</span>
      </div>
      <h6 class="card-title">
        <a href="{{ $blogHref }}" class="text-decoration-none stretched-link">
          {{ $item->title ?? '' }}
        </a>
      </h6>
      @php $excerpt = $item->excerpt ?? ''; @endphp
      @if (!empty($excerpt))
      <p class="card-text small text-muted">
        {{ mb_strimwidth(strip_tags($excerpt), 0, 120, '...') }}
      </p>
      @endif
    </div>
    <div class="card-footer bg-transparent border-0 pt-0">
      <div class="d-flex justify-content-between align-items-center">
        <small class="text-muted">
          {{ $item->author_name ?? '' }}
          @if (!empty($at))
            <span class="badge {{ $atClass }}" style="{{ $atStyle }}">{{ ucfirst(str_replace('_', ' ', $at)) }}</span>
          @endif
        </small>
        <small class="text-muted">
          {{ date('M j, Y', strtotime($item->published_at ?? $item->created_at ?? 'now')) }}
        </small>
      </div>
      <div class="d-flex gap-2">
        @if (!empty($item->view_count))
        <small class="text-muted">
          <i class="fas fa-eye me-1"></i>{{ number_format((int) $item->view_count) }}
        </small>
        @endif
        @if (isset($item->comment_count) && (int) $item->comment_count > 0)
        <small class="text-muted">
          <i class="fas fa-comments me-1"></i>{{ (int) $item->comment_count }}
        </small>
        @endif
      </div>
    </div>
  </div>
</div>
