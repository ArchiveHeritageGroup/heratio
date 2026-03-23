@extends('theme::layouts.1col')

@section('title')
  <h1>Are you sure you want to delete {{ $user->authorized_form_of_name ?? $user->username }}?</h1>
@endsection

@section('content')

  @if(isset($noteCount) && $noteCount > 0)
    <div id="content" class="p-3">
      This user has {{ $noteCount }} note(s) in the system. These notes will not be deleted, but their association with this user will be removed.
    </div>
  @endif

  <form method="POST" action="{{ route('user.destroy', $user->slug) }}">
    @csrf
    @method('DELETE')
    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('user.show', $user->slug) }}" class="btn atom-btn-outline-light">Cancel</a></li>
      <li><input class="btn atom-btn-outline-danger" type="submit" value="Delete"></li>
    </ul>
  </form>
@endsection
