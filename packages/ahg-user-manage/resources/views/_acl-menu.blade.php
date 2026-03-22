<nav>
  <ul class="nav nav-pills mb-3 d-flex gap-2">
    @foreach($userAclMenu->getChildren() as $item)
      @php $options = ['class' => 'btn atom-btn-white active-primary text-wrap']; @endphp
      @if(
          str_replace(
              '%currentSlug%',
              $sf_request->getAttribute('sf_route')->resource->slug,
              $item->path
          )
          == $sf_context->getRouting()->getCurrentInternalUri()
      )
        @php $options['class'] .= ' active'; @endphp
        @php $options['aria-current'] = 'page'; @endphp
      @endforeach
      <li class="nav-item">
        @php echo link_to(
            $item->getLabel(['cultureFallback' => true]),
            $item->getPath(['getUrl' => true, 'resolveAlias' => true]),
            $options
        ); @endphp
      </li>
    @endforeach
  </ul>
</nav>
