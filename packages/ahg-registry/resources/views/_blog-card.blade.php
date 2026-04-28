{{--
  Blog card. Vars: $item (object/array with: featured_image_path, category, title,
  slug, excerpt, author_name, author_type, published_at, view_count, comment_count).

  Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
  Licensed under the GNU AGPL v3.
--}}
@php
    $item = (object) $item;
    $authorTypeBg = [
        'admin' => 'bg-danger',
        'vendor' => 'bg-primary',
        'institution' => 'bg-success',
        'user_group' => 'bg-purple',
    ];
    $at = $item->author_type ?? '';
    $atClass = $authorTypeBg[$at] ?? 'bg-secondary';
    $atStyle = $at === 'user_group' ? 'background-color:#6f42c1!important;' : '';
    $href = \Illuminate\Support\Facades\Route::has('registry.blogView')
        ? route('registry.blogView', ['slug' => $item->slug ?? ''])
        : url('/registry/blog/' . ($item->slug ?? ''));
    $excerpt = $item->excerpt ?? '';
@endphp
<div class="col">
    <div class="card h-100">
        @if (! empty($item->featured_image_path))
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
                <a href="{{ $href }}" class="text-decoration-none stretched-link">{{ $item->title ?? '' }}</a>
            </h6>
            @if ($excerpt !== '')
                <p class="card-text small text-muted">{{ \Illuminate\Support\Str::limit(strip_tags($excerpt), 120) }}</p>
            @endif
        </div>
        <div class="card-footer bg-transparent border-0 pt-0">
            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    {{ $item->author_name ?? '' }}
                    @if ($at !== '')
                        <span class="badge {{ $atClass }}" style="{{ $atStyle }}">{{ ucfirst(str_replace('_', ' ', $at)) }}</span>
                    @endif
                </small>
                <small class="text-muted">
                    {{ \Carbon\Carbon::parse($item->published_at ?? $item->created_at ?? 'now')->format('M j, Y') }}
                </small>
            </div>
            <div class="d-flex gap-2">
                @if (! empty($item->view_count))
                    <small class="text-muted"><i class="fas fa-eye me-1"></i>{{ number_format((int) $item->view_count) }}</small>
                @endif
                @if (isset($item->comment_count) && (int) $item->comment_count > 0)
                    <small class="text-muted"><i class="fas fa-comments me-1"></i>{{ (int) $item->comment_count }}</small>
                @endif
            </div>
        </div>
    </div>
</div>
