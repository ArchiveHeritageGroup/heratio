{{-- 
    Jobs Browse View
    
    Copyright (C) 2026 Johan Pieterse
    Plain Sailing Information Systems
    Email: johan@plainsailingisystems.co.za
    
    This file is part of Heratio.
    
    Heratio is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.
    
    Heratio is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
    GNU Affero General Public License for more details.
    
    You should have received a copy of the GNU Affero General Public License
    along with Heratio. If not, see <https://www.gnu.org/licenses/>.
--}}

@extends('layouts.app')

@section('title', 'Jobs')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h1><i class="bi bi-list-task"></i> Jobs</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="{{ route('jobs.export-csv') }}" class="btn btn-outline-secondary">
                <i class="bi bi-download"></i> {{ __('Export CSV') }}
            </a>
        </div>
    </div>

    {{-- Statistics Cards --}}
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h3 class="mb-0">{{ $stats['total'] ?? 0 }}</h3>
                    <small>{{ __('Total') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h3 class="mb-0">{{ $stats['pending'] ?? 0 }}</h3>
                    <small>{{ __('Pending') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h3 class="mb-0">{{ $stats['running'] ?? 0 }}</h3>
                    <small>{{ __('Running') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h3 class="mb-0">{{ $stats['completed'] ?? 0 }}</h3>
                    <small>{{ __('Completed') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h3 class="mb-0">{{ $stats['failed'] ?? 0 }}</h3>
                    <small>{{ __('Failed') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-secondary text-white">
                <div class="card-body text-center">
                    <h3 class="mb-0">{{ $stats['cancelled'] ?? 0 }}</h3>
                    <small>{{ __('Cancelled') }}</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">{{ __('All Status') }}</option>
                        <option value="pending" {{ ($filters['status'] ?? '') == 'pending' ? 'selected' : '' }}>{{ __('Pending') }}</option>
                        <option value="running" {{ ($filters['status'] ?? '') == 'running' ? 'selected' : '' }}>{{ __('Running') }}</option>
                        <option value="completed" {{ ($filters['status'] ?? '') == 'completed' ? 'selected' : '' }}>{{ __('Completed') }}</option>
                        <option value="failed" {{ ($filters['status'] ?? '') == 'failed' ? 'selected' : '' }}>{{ __('Failed') }}</option>
                        <option value="cancelled" {{ ($filters['status'] ?? '') == 'cancelled' ? 'selected' : '' }}>{{ __('Cancelled') }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="sort" class="form-select">
                        <option value="date" {{ ($filters['sort'] ?? 'date') == 'date' ? 'selected' : '' }}>{{ __('Sort by Date') }}</option>
                        <option value="name" {{ ($filters['sort'] ?? '') == 'name' ? 'selected' : '' }}>{{ __('Sort by Name') }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">{{ __('Filter') }}</button>
                    <a href="{{ route('jobs.browse') }}" class="btn btn-outline-secondary">Clear</a>
                </div>
                <div class="col-md-3 text-end">
                    <form action="{{ route('jobs.clear-inactive') }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Clear all inactive jobs older than 30 days?')">
                            <i class="bi bi-trash"></i> {{ __('Clear Old Jobs') }}
                        </button>
                    </form>
                </div>
            </form>
        </div>
    </div>

    {{-- Jobs Table --}}
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('ID') }}</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Created') }}</th>
                            <th>{{ __('Completed') }}</th>
                            <th>{{ __('Duration') }}</th>
                            <th>{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pager->items() as $job)
                        <tr>
                            <td>{{ $job['id'] }}</td>
                            <td>{{ $job['name'] ?? 'N/A' }}</td>
                            <td><span class="badge bg-secondary">{{ $job['type'] ?? 'N/A' }}</span></td>
                            <td>
                                @switch($job['status_id'] ?? null)
                                    @case(183)
                                        <span class="badge bg-warning text-dark">{{ __('Pending') }}</span>
                                        @break
                                    @case(182)
                                        <span class="badge bg-primary">{{ __('Running') }}</span>
                                        @break
                                    @case(184)
                                        <span class="badge bg-success">{{ __('Completed') }}</span>
                                        @break
                                    @case(185)
                                        <span class="badge bg-danger">{{ __('Failed') }}</span>
                                        @break
                                    @case(186)
                                        <span class="badge bg-secondary">{{ __('Cancelled') }}</span>
                                        @break
                                    @default
                                        <span class="badge bg-light text-dark">{{ __('Unknown') }}</span>
                                @endswitch
                            </td>
                            <td>{{ $job['created_at'] ?? '-' }}</td>
                            <td>{{ $job['completed_at'] ?? '-' }}</td>
                            <td>
                                @if(!empty($job['duration']))
                                    {{ $job['duration'] }}s
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('jobs.show', $job['id']) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center">No jobs found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination Info --}}
            <div class="mt-3">
                <small class="text-muted">
                    Showing {{ count($pager->items()) }} of {{ $total }} jobs
                </small>
            </div>
        </div>
    </div>
</div>
@endsection
