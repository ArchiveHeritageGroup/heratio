@if(isset($physicalObjects) && count($physicalObjects) > 0)
<section id="physical-objects">
  <h4 class="h5 mb-2">{{ config('app.ui_label_physicalobject', 'Physical storage') }}</h4>
  <ul class="list-unstyled">
    @foreach($physicalObjects as $item)
      <li>
        @if($item->type_name ?? null)
          {{ $item->type_name }}:
        @endif
        <a href="{{ route('physicalobject.show', $item->slug) }}">{{ $item->name ?: '[Untitled]' }}</a>
        @auth
          @if($item->location ?? null)
            - {{ $item->location }}
          @endif
        @endauth
      </li>
    @endforeach
  </ul>
</section>
@endif
