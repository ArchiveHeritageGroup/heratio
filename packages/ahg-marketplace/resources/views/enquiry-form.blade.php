{{--
  Marketplace — Enquire About Listing

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/enquiryFormSuccess.php.
--}}
@extends('theme::layouts.1col')
@section('title', __('Enquire') . ' - ' . ($listing->title ?? ''))
@section('body-class', 'marketplace enquiry-form')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.listing', ['slug' => $listing->slug ?? '']) }}">{{ $listing->title ?? '' }}</a></li>
    <li class="breadcrumb-item active">{{ __('Enquire') }}</li>
  </ol>
</nav>

@if(session('success') || session('notice'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') ?? session('notice') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif
@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

<div class="row">
  <div class="col-lg-8 mx-auto">
    <h1 class="h3 mb-4">{{ __('Enquire About This Listing') }}</h1>

    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex">
          @if(!empty($primaryImage->file_path ?? null))
            <img src="{{ $primaryImage->file_path }}" alt="{{ $listing->title ?? '' }}" class="rounded me-3" style="width:100px;height:100px;object-fit:cover;">
          @elseif(!empty($listing->featured_image_path))
            <img src="{{ $listing->featured_image_path }}" alt="{{ $listing->title ?? '' }}" class="rounded me-3" style="width:100px;height:100px;object-fit:cover;">
          @else
            <div class="bg-light rounded d-flex align-items-center justify-content-center me-3" style="width:100px;height:100px;">
              <i class="fas fa-image fa-2x text-muted"></i>
            </div>
          @endif
          <div>
            <h5 class="mb-1">{{ $listing->title ?? '' }}</h5>
            @if(!empty($listing->artist_name))
              <p class="text-muted mb-1">{{ __('by :name', ['name' => $listing->artist_name]) }}</p>
            @endif
            @if(!empty($listing->price) && empty($listing->price_on_request))
              <p class="h5 text-primary mb-0">{{ $listing->currency ?? '' }} {{ number_format((float) $listing->price, 2) }}</p>
            @elseif(!empty($listing->price_on_request))
              <p class="h5 text-muted mb-0">{{ __('Price on Request') }}</p>
            @endif
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0"><i class="fas fa-envelope me-2"></i>{{ __('Your Enquiry') }}</h5>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('ahgmarketplace.enquiry-form.post', ['slug' => $listing->slug ?? '']) }}">
          @csrf
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="enquiry_name" class="form-label">{{ __('Your Name') }} <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="enquiry_name" name="enquiry_name" value="{{ old('enquiry_name', $prefillName ?? '') }}" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="enquiry_email" class="form-label">{{ __('Email Address') }} <span class="text-danger">*</span></label>
              <input type="email" class="form-control" id="enquiry_email" name="enquiry_email" value="{{ old('enquiry_email', $prefillEmail ?? '') }}" required>
            </div>
          </div>
          <div class="mb-3">
            <label for="enquiry_phone" class="form-label">{{ __('Phone Number') }} <span class="text-muted">({{ __('optional') }})</span></label>
            <input type="tel" class="form-control" id="enquiry_phone" name="enquiry_phone" value="{{ old('enquiry_phone') }}">
          </div>
          <div class="mb-3">
            <label for="enquiry_subject" class="form-label">{{ __('Subject') }} <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="enquiry_subject" name="enquiry_subject" value="{{ old('enquiry_subject', __('Enquiry about: :title', ['title' => $listing->title ?? ''])) }}" required>
          </div>
          <div class="mb-4">
            <label for="enquiry_message" class="form-label">{{ __('Message') }} <span class="text-danger">*</span></label>
            <textarea class="form-control" id="enquiry_message" name="enquiry_message" rows="5" placeholder="{{ __('Please enter your enquiry...') }}" required>{{ old('enquiry_message') }}</textarea>
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <a href="{{ route('ahgmarketplace.listing', ['slug' => $listing->slug ?? '']) }}" class="btn btn-outline-secondary">
              <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Listing') }}
            </a>
            <button type="submit" class="btn btn-primary btn-lg">
              <i class="fas fa-paper-plane me-1"></i> {{ __('Send Enquiry') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
