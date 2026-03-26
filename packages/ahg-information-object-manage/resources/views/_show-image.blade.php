@php use_helper('Text'); @endphp

@if(QubitTerm::MASTER_ID == $usageType || QubitTerm::REFERENCE_ID == $usageType)

  @if(isset($link))
    @php echo link_to(image_tag($representation->getFullPath(), ['alt' => __($resource->getDigitalObjectAltText() ?: 'Open original %1%', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']), $link, ['target' => '_blank']); @endphp
  @else
    @php echo image_tag($representation->getFullPath(), ['alt' => __($resource->getDigitalObjectAltText() ?: 'Original %1% not accessible', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']); @endphp
  @endif

@elseif(QubitTerm::THUMBNAIL_ID == $usageType)

  @if($iconOnly)
    @if(isset($link))
      @php echo link_to(image_tag($representation->getFullPath(), ['alt' => __($resource->getDigitalObjectAltText() ?: 'Open original %1%', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']), $link); @endphp
    @else
      @php echo image_tag($representation->getFullPath(), ['alt' => __($resource->getDigitalObjectAltText() ?: 'Original %1% not accessible', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']); @endphp
    @endif

  @else

    <div class="digitalObject">

      <div class="digitalObjectRep">
        @if(isset($link))
          @php echo link_to(image_tag($representation->getFullPath(), ['alt' => __($resource->getDigitalObjectAltText() ?: 'Open original %1%', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']), $link); @endphp
        @else
          @php echo image_tag($representation->getFullPath(), ['alt' => __($resource->getDigitalObjectAltText() ?: 'Original %1% not accessible', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']); @endphp
        @endif
      </div>

      <div class="digitalObjectDesc">
        @php echo wrap_text($resource->name, 18); @endphp
      </div>

    </div>

  @endif

@endif
