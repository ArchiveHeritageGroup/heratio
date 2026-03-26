{{-- Detail layout - standard full record view --}}
@php
$thumbnailSize = $data['thumbnail_size'] ?? 'medium';
$thumbnailPosition = $data['thumbnail_position'] ?? 'left';
$colImage = match($thumbnailSize) {
    'hero', 'full' => 12,
    'large' => 5,
    'medium' => 4,
    'small' => 3,
    default => 0,
};
$colContent = $colImage > 0 && $colImage < 12 ? 12 - $colImage : 12;
@endphp

<div class="row">
    @if($digitalObject && $thumbnailSize !== 'none')
    <div class="col-md-{{ $colImage }} {{ $thumbnailPosition === 'right' ? 'order-md-2' : '' }} mb-4">
        <div class="digital-object-display thumbnail-{{ $thumbnailSize }}">
            <a href="{{ $digitalObject->path }}" data-lightbox="object" data-title="{{ $object->title ?? '' }}">
                <img src="{{ $digitalObject->path }}"
                     class="img-fluid rounded shadow-sm"
                     alt="{{ $object->title ?? '' }}">
            </a>
        </div>
    </div>
    @endif

    <div class="col-md-{{ $colContent }}">
        {{-- Identity Section --}}
        @if(!empty($fields['identity']))
        <section class="field-section identity-section mb-4">
            <dl class="row mb-0">
                @foreach($fields['identity'] as $field)
                <dt class="col-sm-3 text-muted">{{ $field['label'] }}</dt>
                <dd class="col-sm-9">{!! format_field_value($field) !!}</dd>
                @endforeach
            </dl>
        </section>
        @endif

        {{-- Description Section --}}
        @if(!empty($fields['description']))
        <section class="field-section description-section mb-4">
            <h5 class="section-title border-bottom pb-2 mb-3">
                <i class="fas fa-align-left text-muted me-2"></i>Description
            </h5>
            @foreach($fields['description'] as $field)
            <div class="field-block mb-3">
                <h6 class="field-label text-muted">{{ $field['label'] }}</h6>
                <div class="field-value">{!! format_field_value($field) !!}</div>
            </div>
            @endforeach
        </section>
        @endif

        {{-- Context Section --}}
        @if(!empty($fields['context']))
        <section class="field-section context-section mb-4">
            <h5 class="section-title border-bottom pb-2 mb-3">
                <i class="fas fa-history text-muted me-2"></i>Context
            </h5>
            @foreach($fields['context'] as $field)
            <div class="field-block mb-3">
                <h6 class="field-label text-muted">{{ $field['label'] }}</h6>
                <div class="field-value">{!! format_field_value($field) !!}</div>
            </div>
            @endforeach
        </section>
        @endif

        {{-- Access Section --}}
        @if(!empty($fields['access']))
        <section class="field-section access-section mb-4">
            <h5 class="section-title border-bottom pb-2 mb-3">
                <i class="fas fa-lock-open text-muted me-2"></i>Access & Use
            </h5>
            @foreach($fields['access'] as $field)
            <div class="field-block mb-3">
                <h6 class="field-label text-muted">{{ $field['label'] }}</h6>
                <div class="field-value">{!! format_field_value($field) !!}</div>
            </div>
            @endforeach
        </section>
        @endif

        {{-- Technical Section (DAM) --}}
        @if(!empty($fields['technical']))
        <section class="field-section technical-section mb-4">
            <h5 class="section-title border-bottom pb-2 mb-3">
                <i class="fas fa-cog text-muted me-2"></i>Technical Details
            </h5>
            <dl class="row mb-0">
                @foreach($fields['technical'] as $field)
                <dt class="col-sm-4 text-muted">{{ $field['label'] }}</dt>
                <dd class="col-sm-8">{!! format_field_value($field) !!}</dd>
                @endforeach
            </dl>
        </section>
        @endif

        {{-- Rights Section --}}
        @if(!empty($data['rights']))
        @include('ahg-display::layouts._rights_section', ['data' => $data])
        @endif
    </div>
</div>
