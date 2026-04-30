@php
/**
 * Merge Option for Digital Object Upload
 */
$io = $resource ?? $informationObject ?? null; @endphp

@if($io && auth()->check() && in_array(auth()->user()->role ?? '', ['contributor', 'editor', 'administrator']))
<div class="card mb-4 border-info">
    <div class="card-header bg-info text-white">
        <h6 class="mb-0">
            <i class="fas fa-layer-group me-2"></i>
            {{ __('Multi-Page Document') }}
        </h6>
    </div>
    <div class="card-body">
        <p class="mb-3">
            Need to create a multi-page PDF from multiple TIFF or image files?
        </p>
        <a href="{{ url('/tiffpdfmerge/index/' . ($io->slug ?? '')) }}"
           class="btn atom-btn-white">
            <i class="fas fa-file-pdf me-1"></i>
            {{ __('Merge Images to PDF') }}
        </a>
        <p class="small text-muted mt-2 mb-0">
            <i class="fas fa-info-circle me-1"></i>
            {{ __('Upload multiple TIFF, JPEG, PNG files and combine them into a single PDF/A document.') }}
        </p>
    </div>
</div>
@endif
