<div class="field @php echo render_b5_show_field_css_classes(); @endphp">
  @php echo render_b5_show_label(__('Date(s)')); @endphp

    <div xmlns:dc="http://purl.org/dc/elements/1.1/" about="@php echo url_for([$resource, 'module' => 'informationobject'], true); @endphp" class="@php echo render_b5_show_value_css_classes(); @endphp">

    <ul class="@php echo render_b5_show_list_css_classes(); @endphp">
      @foreach($resource->getDates() as $item)
        <li>
          <div class="date">
            <span property="dc:date" start="@php echo $item->startDate; @endphp" end="@php echo $item->endDate; @endphp">@php echo render_value_inline(Qubit::renderDateStartEnd($item->getDate(['cultureFallback' => true]), $item->startDate, $item->endDate)); @endphp</span>
            @if('dc' !== sfConfig::get('app_default_template_informationobject'))
              <span class="date-type">(@php echo render_value_inline($item->type->__toString()); @endphp)</span>
            @endforeach
            <dl class="mb-0">
              @if(isset($item->actor) && null !== $item->type->getRole())
                <dt class="fw-normal text-muted">@php echo render_value_inline($item->type->getRole()); @endphp</dt>
                <dd class="mb-0">@php echo render_title($item->actor); @endphp</dd>
              @endforeach
              @if(null !== $item->getPlace())
                <dt class="fw-normal text-muted">{{ __('Place') }}</dt>
                <dd class="mb-0">@php echo render_value_inline($item->getPlace()); @endphp</dd>
              @endforeach
              @if(0 < strlen($item->description))
                <dt class="fw-normal text-muted">{{ __('Note') }}</dt>
                <dd class="mb-0">@php echo render_value_inline($item->description); @endphp</dd>
              @endforeach
            </dl>

          </div>
        </li>
      @endforeach
    </ul>

  </div>
</div>
