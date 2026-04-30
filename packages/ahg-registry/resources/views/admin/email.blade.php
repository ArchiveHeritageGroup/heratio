{{--
  Registry Admin — Email Settings
  Cloned from PSIS adminEmailSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('ahg-registry::layouts.registry')

@section('title', __('Email Settings') . ' — ' . __('Admin'))
@section('body-class', 'registry registry-admin-email')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dashboard') }}">{{ __('Admin') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Email') }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4"><i class="fas fa-at me-2"></i>{{ __('Email Settings') }}</h1>

<form method="post" action="{{ route('registry.admin.email') }}">
  @csrf

  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('SMTP Configuration') }}</div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">{{ __('SMTP host') }}</label>
          <input type="text" class="form-control" name="smtp_host" value="{{ $settings['smtp_host'] ?? '' }}">
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ __('Port') }}</label>
          <input type="number" class="form-control" name="smtp_port" value="{{ $settings['smtp_port'] ?? '587' }}">
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ __('Username') }}</label>
          <input type="text" class="form-control" name="smtp_username" value="{{ $settings['smtp_username'] ?? '' }}" autocomplete="off">
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ __('Password') }}</label>
          <input type="password" class="form-control" name="smtp_password" placeholder="{{ __('Leave blank to keep current') }}" autocomplete="new-password">
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ __('Encryption') }}</label>
          <select name="smtp_encryption" class="form-select">
            <option value="tls" {{ ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' }}>{{ __('TLS') }}</option>
            <option value="ssl" {{ ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' }}>{{ __('SSL') }}</option>
            <option value="none" {{ ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('None') }}</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('Sender Identity') }}</div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">{{ __('From email') }}</label>
          <input type="email" class="form-control" name="from_email" value="{{ $settings['from_email'] ?? '' }}">
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ __('From name') }}</label>
          <input type="text" class="form-control" name="from_name" value="{{ $settings['from_name'] ?? '' }}">
        </div>
        <div class="col-12">
          <label class="form-label">{{ __('Reply-to') }}</label>
          <input type="email" class="form-control" name="reply_to" value="{{ $settings['reply_to'] ?? '' }}">
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button>
    <button type="submit" name="action" value="test" class="btn btn-outline-info"><i class="fas fa-paper-plane me-1"></i>{{ __('Send test email') }}</button>
  </div>
</form>
@endsection
