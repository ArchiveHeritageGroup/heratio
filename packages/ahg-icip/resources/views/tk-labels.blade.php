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

@section('title', __('Traditional Knowledge Labels'))

@section('content')
@php
  $labelTypes = collect($labelTypes ?? []);
  $appliedLabels = collect($appliedLabels ?? []);
  $recentLabels = collect($recentLabels ?? []);
  $labelGroups = [
    'TK' => ['heading' => __('Traditional Knowledge (TK) Labels'), 'headingClass' => 'text-primary', 'iconClass' => 'icip-tk-label'],
    'BC' => ['heading' => __('Biocultural (BC) Labels'), 'headingClass' => 'text-success', 'iconClass' => 'icip-bc-label'],
  ];
@endphp
<div class="container-xxl">
  <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('ahgicip.dashboard') }}">ICIP</a></li>
      <li class="breadcrumb-item active">{{ __('TK Labels') }}</li>
    </ol>
  </nav>

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-tag me-2"></i>{{ __('Traditional Knowledge Labels') }}</h1>
    <a href="https://localcontexts.org/labels/traditional-knowledge-labels/" target="_blank" rel="noopener" class="btn btn-outline-info">
      <i class="bi bi-box-arrow-up-right me-1"></i> {{ __('Local Contexts') }}
    </a>
  </div>

  @if(!($tablesExist ?? true))
    <div class="alert alert-warning">{{ __('ICIP tables have not been provisioned for this installation.') }}</div>
  @endif

  <div class="row">
    <div class="col-lg-8">
      {{-- Label types --}}
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">{{ __('Available Label Types') }}</h5>
        </div>
        <div class="card-body">
          @foreach($labelGroups as $category => $group)
            @if(!$loop->first)
              <hr>
            @endif
            <h6 class="{{ $group['headingClass'] }} mb-3">{{ $group['heading'] }}</h6>
            <div class="row">
              @foreach($labelTypes->where('category', $category) as $type)
                <div class="col-md-6 mb-3">
                  <div class="d-flex align-items-start">
                    <div class="icip-tk-label-icon {{ $group['iconClass'] }} me-2">{{ strtoupper($type->code) }}</div>
                    <div>
                      <strong>{{ $type->name }}</strong>
                      <br>
                      <small class="text-muted">{{ $type->description }}</small>
                      @if(!empty($type->local_contexts_url))
                        <br><a href="{{ $type->local_contexts_url }}" target="_blank" rel="noopener" class="small">{{ __('Learn more') }}</a>
                      @endif
                    </div>
                  </div>
                </div>
              @endforeach
            </div>
          @endforeach
        </div>
      </div>

      {{-- Recent applications --}}
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">{{ __('Recent Label Applications') }}</h5>
        </div>
        <div class="card-body p-0">
          @if($recentLabels->isEmpty())
            <div class="p-4 text-center text-muted">
              <i class="bi bi-tag fs-1"></i>
              <p class="mb-0 mt-2">{{ __('No labels applied yet') }}</p>
              <p class="small">{{ __('Labels can be applied from individual record ICIP pages') }}</p>
            </div>
          @else
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>{{ __('Label') }}</th>
                    <th>{{ __('Record') }}</th>
                    <th>{{ __('Community') }}</th>
                    <th>{{ __('Applied By') }}</th>
                    <th>{{ __('Date') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($recentLabels as $label)
                    <tr>
                      <td>
                        <span class="badge {{ $label->category === 'TK' ? 'icip-tk-label' : 'icip-bc-label' }}">{{ strtoupper($label->label_code) }}</span>
                        {{ $label->label_name }}
                      </td>
                      <td>
                        @if(!empty($label->slug))
                          <a href="{{ route('ahgicip.object-icip', ['slug' => $label->slug]) }}">{{ $label->object_title ?? __('Untitled') }}</a>
                        @else
                          {{ $label->object_title ?? __('Untitled') }}
                        @endif
                      </td>
                      <td>{{ $label->community_name ?? '-' }}</td>
                      <td>
                        <span class="badge {{ $label->applied_by === 'community' ? 'bg-success' : 'bg-secondary' }}">
                          {{ $label->applied_by === 'community' ? __('Community') : ($label->applied_by === 'institution' ? __('Institution') : ucfirst((string) $label->applied_by)) }}
                        </span>
                      </td>
                      <td>{{ !empty($label->created_at) ? \Carbon\Carbon::parse($label->created_at)->format('j M Y') : '-' }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      {{-- Usage statistics --}}
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">{{ __('Label Usage') }}</h5>
        </div>
        <div class="card-body">
          @if($appliedLabels->isEmpty())
            <p class="text-muted mb-0">{{ __('No labels have been applied yet') }}</p>
          @else
            <ul class="list-unstyled mb-0">
              @foreach($appliedLabels as $stat)
                <li class="d-flex justify-content-between align-items-center mb-2">
                  <span>
                    <span class="badge {{ $stat->category === 'TK' ? 'icip-tk-label' : 'icip-bc-label' }} me-1">{{ strtoupper($stat->code) }}</span>
                    {{ $stat->name }}
                  </span>
                  <span class="badge bg-primary">{{ $stat->usage_count }}</span>
                </li>
              @endforeach
            </ul>
          @endif
        </div>
      </div>

      {{-- About TK labels --}}
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">{{ __('About TK Labels') }}</h5>
        </div>
        <div class="card-body small">
          <p>{!! __('Traditional Knowledge (TK) Labels are a digital tool developed by :org to help Indigenous communities manage their cultural heritage in digital environments.', ['org' => '<strong>Local Contexts</strong>']) !!}</p>
          <p>{{ __('Labels can be:') }}</p>
          <ul>
            <li><strong>{{ __('Applied by communities') }}</strong> {{ __('to express cultural protocols') }}</li>
            <li><strong>{{ __('Applied by institutions') }}</strong> {{ __('to acknowledge Indigenous origin') }}</li>
          </ul>
          <p class="mb-0">
            <a href="https://localcontexts.org/" target="_blank" rel="noopener">
              {{ __('Visit Local Contexts') }} <i class="bi bi-box-arrow-up-right"></i>
            </a>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.icip-tk-label { background-color: #8B4513; color: white; }
.icip-bc-label { background-color: #228B22; color: white; }
.icip-tk-label-icon { padding: 5px 10px; border-radius: 4px; font-size: 0.8rem; }
</style>
@endsection
