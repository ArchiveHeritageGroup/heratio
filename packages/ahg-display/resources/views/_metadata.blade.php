{{-- Digital Object Metadata --}}
{{-- Blocks PDF downloads for non-authenticated users (GLAM/DAM wide policy) --}}
@php
$isPdf = isset($resource) && stripos($resource->mimeType ?? $resource->mime_type ?? '', 'pdf') !== false;
$canAccessMasterFileFinal = $canAccessMasterFile ?? false;
if ($isPdf && !auth()->check()) {
    $canAccessMasterFileFinal = false;
}
@endphp

<section>

  @if($relatedToIo ?? false)
    @can('edit', $resource)
      <a href="{{ route('digitalobject.edit', ['id' => $resource->id]) }}"><h2>{{ __(':label metadata', ['label' => config('app.ui_label_digitalobject', 'Digital object')]) }}</h2></a>
    @else
      <h2>{{ __(':label metadata', ['label' => config('app.ui_label_digitalobject', 'Digital object')]) }}</h2>
    @endcan
  @elseif($relatedToActor ?? false)
    @can('edit', $resource)
      <a href="{{ route('digitalobject.edit', ['id' => $resource->id]) }}"><h2>{{ __(':label metadata', ['label' => config('app.ui_label_digitalobject', 'Digital object')]) }}</h2></a>
    @else
      <h2>{{ __(':label metadata', ['label' => config('app.ui_label_digitalobject', 'Digital object')]) }}</h2>
    @endcan
  @endif

  @if(($showOriginalFileMetadata ?? false) || ($showPreservationCopyMetadata ?? false))

    <fieldset class="collapsible digital-object-metadata single">
      <legend>{{ __('Preservation Copies') }}</legend>

      @if($showOriginalFileMetadata ?? false)

        <div class="digital-object-metadata-header">
          <h3>{{ __('Original file') }} <i class="fa fa-archive{{ !($canAccessOriginalFile ?? false) ? ' inactive' : '' }}" aria-hidden="true"></i></h3>
        </div>

        <div class="digital-object-metadata-body">
          @if($showOriginalFileName ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Filename') }}</h6><div class="field-value">{{ $resource->object->originalFileName ?? '-' }}</div></div>
          @endif

          @if($showOriginalFormatName ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Format name') }}</h6><div class="field-value">{{ $resource->object->formatName ?? '-' }}</div></div>
          @endif

          @if($showOriginalFormatVersion ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Format version') }}</h6><div class="field-value">{{ $resource->object->formatVersion ?? '-' }}</div></div>
          @endif

          @if($showOriginalFormatRegistryKey ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Format registry key') }}</h6><div class="field-value">{{ $resource->object->formatRegistryKey ?? '-' }}</div></div>
          @endif

          @if($showOriginalFormatRegistryName ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Format registry name') }}</h6><div class="field-value">{{ $resource->object->formatRegistryName ?? '-' }}</div></div>
          @endif

          @if($showOriginalFileSize ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Filesize') }}</h6><div class="field-value">{{ \App\Helpers\FormatHelper::hrFilesize(intval($resource->object->originalFileSize ?? 0)) }}</div></div>
          @endif

          @if($showOriginalFileIngestedAt ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Ingested') }}</h6><div class="field-value">{{ $originalFileIngestedAt ? \Carbon\Carbon::parse($originalFileIngestedAt)->format('F j, Y') : '-' }}</div></div>
          @endif

          @if($showOriginalFilePermissions ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Permissions') }}</h6><div class="field-value">{{ $accessStatement ?? '-' }}</div></div>
          @endif

          @auth
            @if($relatedToIo ?? false)
              @if($storageServicePluginEnabled ?? false)
                @include('arStorageService::aipDownload', ['resource' => $resource])
              @else
                <div class="field-block"><h6 class="field-label text-muted">{{ __('File UUID') }}</h6><div class="field-value">{{ $resource->object->objectUUID ?? '-' }}</div></div>
                <div class="field-block"><h6 class="field-label text-muted">{{ __('AIP UUID') }}</h6><div class="field-value">{{ $resource->object->aipUUID ?? '-' }}</div></div>
              @endif
            @endif
          @endauth

        </div>

      @endif

      @if($showPreservationCopyMetadata ?? false)

        <div class="digital-object-metadata-header">
          <h3>{{ __('Preservation copy') }} <i class="fa fa-archive{{ !($canAccessPreservationCopy ?? false) ? ' inactive' : '' }}" aria-hidden="true"></i></h3>
        </div>

        <div class="digital-object-metadata-body">
          @if($showPreservationCopyFileName ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Filename') }}</h6><div class="field-value">{{ $resource->object->preservationCopyFileName ?? '-' }}</div></div>
          @endif

          @if($showPreservationCopyFileSize ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Filesize') }}</h6><div class="field-value">{{ \App\Helpers\FormatHelper::hrFilesize(intval($resource->object->preservationCopyFileSize ?? 0)) }}</div></div>
          @endif

          @if($showPreservationCopyNormalizedAt ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Normalized') }}</h6><div class="field-value">{{ $preservationCopyNormalizedAt ? \Carbon\Carbon::parse($preservationCopyNormalizedAt)->format('F j, Y') : '-' }}</div></div>
          @endif

          @if($showPreservationCopyPermissions ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Permissions') }}</h6><div class="field-value">{{ $accessStatement ?? '-' }}</div></div>
          @endif

        </div>

      @endif

    </fieldset>

  @endif

  @if(($showMasterFileMetadata ?? false) || ($showReferenceCopyMetadata ?? false) || ($showThumbnailCopyMetadata ?? false))

    <fieldset class="collapsible digital-object-metadata single">
      <legend>{{ __('Access Copies') }}</legend>

      @if($showMasterFileMetadata ?? false)

        <div class="digital-object-metadata-header">
          <h3>{{ __('Master file') }} <i class="fa fa-file{{ !$canAccessMasterFileFinal ? ' inactive' : '' }}" aria-hidden="true"></i></h3>
        </div>

        <div class="digital-object-metadata-body">
          @if($showMasterFileGoogleMap ?? false)
            <div id="front-map" class="simple-map" data-key="{{ $googleMapsApiKey ?? '' }}" data-latitude="{{ $latitude ?? '' }}" data-longitude="{{ $longitude ?? '' }}"></div>
          @endif

          @if($showMasterFileGeolocation ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Latitude') }}</h6><div class="field-value">{{ $latitude ?? '-' }}</div></div>
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Longitude') }}</h6><div class="field-value">{{ $longitude ?? '-' }}</div></div>
          @endif

          @if($showMasterFileURL ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('URL') }}</h6><div class="field-value">{{ $resource->path ?? '-' }}</div></div>
          @endif

          @if($showMasterFileName ?? false)
            @if($canAccessMasterFileFinal)
              <div class="field-block"><h6 class="field-label text-muted">{{ __('Filename') }}</h6><div class="field-value"><a href="{{ $resource->object->getDigitalObjectUrl ?? ($resource->path ?? '#') }}" target="_blank">{{ $resource->name ?? '-' }}</a></div></div>
            @else
              <div class="field-block"><h6 class="field-label text-muted">{{ __('Filename') }}</h6><div class="field-value">{{ $resource->name ?? '-' }}</div></div>
              @if($isPdf && !auth()->check())
                <div class="alert alert-info small mt-2">
                  <i class="fas fa-lock me-1"></i>
                  {{ __('Please log in to download this PDF file.') }}
                </div>
              @endif
            @endif
          @endif

          @if($showMasterFileMediaType ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Media type') }}</h6><div class="field-value">{{ $resource->mediaType ?? $resource->media_type ?? '-' }}</div></div>
          @endif

          @if($showMasterFileMimeType ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Mime-type') }}</h6><div class="field-value">{{ $resource->mimeType ?? $resource->mime_type ?? '-' }}</div></div>
          @endif

          @if($showMasterFileSize ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Filesize') }}</h6><div class="field-value">{{ \App\Helpers\FormatHelper::hrFilesize($resource->byteSize ?? $resource->byte_size ?? 0) }}</div></div>
          @endif

          @if($showMasterFileCreatedAt ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Uploaded') }}</h6><div class="field-value">{{ $resource->createdAt ?? $resource->created_at ? \Carbon\Carbon::parse($resource->createdAt ?? $resource->created_at)->format('F j, Y') : '-' }}</div></div>
          @endif

          @if($showMasterFilePermissions ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Permissions') }}</h6><div class="field-value">{{ $masterFileDenyReason ?? '-' }}</div></div>
          @endif

        </div>

      @endif

      @if($showReferenceCopyMetadata ?? false)

        <div class="digital-object-metadata-header">
          <h3>{{ __('Reference copy') }} <i class="fa fa-file{{ !($canAccessReferenceCopy ?? false) ? ' inactive' : '' }}" aria-hidden="true"></i></h3>
        </div>

        <div class="digital-object-metadata-body">
          @if($showReferenceCopyFileName ?? false)
            @if(($canAccessReferenceCopy ?? false) && auth()->check())
              <div class="field-block"><h6 class="field-label text-muted">{{ __('Filename') }}</h6><div class="field-value"><a href="{{ $referenceCopy->path ?? '#' }}" target="_blank">{{ $referenceCopy->name ?? '-' }}</a></div></div>
            @else
              <div class="field-block"><h6 class="field-label text-muted">{{ __('Filename') }}</h6><div class="field-value">{{ $referenceCopy->name ?? '-' }}</div></div>
            @endif
          @endif

          @if($showReferenceCopyMediaType ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Media type') }}</h6><div class="field-value">{{ $referenceCopy->mediaType ?? $referenceCopy->media_type ?? '-' }}</div></div>
          @endif

          @if($showReferenceCopyMimeType ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Mime-type') }}</h6><div class="field-value">{{ $referenceCopy->mimeType ?? $referenceCopy->mime_type ?? '-' }}</div></div>
          @endif

          @if($showReferenceCopyFileSize ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Filesize') }}</h6><div class="field-value">{{ \App\Helpers\FormatHelper::hrFilesize($referenceCopy->byteSize ?? $referenceCopy->byte_size ?? 0) }}</div></div>
          @endif

          @if($showReferenceCopyCreatedAt ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Uploaded') }}</h6><div class="field-value">{{ $referenceCopy->createdAt ?? $referenceCopy->created_at ? \Carbon\Carbon::parse($referenceCopy->createdAt ?? $referenceCopy->created_at)->format('F j, Y') : '-' }}</div></div>
          @endif

          @if($showReferenceCopyPermissions ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Permissions') }}</h6><div class="field-value">{{ $referenceCopyDenyReason ?? '-' }}</div></div>
          @endif

        </div>

      @endif

      @if($showThumbnailCopyMetadata ?? false)

        <div class="digital-object-metadata-header">
          <h3>{{ __('Thumbnail copy') }} <i class="fa fa-file{{ !($canAccessThumbnailCopy ?? false) ? ' inactive' : '' }}" aria-hidden="true"></i></h3>
        </div>

        <div class="digital-object-metadata-body">
          @if($showThumbnailCopyFileName ?? false)
            @if($canAccessThumbnailCopy ?? false)
              <div class="field-block"><h6 class="field-label text-muted">{{ __('Filename') }}</h6><div class="field-value"><a href="{{ $thumbnailCopy->path ?? '#' }}" target="_blank">{{ $thumbnailCopy->name ?? '-' }}</a></div></div>
            @else
              <div class="field-block"><h6 class="field-label text-muted">{{ __('Filename') }}</h6><div class="field-value">{{ $thumbnailCopy->name ?? '-' }}</div></div>
            @endif
          @endif

          @if($showThumbnailCopyMediaType ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Media type') }}</h6><div class="field-value">{{ $thumbnailCopy->mediaType ?? $thumbnailCopy->media_type ?? '-' }}</div></div>
          @endif

          @if($showThumbnailCopyMimeType ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Mime-type') }}</h6><div class="field-value">{{ $thumbnailCopy->mimeType ?? $thumbnailCopy->mime_type ?? '-' }}</div></div>
          @endif

          @if($showThumbnailCopyFileSize ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Filesize') }}</h6><div class="field-value">{{ \App\Helpers\FormatHelper::hrFilesize($thumbnailCopy->byteSize ?? $thumbnailCopy->byte_size ?? 0) }}</div></div>
          @endif

          @if($showThumbnailCopyCreatedAt ?? false)
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Uploaded') }}</h6><div class="field-value">{{ $thumbnailCopy->createdAt ?? $thumbnailCopy->created_at ? \Carbon\Carbon::parse($thumbnailCopy->createdAt ?? $thumbnailCopy->created_at)->format('F j, Y') : '-' }}</div></div>
          @endif

          @if(!empty($thumbnailCopyDenyReason ?? null))
            <div class="field-block"><h6 class="field-label text-muted">{{ __('Permissions') }}</h6><div class="field-value">{{ $thumbnailCopyDenyReason }}</div></div>
          @endif

        </div>

      @endif

    </fieldset>

  @endif

</section>
