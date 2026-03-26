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

$altTextOpen = __($resource->getDigitalObjectAltText() ?: 'Open original %1%', ['%1%' => $digitalObjectLabel]);
$altTextClosed = __($resource->getDigitalObjectAltText() ?: 'Original %1% not accessible', ['%1%' => $digitalObjectLabel]);
@endphp

@if($isStreamableVideo)
  <!-- Streaming Video Player for Legacy Format -->
  <div class="digital-object-block streaming-video-container">
    <div class="alert alert-info mb-2">
      <i class="fas fa-info-circle me-1"></i>
      <strong>@php echo function_exists('ahg_get_format_name') ? ahg_get_format_name($mimeType) : $mimeType; @endphp</strong> - Streaming via server transcoding (original file preserved)
    </div>
    <video controls preload="metadata" class="mw-100" style="max-height: 500px; background: #000;">
      <source src="/media/stream/{{ $resource->id }}" type="video/mp4">
      Your browser does not support video playback.
    </video>
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
    <audio controls preload="metadata" class="w-100">
      <source src="/media/stream/{{ $resource->id }}" type="audio/mpeg">
      Your browser does not support audio playback.
    </audio>
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
            <img src="{{ $representation->getFullPath() }}" alt="{{ __('Open redacted %1%', ['%1%' => $digitalObjectLabel]) }}" class="img-thumbnail">
          </a>
        @else
          <a href="{{ $link }}" target="_blank">
            <img src="{{ $representation->getFullPath() }}" alt="{{ $altTextOpen }}" class="img-thumbnail">
          </a>
        @endif
      @else
        <img src="{{ $representation->getFullPath() }}" alt="{{ $altTextClosed }}" class="img-thumbnail">
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
