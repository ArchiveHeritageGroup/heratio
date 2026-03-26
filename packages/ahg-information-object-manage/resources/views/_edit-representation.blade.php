<div class="row">
  <div class="col-md-6">
    @if(isset($representation->name))
      <div class="mb-3">
        <h3 class="fs-6 mb-2">
          {{ __('Filename') }}
        </h3>
        <span class="text-muted">
          {{ $representation->name }}
        </span>
      </div>
    @endif

    @if(isset($representation->byteSize))
      <div class="mb-3">
        <h3 class="fs-6 mb-2">
          {{ __('Filesize') }}
        </h3>
        <span class="text-muted">
          @php
            $bytes = $representation->byteSize;
            if ($bytes >= 1073741824) {
                $size = number_format($bytes / 1073741824, 2) . ' GB';
            } elseif ($bytes >= 1048576) {
                $size = number_format($bytes / 1048576, 2) . ' MB';
            } elseif ($bytes >= 1024) {
                $size = number_format($bytes / 1024, 2) . ' KB';
            } else {
                $size = $bytes . ' B';
            }
          @endphp
          {{ $size }}
        </span>
      </div>
    @endif

    @if(config('atom.term.CHAPTERS_ID') == ($representation->usageId ?? null))
      <a
        href="{{ $representation->getFullPath() }}"
        class="btn atom-btn-white me-2">
        <i class="fas fa-fw fa-eye me-1" aria-hidden="true"></i>
        {{ __('View file') }}
      </a>
    @endif
    <a
      href="{{ route('io.digitalobject.delete', $representation->id) }}"
      class="btn atom-btn-white">
      <i class="fas fa-fw fa-times me-1" aria-hidden="true"></i>
      {{ __('Delete') }}
    </a>
  </div>
  @if(config('atom.term.CHAPTERS_ID') != ($representation->usageId ?? null))
    <div class="col-md-6 mt-3 mt-md-0">
      @include('ahg-information-object-manage::_show-image', [
          'iconOnly' => true,
          'link' => asset($representation->getFullPath()),
          'resource' => $resource,
          'usageType' => config('atom.term.THUMBNAIL_ID'),
          'representation' => $representation,
      ])
    </div>
  @endif
</div>
