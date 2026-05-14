@extends('theme::layouts.1col')

@section('title-block')
  <h1>{{ $page->title ?? __('Contact') }}</h1>
@endsection

@push('css')
  <meta name="description" content="Get in touch with the Heratio team. Send us a message and we will respond by email.">
@endpush

@section('content')
  <div class="page p-3">

    @if(session('success'))
      <div class="alert alert-success" role="alert">
        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
      </div>
    @endif

    @if(session('error'))
      <div class="alert alert-warning" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}
      </div>
    @endif

    @if(!empty($page->content))
      <div class="contact-info mb-4">
        <h2 class="h5">{{ __('Our address') }}</h2>
        <div class="text-muted" style="white-space: pre-line;">{{ $page->content }}</div>
      </div>
    @endif

    <h2 class="h4 mt-4 mb-3">{{ __('Send us a message') }}</h2>
    <p class="text-muted">{{ __('Fill in the form and we will reply by email.') }}</p>

    <form method="POST" action="{{ route('contact.submit') }}" class="contact-form" novalidate>
      @csrf

      {{-- Honeypot: real users leave this empty; CSS hides it. --}}
      <div aria-hidden="true" style="position:absolute; left:-5000px; top:auto; width:1px; height:1px; overflow:hidden;">
        <label for="contact-website">{{ __('Website (leave blank)') }}</label>
        <input type="text" id="contact-website" name="website" tabindex="-1" autocomplete="off" value="{{ old('website') }}">
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <label for="contact-name" class="form-label">
            {{ __('Your name') }} <span class="text-danger" aria-hidden="true">*</span>
            <span class="visually-hidden">{{ __('required') }}</span>
          </label>
          <input type="text" class="form-control @error('name') is-invalid @enderror"
                 id="contact-name" name="name" required maxlength="120"
                 value="{{ old('name') }}" autocomplete="name">
          @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
          <label for="contact-email" class="form-label">
            {{ __('Email address') }} <span class="text-danger" aria-hidden="true">*</span>
            <span class="visually-hidden">{{ __('required') }}</span>
          </label>
          <input type="email" class="form-control @error('email') is-invalid @enderror"
                 id="contact-email" name="email" required maxlength="200"
                 value="{{ old('email') }}" autocomplete="email">
          @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
          <label for="contact-org" class="form-label">{{ __('Organisation') }}</label>
          <input type="text" class="form-control @error('organisation') is-invalid @enderror"
                 id="contact-org" name="organisation" maxlength="200"
                 value="{{ old('organisation') }}" autocomplete="organization">
          @error('organisation')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
          <label for="contact-message" class="form-label">
            {{ __('Your message') }} <span class="text-danger" aria-hidden="true">*</span>
            <span class="visually-hidden">{{ __('required') }}</span>
          </label>
          <textarea class="form-control @error('message') is-invalid @enderror"
                    id="contact-message" name="message" rows="6" required
                    minlength="10" maxlength="5000">{{ old('message') }}</textarea>
          @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12 d-flex justify-content-between align-items-center">
          <small class="text-muted">
            <i class="fas fa-lock me-1" aria-hidden="true"></i>{{ __('We will only use your details to reply.') }}
          </small>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-paper-plane me-1" aria-hidden="true"></i>{{ __('Send message') }}
          </button>
        </div>
      </div>
    </form>
  </div>
@endsection
