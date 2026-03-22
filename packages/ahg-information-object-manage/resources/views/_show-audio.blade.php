@php use_helper('Text'); @endphp
@php $hasIiifPlayer = false; try { use_helper('Media'); $hasIiifPlayer = function_exists('render_media_player'); } catch (Exception $e) {} @endphp

@if(QubitTerm::CHAPTERS_ID == $usageType)

  @php // Chapters handled internally by player @endphp

@php } elseif (QubitTerm::REFERENCE_ID == $usageType) { @endphp

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
    @php } else { @endphp
      @php // Native HTML5 player (no ahgIiifPlugin) @endphp
      <audio controls class="w-100" preload="metadata">
        <source src="@php echo public_path($representation->getFullPath()); @endphp" type="{{ $resource->mimeType }}">
        Your browser does not support audio playback.
      </audio>
    @endforeach

  @php } else { @endphp
    <div class="text-center">
      @php echo image_tag($representation->getFullPath(), ['class' => 'img-thumbnail', 'alt' => '']); @endphp
    </div>
  @endforeach

  @if(isset($link) && \AtomExtensions\Services\AclService::check($resource->object, 'readMaster'))
    <div class="mt-2">
      @php echo link_to(__('Download audio'), $link, ['class' => 'btn btn-sm btn-outline-secondary']); @endphp
    </div>
  @endforeach

@php } elseif (QubitTerm::THUMBNAIL_ID == $usageType) { @endphp

  @if($iconOnly)
    @if(isset($link))
      @php echo link_to(image_tag('play', ['alt' => __($resource->getDigitalObjectAltText() ?: 'Open original %1%', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']), $link); @endphp
    @php } else { @endphp
      @php echo image_tag('play', ['alt' => __($resource->getDigitalObjectAltText() ?: 'Original %1% not accessible', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']); @endphp
    @endforeach
  @php } else { @endphp
    <div class="digitalObject">
      <div class="digitalObjectRep">
        @if(isset($link))
          @php echo link_to(image_tag('play', ['alt' => __($resource->getDigitalObjectAltText() ?: 'Open original %1%', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']), $link); @endphp
        @php } else { @endphp
          @php echo image_tag('play', ['alt' => __($resource->getDigitalObjectAltText() ?: 'Original %1% not accessible', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']); @endphp
        @endforeach
      </div>
      <div class="digitalObjectDesc">
        @php echo wrap_text($resource->name, 18); @endphp
      </div>
    </div>
  @endforeach

@php } else { @endphp

  <div class="resource">
    @php echo image_tag('play', ['alt' => __($resource->getDigitalObjectAltText() ?: 'Original %1% not accessible', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']); @endphp
  </div>

@endforeach
