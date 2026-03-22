{{-- TIFF/PDF Merge Button --}}
@if(Route::has('preservation.tiffpdfmerge.index'))
<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#tiffPdfMergeModal">
  <i class="fas fa-file-pdf me-1"></i>TIFF/PDF Merge
</button>
@endif