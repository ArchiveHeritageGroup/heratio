{{-- CAS show actions - ported from AtoM ahgThemeB5Plugin/modules/user/templates/_showActions.mod_cas.php --}}
@auth
  <ul class="actions mb-3 nav gap-2">

    <li>
      <a href="{{ route('user.edit', ['slug' => $user->slug]) }}" class="btn atom-btn-outline-light">{{ __('Edit') }}</a>
    </li>

    @if(auth()->user()->id !== $user->id)
      <li>
        <a href="{{ route('user.confirmDelete', ['slug' => $user->slug]) }}" class="btn atom-btn-outline-danger">{{ __('Delete') }}</a>
      </li>
    @endif

    <li><a href="{{ route('user.browse') }}" class="btn atom-btn-outline-light">{{ __('Return to user list') }}</a></li>

  </ul>
@endauth
