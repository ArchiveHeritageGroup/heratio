@php /**
 * Digital Object Metadata Header with TIFF to PDF Merge Button
 * Add this to your information object view template
 */ @endphp

<div class="digital-object-actions mb-3">
    <div class="btn-group" role="group">
        @if(auth()->check() && auth()->user()?->hasAnyRole(['contributor', 'editor', 'administrator']))

        <!-- Upload Digital Object -->
        <a href="{{ route('digitalobject.edit', ['slug' => $resource->slug]) }}"
           class="btn btn-outline-primary btn-sm">
            <i class="fas fa-upload me-1"></i>
            Upload
        </a>

        <!-- TIFF to PDF Merge Button -->
        @include('ahg-theme-b5::_tiff-pdf-merge-button', [
            'informationObjectId' => $resource->id,
            'buttonClass' => 'btn btn-outline-secondary btn-sm'
        ])

        @endif
    </div>
</div>

<!-- Include modal (once per page) -->
@include('ahg-theme-b5::_tiff-pdf-merge-modal', [
    'informationObjectId' => $resource->id
])

<!-- Load required scripts -->
<script src="/plugins/ahgThemeB5Plugin/js/sortable.min.js"></script>
<script src="/plugins/ahgThemeB5Plugin/js/tiff-pdf-merge.js"></script>
