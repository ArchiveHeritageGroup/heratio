<nav>
  <ul class="nav nav-pills mb-3 d-flex gap-2">
  @foreach($groupsMenu->getChildren() as $child)
      @php $options = ['class' => 'btn atom-btn-white active-primary text-wrap']; @endphp
      @if(
          str_replace('%currentId%', $sf_request->id, $child->path)
          == $sf_context->getRouting()->getCurrentInternalUri()
      )
        @php $options['class'] .= ' active'; @endphp
        @php $options['aria-current'] = 'page'; @endphp
      @endif
      <li class="nav-item">
        @php echo link_to(
            $child->getLabel(['cultureFallback' => true]),
            $child->getPath(['getUrl' => true, 'resolveAlias' => true]),
            $options
        ); @endphp
      </li>
    @endforeach
  </ul>
</nav>
