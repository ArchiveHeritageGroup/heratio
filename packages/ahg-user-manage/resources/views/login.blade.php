{{-- Login dispatcher - routes to the correct login variant based on auth mode --}}
@php
  $authMode = config('auth.external_mode', 'standard'); // 'standard', 'cas', 'oidc', 'ext_auth'
@endphp

@if($authMode === 'cas')
  @include('ahg-user-manage::login-success-mod-cas')
@elseif(in_array($authMode, ['oidc', 'ext_auth']))
  @include('ahg-user-manage::login-success-mod-ext-auth')
@else
  @include('ahg-user-manage::login-success-mod-standard')
@endif
