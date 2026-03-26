@php
$digitalObjectLabel = config('app.ui_label_digitalobject', 'digital object');
$showPreservation = ($showOriginalFileMetadata ?? false) || ($showPreservationCopyMetadata ?? false);
$showAccess = ($showMasterFileMetadata ?? false) || ($showReferenceCopyMetadata ?? false) || ($showThumbnailCopyMetadata ?? false);
@endphp

@if($showPreservation || $showAccess)
  <section>

    @php
      $headingCondition = false;
      if ($relatedToIo ?? false) {
          $headingCondition = auth()->check() && in_array(auth()->user()->role ?? '', ['editor', 'administrator']);
      } elseif ($relatedToActor ?? false) {
          $headingCondition = auth()->check() && in_array(auth()->user()->role ?? '', ['editor', 'administrator']);
      }
    @endphp

    @include('ahg-theme-b5::partials._section-heading', [
        'heading' => __('%1% metadata', ['%1%' => $digitalObjectLabel]),
        'condition' => $headingCondition,
        'link' => route('io.digitalobject.edit', $resource->id ?? 0),
        'anchor' => 'content-collapse',
        'title' => __('Edit %1%', ['%1%' => $digitalObjectLabel]),
    ])

    <div class="accordion accordion-flush">

      @if($showPreservation)

        <div class="accordion-item{{ $showAccess ? '' : ' rounded-bottom' }}">
          <h3 class="accordion-header" id="preservation-heading">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#preservation-collapse" aria-expanded="true" aria-controls="preservation-collapse">
              {{ __('Preservation Copies') }}
            </button>
          </h3>
          <div id="preservation-collapse" class="accordion-collapse collapse show" aria-labelledby="preservation-heading">
            <div class="accordion-body p-0">
              @if($showOriginalFileMetadata ?? false)

                <div class="field">

                  <h3 class="field-label">{{ __('Original file') }}<i class="fa fa-archive ms-2 text-dark{{ !($canAccessOriginalFile ?? false) ? ' text-muted' : '' }}" aria-hidden="true"></i></h3>

                  <div class="digital-object-metadata-body field-value">
                    @if($showOriginalFileName ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Filename') }}</h3>
                        <div class="field-value">{{ $resource->object->originalFileName ?? '' }}</div>
                      </div>
                    @endif

                    @if($showOriginalFormatName ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Format name') }}</h3>
                        <div class="field-value">{{ $resource->object->formatName ?? '' }}</div>
                      </div>
                    @endif

                    @if($showOriginalFormatVersion ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Format version') }}</h3>
                        <div class="field-value">{{ $resource->object->formatVersion ?? '' }}</div>
                      </div>
                    @endif

                    @if($showOriginalFormatRegistryKey ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Format registry key') }}</h3>
                        <div class="field-value">{{ $resource->object->formatRegistryKey ?? '' }}</div>
                      </div>
                    @endif

                    @if($showOriginalFormatRegistryName ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Format registry name') }}</h3>
                        <div class="field-value">{{ $resource->object->formatRegistryName ?? '' }}</div>
                      </div>
                    @endif

                    @if($showOriginalFileSize ?? false)
                      @php
                        $origBytes = intval($resource->object->originalFileSize ?? 0);
                        if ($origBytes >= 1073741824) {
                            $origSize = number_format($origBytes / 1073741824, 2) . ' GB';
                        } elseif ($origBytes >= 1048576) {
                            $origSize = number_format($origBytes / 1048576, 2) . ' MB';
                        } elseif ($origBytes >= 1024) {
                            $origSize = number_format($origBytes / 1024, 2) . ' KB';
                        } else {
                            $origSize = $origBytes . ' B';
                        }
                      @endphp
                      <div class="field">
                        <h3 class="field-label">{{ __('Filesize') }}</h3>
                        <div class="field-value">{{ $origSize }}</div>
                      </div>
                    @endif

                    @if($showOriginalFileIngestedAt ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Ingested') }}</h3>
                        <div class="field-value">{{ isset($originalFileIngestedAt) ? \Carbon\Carbon::parse($originalFileIngestedAt)->format('F j, Y') : '' }}</div>
                      </div>
                    @endif

                    @if($showOriginalFilePermissions ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Permissions') }}</h3>
                        <div class="field-value">{{ $accessStatement ?? '' }}</div>
                      </div>
                    @endif

                    @if(auth()->check() && ($relatedToIo ?? false))
                      @if($storageServicePluginEnabled ?? false)
                        @include('ahg-information-object-manage::partials._aip-download', ['resource' => $resource])
                      @else
                        <div class="field">
                          <h3 class="field-label">{{ __('File UUID') }}</h3>
                          <div class="field-value">{{ $resource->object->objectUUID ?? '' }}</div>
                        </div>
                        <div class="field">
                          <h3 class="field-label">{{ __('AIP UUID') }}</h3>
                          <div class="field-value">{{ $resource->object->aipUUID ?? '' }}</div>
                        </div>
                      @endif
                    @endif
                  </div>

                </div>

              @endif

              @if($showPreservationCopyMetadata ?? false)

                <div class="field">

                  <h3 class="field-label">{{ __('Preservation copy') }}<i class="fa fa-archive ms-2 text-dark{{ !($canAccessPreservationCopy ?? false) ? ' text-muted' : '' }}" aria-hidden="true"></i></h3>

                  <div class="digital-object-metadata-body field-value">
                    @if($showPreservationCopyFileName ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Filename') }}</h3>
                        <div class="field-value">{{ $resource->object->preservationCopyFileName ?? '' }}</div>
                      </div>
                    @endif

                    @if($showPreservationCopyFileSize ?? false)
                      @php
                        $pcBytes = intval($resource->object->preservationCopyFileSize ?? 0);
                        if ($pcBytes >= 1073741824) {
                            $pcSize = number_format($pcBytes / 1073741824, 2) . ' GB';
                        } elseif ($pcBytes >= 1048576) {
                            $pcSize = number_format($pcBytes / 1048576, 2) . ' MB';
                        } elseif ($pcBytes >= 1024) {
                            $pcSize = number_format($pcBytes / 1024, 2) . ' KB';
                        } else {
                            $pcSize = $pcBytes . ' B';
                        }
                      @endphp
                      <div class="field">
                        <h3 class="field-label">{{ __('Filesize') }}</h3>
                        <div class="field-value">{{ $pcSize }}</div>
                      </div>
                    @endif

                    @if($showPreservationCopyNormalizedAt ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Normalized') }}</h3>
                        <div class="field-value">{{ isset($preservationCopyNormalizedAt) ? \Carbon\Carbon::parse($preservationCopyNormalizedAt)->format('F j, Y') : '' }}</div>
                      </div>
                    @endif

                    @if($showPreservationCopyPermissions ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Permissions') }}</h3>
                        <div class="field-value">{{ $accessStatement ?? '' }}</div>
                      </div>
                    @endif
                  </div>

                </div>

              @endif
            </div>
          </div>
        </div>

      @endif

      @if($showAccess)

        <div class="accordion-item rounded-bottom">
          <h3 class="accordion-header" id="access-heading">
            <button class="accordion-button{{ $showPreservation ? ' collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#access-collapse" aria-expanded="{{ $showPreservation ? 'false' : 'true' }}" aria-controls="access-collapse">
              {{ __('Access Copies') }}
            </button>
          </h3>
          <div id="access-collapse" class="accordion-collapse collapse{{ $showPreservation ? '' : ' show' }}" aria-labelledby="access-heading">
            <div class="accordion-body p-0">
              @if($showMasterFileMetadata ?? false)

                <div class="field">

                  <h3 class="field-label">{{ __('Master file') }}<i class="fa fa-file ms-2 text-dark{{ !($canAccessMasterFile ?? false) ? ' text-muted' : '' }}" aria-hidden="true"></i></h3>

                  <div class="digital-object-metadata-body field-value">
                    @if($showMasterFileGoogleMap ?? false)
                      <div class="p-1">
                        <div id="front-map" class="simple-map" data-key="{{ $googleMapsApiKey ?? '' }}" data-latitude="{{ $latitude ?? '' }}" data-longitude="{{ $longitude ?? '' }}"></div>
                      </div>
                    @endif

                    @if($showMasterFileGeolocation ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Latitude') }}</h3>
                        <div class="field-value">{{ $latitude ?? '' }}</div>
                      </div>
                      <div class="field">
                        <h3 class="field-label">{{ __('Longitude') }}</h3>
                        <div class="field-value">{{ $longitude ?? '' }}</div>
                      </div>
                    @endif

                    @if($showMasterFileURL ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('URL') }}</h3>
                        <div class="field-value">{{ $resource->path ?? '' }}</div>
                      </div>
                    @endif

                    @if($showMasterFileName ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Filename') }}</h3>
                        <div class="field-value">
                          @if(($canAccessMasterFile ?? false))
                            <a href="{{ $resource->object->getDigitalObjectUrl ?? '#' }}" target="_blank">{{ $resource->name }}</a>
                          @else
                            {{ $resource->name }}
                          @endif
                        </div>
                      </div>
                    @endif

                    @if($showMasterFileMediaType ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Media type') }}</h3>
                        <div class="field-value">{{ $resource->mediaType ?? '' }}</div>
                      </div>
                    @endif

                    @if($showMasterFileMimeType ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Mime-type') }}</h3>
                        <div class="field-value">{{ $resource->mimeType ?? '' }}</div>
                      </div>
                    @endif

                    @if($showMasterFileSize ?? false)
                      @php
                        $masterBytes = $resource->byteSize ?? 0;
                        if ($masterBytes >= 1073741824) {
                            $masterSize = number_format($masterBytes / 1073741824, 2) . ' GB';
                        } elseif ($masterBytes >= 1048576) {
                            $masterSize = number_format($masterBytes / 1048576, 2) . ' MB';
                        } elseif ($masterBytes >= 1024) {
                            $masterSize = number_format($masterBytes / 1024, 2) . ' KB';
                        } else {
                            $masterSize = $masterBytes . ' B';
                        }
                      @endphp
                      <div class="field">
                        <h3 class="field-label">{{ __('Filesize') }}</h3>
                        <div class="field-value">{{ $masterSize }}</div>
                      </div>
                    @endif

                    @if($showMasterFileCreatedAt ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Uploaded') }}</h3>
                        <div class="field-value">{{ isset($resource->createdAt) ? \Carbon\Carbon::parse($resource->createdAt)->format('F j, Y') : '' }}</div>
                      </div>
                    @endif

                    @if($showMasterFilePermissions ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Permissions') }}</h3>
                        <div class="field-value">{{ $masterFileDenyReason ?? '' }}</div>
                      </div>
                    @endif
                  </div>

                </div>

              @endif

              @if($showReferenceCopyMetadata ?? false)

                <div class="field">

                  <h3 class="field-label">{{ __('Reference copy') }}<i class="fa fa-file ms-2 text-dark{{ !($canAccessReferenceCopy ?? false) ? ' text-muted' : '' }}" aria-hidden="true"></i></h3>

                  <div class="digital-object-metadata-body field-value">
                    @if($showReferenceCopyFileName ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Filename') }}</h3>
                        <div class="field-value">
                          @if(($canAccessReferenceCopy ?? false) && auth()->check())
                            <a href="{{ $referenceCopy->getFullPath() }}" target="_blank">{{ $referenceCopy->name }}</a>
                          @else
                            {{ $referenceCopy->name ?? '' }}
                          @endif
                        </div>
                      </div>
                    @endif

                    @if($showReferenceCopyMediaType ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Media type') }}</h3>
                        <div class="field-value">{{ $referenceCopy->mediaType ?? '' }}</div>
                      </div>
                    @endif

                    @if($showReferenceCopyMimeType ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Mime-type') }}</h3>
                        <div class="field-value">{{ $referenceCopy->mimeType ?? '' }}</div>
                      </div>
                    @endif

                    @if($showReferenceCopyFileSize ?? false)
                      @php
                        $refBytes = $referenceCopy->byteSize ?? 0;
                        if ($refBytes >= 1073741824) {
                            $refSize = number_format($refBytes / 1073741824, 2) . ' GB';
                        } elseif ($refBytes >= 1048576) {
                            $refSize = number_format($refBytes / 1048576, 2) . ' MB';
                        } elseif ($refBytes >= 1024) {
                            $refSize = number_format($refBytes / 1024, 2) . ' KB';
                        } else {
                            $refSize = $refBytes . ' B';
                        }
                      @endphp
                      <div class="field">
                        <h3 class="field-label">{{ __('Filesize') }}</h3>
                        <div class="field-value">{{ $refSize }}</div>
                      </div>
                    @endif

                    @if($showReferenceCopyCreatedAt ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Uploaded') }}</h3>
                        <div class="field-value">{{ isset($referenceCopy->createdAt) ? \Carbon\Carbon::parse($referenceCopy->createdAt)->format('F j, Y') : '' }}</div>
                      </div>
                    @endif

                    @if($showReferenceCopyPermissions ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Permissions') }}</h3>
                        <div class="field-value">{{ $referenceCopyDenyReason ?? '' }}</div>
                      </div>
                    @endif
                  </div>

                </div>

              @endif

              @if($showThumbnailCopyMetadata ?? false)

                <div class="field">

                  <h3 class="field-label">{{ __('Thumbnail copy') }}<i class="fa fa-file ms-2 text-dark{{ !($canAccessThumbnailCopy ?? false) ? ' text-muted' : '' }}" aria-hidden="true"></i></h3>

                  <div class="digital-object-metadata-body field-value">
                    @if($showThumbnailCopyFileName ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Filename') }}</h3>
                        <div class="field-value">
                          @if($canAccessThumbnailCopy ?? false)
                            <a href="{{ $thumbnailCopy->getFullPath() }}" target="_blank">{{ $thumbnailCopy->name }}</a>
                          @else
                            {{ $thumbnailCopy->name ?? '' }}
                          @endif
                        </div>
                      </div>
                    @endif

                    @if($showThumbnailCopyMediaType ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Media type') }}</h3>
                        <div class="field-value">{{ $thumbnailCopy->mediaType ?? '' }}</div>
                      </div>
                    @endif

                    @if($showThumbnailCopyMimeType ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Mime-type') }}</h3>
                        <div class="field-value">{{ $thumbnailCopy->mimeType ?? '' }}</div>
                      </div>
                    @endif

                    @if($showThumbnailCopyFileSize ?? false)
                      @php
                        $thumbBytes = $thumbnailCopy->byteSize ?? 0;
                        if ($thumbBytes >= 1073741824) {
                            $thumbSize = number_format($thumbBytes / 1073741824, 2) . ' GB';
                        } elseif ($thumbBytes >= 1048576) {
                            $thumbSize = number_format($thumbBytes / 1048576, 2) . ' MB';
                        } elseif ($thumbBytes >= 1024) {
                            $thumbSize = number_format($thumbBytes / 1024, 2) . ' KB';
                        } else {
                            $thumbSize = $thumbBytes . ' B';
                        }
                      @endphp
                      <div class="field">
                        <h3 class="field-label">{{ __('Filesize') }}</h3>
                        <div class="field-value">{{ $thumbSize }}</div>
                      </div>
                    @endif

                    @if($showThumbnailCopyCreatedAt ?? false)
                      <div class="field">
                        <h3 class="field-label">{{ __('Uploaded') }}</h3>
                        <div class="field-value">{{ isset($thumbnailCopy->createdAt) ? \Carbon\Carbon::parse($thumbnailCopy->createdAt)->format('F j, Y') : '' }}</div>
                      </div>
                    @endif

                    @if(!empty($thumbnailCopyDenyReason))
                      <div class="field">
                        <h3 class="field-label">{{ __('Permissions') }}</h3>
                        <div class="field-value">{{ $thumbnailCopyDenyReason }}</div>
                      </div>
                    @endif
                  </div>

                </div>

              @endif
            </div>
          </div>
        </div>

      @endif

    </div>

  </section>
@endif
