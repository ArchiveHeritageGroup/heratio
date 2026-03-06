@extends('theme::layouts.1col')

@section('title', 'Reset Your Password')
@section('body-class', 'user passwordResetConfirm')

@section('content')

  <h1>Reset Your Password</h1>

  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $error)
        <div>{{ $error }}</div>
      @endforeach
    </div>
  @endif

  <form method="POST" action="{{ route('password.reset.confirm', $token) }}" id="passwordResetConfirmForm">
    @csrf

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="newpw-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#newpw-collapse" aria-expanded="true" aria-controls="newpw-collapse">
            Enter your new password
          </button>
        </h2>
        <div id="newpw-collapse" class="accordion-collapse collapse show" aria-labelledby="newpw-heading">
          <div class="accordion-body">
            <div class="row">
              <div class="col-md-6">
                <div
                  hidden
                  class="password-strength-settings"
                  data-not-strong="Your password is not strong enough."
                  data-strength-title="Password strength:"
                  data-require-strong-password="false"
                  data-too-short="Make it at least six characters"
                  data-add-lower-case="Add lowercase letters"
                  data-add-upper-case="Add uppercase letters"
                  data-add-numbers="Add numbers"
                  data-add-punctuation="Add punctuation"
                  data-username=""
                  data-same-as-username="Make it different from your username"
                  data-confirm-failure="Your password confirmation did not match your password."
                ></div>

                <div class="mb-3">
                  <label for="password" class="form-label">New Password</label>
                  <input type="password" class="form-control password-strength @error('password') is-invalid @enderror"
                         id="password" name="password" required>
                  @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>

                <div class="mb-3">
                  <label for="password_confirmation" class="form-label">Confirm Password</label>
                  <input type="password" class="form-control password-confirm"
                         id="password_confirmation" name="password_confirmation" required>
                </div>
              </div>
              <div class="col-md-6 template" hidden>
                <div class="mb-3 bg-light p-3 rounded border-start border-4">
                  <label class="form-label">Password strength:</label>
                  <div class="progress mb-3">
                    <div class="progress-bar w-0" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('login') }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
      <li><input class="btn atom-btn-outline-success" type="submit" value="Reset Password"></li>
    </ul>

  </form>

@endsection
