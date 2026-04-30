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

@section('title', 'Manage TK Labels')

@section('content')
<div class="container-xxl">
  <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ url('/'.$object->slug) }}">{{ $object->title ?? 'Record' }}</a></li>
      <li class="breadcrumb-item"><a href="{{ route('ahgicip.object-icip', ['slug' => $object->slug]) }}">ICIP</a></li>
      <li class="breadcrumb-item active">TK Labels</li>
    </ol>
  </nav>

  <h1 class="mb-4"><i class="bi bi-tag me-2"></i>Manage TK Labels</h1>

  @if(session('notice'))
    <div class="alert alert-success alert-dismissible fade show">
      {{ session('notice') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="row">
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">{{ __('Applied Labels') }}</h5></div>
        <div class="card-body">
          @if($labels->isEmpty())
            <p class="text-muted">No TK labels applied to this record.</p>
          @else
            <div class="row">
              @foreach($labels as $label)
                <div class="col-md-6 mb-3">
                  <div class="card h-100">
                    <div class="card-body d-flex justify-content-between align-items-start">
                      <div class="d-flex">
                        <span class="badge {{ ($label->category ?? '') === 'TK' ? 'icip-tk-label' : 'icip-bc-label' }} me-2 fs-6">
                          {{ strtoupper($label->label_code ?? '') }}
                        </span>
                        <div>
                          <strong>{{ $label->label_name ?? '' }}</strong>
                          <br>
                          <small class="text-muted">Applied by: {{ ucfirst($label->applied_by ?? '') }}</small>
                          @if(!empty($label->community_name))
                            <br><small class="text-muted">Community: {{ $label->community_name }}</small>
                          @endif
                        </div>
                      </div>
                      <form method="post" class="d-inline" onsubmit="return confirm('Remove this label?');">
                        @csrf
                        <input type="hidden" name="form_action" value="remove">
                        <input type="hidden" name="label_id" value="{{ $label->id }}">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button>
                      </form>
                    </div>
                  </div>
                </div>
              @endforeach
            </div>
          @endif
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h5 class="mb-0">{{ __('Add TK Label') }}</h5></div>
        <div class="card-body">
          <form method="post">
            @csrf
            <input type="hidden" name="form_action" value="add">
            <div class="mb-3">
              <label class="form-label">Label Type <span class="text-danger">*</span></label>
              <select name="label_type_id" class="form-select" required>
                <option value="">{{ __('Select label') }}</option>
                <optgroup label="Traditional Knowledge (TK) Labels">
                  @foreach($labelTypes as $type)
                    @if($type->category === 'TK')
                      <option value="{{ $type->id }}">{{ strtoupper($type->code) }} - {{ $type->name }}</option>
                    @endif
                  @endforeach
                </optgroup>
                <optgroup label="Biocultural (BC) Labels">
                  @foreach($labelTypes as $type)
                    @if($type->category === 'BC')
                      <option value="{{ $type->id }}">{{ strtoupper($type->code) }} - {{ $type->name }}</option>
                    @endif
                  @endforeach
                </optgroup>
              </select>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">{{ __('Community') }}</label>
                <select name="community_id" class="form-select">
                  <option value="">{{ __('Not specified') }}</option>
                  @foreach($communities as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">{{ __('Applied By') }}</label>
                <select name="applied_by" class="form-select">
                  <option value="institution">{{ __('Institution') }}</option>
                  <option value="community">{{ __('Community') }}</option>
                </select>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">{{ __('Local Contexts Project ID') }}</label>
              <input type="text" name="local_contexts_project_id" class="form-control" placeholder="{{ __('Optional - link to Local Contexts Hub project') }}">
            </div>

            <div class="mb-3">
              <label class="form-label">{{ __('Notes') }}</label>
              <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">
              <i class="bi bi-plus-circle me-1"></i> {{ __('Add Label') }}
            </button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">{{ __('About TK Labels') }}</h5></div>
        <div class="card-body small">
          <p>TK Labels are developed by <strong>{{ __('Local Contexts') }}</strong> to help Indigenous communities manage their cultural heritage.</p>
          <p><strong>{{ __('TK Labels') }}</strong> (brown) relate to Traditional Knowledge.</p>
          <p><strong>{{ __('BC Labels') }}</strong> (green) relate to Biocultural heritage.</p>
          <p class="mb-0">
            <a href="https://localcontexts.org/labels/traditional-knowledge-labels/" target="_blank">
              Learn more at Local Contexts <i class="bi bi-box-arrow-up-right"></i>
            </a>
          </p>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h5 class="mb-0">{{ __('Applied By') }}</h5></div>
        <div class="card-body small">
          <p><strong>{{ __('Community:') }}</strong> Labels applied directly by or at the request of the community.</p>
          <p class="mb-0"><strong>{{ __('Institution:') }}</strong> Labels applied by the institution to acknowledge Indigenous origin or protocols.</p>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.icip-tk-label { background-color: #8B4513; color: white; }
.icip-bc-label { background-color: #228B22; color: white; }
</style>
@endsection
