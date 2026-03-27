@extends('theme::layout_1col')

@section('title')
  <h1>{{ __('User %1%', ['%1%' => $resource->authorized_form_of_name ?? $resource->username ?? '']) }}</h1>
@endsection

@section('content')

  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $error)
        <p>{{ $error }}</p>
      @endforeach
    </div>
  @endif

  <form method="post" action="{{ route('user.passwordEdit', ['slug' => $resource->slug]) }}" id="editForm">
    @csrf

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="password-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#password-collapse" aria-expanded="false" aria-controls="password-collapse">
            {{ __('Reset password') }}
          </button>
        </h2>
        <div id="password-collapse" class="accordion-collapse collapse" aria-labelledby="password-heading">
          <div class="accordion-body">
            <div class="row">
              <div class="col-md-6">
                <div
                    hidden
                    class="password-strength-settings"
                    data-not-strong="{{ __('Your password is not strong enough.') }}"
                    data-strength-title="{{ __('Password strength:') }}"
                    data-require-strong-password="{{ config('atom.require_strong_passwords', false) }}"
                    data-too-short="{{ __('Make it at least six characters') }}"
                    data-add-lower-case="{{ __('Add lowercase letters') }}"
                    data-add-upper-case="{{ __('Add uppercase letters') }}"
                    data-add-numbers="{{ __('Add numbers') }}"
                    data-add-punctuation="{{ __('Add punctuation') }}"
                    data-username="{{ $resource->username }}"
                    data-same-as-username="{{ __('Make it different from your username') }}"
                    data-confirm-failure="{{ __('Your password confirmation did not match your password.') }}"
                  >
                </div>
                <div class="mb-3">
                  <label for="password" class="form-label">{{ __('New password') }}</label>
                  <input type="password" name="password" id="password" class="form-control password-strength" required>
                </div>
                <div class="mb-3">
                  <label for="confirmPassword" class="form-label">{{ __('Confirm password') }}</label>
                  <input type="password" name="confirmPassword" id="confirmPassword" class="form-control password-confirm" required>
                </div>
              </div>
              <div class="col-md-6 template" hidden>
                <div class="mb-3 bg-light p-3 rounded border-start border-4">
                  <label class="form-label">{{ __('Password strength:') }} <span class="badge bg-secondary ms-1">Optional</span></label>
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
      <li><a href="{{ route('user.show', ['slug' => $resource->slug]) }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a></li>
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Save') }}"></li>
    </ul>

  </form>

@endsection
