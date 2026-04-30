{{--
  Form Assignments view - Heratio
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Licensed under AGPL v3 or later.
--}}
@extends('theme::layouts.1col')

@section('title', 'Form Assignments')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-link me-2"></i>Form Assignments</h1>
            <p class="text-muted">Assign form templates to repositories and description levels</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('forms.assignment.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> New Assignment
            </a>
            <a href="{{ route('forms.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            @if (empty($assignments) || (is_countable($assignments) && count($assignments) === 0))
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>No assignments found. Create one to specify which form templates are used where.</p>
                </div>
            @else
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Template') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Repository') }}</th>
                            <th>{{ __('Level') }}</th>
                            <th>{{ __('Priority') }}</th>
                            <th>{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($assignments as $assignment)
                            <tr>
                                <td>
                                    <strong>{{ $assignment->template_name ?? '' }}</strong>
                                </td>
                                <td>
                                    <span class="badge bg-info">{{ $assignment->form_type ?? '' }}</span>
                                </td>
                                <td>
                                    @if (!empty($assignment->repository_name))
                                        {{ $assignment->repository_name }}
                                    @else
                                        <span class="text-muted">All repositories</span>
                                    @endif
                                </td>
                                <td>
                                    @if (!empty($assignment->level_name))
                                        {{ $assignment->level_name }}
                                    @else
                                        <span class="text-muted">All levels</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-secondary">{{ $assignment->priority ?? 0 }}</span>
                                </td>
                                <td>
                                    <form method="POST" action="{{ url('/forms/assignment/delete') }}" style="display:inline" onsubmit="return confirm('Delete this assignment?')">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $assignment->id }}">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <h5><i class="fas fa-info-circle me-2"></i>How Assignments Work</h5>
            <p class="mb-2">
                When editing a record, the system selects the best matching form template based on:
            </p>
            <ol class="mb-0">
                <li>Repository (if specified)</li>
                <li>Level of Description (if specified)</li>
                <li>Priority (higher number = higher priority)</li>
            </ol>
        </div>
    </div>
</div>
@endsection
