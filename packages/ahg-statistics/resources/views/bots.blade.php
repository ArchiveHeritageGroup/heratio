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

@section('title', 'Bot Filter List')

@section('content')
<div class="container-fluid px-4 py-3">
    <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('statistics.dashboard') }}">Statistics</a></li>
            <li class="breadcrumb-item"><a href="{{ route('statistics.admin') }}">Settings</a></li>
            <li class="breadcrumb-item active">Bot List</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-robot me-2"></i>Bot Filter List</h1>
    </div>

    @if(session('notice'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('notice') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Configured Bot Patterns') }}</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Pattern') }}</th>
                                    <th>{{ __('Category') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($bots ?? [] as $bot)
                                    <tr>
                                        <td>{{ $bot->name }}</td>
                                        <td><code class="small">{{ $bot->pattern }}</code></td>
                                        <td><span class="badge bg-info">{{ str_replace('_', ' ', $bot->category) }}</span></td>
                                        <td>
                                            @if(!empty($bot->is_active))
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-secondary">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <form method="post" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="form_action" value="toggle">
                                                <input type="hidden" name="id" value="{{ $bot->id }}">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ __('Toggle') }}">
                                                    <i class="fas fa-toggle-{{ !empty($bot->is_active) ? 'on' : 'off' }}"></i>
                                                </button>
                                            </form>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this bot pattern?');">
                                                @csrf
                                                <input type="hidden" name="form_action" value="delete">
                                                <input type="hidden" name="id" value="{{ $bot->id }}">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-trash"></i>
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
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Add Bot Pattern') }}</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        @csrf
                        <input type="hidden" name="form_action" value="add">

                        <div class="mb-3">
                            <label for="name" class="form-label">{{ __('Name *') }}</label>
                            <input type="text" class="form-control" id="name" name="name" required placeholder="{{ __('e.g., MyBot') }}">
                        </div>

                        <div class="mb-3">
                            <label for="pattern" class="form-label">{{ __('Regex Pattern *') }}</label>
                            <input type="text" class="form-control" id="pattern" name="pattern" required placeholder="{{ __('e.g., MyBot|mybot') }}">
                            <small class="form-text text-muted">Case-insensitive regex to match user agent</small>
                        </div>

                        <div class="mb-3">
                            <label for="category" class="form-label">{{ __('Category') }}</label>
                            <select class="form-select" id="category" name="category">
                                <option value="search_engine">{{ __('Search Engine') }}</option>
                                <option value="social">{{ __('Social Media') }}</option>
                                <option value="monitoring">{{ __('Monitoring') }}</option>
                                <option value="crawler" selected>{{ __('Crawler') }}</option>
                                <option value="spam">{{ __('Spam') }}</option>
                                <option value="other">{{ __('Other') }}</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-1"></i>Add Pattern
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
