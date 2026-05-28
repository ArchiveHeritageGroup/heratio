@extends('theme::layouts.1col')
@section('title', 'Library Account Sign In')
@section('content')
<div class="container py-4 d-flex justify-content-center">
    <div class="card" style="max-width: 420px; width: 100%;">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <i class="fas fa-id-card fa-3x text-primary mb-3"></i>
                <h2 class="h4 mb-1">{{ __('Patron Login') }}</h2>
                <p class="text-muted small">{{ __('Sign in with your library card number to access your account.') }}</p>
            </div>

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <form method="POST" action="{{ route('opac.patron.authenticate') }}" autocomplete="off">
                @csrf

                <div class="mb-3">
                    <label for="card_number" class="form-label">{{ __('Library Card Number') }}</label>
                    <input
                        type="text"
                        name="card_number"
                        id="card_number"
                        class="form-control form-control-lg"
                        value="{{ old('card_number', session('_old_input.card_number', '')) }}"
                        placeholder="{{ __('e.g. LIB-26-3F4A2C') }}"
                        required
                        autofocus
                        minlength="3"
                    >
                    @error('card_number')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="pin" class="form-label">{{ __('PIN') }} <span class="text-muted">({{ __('optional') }})</span></label>
                    <input
                        type="password"
                        name="pin"
                        id="pin"
                        class="form-control"
                        placeholder="{{ __('Your 4–6 digit PIN if set') }}"
                    >
                    @error('pin')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <div class="form-check">
                        <input type="checkbox" name="remember" id="remember" class="form-check-input" value="1">
                        <label for="pin" class="form-check-input"></label>
                        <label for="remember" class="form-check-label small text-muted">{{ __('Remember this device') }}</label>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-sign-in-alt me-1"></i>{{ __('Sign In') }}
                    </button>
                </div>
            </form>

            <hr class="my-4">
            <p class="text-center small text-muted mb-0">
                {{ __('Do not have a library card?') }}
                <a href="{{ route('library.patron-create') }}">{{ __('Apply for one at the library') }}</a>.
            </p>
        </div>
    </div>
</div>
@endsection
