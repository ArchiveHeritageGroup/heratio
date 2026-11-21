{{-- Faces found in one digital object, to name (heratio#1764). Staff only. --}}
@extends('theme::layouts.1col')
@section('title', __('Faces in :name', ['name' => $do->name]))

@section('content')
<h1 class="h3">{{ __('Faces in :name', ['name' => $do->name]) }}</h1>
@if($slug)<p><a href="{{ url('/'.$slug) }}">{{ __('Back to the record') }}</a></p>@endif
@include('ahg-core::faces._flash')

@if(count($faces) === 0)
  <p class="text-muted">{{ __('No faces were found in this file.') }}</p>
@else
<div class="table-responsive">
<table class="table align-middle">
  <thead><tr><th>{{ __('Face') }}</th><th>{{ __('Time') }}</th><th>{{ __('Person') }}</th><th></th></tr></thead>
  <tbody>
  @foreach($faces as $f)
    <tr>
      <td><img src="{{ route('faces.face.image', $f->id) }}" alt="{{ __('Face :id', ['id' => $f->id]) }}" width="80" height="80" class="rounded border" style="object-fit:cover" loading="lazy"></td>
      <td>{{ $f->frame_time_seconds !== null ? gmdate('H:i:s', (int) $f->frame_time_seconds) : '' }}</td>
      <td>
        @if($f->person_id)
          <a href="{{ route('faces.person', $f->person_id) }}">{{ $f->person_name }}</a>
          <span class="badge bg-success">{{ $f->identification_source === 'verified' ? __('Confirmed match') : __('Named') }}</span>
        @else
          <span class="text-muted">{{ __('Unnamed') }}</span>
        @endif
      </td>
      <td>
        @if($f->person_id)
          <form method="POST" action="{{ route('faces.face.unassign', $f->id) }}">@csrf<button class="btn btn-sm btn-outline-secondary">{{ __('Not this person') }}</button></form>
        @else
          <form method="POST" action="{{ route('faces.face.name', $f->id) }}" class="d-flex gap-2 flex-wrap">
            @csrf
            <select name="person_id" class="form-select form-select-sm" style="max-width:14rem" aria-label="{{ __('Person') }}">
              <option value="">{{ __('New person...') }}</option>
              @foreach($people as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
            </select>
            <input name="new_person" class="form-control form-control-sm" style="max-width:14rem" maxlength="255" placeholder="{{ __('Name of new person') }}" aria-label="{{ __('Name of new person') }}">
            <button class="btn btn-sm btn-primary">{{ __('Name') }}</button>
          </form>
        @endif
      </td>
    </tr>
  @endforeach
  </tbody>
</table>
</div>
@endif
@endsection
