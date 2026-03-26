<div class="field{{ isset($sidebar) ? '' : ' mb-3' }}">

  @if(isset($sidebar))
    <h4 class="h5 mb-2">{{ __('Related genres') }}</h4>
  @elseif(isset($mods))
    <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Genres') }}</h3>
  @else
    <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Genre access points') }}</h3>
  @endif

  <div{!! isset($sidebar) ? '' : ' class="ms-0"' !!}>
    <ul class="{{ isset($sidebar) ? 'list-unstyled' : 'list-unstyled ms-0' }}">
      @foreach($resource->getTermRelations($genreTaxonomyId ?? 78) as $item)
        <li>
          @foreach($item->term->ancestors->andSelf()->orderBy('lft') as $key => $subject)
            @if(($rootTermId ?? 110) == $subject->id)
              @continue
            @endif
            @if(1 < $key)
              &raquo;
            @endif
            <a href="{{ route('term.show', $subject->slug ?? $subject->id) }}">{{ $subject->authorized_form_of_name ?? $subject->title ?? $subject->name ?? '' }}</a>
          @endforeach
        </li>
      @endforeach
    </ul>
  </div>

</div>
