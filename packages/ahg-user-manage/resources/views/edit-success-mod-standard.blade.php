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

  @if(isset($resource->id) && $resource->id)
    <form method="post" action="{{ route('user.edit', ['slug' => $resource->slug]) }}" id="editForm">
  @else
    <form method="post" action="{{ route('user.add') }}" id="editForm">
  @endif
    @csrf

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="basic-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#basic-collapse" aria-expanded="false" aria-controls="basic-collapse">
            {{ __('Basic info') }}
          </button>
        </h2>
        <div id="basic-collapse" class="accordion-collapse collapse" aria-labelledby="basic-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="username" class="form-label">{{ __('Username') }}</label>
              <input type="text" name="username" id="username" class="form-control" value="{{ old('username', $resource->username ?? '') }}">
            </div>

            <div class="mb-3">
              <label for="email" class="form-label">{{ __('Email') }}</label>
              <input type="email" name="email" id="email" class="form-control" value="{{ old('email', $resource->email ?? '') }}">
            </div>

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
                  data-username="{{ $resource->username ?? '' }}"
                  data-same-as-username="{{ __('Make it different from your username') }}"
                  data-confirm-failure="{{ __('Your password confirmation did not match your password.') }}"
                >
                </div>
                @if(isset($resource->id) && $resource->id)
                  <div class="mb-3">
                    <label for="password" class="form-label">{{ __('Change password') }}</label>
                    <input type="password" name="password" id="password" class="form-control password-strength">
                  </div>
                @else
                  <div class="mb-3">
                    <label for="password" class="form-label">{{ __('Password') }}</label>
                    <input type="password" name="password" id="password" class="form-control password-strength">
                  </div>
                @endif
                <div class="mb-3">
                  <label for="confirmPassword" class="form-label">{{ __('Confirm password') }}</label>
                  <input type="password" name="confirmPassword" id="confirmPassword" class="form-control password-confirm">
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

            @if(Auth::user() && Auth::id() !== ($resource->id ?? null))
              <div class="mb-3">
                <label for="active" class="form-label">{{ __('Active') }}</label>
                <select name="active" id="active" class="form-select">
                  <option value="1" {{ ($resource->active ?? 1) ? 'selected' : '' }}>{{ __('Yes') }}</option>
                  <option value="0" {{ !($resource->active ?? 1) ? 'selected' : '' }}>{{ __('No') }}</option>
                </select>
              </div>
            @endif
          </div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header" id="access-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#access-collapse" aria-expanded="false" aria-controls="access-collapse">
            {{ __('Access control') }}
          </button>
        </h2>
        <div id="access-collapse" class="accordion-collapse collapse" aria-labelledby="access-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="groups" class="form-label">{{ __('User groups') }}</label>
              <input type="text" name="groups" id="groups" class="form-control form-autocomplete" value="{{ old('groups', '') }}">
            </div>

            <div class="mb-3">
              <label for="translate" class="form-label">{{ __('Allowed languages for translation') }}</label>
              <input type="text" name="translate" id="translate" class="form-control form-autocomplete" value="{{ old('translate', '') }}">
            </div>

            @if(isset($restEnabled) && $restEnabled)
              <div class="mb-3">
                <label for="restApiKey" class="form-label">{!! __('REST API access key') . (isset($restApiKey) ? ': <code class="ms-2">'.$restApiKey.'</code>' : '') !!}</label>
                <select name="restApiKey" id="restApiKey" class="form-select">
                  <option value="">{{ __('-- Select --') }}</option>
                  <option value="generate">{{ __('Generate new key') }}</option>
                  <option value="delete">{{ __('Delete key') }}</option>
                </select>
              </div>
            @endif

            @if(isset($oaiEnabled) && $oaiEnabled)
              <div class="mb-3">
                <label for="oaiApiKey" class="form-label">{!! __('OAI-PMH API access key') . (isset($oaiApiKey) ? ': <code class="ms-2">'.$oaiApiKey.'</code>' : '') !!}</label>
                <select name="oaiApiKey" id="oaiApiKey" class="form-select">
                  <option value="">{{ __('-- Select --') }}</option>
                  <option value="generate">{{ __('Generate new key') }}</option>
                  <option value="delete">{{ __('Delete key') }}</option>
                </select>
              </div>
            @endif
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      @if(isset($resource->id) && $resource->id)
        <li><a href="{{ route('user.show', ['slug' => $resource->slug]) }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Save') }}"></li>
      @else
        <li><a href="{{ route('user.list') }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Create') }}"></li>
      @endif
    </ul>

  </form>

@endsection
