@extends('theme::layouts.1col')

@section('title', 'General Feedback')
@section('body-class', 'feedback')

@section('content')
<div class="d-flex align-items-center mb-3">
  <i class="fas fa-comments fa-2x text-primary me-3"></i>
  <div>
    <h1 class="h3 mb-0">{{ __('General Feedback') }}</h1>
    <p class="text-muted mb-0">Share your feedback, suggestions, or report issues</p>
  </div>
</div>

@if($errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li> @endforeach</ul></div>
@endif

<form method="POST" action="{{ route('feedback.general') }}">
  @csrf

  <div class="row justify-content-center">
    <div class="col-lg-8">

      {{-- Feedback Type & Content --}}
      <div class="card shadow-sm mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-comment-alt me-2"></i>{{ __('Your Feedback') }}
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Subject <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
            <input type="text" name="subject" class="form-control" required value="{{ old('subject') }}" placeholder="{{ __('Brief subject of your feedback') }}">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Feedback Type <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
            <select name="feed_type_id" class="form-select" required>
              <option value="">-- Select --</option>
              @foreach($feedbackTypes as $type)
                <option value="{{ $type->id }}" @selected(old('feed_type_id') == $type->id)>{{ $type->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Your Feedback / Comments <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
            <textarea name="remarks" class="form-control" rows="6" required placeholder="{{ __('Please provide details about your feedback, suggestion, or issue...') }}">{{ old('remarks') }}</textarea>
          </div>
          <div class="mb-0">
            <label class="form-label fw-semibold">Your Relationship to the Archive <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            <textarea name="feed_relationship" class="form-control" rows="2" placeholder="{{ __('e.g., Researcher, visitor, community member, donor...') }}">{{ old('feed_relationship') }}</textarea>
          </div>
        </div>
      </div>

      {{-- Contact Details --}}
      <div class="card shadow-sm mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-address-card me-2"></i>{{ __('Your Contact Details') }}
        </div>
        <div class="card-body">
          <p class="text-muted small mb-3">Please provide your contact details so we can follow up if needed.</p>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Name <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <input type="text" name="feed_name" class="form-control" required value="{{ old('feed_name', auth()->user()->username ?? '') }}" placeholder="{{ __('Your first name') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Surname <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <input type="text" name="feed_surname" class="form-control" required value="{{ old('feed_surname') }}" placeholder="{{ __('Your surname') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Phone Number <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                <input type="tel" name="feed_phone" class="form-control" value="{{ old('feed_phone') }}" placeholder="{{ __('Contact number') }}">
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                <input type="email" name="feed_email" class="form-control" required value="{{ old('feed_email', auth()->user()->email ?? '') }}" placeholder="{{ __('your@email.com') }}">
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Submit --}}
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <a href="{{ url('/') }}" class="btn atom-btn-white">
              <i class="fas fa-times me-1"></i>{{ __('Cancel') }}
            </a>
            <button type="submit" class="btn atom-btn-outline-success btn-lg">
              <i class="fas fa-paper-plane me-1"></i>{{ __('Submit Feedback') }}
            </button>
          </div>
        </div>
      </div>

    </div>
  </div>
</form>
@endsection
