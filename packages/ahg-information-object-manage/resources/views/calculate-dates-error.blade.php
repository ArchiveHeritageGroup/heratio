@php decorate_with('layout_2col'); @endphp

@php slot('sidebar'); @endphp
  @php include_component('informationobject', 'contextMenu'); @endphp
@php end_slot(); @endphp

@php slot('title'); @endphp
  <h1>{{ __('Calculate dates - Error') }}</h1>
@php end_slot(); @endphp

@php slot('content'); @endphp
  @php echo $form->renderFormTag(url_for([
      $resource, 'module' => 'informationobject', 'action' => 'calculateDates', ]
  )); @endphp
    @if(1 == $resource->rgt - $resource->lft || 0 == count($descendantEventTypes))
      <div id="content" class="p-3">
        @if(1 == $resource->rgt - $resource->lft)
            {{ __(
                'Cannot calculate accumulated dates because this %1% has no children',
                ['%1%' => sfConfig::get('app_ui_label_informationobject')]
            ) }}
        @php } else { @endphp
          {{ __('Cannot calculate accumulated dates because no lower level dates exist') }}
        @endforeach
      </div>
    @endforeach

    <section class="actions mb-3">
      @php echo link_to(
          __('Cancel'),
          [$resource, 'module' => 'informationobject'],
          ['class' => 'btn atom-btn-outline-light', 'role' => 'button']
      ); @endphp
    </section>
  </form>
@php end_slot(); @endphp
