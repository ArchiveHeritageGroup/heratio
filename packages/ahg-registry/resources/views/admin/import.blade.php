{{--
  Registry Admin — Import
  Cloned from PSIS adminImportSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('theme::layouts.1col')

@section('title', __('Import') . ' — ' . __('Admin'))
@section('body-class', 'registry registry-admin-import')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dashboard') }}">{{ __('Admin') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Import') }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4"><i class="fas fa-file-import me-2"></i>{{ __('Import Data') }}</h1>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header fw-semibold">{{ __('Institutions') }}</div>
      <div class="card-body">
        <p class="text-muted small">{{ __('Bulk-import institution records from CSV.') }}</p>
        <form method="post" enctype="multipart/form-data" action="{{ url('/registry/admin/import/institutions') }}">
          @csrf
          <div class="mb-3">
            <label class="form-label">{{ __('CSV file') }}</label>
            <input type="file" class="form-control" name="file" accept=".csv" required>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i>{{ __('Upload') }}</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card">
      <div class="card-header fw-semibold">{{ __('Vendors') }}</div>
      <div class="card-body">
        <p class="text-muted small">{{ __('Bulk-import vendor records from CSV.') }}</p>
        <form method="post" enctype="multipart/form-data" action="{{ url('/registry/admin/import/vendors') }}">
          @csrf
          <div class="mb-3">
            <label class="form-label">{{ __('CSV file') }}</label>
            <input type="file" class="form-control" name="file" accept=".csv" required>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i>{{ __('Upload') }}</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card">
      <div class="card-header fw-semibold">{{ __('Software') }}</div>
      <div class="card-body">
        <p class="text-muted small">{{ __('Bulk-import software records from CSV.') }}</p>
        <form method="post" enctype="multipart/form-data" action="{{ url('/registry/admin/import/software') }}">
          @csrf
          <div class="mb-3">
            <label class="form-label">{{ __('CSV file') }}</label>
            <input type="file" class="form-control" name="file" accept=".csv" required>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i>{{ __('Upload') }}</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card">
      <div class="card-header fw-semibold">{{ __('Standards') }}</div>
      <div class="card-body">
        <p class="text-muted small">{{ __('Bulk-import standards records from CSV.') }}</p>
        <form method="post" enctype="multipart/form-data" action="{{ url('/registry/admin/import/standards') }}">
          @csrf
          <div class="mb-3">
            <label class="form-label">{{ __('CSV file') }}</label>
            <input type="file" class="form-control" name="file" accept=".csv" required>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i>{{ __('Upload') }}</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
