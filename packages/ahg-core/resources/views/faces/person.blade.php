{{-- "Find all items featuring this person" (heratio#1764). --}}
@extends('theme::layouts.1col')
@section('title', $person->name)

@section('content')
<h1 class="h3"><i class="fas fa-user me-2"></i>{{ $person->name }}</h1>
@if($person->actor_id && $person->actor_slug)
  <p>{{ __('Authority record') }}: <a href="{{ url('/'.$person->actor_slug) }}">{{ $person->actor_name ?: $person->actor_slug }}</a></p>
@endif
@include('ahg-core::faces._flash')

<h2 class="h5">{{ __('Items featuring this person') }}</h2>
@if(count($items) === 0)
  <p class="text-muted">{{ __('No confirmed appearances yet.') }}</p>
@else
  <ul class="list-group mb-4">
    @foreach($items as $it)
      <li class="list-group-item">
        @if($it->slug)<a href="{{ url('/'.$it->slug) }}">{{ $it->title ?: $it->slug }}</a>@else#{{ $it->io_id }}@endif
        @if(count($it->times))
          <div class="small text-muted">{{ __('In the video at') }}
            @foreach($it->times as $t)<span class="badge bg-light text-dark border">{{ gmdate('H:i:s', (int) $t) }}</span>@endforeach
          </div>
        @endif
      </li>
    @endforeach
  </ul>
@endif

@if($staff)
  <h2 class="h5">{{ __('Possible matches') }}</h2>
  <p class="text-muted small">{{ __('Unnamed faces with a similarity of :t or more to this person\'s confirmed faces. A suggestion, not an identification: confirm or reject each one.', ['t' => number_format($threshold, 2)]) }}</p>
  @if(count($possible) === 0)
    <p class="text-muted">{{ __('No possible matches above the threshold.') }}</p>
  @else
    @include('ahg-core::faces._hits', ['hits' => $possible, 'confirmFor' => $person->id])
  @endif

  <h2 class="h5 mt-4">{{ __('Edit person') }}</h2>
  <form method="POST" action="{{ route('faces.person.update', $person->id) }}" class="card card-body" style="max-width:32rem">
    @csrf
    <label class="form-label" for="fp-name">{{ __('Name') }}</label>
    <input class="form-control mb-2" id="fp-name" name="name" value="{{ $person->name }}" required maxlength="255">
    <label class="form-label" for="fp-actor">{{ __('Authority record slug or id (optional)') }}</label>
    <input class="form-control mb-2" id="fp-actor" name="actor" value="{{ $person->actor_slug ?: $person->actor_id }}" maxlength="255">
    <div class="form-text mb-2">{{ __('With an authority record, every confirmed face adds it to the record as a name access point.') }}</div>
    <label class="form-label" for="fp-notes">{{ __('Notes') }}</label>
    <textarea class="form-control mb-2" id="fp-notes" name="notes" rows="2" maxlength="5000">{{ $person->notes }}</textarea>
    <button class="btn btn-primary">{{ __('Save') }}</button>
  </form>
  <p class="mt-3"><a href="{{ route('faces.index') }}">{{ __('Back to face recognition') }}</a></p>
@endif
@endsection
