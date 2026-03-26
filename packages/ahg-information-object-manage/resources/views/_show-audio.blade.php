@php use_helper('Text'); @endphp
@php $hasIiifPlayer = false; try { use_helper('Media'); $hasIiifPlayer = function_exists('render_media_player'); } catch (Exception $e) {} @endphp

@if(QubitTerm::CHAPTERS_ID == $usageType)

  @php // Chapters handled internally by player @endphp

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
      <audio controls class="w-100" preload="metadata">
        <source src="@php echo public_path($representation->getFullPath()); @endphp" type="{{ $resource->mimeType }}">
        Your browser does not support audio playback.
      </audio>
    @endif

  @else
    <div class="text-center">
      @php echo image_tag($representation->getFullPath(), ['class' => 'img-thumbnail', 'alt' => '']); @endphp
    </div>
  @endif

  @if(isset($link) && \AtomExtensions\Services\AclService::check($resource->object, 'readMaster'))
    <div class="mt-2">
      @php echo link_to(__('Download audio'), $link, ['class' => 'btn btn-sm btn-outline-secondary']); @endphp
    </div>
  @endif

@elseif(QubitTerm::THUMBNAIL_ID == $usageType)

  @if($iconOnly)
    @if(isset($link))
      @php echo link_to(image_tag('play', ['alt' => __($resource->getDigitalObjectAltText() ?: 'Open original %1%', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']), $link); @endphp
    @else
      @php echo image_tag('play', ['alt' => __($resource->getDigitalObjectAltText() ?: 'Original %1% not accessible', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']); @endphp
    @endif
  @else
    <div class="digitalObject">
      <div class="digitalObjectRep">
        @if(isset($link))
          @php echo link_to(image_tag('play', ['alt' => __($resource->getDigitalObjectAltText() ?: 'Open original %1%', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']), $link); @endphp
        @else
          @php echo image_tag('play', ['alt' => __($resource->getDigitalObjectAltText() ?: 'Original %1% not accessible', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']); @endphp
        @endif
      </div>
      <div class="digitalObjectDesc">
        @php echo wrap_text($resource->name, 18); @endphp
      </div>
    </div>
  @endif

@else

  <div class="resource">
    @php echo image_tag('play', ['alt' => __($resource->getDigitalObjectAltText() ?: 'Original %1% not accessible', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']); @endphp
  </div>

@endif
