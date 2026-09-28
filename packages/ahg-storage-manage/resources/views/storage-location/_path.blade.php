{{-- heratio#1514 - root-to-leaf breadcrumb; the last entry is the current location --}}
@if(count($path) > 0)
  <nav aria-label="{{ __('Location path') }}">
    <ol class="breadcrumb mb-0">
      @foreach($path as $i => $step)
        @if($i === count($path) - 1 && ($linkLast ?? false) === false)
          <li class="breadcrumb-item active" aria-current="page">{{ $step->name }}</li>
        @else
          <li class="breadcrumb-item"><a href="{{ route('storagelocation.show', ['slug' => $step->slug]) }}">{{ $step->name }}</a></li>
        @endif
      @endforeach
    </ol>
  </nav>
@else
  <p class="text-muted mb-0">{{ __('Root level') }}</p>
@endif
