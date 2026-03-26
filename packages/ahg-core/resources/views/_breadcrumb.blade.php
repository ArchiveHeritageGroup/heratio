<nav aria-label="breadcrumb" id="breadcrumb">
  <ol class="breadcrumb">
    @foreach($objects as $object)
      @if(isset($object->parent))
        @if(isset($resource) && $object == $resource)
          <li class="breadcrumb-item active" aria-current="page">
            {{ $object->authorized_form_of_name ?? $object->title ?? '' }}
          </li>
        @else
          <li class="breadcrumb-item">
            <a href="{{ route('informationobject.show', $object->slug ?? '') }}">{{ $object->authorized_form_of_name ?? $object->title ?? '' }}</a>
          </li>
        @endif
      @endif
    @endforeach
  </ol>
</nav>
