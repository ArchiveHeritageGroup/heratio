{{-- CAS login variant - ported from AtoM ahgThemeB5Plugin/modules/user/templates/loginSuccess.mod_cas.php --}}
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

  <form action="{{ route('cas.login') }}" method="POST">
    @csrf

    <ul class="actions mb-3 nav gap-2">
      <button type="submit" class="btn atom-btn-outline-success">{{ __('Log in with CAS') }}</button>
    </ul>

  </form>

@endsection
