{{-- Digital object upload form partial --}}
@php
    $digitalObjects = \AhgCore\Services\DigitalObjectService::getForObject($io->id);
    $hasMaster = !empty($digitalObjects['master']);
    $maxUploadBytes = \AhgCore\Services\DigitalObjectService::getMaxUploadSize();
    $maxUploadDisplay = \AhgCore\Services\DigitalObjectService::formatFileSize($maxUploadBytes);
@endphp

<div class="accordion-item">
  <h2 class="accordion-header" id="headingDigitalObject">
    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
            data-bs-target="#collapseDigitalObject" aria-expanded="false" aria-controls="collapseDigitalObject">
      <i class="fas fa-file-upload me-2"></i> Digital object
    </button>
  </h2>
  <div id="collapseDigitalObject" class="accordion-collapse collapse" aria-labelledby="headingDigitalObject">
    <div class="accordion-body">

      @if($hasMaster)
        {{-- Show existing digital object --}}
        @php
            $master = $digitalObjects['master'];
            $mediaType = \AhgCore\Services\DigitalObjectService::getMediaType($master);
            $fileSize = \AhgCore\Services\DigitalObjectService::formatFileSize($master->byte_size);
            $thumbUrl = \AhgCore\Services\DigitalObjectService::getThumbnailUrl($digitalObjects);
        @endphp
        <div class="card mb-3">
          <div class="card-body">
            <div class="d-flex align-items-start gap-3">
              @if($thumbUrl)
                <img src="{{ $thumbUrl }}" alt="Thumbnail" class="img-thumbnail" style="max-width: 100px; max-height: 100px;">
              @endif
              <div>
                <h6 class="mb-1">{{ $master->name }}</h6>
                <p class="text-muted mb-1">
                  <small>
                    {{ $master->mime_type }} &middot; {{ $fileSize }}
                    @if($master->checksum)
                      &middot; {{ $master->checksum_type }}: <code>{{ substr($master->checksum, 0, 12) }}...</code>
                    @endif
                  </small>
                </p>
                <form method="POST" action="{{ route('io.digitalobject.delete', $master->id) }}"
                      onsubmit="return confirm('Delete this digital object and all derivatives? This cannot be undone.')"
                      class="d-inline">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn atom-btn-outline-danger btn-sm">
                    <i class="fas fa-trash me-1"></i> Delete digital object
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      @else
        {{-- Upload form --}}
        <form method="POST" action="{{ route('io.digitalobject.upload', $io->slug) }}"
              enctype="multipart/form-data">
          @csrf

          <div class="mb-3">
            <label for="digital_object" class="form-label">Select file to upload</label>
            <input type="file" class="form-control @error('digital_object') is-invalid @enderror"
                   id="digital_object" name="digital_object"
                   accept="image/*,application/pdf,audio/*,video/*,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.txt,.rtf,.csv,.xml,.json">
            @error('digital_object')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">
              Accepted formats: images, PDF, audio, video, office documents.
              Maximum file size: {{ $maxUploadDisplay }}.
            </div>
          </div>

          <button type="submit" class="btn atom-btn-outline-light btn-sm">
            <i class="fas fa-upload me-1"></i> Upload
          </button>
        </form>
      @endif

    </div>
  </div>
</div>
