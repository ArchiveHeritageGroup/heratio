<div class="table-responsive mb-3">
  <table class="table table-bordered mb-0 compound_digiobj">
    <tbody>
      <tr>
        <td>
          @if(isset($leftObject) && null !== ($representation = $leftObject->getCompoundRepresentation ?? null))
            @php
              $canEdit = auth()->check() && in_array(auth()->user()->role ?? '', ['editor', 'administrator']);
              $isText = ($resource->mediaType->id ?? null) == config('atom.term.TEXT_ID');
              $showLink = $canEdit || $isText;
              $altText = __($resource->getDigitalObjectAltText() ?: 'Open original %1%', ['%1%' => config('app.ui_label_digitalobject', 'digital object')]);
              $imgPath = $representation->getFullPath();
            @endphp
            @if($showLink)
              <a href="{{ asset($leftObject->getFullPath()) }}" title="{{ __('View full size') }}">
                <img src="{{ $imgPath }}" alt="" class="img-thumbnail">
              </a>
            @else
              <img src="{{ $imgPath }}" alt="" class="img-thumbnail">
            @endif
          @endif
        </td><td>
          @if(isset($rightObject) && null !== ($representation = $rightObject->getCompoundRepresentation ?? null))
            @php
              $canEdit = auth()->check() && in_array(auth()->user()->role ?? '', ['editor', 'administrator']);
              $isText = ($resource->mediaType->id ?? null) == config('atom.term.TEXT_ID');
              $showLink = $canEdit || $isText;
              $imgPath = $representation->getFullPath();
            @endphp
            @if($showLink)
              <a href="{{ asset($rightObject->getFullPath()) }}" title="{{ __('View full size') }}">
                <img src="{{ $imgPath }}" alt="" class="img-thumbnail">
              </a>
            @else
              <img src="{{ $imgPath }}" alt="" class="img-thumbnail">
            @endif
          @endif
        </td>
      </tr>

      @php
        $canEditIO = auth()->check() && in_array(auth()->user()->role ?? '', ['editor', 'administrator']);
      @endphp
      @if($canEditIO)
        <tr>
          <td colspan="2" class="text-center">
            <a href="{{ asset($resource->getFullPath()) }}" class="btn btn-sm atom-btn-white">
              <i class="fas fa-download me-1" aria-hidden="true"></i>
              {{ __('Download %1%', ['%1%' => $resource]) }}
            </a>
          </td>
        </tr>
      @endif
    </tbody>
  </table>
</div>

@if(isset($pager))
  @include('ahg-core::partials._pager', ['pager' => $pager])
@endif
