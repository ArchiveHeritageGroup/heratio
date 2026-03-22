@if(isset($digitalObjects) && $digitalObjects && $digitalObjects['master'])
  @php
    $master = $digitalObjects['master'];
    $mediaType = \AhgCore\Services\DigitalObjectService::getMediaType($master);
    $displayUrl = \AhgCore\Services\DigitalObjectService::getDisplayUrl($digitalObjects);
    $thumbnailUrl = \AhgCore\Services\DigitalObjectService::getThumbnailUrl($digitalObjects);
  @endphp

  <div class="digital-object mb-3">
    @if($mediaType === 'image')
      <a href="{{ $displayUrl }}" target="_blank">
        <img src="{{ $displayUrl }}"
             class="img-fluid rounded"
             alt="{{ $master->name }}"
             style="max-height: 500px;"
             onerror="this.style.display='none'">
      </a>
      <div class="mt-1 text-muted small">{{ $master->name }}</div>
    @elseif($mediaType === 'audio')
      <audio controls class="w-100">
        <source src="{{ \AhgCore\Services\DigitalObjectService::getUrl($master) }}" type="{{ $master->mime_type }}">
        Your browser does not support audio playback.
      </audio>
      <div class="mt-1 text-muted small">{{ $master->name }}</div>
    @elseif($mediaType === 'video')
      <video controls class="w-100" style="max-height: 500px;">
        <source src="{{ \AhgCore\Services\DigitalObjectService::getUrl($master) }}" type="{{ $master->mime_type }}">
        Your browser does not support video playback.
      </video>
      <div class="mt-1 text-muted small">{{ $master->name }}</div>
    @elseif($mediaType === 'text')
      <div class="card">
        <div class="card-body">
          <i class="fas fa-file-alt fa-3x text-muted"></i>
          <p class="mt-2">{{ $master->name }}</p>
          <a href="{{ \AhgCore\Services\DigitalObjectService::getUrl($master) }}" class="btn btn-sm atom-btn-white" target="_blank">
            View document
          </a>
        </div>
      </div>
    @else
      <div class="card">
        <div class="card-body text-center">
          <i class="fas fa-file fa-3x text-muted"></i>
          <p class="mt-2">{{ $master->name }}</p>
          <a href="{{ \AhgCore\Services\DigitalObjectService::getUrl($master) }}" class="btn btn-sm atom-btn-white" target="_blank">
            Download
          </a>
        </div>
      </div>
    @endif

    @if($master->mime_type)
      <span class="badge bg-secondary mt-1">{{ $master->mime_type }}</span>
    @endif
    @if($master->byte_size)
      <span class="badge bg-light text-dark mt-1">{{ number_format($master->byte_size / 1024, 1) }} KB</span>
    @endif
  </div>
@endif
