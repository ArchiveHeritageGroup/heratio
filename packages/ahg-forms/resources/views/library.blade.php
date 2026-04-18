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

@section('title', 'Template Library')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-book me-2"></i>Template Library</h1>
            <p class="text-muted">Pre-built form templates ready to install</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('forms.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    @if(session('notice'))
        <div class="alert alert-info">{{ session('notice') }}</div>
    @endif

    <div class="row">
        @foreach(($library ?? []) as $item)
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            {{ $item['name'] ?? '' }}
                            @if(!empty($item['installed']))
                                <span class="badge bg-success">Installed</span>
                            @endif
                        </h5>
                        <p class="card-text text-muted">{{ $item['description'] ?? '' }}</p>
                        <p class="card-text">
                            <small class="text-muted">
                                <i class="fas fa-list me-1"></i>{{ $item['fields'] ?? 0 }} fields
                            </small>
                        </p>
                    </div>
                    <div class="card-footer bg-transparent">
                        @if(!empty($item['installed']))
                            <span class="text-success"><i class="fas fa-check me-1"></i>Already installed</span>
                        @else
                            <button type="button" class="btn btn-primary btn-sm" disabled title="Library install not yet available">
                                <i class="fas fa-download me-1"></i> Install
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <h5><i class="fas fa-info-circle me-2"></i>About Template Library</h5>
            <p class="mb-0">
                These pre-built templates follow international standards and best practices.
                Install them to quickly set up common form configurations, then customize as needed.
            </p>
        </div>
    </div>
</div>
@endsection
