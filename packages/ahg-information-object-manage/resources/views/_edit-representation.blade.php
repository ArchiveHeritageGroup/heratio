<div class="row">
  <div class="col-md-6">
    @if(isset($representation->name))
      <div class="mb-3">
        <h3 class="fs-6 mb-2">
          {{ __('Filename') }}
        </h3>
        <span class="text-muted">
          @php echo render_value_inline($representation->name); @endphp
        </span>
      </div>
    @endforeach

    @if(isset($representation->byteSize))
      <div class="mb-3">
        <h3 class="fs-6 mb-2">
          {{ __('Filesize') }}
        </h3>
        <span class="text-muted">
          @php echo hr_filesize($representation->byteSize); @endphp
        </span>
      </div>
    @endforeach

    @if(QubitTerm::CHAPTERS_ID == $representation->usageId)
      <a
        href="@php echo $representation->getFullPath(); @endphp"
        class="btn atom-btn-white me-2">
        <i class="fas fa-fw fa-eye me-1" aria-hidden="true"></i>
        {{ __('View file') }}
      </a>
    @endforeach
    <a
      href="@php echo url_for([
          $representation,
          'module' => 'digitalobject',
          'action' => 'delete',
      ]); @endphp"
      class="btn atom-btn-white">
      <i class="fas fa-fw fa-times me-1" aria-hidden="true"></i>
      {{ __('Delete') }}
    </a>
  </div>
  @if(QubitTerm::CHAPTERS_ID != $representation->usageId)
    <div class="col-md-6 mt-3 mt-md-0">
      @php echo get_component('digitalobject', 'show', [
          'iconOnly' => true,
          'link' => public_path($representation->getFullPath()),
          'resource' => $resource,
          'usageType' => QubitTerm::THUMBNAIL_ID,
          'editForm' => true,
      ]); @endphp
    </div>
  @endforeach
</div>
