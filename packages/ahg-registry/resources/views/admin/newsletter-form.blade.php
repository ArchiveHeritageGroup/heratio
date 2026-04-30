{{--
  Registry Admin — Newsletter Form
  Cloned from PSIS adminNewsletterFormSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('ahg-registry::layouts.registry')

@php $isNew = empty($newsletter); @endphp

@section('title', $isNew ? __('New Newsletter') : __('Edit Newsletter'))
@section('body-class', 'registry registry-admin-newsletter-form')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dashboard') }}">{{ __('Admin') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.newsletters') }}">{{ __('Newsletters') }}</a></li>
    <li class="breadcrumb-item active">{{ $isNew ? __('New') : __('Edit') }}</li>
  </ol>
</nav>

<div class="row justify-content-center">
  <div class="col-lg-10">

    <h1 class="h3 mb-4">
      <i class="fas fa-{{ $isNew ? 'plus' : 'edit' }} me-2"></i>
      {{ $isNew ? __('New Newsletter') : __('Edit Newsletter') }}
    </h1>

    @if($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
      </div>
    @endif

    @php
      $formAction = $isNew
        ? route('registry.admin.newsletterForm')
        : route('registry.admin.newsletterForm', ['id' => (int) $newsletter->id]);
    @endphp

    <form method="post" action="{{ $formAction }}">
      @csrf
      @if(!$isNew) @method('PUT') @endif
      <div class="card mb-4">
        <div class="card-body">

          <div class="mb-3">
            <label for="nl-subject" class="form-label fw-semibold">{{ __('Subject') }} <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="nl-subject" name="subject" required
                   value="{{ old('subject', $newsletter->subject ?? '') }}"
                   placeholder="{{ __('Newsletter subject line...') }}">
          </div>

          <div class="mb-3">
            <label for="nl-excerpt" class="form-label fw-semibold">{{ __('Excerpt / Preview Text') }}</label>
            <input type="text" class="form-control" id="nl-excerpt" name="excerpt"
                   value="{{ old('excerpt', $newsletter->excerpt ?? '') }}"
                   placeholder="{{ __('Brief preview text shown in email clients...') }}">
            <div class="form-text">{{ __('Optional — shown as preview in email clients.') }}</div>
          </div>

          <div class="mb-3">
            <label for="nl-content" class="form-label fw-semibold">{{ __('Content') }} <span class="text-danger">*</span></label>
            <textarea name="content" id="nl-content" class="form-control" rows="14" required>{{ old('content', $newsletter->content ?? '') }}</textarea>
            <div class="form-text">{{ __('HTML is permitted.') }}</div>
          </div>

        </div>
      </div>

      <div class="d-flex justify-content-between">
        <a href="{{ route('registry.admin.newsletters') }}" class="btn btn-outline-secondary">
          <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Newsletters') }}
        </a>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save me-1"></i> {{ $isNew ? __('Create Newsletter') : __('Update Newsletter') }}
        </button>
      </div>
    </form>

  </div>
</div>
@endsection
