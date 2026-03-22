<div class="field@php echo isset($sidebar) ? '' : ' '.render_b5_show_field_css_classes(); @endphp">

  @if(isset($sidebar))
    <h4 class="h5 mb-2">{{ __('Related genres') }}</h4>
  @php } elseif (isset($mods)) { @endphp
    @php echo render_b5_show_label(__('Genres')); @endphp
  @php } else { @endphp
    @php echo render_b5_show_label(__('Genre access points')); @endphp
  @endforeach

  <div@php echo isset($sidebar) ? '' : ' class="'.render_b5_show_value_css_classes().'"'; @endphp>
    <ul class="@php echo isset($sidebar) ? 'list-unstyled' : render_b5_show_list_css_classes(); @endphp">
      @foreach($resource->getTermRelations(QubitTaxonomy::GENRE_ID) as $item)
        <li>
          @foreach($item->term->ancestors->andSelf()->orderBy('lft') as $key => $subject)
            @if(QubitTerm::ROOT_ID == $subject->id)
              @php continue; @endphp
            @endforeach
            @if(1 < $key)
              &raquo;
            @endforeach
            @php echo link_to(render_title($subject), [$subject, 'module' => 'term']); @endphp
          @endforeach
        </li>
      @endforeach
    </ul>
  </div>

</div>
