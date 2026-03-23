{{-- CAS show actions (same as ext-auth) --}}
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

    <li><a href="{{ route('user.browse') }}" class="btn atom-btn-outline-light">{{ __('Return to user list') }}</a></li>

  </ul>
@endauth
