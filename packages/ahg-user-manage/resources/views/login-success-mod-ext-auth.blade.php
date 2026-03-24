{{-- External auth / OIDC login variant - ported from AtoM ahgThemeB5Plugin/modules/user/templates/loginSuccess.mod_ext_auth.php --}}
@extends('theme::layouts.1col')

@section('content')

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @php
    $authMode = config('auth.external_mode', 'cas'); // 'cas' or 'oidc'
  @endphp

  @if($authMode === 'cas')
    <form action="{{ route('cas.login') }}" method="POST">
  @else
    <form action="{{ route('oidc.login') }}" method="POST">
  @endif
    @csrf

    <ul class="actions mb-3 nav gap-2">
      @if($authMode === 'cas')
        <button type="submit" class="btn atom-btn-outline-success">{{ __('Log in with CAS') }}</button>
      @else
        <button type="submit" class="btn atom-btn-outline-success">{{ __('Log in with SSO') }}</button>
      @endif
    </ul>

  </form>

@endsection
