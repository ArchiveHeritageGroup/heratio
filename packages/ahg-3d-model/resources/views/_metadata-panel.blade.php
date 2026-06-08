{{--
  #1178 - 3D technical metadata panel.
  Include with ['objectId' => <io id>]; the ahg-3d-model View::composer fills
  $threeDModel (the object_3d_model row, created/extracted on demand). Renders
  nothing when the record has no 3D model. Used by the shared sidebar partial
  (DAM/gallery/museum) and the main GLAM show — one panel, every surface.
--}}
@if(!empty($threeDModel ?? null))
  @php $__m = $threeDModel; @endphp
  <div class="card mb-3">
    <div class="card-header fw-bold"><i class="fas fa-cube me-1"></i> {{ __('3D technical metadata') }}</div>
    <div class="card-body p-0">
      <table class="table table-sm small mb-0">
        @if(!empty($__m->format_version))<tr><th class="ps-2" style="width:42%">{{ __('Format') }}</th><td>{{ $__m->format_version }}</td></tr>@endif
        @if(!empty($__m->vertex_count))<tr><th class="ps-2">{{ __('Vertices') }}</th><td>{{ number_format($__m->vertex_count) }}</td></tr>@endif
        @if(!empty($__m->face_count))<tr><th class="ps-2">{{ __('Faces') }}</th><td>{{ number_format($__m->face_count) }}</td></tr>@endif
        @if(!empty($__m->bounding_box))<tr><th class="ps-2">{{ __('Bounding box') }}</th><td class="text-break">{{ $__m->bounding_box }}</td></tr>@endif
        @if(!empty($__m->real_width) || !empty($__m->real_height) || !empty($__m->real_depth))<tr><th class="ps-2">{{ __('Dimensions') }}</th><td>{{ $__m->real_width ?? '?' }} &times; {{ $__m->real_height ?? '?' }} &times; {{ $__m->real_depth ?? '?' }} {{ $__m->dimension_unit }}</td></tr>@endif
        @if(!empty($__m->compression))<tr><th class="ps-2">{{ __('Compression') }}</th><td>{{ $__m->compression }}</td></tr>@endif
        @if(!empty($__m->capture_method))<tr><th class="ps-2">{{ __('Capture') }}</th><td>{{ $__m->capture_method }}{{ !empty($__m->capture_device) ? ' · '.e($__m->capture_device) : '' }}</td></tr>@endif
        @if(!empty($__m->accuracy_mm))<tr><th class="ps-2">{{ __('Accuracy') }}</th><td>{{ $__m->accuracy_mm }} mm</td></tr>@endif
        @if(!empty($__m->model_author))<tr><th class="ps-2">{{ __('Author') }}</th><td>{{ $__m->model_author }}</td></tr>@endif
        @if(!empty($__m->model_license))<tr><th class="ps-2">{{ __('Licence') }}</th><td>{{ $__m->model_license }}</td></tr>@endif
      </table>
      @auth
        <div class="p-2"><a href="{{ url('/admin/3d-models/'.$__m->id.'/edit') }}" class="small"><i class="fas fa-edit me-1"></i>{{ __('Edit 3D metadata') }}</a></div>
      @endauth
    </div>
  </div>
@endif
