<div class="field mb-3">

  @if(($template ?? '') == 'rad')
    <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Related materials') }}</h3>
  @else
    <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Related descriptions') }}</h3>
  @endif

  <div>
    <ul class="list-unstyled ms-0">
      @foreach($resource->relationsRelatedBysubjectId ?? [] as $item)
        @if(isset($item->type) && ($relatedMaterialDescriptionsId ?? 0) == $item->type->id)
          <li><a href="{{ route('informationobject.show', $item->object->slug ?? $item->object->id ?? '') }}">{{ $item->object->authorized_form_of_name ?? $item->object->title ?? '' }}</a></li>
        @endif
      @endforeach
      @foreach($resource->relationsRelatedByobjectId ?? [] as $item)
        @if(isset($item->type) && ($relatedMaterialDescriptionsId ?? 0) == $item->type->id)
          <li><a href="{{ route('informationobject.show', $item->subject->slug ?? $item->subject->id ?? '') }}">{{ $item->subject->authorized_form_of_name ?? $item->subject->title ?? '' }}</a></li>
        @endif
      @endforeach
    </ul>
  </div>

</div>
