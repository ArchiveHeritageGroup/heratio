{{-- Action: Zoom --}}
@if($digitalObject)
<a href="{{ $digitalObject->path }}"
   class="btn btn-outline-info me-2" data-lightbox="object" data-title="{{ $object->title ?? '' }}">
    <i class="fas fa-search-plus me-1"></i> Zoom
</a>
@endif
