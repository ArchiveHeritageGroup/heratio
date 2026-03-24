{{-- External auth / OIDC user menu variant - ported from AtoM ahgThemeB5Plugin/modules/menu/templates/_userMenu.mod_ext_auth.php --}}

@php
  $authMode = config('auth.external_mode', 'cas'); // 'cas' or 'oidc'
@endphp

@if(!auth()->check())
{{-- Login dropdown for unauthenticated users --}}
<div class="dropdown my-2">
  <button class="btn btn-sm atom-btn-secondary dropdown-toggle" type="button" id="user-menu" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
    {{ __('Log in') }}
  </button>
  <div class="dropdown-menu dropdown-menu-lg-end mt-2" aria-labelledby="user-menu">
    <div>
      <h6 class="dropdown-header">
        {{ __('Have an account?') }}
      </h6>
    </div>
    @if($authMode === 'cas')
      <form action="{{ route('cas.login') }}" method="POST" class="mx-3 my-2">
    @else
      <form action="{{ route('oidc.login') }}" method="POST" class="mx-3 my-2">
    @endif
      @csrf
      <button class="btn btn-sm atom-btn-secondary" type="submit">
        @if($authMode === 'cas')
          {{ __('Log in with CAS') }}
        @else
          {{ __('Log in with SSO') }}
        @endif
      </button>
    </form>
  </div>
</div>

@else
{{-- User menu for authenticated users --}}
<div class="dropdown my-2">
  <button class="btn btn-sm atom-btn-secondary dropdown-toggle" type="button" id="user-menu" data-bs-toggle="dropdown" aria-expanded="false">
    {{ auth()->user()->username }}
  </button>
  <ul class="dropdown-menu dropdown-menu-lg-end mt-2" aria-labelledby="user-menu">
    <li>
      <h6 class="dropdown-header">
        <img src="{{ \Gravatar::get(auth()->user()->email ?? '', ['size' => 24]) }}" alt="" class="rounded-circle me-1" width="24" height="24">&nbsp;
        {{ __('Hi, :name', ['name' => auth()->user()->username]) }}
      </h6>
    </li>
    <li><a class="dropdown-item" href="{{ route('user.show', ['slug' => auth()->user()->slug]) }}">{{ __('My profile') }}</a></li>
    <li>
      @if($authMode === 'cas')
        <a class="dropdown-item" href="{{ route('cas.logout') }}">{{ __('Log out') }}</a>
      @else
        <a class="dropdown-item" href="{{ route('oidc.logout') }}">{{ __('Log out') }}</a>
      @endif
    </li>
  </ul>
</div>
@endif
