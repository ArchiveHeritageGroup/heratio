{{-- Standard show actions for user pages --}}
@auth
  <ul class="actions mb-3 nav gap-2">

    <li>
      @php
        $currentRoute = \Illuminate\Support\Facades\Route::currentRouteName();
        $editAction = str_replace('index', 'edit', $currentRoute);
        $editRoute = \Illuminate\Support\Facades\Route::has($editAction) ? $editAction : 'user.edit';
      @endphp
      <a href="{{ route($editRoute, ['slug' => $user->slug]) }}" class="btn atom-btn-outline-light">{{ __('Edit') }}</a>
    </li>

    @php
      $userNoteCount = \Illuminate\Support\Facades\DB::table('note')
          ->where('user_id', $user->id)
          ->count();
    @endphp
    @if(auth()->user()->id !== $user->id && $userNoteCount === 0)
      <li><a href="{{ route('user.confirmDelete', ['slug' => $user->slug]) }}" class="btn atom-btn-outline-danger">{{ __('Delete') }}</a></li>
    @endif

    <li><a href="{{ route('user.add') }}" class="btn atom-btn-outline-light">{{ __('Add new') }}</a></li>

    <li><a href="{{ route('user.browse') }}" class="btn atom-btn-outline-light">{{ __('Return to user list') }}</a></li>

  </ul>
@endauth
