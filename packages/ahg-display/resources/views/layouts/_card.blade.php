{{-- Card layout - compact card view for search results --}}
<div class="card h-100 shadow-sm">
    <div class="row g-0">
        @if($digitalObject && ($data['thumbnail_size'] ?? '') !== 'none')
        <div class="col-4">
            <img src="{{ $digitalObject->path }}"
                 class="img-fluid rounded-start h-100"
                 style="object-fit: cover; min-height: 120px;"
                 alt="{{ $object->title ?? '' }}">
        </div>
        @endif
        <div class="col-{{ $digitalObject && ($data['thumbnail_size'] ?? '') !== 'none' ? '8' : '12' }}">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge bg-{{ get_type_color($objectType) }} bg-opacity-75">
                        <i class="fas {{ get_type_icon($objectType) }} me-1"></i>
                        {{ $object->level_name ?? ucfirst($objectType) }}
                    </span>
                    @if(!empty($fields['identity']['identifier']))
                    <small class="text-muted">{{ $fields['identity']['identifier']['value'] }}</small>
                    @endif
                </div>

                <h6 class="card-title mb-1">
                    <a href="{{ route('informationobject.show', ['slug' => $object->slug]) }}" class="text-decoration-none">
                        {{ $object->title ?? 'Untitled' }}
                    </a>
                </h6>

                @if(!empty($fields['identity']['creator']))
                <p class="card-text small text-muted mb-1">{{ $fields['identity']['creator']['value'] }}</p>
                @endif

                @if(!empty($fields['identity']['date']))
                <p class="card-text small text-muted mb-0">{{ $fields['identity']['date']['value'] }}</p>
                @endif

                @if(!empty($fields['description']['description']))
                <p class="card-text small mt-2 text-truncate-3">
                    {{ Str::limit(strip_tags($fields['description']['description']['value']), 150) }}
                </p>
                @endif
            </div>
        </div>
    </div>
</div>
