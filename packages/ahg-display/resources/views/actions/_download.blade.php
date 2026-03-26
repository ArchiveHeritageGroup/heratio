{{-- Action: Download --}}
@if($digitalObject)
<a href="{{ $digitalObject->path }}"
   class="btn btn-outline-success me-2" download>
    <i class="fas fa-download me-1"></i> Download
</a>
@endif
