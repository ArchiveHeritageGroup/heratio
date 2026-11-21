{{-- Schedule a live event on a record (heratio#1743). Staff only (acl:update). --}}
@extends('theme::layouts.1col')
@section('title', __('Schedule a live event'))

@section('content')
<h1 class="h3"><i class="fas fa-tower-broadcast me-2"></i>{{ __('Schedule a live event') }}</h1>
<p><a href="{{ $io->slug ? url('/'.$io->slug) : '#' }}">{{ $io->title ?: __('Untitled') }}</a></p>
@if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

<form method="post" action="{{ route('media-streaming.live.store', $io->id) }}" style="max-width:40rem">@csrf
  <div class="mb-3">
    <label class="form-label" for="lv-title">{{ __('Title') }}</label>
    <input class="form-control" id="lv-title" name="title" maxlength="255" required value="{{ old('title') }}">
  </div>
  <div class="mb-3">
    <label class="form-label" for="lv-when">{{ __('Starts at') }}</label>
    <input class="form-control" type="datetime-local" id="lv-when" name="scheduled_at" required value="{{ old('scheduled_at', now()->addHour()->format('Y-m-d\TH:00')) }}">
  </div>
  <div class="mb-3">
    <label class="form-label" for="lv-desc">{{ __('Description') }}</label>
    <textarea class="form-control" id="lv-desc" name="description" rows="3" maxlength="5000">{{ old('description') }}</textarea>
  </div>
  <p class="small text-muted">{{ __('Viewers see the stream on this record while it is live, under the same access rules as the record\'s media. When it ends, the recording is added to the record as a digital object.') }}</p>
  <button class="btn atom-btn-white" type="submit">{{ __('Schedule') }}</button>
</form>
@endsection
