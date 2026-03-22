<nav>
  <ul class="nav nav-pills mb-3 d-flex gap-2">
    @php $options = ['class' => 'btn atom-btn-white active-primary text-wrap']; @endphp
    @if('index' == $sf_context->getActionName())
      @php $options['class'] .= ' active'; @endphp
      @php $options['aria-current'] = 'page'; @endphp
    @endforeach
    <li class="nav-item">
      @if($relatedIoCount || 'relatedAuthorities' == $sf_context->getActionName())
        @php echo link_to(
            __('Related %1% (%2%)', ['%1%' => sfConfig::get('app_ui_label_informationobject'), '%2%' => $relatedIoCount]),
            [$resource, 'module' => 'term', 'action' => 'index'],
            $options
        ); @endphp
      @php } else { @endphp
        <a class="@php echo $options['class']; @endphp" href="#">{{ __('Related %1% (%2%)', ['%1%' => sfConfig::get('app_ui_label_informationobject'), '%2%' => $relatedIoCount]) }}</a>
      @endforeach
    </li>
    @php $options = ['class' => 'btn atom-btn-white active-primary text-wrap']; @endphp
    @if('index' != $sf_context->getActionName())
      @php $options['class'] .= ' active'; @endphp
      @php $options['aria-current'] = 'page'; @endphp
    @endforeach
    <li class="nav-item">
      @if($relatedActorCount)
        @php echo link_to(
            __('Related %1% (%2%)', ['%1%' => sfConfig::get('app_ui_label_actor'), '%2%' => $relatedActorCount]),
            [$resource, 'module' => 'term', 'action' => 'relatedAuthorities'],
            $options
        ); @endphp
      @php } else { @endphp
        <a class="@php echo $options['class']; @endphp" href="#">{{ __('Related %1% (%2%)', ['%1%' => sfConfig::get('app_ui_label_actor'), '%2%' => $relatedActorCount]) }}</a>
      @endforeach
    </li>
  </ul>
</nav>
