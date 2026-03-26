{{-- Masonry layout - Pinterest-style grid for photos --}}
<div class="masonry-item mb-3">
    <div class="card">
        @if($digitalObject)
        <a href="{{ $digitalObject->path }}" data-lightbox="masonry" data-title="{{ $object->title ?? '' }}">
            <img src="{{ $digitalObject->path }}"
                 class="card-img-top"
                 alt="{{ $object->title ?? '' }}"
                 loading="lazy">
        </a>
        @endif
        <div class="card-body p-2">
            <h6 class="card-title mb-1 small">{{ $object->title ?? 'Untitled' }}</h6>
            <div class="btn-group btn-group-sm w-100">
                @if(in_array('select', $data['actions'] ?? []))
                <button type="button" class="btn btn-outline-success select-toggle" data-id="{{ $object->id }}">
                    <i class="fas fa-check"></i>
                </button>
                @endif
                @if(in_array('compare', $data['actions'] ?? []))
                <button type="button" class="btn btn-outline-info compare-toggle" data-id="{{ $object->id }}">
                    <i class="fas fa-columns"></i>
                </button>
                @endif
                @if(in_array('download', $data['actions'] ?? []) && $digitalObject)
                <a href="{{ $digitalObject->path }}" class="btn btn-outline-secondary" download>
                    <i class="fas fa-download"></i>
                </a>
                @endif
            </div>
        </div>
    </div>
</div>
