{{-- Grid layout - thumbnail grid for photos/DAM --}}
<div class="grid-item">
    <div class="card h-100 shadow-sm">
        @if($digitalObject)
        <div class="card-img-wrapper position-relative overflow-hidden">
            <img src="{{ $digitalObject->path }}"
                 class="card-img-top"
                 style="height: 180px; object-fit: cover;"
                 alt="{{ $object->title ?? '' }}"
                 loading="lazy">
            <div class="card-img-overlay d-flex flex-column justify-content-end p-0">
                <div class="bg-gradient-dark p-2" style="background: linear-gradient(transparent, rgba(0,0,0,0.8));">
                    <div class="btn-group btn-group-sm">
                        @if(in_array('view', $data['actions'] ?? []))
                        <a href="{{ route('informationobject.show', ['slug' => $object->slug]) }}"
                           class="btn btn-light" title="{{ __('View') }}"><i class="fas fa-eye"></i></a>
                        @endif
                        @if(in_array('zoom', $data['actions'] ?? []))
                        <a href="{{ $digitalObject->path }}"
                           class="btn btn-light" data-lightbox="grid" title="{{ __('Zoom') }}"><i class="fas fa-search-plus"></i></a>
                        @endif
                        @if(in_array('add_to_lightbox', $data['actions'] ?? []))
                        <a href="{{ route('dam.addToLightbox', ['digital_object_id' => $digitalObject->id]) }}"
                           class="btn btn-light" title="{{ __('Add to Lightbox') }}"><i class="fas fa-plus"></i></a>
                        @endif
                        @if(in_array('select', $data['actions'] ?? []))
                        <button type="button" class="btn btn-light select-toggle" data-id="{{ $object->id }}" title="{{ __('Select') }}">
                            <i class="fas fa-check"></i>
                        </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif
        <div class="card-body p-2">
            <h6 class="card-title mb-1 text-truncate" title="{{ $object->title ?? '' }}">
                {{ $object->title ?? \AhgCore\Support\GlobalSettings::displayFilename($digitalObject->name) ?? 'Untitled' }}
            </h6>
            @if(!empty($fields['identity']['date']))
            <small class="text-muted">{{ $fields['identity']['date']['value'] }}</small>
            @endif
        </div>
    </div>
</div>
