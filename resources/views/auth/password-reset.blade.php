@extends('theme::layouts.1col')

@section('title', 'Reset Password')
@section('body-class', 'user passwordReset')

@section('content')

  <h1>Reset Password</h1>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $error)
        <div>{{ $error }}</div>
      @endforeach
    </div>
  @endif

  <form method="POST" action="{{ route('password.reset') }}" id="passwordResetForm">
    @csrf

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="reset-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#reset-collapse" aria-expanded="true" aria-controls="reset-collapse">
            Enter your email address
          </button>
        </h2>
        <div id="reset-collapse" class="accordion-collapse collapse show" aria-labelledby="reset-heading">
          <div class="accordion-body">
            <p>Enter the email address associated with your account and we will send you instructions to reset your password.</p>

            <div class="mb-3">
              <label for="email" class="form-label">Email</label>
              <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
                     value="{{ old('email') }}" required autofocus>
              @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('login') }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
      <li><input class="btn atom-btn-outline-success" type="submit" value="Send Reset Instructions"></li>
    </ul>

  </form>

@endsection
