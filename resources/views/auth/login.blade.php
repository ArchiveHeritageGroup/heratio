@extends('theme::layouts.1col')

@section('title', 'Login - ' . ($themeData['siteTitle'] ?? 'Heratio'))
@section('body-class', 'user login')

@section('content')
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card shadow mt-4">
        <div class="card-header">
          <h4 class="mb-0">Log in</h4>
        </div>
        <div class="card-body p-4">
          <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="mb-3">
              <label for="email" class="form-label">Email or Username</label>
              <input type="text" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
                     value="{{ old('email') }}" required autofocus>
              @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Log in</button>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
