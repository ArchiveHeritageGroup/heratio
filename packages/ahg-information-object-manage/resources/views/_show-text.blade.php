@php
$digitalObjectLabel = config('app.ui_label_digitalobject', 'digital object');
$altTextOpen = __($resource->getDigitalObjectAltText() ?: 'Open original %1%', ['%1%' => $digitalObjectLabel]);
$altTextClosed = __($resource->getDigitalObjectAltText() ?: 'Original %1% not accessible', ['%1%' => $digitalObjectLabel]);

// Check for PII redaction (for PDFs)
$hasPiiRedaction = false;
$piiDownloadUrl = null;
$mimeType = ($digitalObject ?? $resource)->mimeType ?? '';
$isPdf = (stripos($mimeType, 'pdf') !== false);

if ($isPdf) {
    $objectId = ($digitalObject ?? $resource)->object->id ?? (($digitalObject ?? $resource)->objectId ?? null);
    if ($objectId && function_exists('pii_has_redacted') && pii_has_redacted($objectId)) {
        $hasPiiRedaction = true;
        $piiDownloadUrl = url('/privacyAdmin/downloadPdf/' . $objectId);
        if (!function_exists('pii_can_view_unmasked') || !pii_can_view_unmasked()) {
            $link = $piiDownloadUrl;
        }
    }
}

$referenceUsage = config('atom.term.REFERENCE_ID');
$thumbnailUsage = config('atom.term.THUMBNAIL_ID');
@endphp

@if($referenceUsage == $usageType)

  @if(isset($link))
    @if($hasPiiRedaction)
      <a href="{{ $link }}" target="_blank">
        <img src="{{ $representation->getFullPath() }}" alt="{{ __('Open redacted %1%', ['%1%' => $digitalObjectLabel]) }}" class="img-thumbnail">
      </a>
      <div class="mt-1"><span class="badge bg-warning text-dark"><i class="fas fa-shield-alt me-1"></i>{{ __('PII Redacted') }}</span></div>
    @else
      <a href="{{ $link }}">
        <img src="{{ $representation->getFullPath() }}" alt="{{ $altTextOpen }}" class="img-thumbnail">
      </a>
    @endif
  @else
    <img src="{{ $representation->getFullPath() }}" alt="{{ $altTextClosed }}" class="img-thumbnail">
  @endif

@else

  @if(($iconOnly ?? false) && isset($link))

    @if($hasPiiRedaction)
      <a href="{{ $link }}" target="_blank">
        <img src="{{ $representation->getFullPath() }}" alt="{{ __('Open redacted %1%', ['%1%' => $digitalObjectLabel]) }}" class="img-thumbnail">
      </a>
    @else
      <a href="{{ $link }}">
        <img src="{{ $representation->getFullPath() }}" alt="{{ $altTextOpen }}" class="img-thumbnail">
      </a>
    @endif

  @else

    <div class="digitalObject text-center" style="width: 120px;">

      @if(isset($link))
        @if($hasPiiRedaction)
          <a href="{{ $link }}" target="_blank">
            <img src="{{ $representation->getFullPath() }}" alt="{{ __('Open redacted %1%', ['%1%' => $digitalObjectLabel]) }}" class="img-thumbnail">
          </a>
        @else
          <a href="{{ $link }}">
            <img src="{{ $representation->getFullPath() }}" alt="{{ $altTextOpen }}" class="img-thumbnail">
          </a>
        @endif
      @else
        <img src="{{ $representation->getFullPath() }}" alt="{{ $altTextClosed }}" class="img-thumbnail">
      @endif

      <div class="digitalObjectDesc small">
        {{ Illuminate\Support\Str::limit(($digitalObject ?? $resource)->name ?? '', 18) }}
      </div>
      @if($hasPiiRedaction)
        <div class="mt-1"><span class="badge bg-warning text-dark small"><i class="fas fa-shield-alt me-1"></i>{{ __('PII Redacted') }}</span></div>
      @endif

    </div>

  @endif

@endif
