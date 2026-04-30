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

@section('title', 'Community Registry')

@section('content')
<div class="container-xxl">
  <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('ahgicip.dashboard') }}">ICIP</a></li>
      <li class="breadcrumb-item active">Communities</li>
    </ol>
  </nav>

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-people me-2"></i>Community Registry</h1>
    <a href="{{ route('ahgicip.community-edit') }}" class="btn btn-primary">
      <i class="bi bi-plus-circle me-1"></i> {{ __('Add Community') }}
    </a>
  </div>

  @if(!($tablesExist ?? true))
    <div class="alert alert-warning">ICIP tables have not been provisioned for this installation.</div>
  @endif

  <div class="card mb-4">
    <div class="card-body">
      <form method="get" class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label">{{ __('State/Territory') }}</label>
          <select name="state" class="form-select">
            <option value="">{{ __('All States') }}</option>
            @foreach($states as $code => $name)
              <option value="{{ $code }}" @selected(($filters['state'] ?? '') === $code)>{{ $name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ __('Search') }}</label>
          <input type="text" name="search" class="form-control" placeholder="{{ __('Name, language group, region...') }}" value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="col-md-2">
          <div class="form-check">
            <input type="checkbox" name="active_only" value="1" class="form-check-input" id="activeOnly" @checked(($filters['active_only'] ?? '1') === '1')>
            <label class="form-check-label" for="activeOnly">{{ __('Active only') }}</label>
          </div>
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-outline-primary me-2"><i class="bi bi-search"></i> {{ __('Filter') }}</button>
          <a href="{{ route('ahgicip.communities') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><strong>{{ $communities->count() }}</strong> communities found</div>
    <div class="card-body p-0">
      @if($communities->isEmpty())
        <div class="p-4 text-center text-muted">
          <i class="bi bi-people fs-1"></i>
          <p class="mb-0 mt-2">No communities found</p>
          <a href="{{ route('ahgicip.community-edit') }}" class="btn btn-primary mt-3">Add First Community</a>
        </div>
      @else
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ __('Community Name') }}</th>
                <th>{{ __('Language Group') }}</th>
                <th>{{ __('Region') }}</th>
                <th>{{ __('State') }}</th>
                <th>{{ __('Contact') }}</th>
                <th>{{ __('Status') }}</th>
                <th width="120">{{ __('Actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($communities as $community)
                <tr>
                  <td>
                    <a href="{{ route('ahgicip.community-view', ['id' => $community->id]) }}">
                      <strong>{{ $community->name }}</strong>
                    </a>
                    @if(!empty($community->prescribed_body_corporate))
                      <br><small class="text-muted">PBC: {{ $community->prescribed_body_corporate }}</small>
                    @endif
                  </td>
                  <td>{{ $community->language_group ?? '-' }}</td>
                  <td>{{ $community->region ?? '-' }}</td>
                  <td><span class="badge bg-secondary">{{ $community->state_territory }}</span></td>
                  <td>
                    @if(!empty($community->contact_name))
                      {{ $community->contact_name }}
                      @if(!empty($community->contact_email))
                        <br><small><a href="mailto:{{ $community->contact_email }}">{{ $community->contact_email }}</a></small>
                      @endif
                    @else
                      <span class="text-muted">-</span>
                    @endif
                  </td>
                  <td>
                    @if($community->is_active)
                      <span class="badge bg-success">{{ __('Active') }}</span>
                    @else
                      <span class="badge bg-secondary">{{ __('Inactive') }}</span>
                    @endif
                  </td>
                  <td>
                    <div class="btn-group btn-group-sm">
                      <a href="{{ route('ahgicip.community-view', ['id' => $community->id]) }}" class="btn btn-outline-primary" title="{{ __('View') }}"><i class="bi bi-eye"></i></a>
                      <a href="{{ route('ahgicip.community-edit', ['id' => $community->id]) }}" class="btn btn-outline-secondary" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                      <a href="{{ route('ahgicip.report-community', ['id' => $community->id]) }}" class="btn btn-outline-info" title="{{ __('Report') }}"><i class="bi bi-graph-up"></i></a>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>
</div>
@endsection
