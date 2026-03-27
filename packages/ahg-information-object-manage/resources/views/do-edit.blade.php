@extends('theme::layout_1col')

@section('title')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      {{ __('Edit %1%', ['%1%' => mb_strtolower(config('app.ui_label_digitalobject', 'digital object'))]) }}
    </h1>
    <span class="small" id="heading-label">
      {{ $object->authorized_form_of_name ?? $object->title ?? '' }}
    </span>
  </div>
@endsection

@section('content')

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ $formAction ?? route('io.digitalobject.update', $resource->id ?? 0) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="border border-bottom-0 rounded-0 rounded-top bg-white">
      @include('ahg-information-object-manage::_show-image', [
          'resource' => $resource,
          'usageType' => config('atom.term.REFERENCE_ID'),
          'representation' => $referenceRepresentation ?? $resource,
          'iconOnly' => false,
          'link' => null,
      ])
    </div>

    <div class="accordion mb-3">
      <div class="accordion-item rounded-0">
        <h2 class="accordion-header" id="master-heading">
          <button class="accordion-button rounded-0" type="button" data-bs-toggle="collapse" data-bs-target="#master-collapse" aria-expanded="true" aria-controls="master-collapse">
            {{ __('Master') }}
          </button>
        </h2>
        <div id="master-collapse" class="accordion-collapse collapse show" aria-labelledby="master-heading">
          <div class="accordion-body">
            @if(isset($resource->name))
              <div class="mb-3">
                <h3 class="fs-6 mb-2">{{ __('Filename') }}</h3>
                <span class="text-muted">{{ $resource->name }}</span>
              </div>
            @endif

            @if(isset($resource->byteSize))
              <div class="mb-3">
                <h3 class="fs-6 mb-2">{{ __('Filesize') }}</h3>
                <span class="text-muted">
                  @php
                    $bytes = $resource->byteSize;
                    if ($bytes >= 1073741824) { $size = number_format($bytes / 1073741824, 2) . ' GB'; }
                    elseif ($bytes >= 1048576) { $size = number_format($bytes / 1048576, 2) . ' MB'; }
                    elseif ($bytes >= 1024) { $size = number_format($bytes / 1024, 2) . ' KB'; }
                    else { $size = $bytes . ' B'; }
                  @endphp
                  {{ $size }}
                </span>
              </div>
            @endif

            <div class="mb-3">
              <label for="mediaType" class="form-label">{{ __('Media type') }}</label>
              <select class="form-select" id="mediaType" name="mediaType">
                @foreach($mediaTypes ?? [] as $id => $name)
                  <option value="{{ $id }}" @selected(($resource->mediaTypeId ?? '') == $id)>{{ $name }}</option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label for="digitalObjectAltText" class="form-label">{{ __('Alt text') }}</label>
              <input type="text" class="form-control" id="digitalObjectAltText" name="digitalObjectAltText" value="{{ $resource->getDigitalObjectAltText() ?? '' }}">
            </div>

            @if($showCompoundObjectToggle ?? false)
              <div class="mb-3">
                <label for="displayAsCompound" class="form-label">
                  {{ __('View children as a compound %1%?', ['%1%' => mb_strtolower(config('app.ui_label_digitalobject', 'digital object'))]) }}
                </label>
                <select class="form-select" id="displayAsCompound" name="displayAsCompound">
                  <option value="1" @selected($resource->displayAsCompoundObject ?? false)>{{ __('Yes') }}</option>
                  <option value="0" @selected(!($resource->displayAsCompoundObject ?? false))>{{ __('No') }}</option>
                </select>
              </div>
            @endif

            <div class="mb-3">
              <label for="latitude" class="form-label">{{ __('Latitude') }}</label>
              <input type="text" class="form-control" id="latitude" name="latitude" value="{{ $resource->latitude ?? '' }}">
            </div>

            <div class="mb-3">
              <label for="longitude" class="form-label">{{ __('Longitude') }}</label>
              <input type="text" class="form-control" id="longitude" name="longitude" value="{{ $resource->longitude ?? '' }}">
            </div>
          </div>
        </div>
      </div>

      @foreach($representations ?? [] as $usageId => $representation)
        <div class="accordion-item">
          <h2 class="accordion-header" id="heading-{{ $usageId }}">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $usageId }}" aria-expanded="false" aria-controls="collapse-{{ $usageId }}">
              {{ __('%1% representation', ['%1%' => $usageLabels[$usageId] ?? $usageId]) }}
            </button>
          </h2>
          <div id="collapse-{{ $usageId }}" class="accordion-collapse collapse" aria-labelledby="heading-{{ $usageId }}">
            <div class="accordion-body">
              @if(isset($representation))
                @include('ahg-information-object-manage::_edit-representation', [
                    'resource' => $resource,
                    'representation' => $representation,
                ])
              @else
                <div class="mb-3">
                  <label for="repFile_{{ $usageId }}" class="form-label">
                    {{ __('Select a %1% to upload', ['%1%' => mb_strtolower(config('app.ui_label_digitalobject', 'digital object'))]) }}
                  </label>
                  <input type="file" class="form-control" id="repFile_{{ $usageId }}" name="repFile_{{ $usageId }}">
                </div>

                @if($resource->canThumbnail ?? false)
                  <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="generateDerivative_{{ $usageId }}" name="generateDerivative_{{ $usageId }}" value="1">
                    <label class="form-check-label" for="generateDerivative_{{ $usageId }}">
                      {{ __('Or auto-generate a new representation from master image') }}
                    </label>
                  </div>
                @endif
              @endif
            </div>
          </div>
        </div>
      @endforeach

      @php
        $videoId = config('atom.term.VIDEO_ID');
        $audioId = config('atom.term.AUDIO_ID');
        $subtitlesId = config('atom.term.SUBTITLES_ID');
      @endphp

      @if($videoId == ($resource->mediaTypeId ?? null) || $audioId == ($resource->mediaTypeId ?? null))
        @foreach($videoTracks ?? [] as $usageId => $videoTrack)
          @if($videoId == ($resource->mediaTypeId ?? null) && $subtitlesId == $usageId)
            @include('ahg-information-object-manage::_edit-subtitles', [
                'resource' => $resource,
                'subtitles' => $videoTrack,
                'usageId' => $usageId,
                'usageLabel' => $usageLabels[$usageId] ?? $usageId,
                'languages' => $languages ?? [],
            ])
          @elseif($subtitlesId != $usageId)
            <div class="accordion-item">
              <h2 class="accordion-header" id="heading-{{ $usageId }}">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $usageId }}" aria-expanded="false" aria-controls="collapse-{{ $usageId }}">
                  {{ $usageLabels[$usageId] ?? $usageId }}
                </button>
              </h2>
              <div id="collapse-{{ $usageId }}" class="accordion-collapse collapse" aria-labelledby="heading-{{ $usageId }}">
                <div class="accordion-body">
                  @if(isset($videoTrack))
                    @include('ahg-information-object-manage::_edit-representation', [
                        'resource' => $resource,
                        'representation' => $videoTrack,
                    ])
                  @else
                    <div class="mb-3">
                      <label for="trackFile_{{ $usageId }}" class="form-label">
                        {{ __('Select a file to upload (.vtt|.srt)') }}
                      </label>
                      <input type="file" class="form-control" id="trackFile_{{ $usageId }}" name="trackFile_{{ $usageId }}" accept=".vtt,.srt">
                    </div>
                  @endif
                </div>
              </div>
            </div>
          @endif
        @endforeach
      @endif
    </div>

    <ul class="actions mb-3 nav gap-2">
      @if(isset($resource->id))
        <li><a href="{{ route('io.digitalobject.delete', $resource->id) }}" class="btn atom-btn-outline-danger" role="button">{{ __('Delete') }}</a></li>
      @endif
      <li><a href="{{ route('informationobject.show', $object->slug ?? $object->id ?? '') }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a></li>
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Save') }}"></li>
    </ul>

  </form>

@endsection
