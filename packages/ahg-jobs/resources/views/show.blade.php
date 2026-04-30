{{-- 
    Job Detail View
    
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

@section('title', 'Job #' . $job->id)

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h1>
                <a href="{{ route('jobs.browse') }}" class="text-decoration-none">
                    <i class="bi bi-arrow-left"></i>
                </a>
                Job #{{ $job->id }}
            </h1>
        </div>
        <div class="col-md-6 text-end">
            <span class="badge fs-6 
                @switch($job->status_id)
                    @case(183) bg-warning text-dark @break
                    @case(182) bg-primary @break
                    @case(184) bg-success @break
                    @case(185) bg-danger @break
                    @case(186) bg-secondary @break
                    @default bg-light text-dark
                @endswitch
            ">
                @switch($job->status_id)
                    @case(183) Pending @break
                    @case(182) Running @break
                    @case(184) Completed @break
                    @case(185) Failed @break
                    @case(186) Cancelled @break
                    @default Unknown
                @endswitch
            </span>
        </div>
    </div>

    {{-- Job Details --}}
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">{{ __('Job Details') }}</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">{{ __('Name:') }}</th>
                            <td>{{ $job->name ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('Type:') }}</th>
                            <td><span class="badge bg-secondary">{{ $job->type ?? 'N/A' }}</span></td>
                        </tr>
                        <tr>
                            <th>{{ __('Status:') }}</th>
                            <td>
                                @switch($job->status_id)
                                    @case(183) <span class="badge bg-warning text-dark">Pending</span> @break
                                    @case(182) <span class="badge bg-primary">Running</span> @break
                                    @case(184) <span class="badge bg-success">Completed</span> @break
                                    @case(185) <span class="badge bg-danger">Failed</span> @break
                                    @case(186) <span class="badge bg-secondary">Cancelled</span> @break
                                    @default <span class="badge bg-light text-dark">Unknown</span>
                                @endswitch
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">{{ __('Created:') }}</th>
                            <td>{{ $job->created_at ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('Started:') }}</th>
                            <td>{{ $job->started_at ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('Completed:') }}</th>
                            <td>{{ $job->completed_at ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('Duration:') }}</th>
                            <td>
                                @if(!empty($job->duration))
                                    {{ $job->duration }} seconds
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Error Details (if failed) --}}
    @if($job->status_id == 185 && !empty($job->error_message))
    <div class="card mb-4 border-danger">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Error Details</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-danger mb-0">
                <strong>Error Message:</strong><br>
                {{ $job->error_message }}
            </div>
            @if(!empty($job->stack_trace))
            <div class="mt-3">
                <strong>Stack Trace:</strong>
                <pre class="bg-dark text-light p-3 mt-2" style="max-height: 300px; overflow-y: auto;"><code>{{ $job->stack_trace }}</code></pre>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Output (if any) --}}
    @if(!empty($job->output))
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">{{ __('Output') }}</h5>
        </div>
        <div class="card-body">
            <pre class="bg-dark text-light p-3" style="max-height: 300px; overflow-y: auto;"><code>{{ is_string($job->output) ? $job->output : json_encode($job->output, JSON_PRETTY_PRINT) }}</code></pre>
        </div>
    </div>
    @endif

    {{-- Download (if available) --}}
    @if(!empty($job->download_path))
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">{{ __('Download') }}</h5>
        </div>
        <div class="card-body">
            <a href="{{ $job->download_path }}" class="btn btn-primary">
                <i class="bi bi-download"></i> Download Result
            </a>
        </div>
    </div>
    @endif

    {{-- Back Button --}}
    <div class="mt-4">
        <a href="{{ route('jobs.browse') }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Jobs
        </a>
    </div>
</div>
@endsection
