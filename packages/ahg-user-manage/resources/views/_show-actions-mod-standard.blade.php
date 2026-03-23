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

    @if(auth()->user()->id !== $user->id)
      <li><a href="{{ route('user.confirmDelete', ['slug' => $user->slug]) }}" class="btn atom-btn-outline-danger">{{ __('Delete') }}</a></li>
    @endif

    <li><a href="{{ route('user.create') }}" class="btn atom-btn-outline-light">{{ __('Add new') }}</a></li>

    <li><a href="{{ route('user.browse') }}" class="btn atom-btn-outline-light">{{ __('Return to user list') }}</a></li>

  </ul>
@endauth
