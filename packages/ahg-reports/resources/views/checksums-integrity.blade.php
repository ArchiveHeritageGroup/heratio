{{-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems — johan@plainsailingisystems.co.za
     This file is part of Heratio. Licensed under the GNU Affero General Public License v3+. --}}
@extends('theme::layouts.1col')

@section('title', 'Checksums & Integrity')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('reports.dashboard') }}">Reports</a></li>
                    <li class="breadcrumb-item active">Checksums &amp; Integrity</li>
                </ol>
            </nav>
            <h1><i class="fas fa-fingerprint me-2"></i>Checksums &amp; Integrity</h1>
            <p class="text-muted">TIFF-to-PDF merge jobs, checksum coverage and fixity verification</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('reports.dashboard') }}" class="btn atom-btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> {{ __('Dashboard') }}
            </a>
        </div>
    </div>

    @if (!$hasJobTable)
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            The <code>tiff_pdf_merge_job</code> table is not present in this install — the merge-job module has not been provisioned yet.
        </div>
    @endif

    <div class="card mb-4" id="tiff-pdf-merge-section">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-layer-group me-2"></i>TIFF to PDF Merge Jobs
            </h5>
            <div>
                <a href="{{ url('/tiff-pdf-merge/browse') }}" class="btn atom-btn-light btn-sm me-2">
                    <i class="fas fa-list me-1"></i>{{ __('View All') }}
                </a>
                <a href="{{ url('/tiff-pdf-merge') }}" class="btn atom-btn-light btn-sm">
                    <i class="fas fa-plus me-1"></i>{{ __('New Merge') }}
                </a>
            </div>
        </div>

        <div class="card-body">
            <div class="row g-3 mb-4">
                <div class="col-md-2 col-sm-4 col-6">
                    <div class="border rounded p-3 text-center h-100">
                        <div class="fs-3 fw-bold text-primary">{{ $stats['total_jobs'] }}</div>
                        <small class="text-muted">{{ __('Total Jobs') }}</small>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4 col-6">
                    <div class="border rounded p-3 text-center h-100">
                        <div class="fs-3 fw-bold text-warning">{{ $stats['pending'] }}</div>
                        <small class="text-muted">{{ __('Pending') }}</small>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4 col-6">
                    <div class="border rounded p-3 text-center h-100">
                        <div class="fs-3 fw-bold text-info">{{ $stats['queued'] + $stats['processing'] }}</div>
                        <small class="text-muted">{{ __('In Progress') }}</small>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4 col-6">
                    <div class="border rounded p-3 text-center h-100">
                        <div class="fs-3 fw-bold text-success">{{ $stats['completed'] }}</div>
                        <small class="text-muted">{{ __('Completed') }}</small>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4 col-6">
                    <div class="border rounded p-3 text-center h-100">
                        <div class="fs-3 fw-bold text-danger">{{ $stats['failed'] }}</div>
                        <small class="text-muted">{{ __('Failed') }}</small>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4 col-6">
                    <div class="border rounded p-3 text-center h-100">
                        <div class="fs-3 fw-bold text-secondary">{{ $stats['total_files'] }}</div>
                        <small class="text-muted">{{ __('Total Files') }}</small>
                    </div>
                </div>
            </div>

            @if ($recentJobs->count() > 0)
                <h6 class="mb-3"><i class="fas fa-history me-2"></i>Recent Jobs</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('ID') }}</th>
                                <th>{{ __('Job Name') }}</th>
                                <th>{{ __('User') }}</th>
                                <th class="text-center">{{ __('Files') }}</th>
                                <th class="text-center">{{ __('Status') }}</th>
                                <th>{{ __('Created') }}</th>
                                <th class="text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentJobs as $job)
                                @php
                                    $statusClass = match ($job->status) {
                                        'pending'    => 'warning',
                                        'queued'     => 'info',
                                        'processing' => 'primary',
                                        'completed'  => 'success',
                                        'failed'     => 'danger',
                                        default      => 'secondary',
                                    };
                                    $statusIcon = match ($job->status) {
                                        'pending'    => 'clock',
                                        'queued'     => 'hourglass-half',
                                        'processing' => 'spinner fa-spin',
                                        'completed'  => 'check-circle',
                                        'failed'     => 'times-circle',
                                        default      => 'question-circle',
                                    };
                                @endphp
                                <tr>
                                    <td><span class="badge bg-secondary">#{{ $job->id }}</span></td>
                                    <td>
                                        <a href="{{ url('/tiff-pdf-merge/view/' . $job->id) }}">
                                            <i class="fas fa-file-pdf text-danger me-1"></i>{{ $job->job_name ?? 'Untitled' }}
                                        </a>
                                    </td>
                                    <td><small class="text-muted">{{ $job->username ?? 'Unknown' }}</small></td>
                                    <td class="text-center"><span class="badge bg-secondary">{{ $job->total_files }}</span></td>
                                    <td class="text-center">
                                        <span class="badge bg-{{ $statusClass }}">
                                            <i class="fas fa-{{ $statusIcon }} me-1"></i>{{ ucfirst($job->status) }}
                                        </span>
                                    </td>
                                    <td><small>{{ date('M j, g:i A', strtotime($job->created_at)) }}</small></td>
                                    <td class="text-end">
                                        @if ($job->status === 'completed' && !empty($job->output_path))
                                            <a href="{{ url('/tiff-pdf-merge/download/' . $job->id) }}" class="btn btn-sm btn-success" title="{{ __('Download PDF') }}">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        @endif
                                        <a href="{{ url('/tiff-pdf-merge/view/' . $job->id) }}" class="btn btn-sm atom-btn-outline-secondary" title="{{ __('View Details') }}">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 text-center">
                    <a href="{{ url('/tiff-pdf-merge/browse') }}" class="btn atom-btn-outline-primary">
                        <i class="fas fa-list me-1"></i>{{ __('View All Jobs') }}
                    </a>
                </div>
            @else
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p class="mb-3">No PDF merge jobs yet.</p>
                    <a href="{{ url('/tiff-pdf-merge') }}" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>{{ __('Create Your First PDF') }}
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>

@if ($hasProcessing)
    <script>
        setTimeout(function () { location.reload(); }, 5000);
    </script>
@endif
@endsection
