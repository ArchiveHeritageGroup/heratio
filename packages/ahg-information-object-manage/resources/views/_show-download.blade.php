@php use_helper('Text'); @endphp

@php // Check for PII redaction (for PDFs)
$hasPiiRedaction = false;
$piiDownloadUrl = null;
$mimeType = $digitalObject->mimeType ?? '';
$isPdf = (stripos($mimeType, 'pdf') !== false);

if ($isPdf && in_array('ahgPrivacyPlugin', sfProjectConfiguration::getActive()->getPlugins())) {
    require_once sfConfig::get('sf_plugins_dir') . '/ahgPrivacyPlugin/lib/helper/PiiHelper.php';
    $objectId = $digitalObject->object->id ?? ($digitalObject->objectId ?? null);
    if ($objectId && function_exists('pii_has_redacted') && pii_has_redacted($objectId)) {
        $hasPiiRedaction = true;
        $piiDownloadUrl = url_for(['module' => 'privacyAdmin', 'action' => 'downloadPdf', 'id' => $objectId]);
        // Replace the link with the redacted download URL for non-admins
        if (!function_exists('pii_can_view_unmasked') || !pii_can_view_unmasked()) {
            $link = $piiDownloadUrl;
        }
    }
} @endphp

@if(QubitTerm::REFERENCE_ID == $usageType)

  @if(isset($link))
    @if($hasPiiRedaction)
      @php echo link_to(image_tag($representation->getFullPath(), ['alt' => __('Open redacted %1%', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']), $link, ['target' => '_blank']); @endphp
      <div class="mt-1"><span class="badge bg-warning text-dark"><i class="fas fa-shield-alt me-1"></i>{{ __('PII Redacted') }}</span></div>
    @else
      @php echo link_to(image_tag($representation->getFullPath(), ['alt' => __($representation->getDigitalObjectAltText() ?: 'Open original %1%', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']), $link); @endphp
    @endif
  @php } else { @endphp
    @php echo image_tag($representation->getFullPath(), ['alt' => __($representation->getDigitalObjectAltText() ?: 'Original %1% not accessible', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']); @endphp
  @endforeach

@php } else { @endphp

  @if($iconOnly && isset($link))

    @if($hasPiiRedaction)
      @php echo link_to(image_tag($representation->getFullPath(), ['alt' => __('Open redacted %1%', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']), $link, ['target' => '_blank']); @endphp
    @else
      @php echo link_to(image_tag($representation->getFullPath(), ['alt' => __($representation->getDigitalObjectAltText() ?: 'Open original %1%', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']), $link); @endphp
    @endif

  @php } else { @endphp

    <div class="digitalObject text-center" style="width: 120px;">

      @if(isset($link))
        @if($hasPiiRedaction)
          @php echo link_to(image_tag($representation->getFullPath(), ['alt' => __('Open redacted %1%', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']), $link, ['target' => '_blank']); @endphp
        @else
          @php echo link_to(image_tag($representation->getFullPath(), ['alt' => __($representation->getDigitalObjectAltText() ?: 'Open original %1%', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']), $link); @endphp
        @endif
      @php } else { @endphp
        @php echo image_tag($representation->getFullPath(), ['alt' => __($representation->getDigitalObjectAltText() ?: 'Original %1% is not accessible', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']); @endphp
      @endforeach

      <div class="digitalObjectDesc small">
        @php echo wrap_text($digitalObject->name, 18); @endphp
      </div>
      @if($hasPiiRedaction)
        <div class="mt-1"><span class="badge bg-warning text-dark small"><i class="fas fa-shield-alt me-1"></i>{{ __('PII Redacted') }}</span></div>
      @endif

    </div>

  @endforeach

@endforeach
