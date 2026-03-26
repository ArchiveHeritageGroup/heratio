<div class="field">
  <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('File UUID') }}</h3>
  <div class="aip-download">
    {{ $resource->object->objectUUID }}
    @if(Auth::check() && Auth::user()->can('extractFile', $resource))
      <a href="{{ route('storageService.extractFile', ['slug' => $resource->slug]) }}" target="_blank">
        <i class="fa fa-download me-1" aria-hidden="true"></i>{{ __('Download file') }}
      </a>
    @endif
  </div>
</div>

<div class="field">
  <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('AIP UUID') }}</h3>
  <div class="aip-download">
    {{ $resource->object->aipUUID }}
    @if(Auth::check() && Auth::user()->can('download', $resource))
      <a href="{{ route('storageService.download', ['slug' => $resource->slug]) }}" target="_blank">
        <i class="fa fa-download me-1" aria-hidden="true"></i>{{ __('Download AIP') }}
      </a>
    @endif
  </div>
</div>
