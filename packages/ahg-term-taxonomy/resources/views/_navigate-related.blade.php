<nav>
  <ul class="nav nav-pills mb-3 d-flex gap-2">
    @php $options = ['class' => 'btn atom-btn-white active-primary text-wrap']; @endphp
    @if(request()->route() && request()->route()->getName() === 'term.index')
      @php $options['class'] .= ' active'; @endphp
    @endif
    <li class="nav-item">
      @if($relatedIoCount || (request()->route() && request()->route()->getName() === 'term.relatedAuthorities'))
        <a class="{{ $options['class'] }}" href="{{ route('term.index', ['slug' => $resource->slug]) }}"
           {{ (request()->route() && request()->route()->getName() === 'term.index') ? 'aria-current=page' : '' }}>
          {{ __('Related %1% (%2%)', ['%1%' => config('app.ui_label_informationobject', __('Archival description')), '%2%' => $relatedIoCount]) }}
        </a>
      @else
        <a class="{{ $options['class'] }}" href="#">{{ __('Related %1% (%2%)', ['%1%' => config('app.ui_label_informationobject', __('Archival description')), '%2%' => $relatedIoCount]) }}</a>
      @endif
    </li>
    @php $options = ['class' => 'btn atom-btn-white active-primary text-wrap']; @endphp
    @if(request()->route() && request()->route()->getName() !== 'term.index')
      @php $options['class'] .= ' active'; @endphp
    @endif
    <li class="nav-item">
      @if($relatedActorCount)
        <a class="{{ $options['class'] }}" href="{{ route('term.relatedAuthorities', ['slug' => $resource->slug]) }}"
           {{ (request()->route() && request()->route()->getName() !== 'term.index') ? 'aria-current=page' : '' }}>
          {{ __('Related %1% (%2%)', ['%1%' => config('app.ui_label_actor', __('Authority record')), '%2%' => $relatedActorCount]) }}
        </a>
      @else
        <a class="{{ $options['class'] }}" href="#">{{ __('Related %1% (%2%)', ['%1%' => config('app.ui_label_actor', __('Authority record')), '%2%' => $relatedActorCount]) }}</a>
      @endif
    </li>
  </ul>
</nav>
