{{-- Gallery layout - artwork/hero image view --}}
@php
$siblings = $data['siblings'] ?? [];
@endphp

<div class="gallery-view">
    <div class="row">
        <div class="col-lg-8">
            @if($digitalObject)
            <div class="gallery-image mb-4 text-center bg-light rounded p-3">
                <a href="{{ $digitalObject->path }}" data-lightbox="gallery" data-title="{{ $object->title ?? '' }}">
                    <img src="{{ $digitalObject->path }}"
                         class="img-fluid"
                         alt="{{ $object->title ?? '' }}"
                         style="max-height: 70vh; object-fit: contain;">
                </a>
            </div>
            @endif

            {{-- Siblings/related works --}}
            @if(!empty($siblings))
            <div class="gallery-siblings mt-4">
                <h6 class="text-muted mb-3">Related Works</h6>
                <div class="row g-2">
                    @foreach($siblings as $s)
                    <div class="col-3">
                        <a href="{{ route('informationobject.show', ['slug' => $s->slug]) }}">
                            @if($s->thumbnail_path)
                            <img src="{{ $s->thumbnail_path }}" class="img-fluid rounded" alt="{{ $s->title }}">
                            @else
                            <div class="bg-light rounded p-3 text-center"><i class="fas fa-image fa-2x text-muted"></i></div>
                            @endif
                        </a>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="artwork-details sticky-top" style="top: 20px;">
                {{-- Artist --}}
                @if(!empty($fields['identity']['artist']))
                <h4 class="artist-name mb-1">{{ $fields['identity']['artist']['value'] }}</h4>
                @elseif(!empty($fields['identity']['creator']))
                <h4 class="artist-name mb-1">{{ $fields['identity']['creator']['value'] }}</h4>
                @endif

                {{-- Title & Date --}}
                <h2 class="artwork-title mb-3">
                    <em>{{ $object->title ?? 'Untitled' }}</em>
                    @if(!empty($fields['identity']['date']))
                    <span class="text-muted">, {{ $fields['identity']['date']['value'] }}</span>
                    @endif
                </h2>

                {{-- Physical details --}}
                <table class="table table-sm table-borderless">
                    @if(!empty($fields['identity']['medium']))
                    <tr><th class="text-muted" width="100">Medium</th><td>{{ $fields['identity']['medium']['value'] }}</td></tr>
                    @endif
                    @if(!empty($fields['identity']['dimensions']))
                    <tr><th class="text-muted">Dimensions</th><td>{{ $fields['identity']['dimensions']['value'] }}</td></tr>
                    @endif
                    @if(!empty($fields['identity']['edition_info']))
                    <tr><th class="text-muted">Edition</th><td>{{ $fields['identity']['edition_info']['value'] }}</td></tr>
                    @endif
                </table>

                {{-- Description --}}
                @if(!empty($fields['description']))
                <div class="mt-4">
                    @foreach($fields['description'] as $field)
                    <div class="mb-3">
                        <h6 class="text-muted">{{ $field['label'] }}</h6>
                        <p>{!! format_field_value($field) !!}</p>
                    </div>
                    @endforeach
                </div>
                @endif

                {{-- Context (provenance, exhibitions) --}}
                @if(!empty($fields['context']))
                <div class="mt-4">
                    @foreach($fields['context'] as $field)
                    <div class="mb-3">
                        <h6 class="text-muted">{{ $field['label'] }}</h6>
                        <p class="small">{!! format_field_value($field) !!}</p>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
