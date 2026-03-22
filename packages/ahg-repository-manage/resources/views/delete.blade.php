@extends('theme::layouts.1col')

@section('title')
  <h1>Are you sure you want to delete {{ $repository->authorized_form_of_name }}?</h1>
@endsection

@section('content')

  @if($holdingsCount > 0)
    <div class="alert alert-warning">
      This repository has <strong>{{ $holdingsCount }}</strong> archival description(s) associated with it.
      Deleting the repository will not delete the descriptions, but they will no longer be linked to a repository.
    </div>
  @endif

  <form method="POST" action="{{ route('repository.destroy', $repository->slug) }}">
    @csrf
    @method('DELETE')

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('repository.show', $repository->slug) }}" class="btn atom-btn-outline-light">Cancel</a></li>
      <li><input class="btn atom-btn-outline-danger" type="submit" value="Delete"></li>
    </ul>
  </form>

@endsection
