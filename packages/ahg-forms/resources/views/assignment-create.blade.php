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

@section('title', 'Create Form Assignment')

@section('content')
@php
    $templates = $templates ?? collect();
    try {
        $repos = \Illuminate\Support\Facades\DB::table('repository as r')
            ->leftJoin('actor_i18n as ai', function ($j) {
                $j->on('r.id', '=', 'ai.id')->where('ai.culture', '=', app()->getLocale() ?: 'en');
            })
            ->select('r.id', 'ai.authorized_form_of_name')
            ->orderBy('ai.authorized_form_of_name')
            ->get();
    } catch (\Throwable $e) {
        $repos = collect();
    }
    try {
        $levels = \Illuminate\Support\Facades\DB::table('term as t')
            ->leftJoin('term_i18n as ti', function ($j) {
                $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', app()->getLocale() ?: 'en');
            })
            ->where('t.taxonomy_id', 157)
            ->select('t.id', 'ti.name')
            ->orderBy('ti.name')
            ->get();
    } catch (\Throwable $e) {
        $levels = collect();
    }
@endphp
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-plus me-2"></i>Create Assignment</h1>
            <p class="text-muted">Assign a form template to specific contexts</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('forms.assignments') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="post">
                @csrf
                <div class="mb-3">
                    <label class="form-label">{{ __('Form Template *') }}</label>
                    <select name="template_id" class="form-select" required>
                        <option value="">{{ __('Select template...') }}</option>
                        @foreach($templates as $template)
                            <option value="{{ $template->id }}">
                                {{ $template->name }} ({{ $template->form_type }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ __('Repository (optional)') }}</label>
                    <select name="repository_id" class="form-select">
                        <option value="">{{ __('All repositories') }}</option>
                        @foreach($repos as $repo)
                            <option value="{{ $repo->id }}">{{ $repo->authorized_form_of_name }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">Leave empty to apply to all repositories</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ __('Level of Description (optional)') }}</label>
                    <select name="level_of_description_id" class="form-select">
                        <option value="">{{ __('All levels') }}</option>
                        @foreach($levels as $level)
                            <option value="{{ $level->id }}">{{ $level->name }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">Leave empty to apply to all levels</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ __('Priority') }}</label>
                    <input type="number" name="priority" class="form-control" value="100" min="1" max="1000">
                    <small class="text-muted">Higher numbers = higher priority. When multiple assignments match, the highest priority wins.</small>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" name="inherit_to_children" class="form-check-input" id="inheritCheck" value="1">
                    <label class="form-check-label" for="inheritCheck">{{ __('Inherit to child records') }}</label>
                    <small class="d-block text-muted">Apply this template to child descriptions as well</small>
                </div>

                <hr>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('forms.assignments') }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Create Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
