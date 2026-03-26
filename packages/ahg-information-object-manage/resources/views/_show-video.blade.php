@php use_helper('Text'); @endphp
@php $hasIiifPlayer = false; try { use_helper('Media'); $hasIiifPlayer = function_exists('render_media_player'); } catch (Exception $e) {} @endphp

@if(QubitTerm::MASTER_ID == $usageType)

  @if(isset($link))
    @php echo image_tag($representation->getFullPath(), ['alt' => __($resource->getDigitalObjectAltText() ?: 'Original %1% not accessible', ['%1%' => sfConfig::get('app_ui_label_digitalobject')])]); @endphp
  @else
    @php echo link_to(image_tag($representation->getFullPath(), ['alt' => __($resource->getDigitalObjectAltText() ?: 'Open original %1%', ['%1%' => sfConfig::get('app_ui_label_digitalobject')])]), $link); @endphp
  @endif

@elseif(QubitTerm::CHAPTERS_ID == $usageType)

  @php // Chapters handled internally by player @endphp

@elseif(QubitTerm::SUBTITLES_ID == $usageType)

  @php // Subtitles handled internally by player @endphp

@elseif(QubitTerm::REFERENCE_ID == $usageType)

  @if($showMediaPlayer)

    @if($hasIiifPlayer)
      @php // ahgIiifPlugin enabled — use AhgMediaPlayer JS player @endphp
      @php echo render_media_player([
          'id' => $resource->id,
          'name' => $resource->name,
          'path' => $resource->path,
          'mimeType' => $resource->mimeType,
          'mediaTypeId' => $resource->mediaTypeId ?? null,
          'object_id' => $resource->object->id ?? $resource->objectId ?? 0,
      ]); @endphp
    @else
      @php // Native HTML5 player (no ahgIiifPlugin) @endphp
      <video controls class="w-100" style="max-height:500px;" preload="metadata">
        <source src="@php echo public_path($representation->getFullPath()); @endphp" type="{{ $resource->mimeType }}">
        Your browser does not support video playback.
      </video>
    @endif

  @else
    <div style="text-align: center">
      @php echo image_tag($representation->getFullPath(), ['style' => 'border: #999 1px solid', 'alt' => '']); @endphp
    </div>
  @endif

  @if(isset($link) && \AtomExtensions\Services\AclService::check($resource->object, 'readMaster'))
    <div class="mt-2">
      @php echo link_to(__('Download video'), $link, ['class' => 'btn btn-sm btn-outline-secondary']); @endphp
    </div>
  @endif

@elseif(QubitTerm::THUMBNAIL_ID == $usageType)

  @if($iconOnly)
    @if(isset($link))
      @php echo link_to(image_tag($representation->getFullPath(), ['alt' => __($resource->getDigitalObjectAltText() ?: 'Open original %1%', ['%1%' => sfConfig::get('app_ui_label_digitalobject')])]), $link); @endphp
    @else
      @php echo image_tag($representation->getFullPath(), ['alt' => __($resource->getDigitalObjectAltText() ?: 'Original %1% not accessible', ['%1%' => sfConfig::get('app_ui_label_digitalobject')])]); @endphp
    @endif
  @else
    <div class="digitalObject">
      <div class="digitalObjectRep">
        @if(isset($link))
          @php echo link_to(image_tag($representation->getFullPath(), ['alt' => __($resource->getDigitalObjectAltText() ?: 'Open original %1%', ['%1%' => sfConfig::get('app_ui_label_digitalobject')])]), $link); @endphp
        @else
          @php echo image_tag($representation->getFullPath(), ['alt' => __($resource->getDigitalObjectAltText() ?: 'Original %1% not accessible', ['%1%' => sfConfig::get('app_ui_label_digitalobject')])]); @endphp
        @endif
      </div>
      <div class="digitalObjectDesc">
        @php echo wrap_text($resource->name, 18); @endphp
      </div>
    </div>
  @endif

@endif
