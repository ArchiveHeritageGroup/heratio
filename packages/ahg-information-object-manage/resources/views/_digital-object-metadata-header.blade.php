@php /**
 * Digital Object Metadata Header with TIFF to PDF Merge Button
 * Add this to your information object view template
 */ @endphp

<div class="digital-object-actions mb-3">
    <div class="btn-group" role="group">
        @if($sf_user->hasCredential(['contributor', 'editor', 'administrator'], false))
        
        <!-- Upload Digital Object -->
        <a href="@php echo url_for(['module' => 'digitalobject', 'action' => 'edit', 'informationObject' => $resource->slug]); @endphp" 
           class="btn btn-outline-primary btn-sm">
            <i class="fas fa-upload me-1"></i>
            Upload
        </a>
        
        <!-- TIFF to PDF Merge Button -->
        @php include_partial('ahgThemeB5Plugin/tiffPdfMergeButton', [
            'informationObjectId' => $resource->id,
            'buttonClass' => 'btn btn-outline-secondary btn-sm'
        ]); @endphp
        
        @endif
    </div>
</div>

<!-- Include modal (once per page) -->
@php include_partial('ahgThemeB5Plugin/tiffPdfMergeModal', [
    'informationObjectId' => $resource->id
]); @endphp

<!-- Load required scripts -->
<script src="/plugins/ahgThemeB5Plugin/js/sortable.min.js"></script>
<script src="@php echo public_path('plugins/ahgThemeB5Plugin/js/tiff-pdf-merge.js'); @endphp"></script>
