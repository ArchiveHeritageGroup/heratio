@extends('theme::layout_1col')

@section('title')
  <h1>{{ __('Reset Your Password') }}</h1>
@endsection

@section('content')

  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $error)
        <p>{{ $error }}</p>
      @endforeach
    </div>
  @endif

  <form method="post" action="{{ route('user.passwordResetConfirm', ['token' => request('token')]) }}" id="passwordResetConfirmForm">
    @csrf

    <section id="content">
      <fieldset class="collapsible">
        <legend>{{ __('Enter your new password') }}</legend>

        <div class="form-item password-parent">
          <label for="password" class="form-label">{{ __('New Password') }}</label>
          <input type="password" name="password" id="password" class="form-control password-field" required>
          @error('password')
            <div class="text-danger">{{ $message }}</div>
          @enderror
        </div>

        <div class="form-item confirm-parent mt-3">
          <label for="confirmPassword" class="form-label">{{ __('Confirm Password') }}</label>
          <input type="password" name="confirmPassword" id="confirmPassword" class="form-control password-confirm" required>
        </div>

      </fieldset>
    </section>

    <section class="actions mt-3">
      <ul class="nav gap-2">
        <li><a href="{{ route('user.login') }}" class="btn atom-btn-outline-light">{{ __('Cancel') }}</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Reset Password') }}"></li>
      </ul>
    </section>

  </form>

@endsection
