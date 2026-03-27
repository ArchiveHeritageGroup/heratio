<section id="physical-objects">

  <h4 class="h5 mb-2">{{ config('app.ui_label_physicalobject', __('Physical storage')) }}</h4>
  <ul class="list-unstyled">

    @foreach($physicalObjects as $item)
      <li>

        @if(isset($item->type))
          {{ $item->type }}:
        @endif

        @if(Auth::check() && Auth::user()->can('update', $resource))
          <a href="{{ route('physicalobject.show', ['slug' => $item->slug]) }}">{{ $item->authorized_form_of_name ?? $item->title ?? '' }}</a>
        @else
          {{ $item->authorized_form_of_name ?? $item->title ?? '' }}
        @endif

        @if(isset($item->location) && Auth::check())
          - {{ $item->getLocation(['cultureFallback' => 'true']) }}
        @endif

      </li>
    @endforeach

  </ul>

</section>
