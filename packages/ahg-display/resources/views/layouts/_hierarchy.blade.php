{{-- Hierarchy layout - tree view for archives --}}
@php
$ancestors = $data['ancestors'] ?? [];
$children = $data['children'] ?? [];
@endphp

{{-- Breadcrumb/Ancestors --}}
@if(!empty($ancestors))
<nav class="hierarchy-breadcrumb mb-3" aria-label="{{ __('Hierarchy') }}">
    <ol class="breadcrumb mb-0">
        @foreach($ancestors as $a)
        <li class="breadcrumb-item">
            <a href="{{ route('informationobject.show', ['slug' => $a->slug]) }}">
                {{ $a->title ?? $a->identifier }}
            </a>
        </li>
        @endforeach
        <li class="breadcrumb-item active">{{ $object->title ?? $object->identifier }}</li>
    </ol>
</nav>
@endif

{{-- Current Object Summary --}}
<div class="hierarchy-current card mb-4">
    <div class="card-body">
        <div class="d-flex align-items-start">
            @if($digitalObject)
            <img src="{{ $digitalObject->path }}" class="me-3 rounded" style="max-width: 80px;" alt="">
            @endif
            <div class="flex-grow-1">
                <h4 class="mb-1">
                    <span class="badge bg-secondary me-2">{{ $object->level_name }}</span>
                    {{ $object->title ?? 'Untitled' }}
                </h4>
                @if($object->identifier)
                <p class="text-muted mb-2">{{ $object->identifier }}</p>
                @endif
                @if(!empty($fields['description']['scope_content']))
                <p class="mb-0">{{ Str::limit(strip_tags($fields['description']['scope_content']['value']), 300) }}</p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Children --}}
@if(!empty($children))
<div class="hierarchy-children">
    <h5 class="mb-3">
        <i class="fas fa-folder-open me-2 text-muted"></i>
        Contents ({{ count($children) }})
    </h5>
    <div class="list-group">
        @foreach($children as $child)
        <a href="{{ route('informationobject.show', ['slug' => $child->slug]) }}"
           class="list-group-item list-group-item-action d-flex align-items-center">
            @if($child->thumbnail_path)
            <img src="{{ $child->thumbnail_path }}" class="me-3 rounded" style="width: 50px; height: 50px; object-fit: cover;" alt="">
            @else
            <div class="me-3 text-muted" style="width: 50px; text-align: center;">
                <i class="fas {{ get_level_icon(strtolower($child->level_name ?? 'file')) }} fa-2x"></i>
            </div>
            @endif
            <div class="flex-grow-1">
                <strong>{{ $child->title ?? 'Untitled' }}</strong>
                @if($child->identifier)
                <br><small class="text-muted">{{ $child->identifier }}</small>
                @endif
            </div>
            <span class="badge bg-light text-dark">{{ $child->level_name }}</span>
        </a>
        @endforeach
    </div>
</div>
@else
<div class="alert alert-light">
    <i class="fas fa-info-circle me-2"></i>No child items
</div>
@endif
