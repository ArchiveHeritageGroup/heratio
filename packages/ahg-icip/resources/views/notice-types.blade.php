{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'Manage Notice Types')

@section('content')
<div class="container-xxl">
  <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('ahgicip.dashboard') }}">ICIP</a></li>
      <li class="breadcrumb-item"><a href="{{ route('ahgicip.notices') }}">Cultural Notices</a></li>
      <li class="breadcrumb-item active">Notice Types</li>
    </ol>
  </nav>

  <h1 class="mb-4"><i class="bi bi-gear me-2"></i>Manage Notice Types</h1>

  @if(session('notice'))
    <div class="alert alert-success alert-dismissible fade show">
      {{ session('notice') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if(!($tablesExist ?? true))
    <div class="alert alert-warning">ICIP tables have not been provisioned for this installation.</div>
  @endif

  <div class="row">
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">{{ __('Notice Types') }}</h5></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>{{ __('Code') }}</th>
                  <th>{{ __('Name') }}</th>
                  <th>{{ __('Severity') }}</th>
                  <th>{{ __('Options') }}</th>
                  <th>{{ __('Status') }}</th>
                  <th>{{ __('Actions') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($noticeTypes as $type)
                  @php
                    $severityIcon = match($type->severity) {
                      'critical' => 'bi-exclamation-triangle-fill text-danger',
                      'warning' => 'bi-exclamation-circle text-warning',
                      default => 'bi-info-circle text-info',
                    };
                    $severityClass = match($type->severity) {
                      'critical' => 'bg-danger',
                      'warning' => 'bg-warning text-dark',
                      default => 'bg-info',
                    };
                  @endphp
                  <tr class="{{ !$type->is_active ? 'table-secondary' : '' }}">
                    <td><code>{{ $type->code }}</code></td>
                    <td><i class="bi {{ $severityIcon }} me-1"></i>{{ $type->name }}</td>
                    <td><span class="badge {{ $severityClass }}">{{ ucfirst($type->severity) }}</span></td>
                    <td>
                      @if($type->requires_acknowledgement)<span class="badge bg-secondary" title="{{ __('Requires Acknowledgement') }}">ACK</span>@endif
                      @if($type->blocks_access)<span class="badge bg-dark" title="{{ __('Blocks Access') }}">BLOCK</span>@endif
                      @if($type->display_public)<span class="badge bg-light text-dark" title="{{ __('Shown to Public') }}">PUB</span>@endif
                    </td>
                    <td>
                      @if($type->is_active)<span class="badge bg-success">Active</span>
                      @else<span class="badge bg-secondary">Inactive</span>@endif
                    </td>
                    <td>
                      <form method="post" class="d-inline">
                        @csrf
                        <input type="hidden" name="form_action" value="toggle">
                        <input type="hidden" name="type_id" value="{{ $type->id }}">
                        <button type="submit" class="btn btn-sm btn-outline-{{ $type->is_active ? 'warning' : 'success' }}" title="{{ $type->is_active ? 'Deactivate' : 'Activate' }}">
                          <i class="bi bi-{{ $type->is_active ? 'pause' : 'play' }}"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h5 class="mb-0">{{ __('Add Notice Type') }}</h5></div>
        <div class="card-body">
          <form method="post">
            @csrf
            <input type="hidden" name="form_action" value="add">
            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">Code <span class="text-danger">*</span></label>
                <input type="text" name="code" class="form-control" required placeholder="{{ __('e.g., custom_notice') }}">
                <div class="form-text">Unique identifier (lowercase, no spaces)</div>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required placeholder="{{ __('e.g., Custom Notice') }}">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">{{ __('Severity') }}</label>
                <select name="severity" class="form-select">
                  <option value="info">{{ __('Info') }}</option>
                  <option value="warning" selected>{{ __('Warning') }}</option>
                  <option value="critical">{{ __('Critical') }}</option>
                </select>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Description') }}</label>
              <input type="text" name="description" class="form-control" placeholder="{{ __('Brief description of when to use this notice') }}">
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Default Text') }}</label>
              <textarea name="default_text" class="form-control" rows="3" placeholder="{{ __('Default notice text shown to users') }}"></textarea>
            </div>
            <div class="row mb-3">
              <div class="col-md-6">
                <div class="form-check">
                  <input type="checkbox" name="requires_acknowledgement" value="1" class="form-check-input" id="reqAck">
                  <label class="form-check-label" for="reqAck">
                    Requires Acknowledgement
                    <br><small class="text-muted">User must acknowledge before viewing</small>
                  </label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check">
                  <input type="checkbox" name="blocks_access" value="1" class="form-check-input" id="blockAccess">
                  <label class="form-check-label" for="blockAccess">
                    Blocks Access
                    <br><small class="text-muted">Prevents viewing until acknowledged</small>
                  </label>
                </div>
              </div>
            </div>
            <div class="row mb-3">
              <div class="col-md-6">
                <div class="form-check">
                  <input type="checkbox" name="display_public" value="1" class="form-check-input" id="dispPublic" checked>
                  <label class="form-check-label" for="dispPublic">{{ __('Display to Public') }}</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check">
                  <input type="checkbox" name="display_staff" value="1" class="form-check-input" id="dispStaff" checked>
                  <label class="form-check-label" for="dispStaff">{{ __('Display to Staff') }}</label>
                </div>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Display Order') }}</label>
              <input type="number" name="display_order" class="form-control" value="100" style="max-width: 100px;">
            </div>
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-plus-circle me-1"></i> Add Notice Type
            </button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><h5 class="mb-0">{{ __('Legend') }}</h5></div>
        <div class="card-body small">
          <p><strong>Severity Levels:</strong></p>
          <ul>
            <li><span class="badge bg-info">Info</span> - General information</li>
            <li><span class="badge bg-warning text-dark">Warning</span> - Cultural sensitivity</li>
            <li><span class="badge bg-danger">Critical</span> - Restricted content</li>
          </ul>
          <p><strong>Options:</strong></p>
          <ul>
            <li><span class="badge bg-secondary">ACK</span> - Requires user acknowledgement</li>
            <li><span class="badge bg-dark">BLOCK</span> - Blocks access until acknowledged</li>
            <li><span class="badge bg-light text-dark">PUB</span> - Visible to public users</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
