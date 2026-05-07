@php
// Media type IDs: VIDEO=137, AUDIO=135, IMAGE=136, OTHER=139
$isStreamableVideo = false;
$isStreamableAudio = false;
$mimeType = $resource->mimeType ?? '';
$mediaTypeId = $resource->mediaTypeId ?? null;
$digitalObjectLabel = config('app.ui_label_digitalobject', 'digital object');

// Check for PII redaction (for PDFs)
$hasPiiRedaction = false;
$piiDownloadUrl = null;
$isPdf = (stripos($mimeType, 'pdf') !== false);
if ($isPdf && isset($resource->object)) {
    $objectId = $resource->object->id ?? ($resource->objectId ?? null);
    if ($objectId && function_exists('pii_has_redacted') && pii_has_redacted($objectId)) {
        $hasPiiRedaction = true;
        $piiDownloadUrl = url('/privacyAdmin/downloadPdf/' . $objectId);
        if (!function_exists('pii_can_view_unmasked') || !pii_can_view_unmasked()) {
            $link = $piiDownloadUrl;
        }
    }
}

// Check if streamable using numeric IDs
if ($mediaTypeId == 137 && function_exists('ahg_needs_streaming') && ahg_needs_streaming($resource)) {
    $isStreamableVideo = true;
} elseif ($mediaTypeId == 135 && function_exists('ahg_needs_streaming') && ahg_needs_streaming($resource)) {
    $isStreamableAudio = true;
}

$altText = $resource->alt_text ?? $resource->name ?? '';
$altTextOpen = __($altText ?: 'Open original %1%', ['%1%' => $digitalObjectLabel]);
$altTextClosed = __($altText ?: 'Original %1% not accessible', ['%1%' => $digitalObjectLabel]);
@endphp

@if($isStreamableVideo)
  <!-- Streaming Video Player for Legacy Format -->
  <div class="digital-object-block streaming-video-container">
    <div class="alert alert-info mb-2">
      <i class="fas fa-info-circle me-1"></i>
      <strong>@php echo function_exists('ahg_get_format_name') ? ahg_get_format_name($mimeType) : $mimeType; @endphp</strong> - Streaming via server transcoding (original file preserved)
    </div>
    {{-- #106 Phase 2+4: shared Heratio video player component on the
         streaming-transcode path (the streaming endpoint always serves
         transcoded MP4, so MIME is video/mp4 here). --}}
    @include('theme::components.media-player', [
        'type'           => 'video',
        'playerId'       => 'ahg-video-stream-' . ($resource->id ?? uniqid()),
        'src'            => '/media/stream/' . $resource->id,
        'mime'           => 'video/mp4',
        'name'           => $resource->name ?? '',
        'masterUrl'      => '/media/stream/' . $resource->id,
        'masterMime'     => 'video/mp4',
        'byteSize'       => $resource->byte_size ?? null,
        'needsStreaming' => true,
        'showDownload'   => false,
        'poster'         => null,
    ])
    @if(isset($link) && ($canReadMaster ?? false))
      <div class="mt-2">
        <a href="{{ $link }}" class="btn btn-sm btn-outline-primary" target="_blank">
          <i class="fas fa-download me-1"></i>{{ __('Download original %1%', ['%1%' => strtoupper(pathinfo($resource->name, PATHINFO_EXTENSION))]) }}
        </a>
      </div>
    @endif
  </div>

@elseif($isStreamableAudio)
  <!-- Streaming Audio Player for Legacy Format -->
  <div class="digital-object-block streaming-audio-container">
    <div class="alert alert-info mb-2">
      <i class="fas fa-info-circle me-1"></i>
      <strong>@php echo function_exists('ahg_get_format_name') ? ahg_get_format_name($mimeType) : $mimeType; @endphp</strong> - Streaming via server transcoding (original file preserved)
    </div>
    {{-- #106 Phase 1+4: shared Heratio audio player component (streaming
         path - the streaming endpoint serves transcoded MP3, so MIME
         is always audio/mpeg here). --}}
    @include('theme::components.media-player', [
        'type' => 'audio',
        'playerId' => 'ahg-audio-stream-' . ($resource->id ?? uniqid()),
        'src' => '/media/stream/' . $resource->id,
        'mime' => 'audio/mpeg',
        'name' => $resource->name ?? '',
        'masterUrl' => '/media/stream/' . $resource->id,
        'masterMime' => 'audio/mpeg',
        'byteSize' => $resource->byte_size ?? null,
        'needsStreaming' => true,
        'showDownload' => false,
    ])
    @if(isset($link) && ($canReadMaster ?? false))
      <div class="mt-2">
        <a href="{{ $link }}" class="btn btn-sm btn-outline-primary" target="_blank">
          <i class="fas fa-download me-1"></i>{{ __('Download original %1%', ['%1%' => strtoupper(pathinfo($resource->name, PATHINFO_EXTENSION))]) }}
        </a>
      </div>
    @endif
  </div>

@else
  <!-- Default Generic Icon -->
  <div class="digitalObject">
    <div class="digitalObjectRep">
      @if(isset($link) && ($canReadMaster ?? false))
        @if($hasPiiRedaction && $piiDownloadUrl)
          <!-- PDF with PII redaction -->
          <a href="{{ $piiDownloadUrl }}" target="_blank">
            <img src="{{ \AhgCore\Services\DigitalObjectService::getUrl($representation) }}" alt="{{ __('Open redacted %1%', ['%1%' => $digitalObjectLabel]) }}" class="img-thumbnail">
          </a>
        @else
          <a href="{{ $link }}" target="_blank">
            <img src="{{ \AhgCore\Services\DigitalObjectService::getUrl($representation) }}" alt="{{ $altTextOpen }}" class="img-thumbnail">
          </a>
        @endif
      @else
        <img src="{{ \AhgCore\Services\DigitalObjectService::getUrl($representation) }}" alt="{{ $altTextClosed }}" class="img-thumbnail">
      @endif
    </div>
    <div class="digitalObjectDesc">
      {{ Illuminate\Support\Str::limit($resource->name, 18) }}
      @if($hasPiiRedaction)
        <div class="mt-1">
          <span class="badge bg-warning text-dark"><i class="fas fa-shield-alt me-1"></i>{{ __('PII Redacted') }}</span>
        </div>
      @endif
    </div>
  </div>
@endif
