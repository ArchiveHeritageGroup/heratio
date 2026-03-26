@php $actorsShown = []; @endphp
@foreach($ancestor->getCreators() as $item)
  @if(!isset($actorsShown[$item->id]))
    <div class="field @php echo render_b5_show_field_css_classes(); @endphp">
      @php echo render_b5_show_label(__('Name of creator')); @endphp
      <div class="@php echo render_b5_show_value_css_classes(); @endphp">

        <div class="creator">
          @if(0 < count($resource->getCreators()))
            @php echo link_to(render_title($item), [$item]); @endphp
          @else
            @php echo link_to(render_title($item), [$item], ['title' => __('Inherited from %1%', ['%1%' => $ancestor])]); @endphp
          @endif
        </div>

        @if(isset($item->datesOfExistence))
          <div class="datesOfExistence">
            (@php echo render_value_inline($item->getDatesOfExistence(['cultureFallback' => true])); @endphp)
          </div>
        @endif

        @if(0 < count($resource->getCreators()))
          <div class="field @php echo render_b5_show_field_css_classes(); @endphp">
            @if(QubitTerm::CORPORATE_BODY_ID == $item->entityTypeId)
              @php $history_kind = __('Administrative history'); @endphp
            @else
              @php $history_kind = __('Biographical history'); @endphp
            @endif
            @php echo render_show($history_kind, render_value($item->getHistory(['cultureFallback' => true])), ['isSubField' => true]); @endphp
          </div>
        @endif

      </div>
    </div>
    @php $actorsShown[$item->id] = true; @endphp
  @endif
@endforeach
