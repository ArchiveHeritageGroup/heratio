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

@section('title', 'Consultation Log')

@section('content')
<div class="container-xxl">
  <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('ahgicip.dashboard') }}">ICIP</a></li>
      <li class="breadcrumb-item active">Consultations</li>
    </ol>
  </nav>

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-chat-dots me-2"></i>Consultation Log</h1>
    <a href="{{ route('ahgicip.consultation-edit') }}" class="btn btn-primary">
      <i class="bi bi-plus-circle me-1"></i> Log Consultation
    </a>
  </div>

  @if(!($tablesExist ?? true))
    <div class="alert alert-warning">ICIP tables have not been provisioned for this installation.</div>
  @endif

  <div class="card mb-4">
    <div class="card-body">
      <form method="get" class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label">{{ __('Type') }}</label>
          <select name="type" class="form-select">
            <option value="">{{ __('All Types') }}</option>
            @foreach(($consultationTypes ?? []) as $k => $v)
              <option value="{{ $k }}" @selected(($filters['type'] ?? '') === $k)>{{ $v }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ __('Community') }}</label>
          <select name="community_id" class="form-select">
            <option value="">{{ __('All Communities') }}</option>
            @foreach($communities as $c)
              <option value="{{ $c->id }}" @selected(($filters['community_id'] ?? '') == $c->id)>{{ $c->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ __('Status') }}</label>
          <select name="status" class="form-select">
            <option value="">{{ __('All') }}</option>
            @foreach(($consultationStatuses ?? []) as $k => $v)
              <option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $v }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <button type="submit" class="btn btn-outline-primary me-2"><i class="bi bi-search"></i> Filter</button>
          <a href="{{ route('ahgicip.consultations') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><strong>{{ $consultations->count() }}</strong> consultations found</div>
    <div class="card-body p-0">
      @if($consultations->isEmpty())
        <div class="p-4 text-center text-muted">
          <i class="bi bi-chat-dots fs-1"></i>
          <p class="mb-0 mt-2">No consultations found</p>
        </div>
      @else
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Community') }}</th>
                <th>{{ __('Type') }}</th>
                <th>{{ __('Method') }}</th>
                <th>{{ __('Summary') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Follow-up') }}</th>
                <th width="100">{{ __('Actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($consultations as $consultation)
                @php
                  $methodIcon = match($consultation->consultation_method ?? '') {
                    'in_person' => 'bi-person',
                    'phone' => 'bi-telephone',
                    'video' => 'bi-camera-video',
                    'email' => 'bi-envelope',
                    'letter' => 'bi-envelope-paper',
                    default => 'bi-chat',
                  };
                  $statusClass = match($consultation->status ?? '') {
                    'completed' => 'bg-success',
                    'scheduled' => 'bg-info',
                    'cancelled' => 'bg-secondary',
                    'follow_up_required' => 'bg-warning text-dark',
                    default => 'bg-secondary',
                  };
                  $isOverdue = !empty($consultation->follow_up_date)
                    && \Carbon\Carbon::parse($consultation->follow_up_date)->isPast()
                    && ($consultation->status ?? '') === 'follow_up_required';
                @endphp
                <tr>
                  <td>{{ \Carbon\Carbon::parse($consultation->consultation_date)->format('j M Y') }}</td>
                  <td>{{ $consultation->community_name }}</td>
                  <td><span class="badge bg-light text-dark">{{ ucwords(str_replace('_', ' ', $consultation->consultation_type ?? '')) }}</span></td>
                  <td>
                    <i class="bi {{ $methodIcon }}" title="{{ ucwords(str_replace('_', ' ', $consultation->consultation_method ?? '')) }}"></i>
                    {{ ucwords(str_replace('_', ' ', $consultation->consultation_method ?? '')) }}
                  </td>
                  <td>
                    {{ \Illuminate\Support\Str::limit($consultation->summary ?? '', 60) }}
                    @if(!empty($consultation->object_title))
                      <br><small class="text-muted">Re: {{ $consultation->object_title }}</small>
                    @endif
                  </td>
                  <td><span class="badge {{ $statusClass }}">{{ ucwords(str_replace('_', ' ', $consultation->status ?? '')) }}</span></td>
                  <td>
                    @if(!empty($consultation->follow_up_date))
                      <span class="{{ $isOverdue ? 'text-danger fw-bold' : '' }}">
                        {{ \Carbon\Carbon::parse($consultation->follow_up_date)->format('j M Y') }}
                        @if($isOverdue)<i class="bi bi-exclamation-circle"></i>@endif
                      </span>
                    @else
                      -
                    @endif
                  </td>
                  <td>
                    <div class="btn-group btn-group-sm">
                      <a href="{{ route('ahgicip.consultation-view', ['id' => $consultation->id]) }}" class="btn btn-outline-primary" title="{{ __('View') }}"><i class="bi bi-eye"></i></a>
                      <a href="{{ route('ahgicip.consultation-edit', ['id' => $consultation->id]) }}" class="btn btn-outline-secondary" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
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
