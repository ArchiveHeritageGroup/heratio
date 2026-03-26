@php $actorsShown = []; @endphp
@foreach($ancestor->getCreators() as $item)
  @if(!isset($actorsShown[$item->id]))
    <div class="field mb-3">
      <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Name of creator') }}</h3>
      <div>

        <div class="creator">
          @if(0 < count($resource->getCreators()))
            <a href="{{ route('actor.show', $item->slug ?? $item->id) }}">{{ $item->authorized_form_of_name ?? $item->title ?? '' }}</a>
          @else
            <a href="{{ route('actor.show', $item->slug ?? $item->id) }}" title="{{ __('Inherited from %1%', ['%1%' => $ancestor->authorized_form_of_name ?? $ancestor->title ?? '']) }}">{{ $item->authorized_form_of_name ?? $item->title ?? '' }}</a>
          @endif
        </div>

        @if(isset($item->datesOfExistence))
          <div class="datesOfExistence">
            ({{ $item->datesOfExistence ?? '' }})
          </div>
        @endif

        @if(0 < count($resource->getCreators()))
          <div class="field mb-3">
            @if(config('atom.term.CORPORATE_BODY_ID') == $item->entityTypeId)
              @php $history_kind = __('Administrative history'); @endphp
            @else
              @php $history_kind = __('Biographical history'); @endphp
            @endif
            <div class="field">
              <h3>{{ $history_kind }}</h3>
              <div>{!! $item->history ?? '' !!}</div>
            </div>
          </div>
        @endif

      </div>
    </div>
    @php $actorsShown[$item->id] = true; @endphp
  @endif
@endforeach
