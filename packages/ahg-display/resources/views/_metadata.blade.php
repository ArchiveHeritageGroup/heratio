{{-- Partial: Digital object metadata --}}
@props(['object' => null])
@if($object)<div class="metadata-panel"><dl class="row mb-0 small"><dt class="col-sm-4">Format</dt><dd class="col-sm-8">{{ $object->format ?? '-' }}</dd><dt class="col-sm-4">Size</dt><dd class="col-sm-8">{{ $object->byte_size ?? '-' }}</dd><dt class="col-sm-4">MIME</dt><dd class="col-sm-8">{{ $object->mime_type ?? '-' }}</dd></dl></div>@endif
