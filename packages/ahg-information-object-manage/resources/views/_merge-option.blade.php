@php /**
 * Merge Option for Digital Object Upload
 */

$io = $resource ?? $informationObject ?? null; @endphp

@if($io && $sf_user->hasCredential(['contributor', 'editor', 'administrator'], false))
<div class="card mb-4 border-info">
    <div class="card-header bg-info text-white">
        <h6 class="mb-0">
            <i class="fas fa-layer-group me-2"></i>
            Multi-Page Document
        </h6>
    </div>
    <div class="card-body">
        <p class="mb-3">
            Need to create a multi-page PDF from multiple TIFF or image files?
        </p>
        <a href="@php echo url_for(['module' => 'tiffpdfmerge', 'action' => 'index', 'informationObject' => $io->slug]); @endphp" 
           class="btn btn-info">
            <i class="fas fa-file-pdf me-1"></i>
            Merge Images to PDF
        </a>
        <p class="small text-muted mt-2 mb-0">
            <i class="fas fa-info-circle me-1"></i>
            Upload multiple TIFF, JPEG, PNG files and combine them into a single PDF/A document.
        </p>
    </div>
</div>
@endif
