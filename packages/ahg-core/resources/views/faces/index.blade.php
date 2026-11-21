{{-- Face recognition: people, unnamed faces, search by photo (heratio#1764). Staff only. --}}
@extends('theme::layouts.1col')
@section('title', __('Face recognition'))

@section('content')
<h1 class="h3"><i class="fas fa-user-circle me-2"></i>{{ __('Face recognition') }}</h1>
<p class="text-muted">{{ __('Faces are personal information. These screens are for staff; every naming and confirmation is written to the audit log.') }}</p>
@include('ahg-core::faces._flash')

@if(! $enabled)
  <div class="alert alert-warning">{{ __('Face processing is switched off (Settings - Face Detection).') }}</div>
@else
<div class="row g-4">
  <div class="col-lg-6">
    <h2 class="h5">{{ __('People') }}</h2>
    @if(count($people) === 0)
      <p class="text-muted">{{ __('No people yet. Name a face below, or add a person.') }}</p>
    @else
      <ul class="list-group mb-3">
        @foreach($people as $p)
          <li class="list-group-item d-flex justify-content-between">
            <a href="{{ route('faces.person', $p->id) }}">{{ $p->name }}</a>
            <span class="badge bg-secondary">{{ __(':n confirmed', ['n' => $p->faces]) }}</span>
          </li>
        @endforeach
      </ul>
    @endif
    <form method="POST" action="{{ route('faces.people.store') }}" class="card card-body">
      @csrf
      <label class="form-label" for="fp-name">{{ __('Add a person') }}</label>
      <input class="form-control mb-2" id="fp-name" name="name" required maxlength="255" placeholder="{{ __('Name') }}">
      <input class="form-control mb-2" name="actor" maxlength="255" placeholder="{{ __('Authority record slug or id (optional)') }}">
      <button class="btn btn-primary">{{ __('Add') }}</button>
    </form>
  </div>
  <div class="col-lg-6">
    <h2 class="h5">{{ __('Search by photo') }}</h2>
    <form method="POST" action="{{ route('faces.search-photo') }}" enctype="multipart/form-data" class="card card-body">
      @csrf
      <input class="form-control mb-2" type="file" name="photo" accept="image/jpeg,image/png" required>
      <div class="form-text mb-2">{{ __('The photo is sent to the AI gateway to find the face. Heratio does not keep it.') }}</div>
      <button class="btn btn-primary">{{ __('Search') }}</button>
    </form>
  </div>
</div>

<h2 class="h5 mt-4">{{ __('Unnamed faces') }}</h2>
@if(count($unnamed) === 0)
  <p class="text-muted">{{ __('No unnamed faces.') }}</p>
@else
  <div class="d-flex flex-wrap gap-2">
    @foreach($unnamed as $f)
      <a href="{{ route('faces.object', $f->do_id) }}" class="text-center small text-decoration-none">
        <img src="{{ route('faces.face.image', $f->id) }}" alt="{{ __('Face :id', ['id' => $f->id]) }}" width="96" height="96" class="rounded border" style="object-fit:cover" loading="lazy">
        @if($f->frame_time_seconds !== null)<div>{{ gmdate('H:i:s', (int) $f->frame_time_seconds) }}</div>@endif
      </a>
    @endforeach
  </div>
@endif
@endif
@endsection
