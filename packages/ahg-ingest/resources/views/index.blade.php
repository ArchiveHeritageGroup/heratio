{{--
  Ingestion Manager — Heratio
  Migrated from AtoM ahgIngestPlugin ingest/indexSuccess.php

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems

  This file is part of Heratio.
  Heratio is free software under the GNU AGPL v3.
--}}
@extends('theme::layouts.1col')
@section('title', 'Ingestion Manager')

@section('content')
<h1>Ingestion Manager</h1>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Admin</a></li>
        <li class="breadcrumb-item active">Ingestion Manager</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0">Manage batch imports of records and digital objects</p>
    <div>
        <div class="btn-group me-2">
            <a href="{{ route('ingest.template', 'archive') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-download me-1"></i>CSV Template
            </a>
        </div>
        <a href="{{ route('ingest.configure') }}" class="btn atom-btn-white">
            <i class="fas fa-plus me-1"></i>New Ingest
        </a>
    </div>
</div>

@if(empty($sessions) || count($sessions) === 0)
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No ingest sessions yet</h5>
            <p class="text-muted">Start a new ingest to batch-import records and digital objects</p>
            <a href="{{ route('ingest.configure') }}" class="btn atom-btn-white">
                <i class="fas fa-plus me-1"></i>New Ingest
            </a>
        </div>
    </div>
@else
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Sector</th>
                    <th>Status</th>
                    @if($isAdmin ?? false)
                        <th>User</th>
                    @endif
                    <th>Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sessions as $s)
                @php
                    $s = (object) $s;
                    $statusLabel = $s->status ?? 'unknown';
                    $statusClass = match($statusLabel) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        'cancelled' => 'warning',
                        'commit' => 'info',
                        default => 'primary',
                    };
                @endphp
                <tr>
                    <td>{{ $s->id }}</td>
                    <td><strong>{{ e($s->title ?: 'Untitled session') }}</strong></td>
                    <td><span class="badge bg-secondary">{{ ucfirst($s->sector ?? '') }}</span></td>
                    <td><span class="badge bg-{{ $statusClass }}">{{ ucfirst($statusLabel) }}</span></td>
                    @if($isAdmin ?? false)
                        <td>{{ e($s->user_name ?? '') }}</td>
                    @endif
                    <td>{{ isset($s->updated_at) ? date('Y-m-d H:i', strtotime($s->updated_at)) : '' }}</td>
                    <td>
                        @if(in_array($statusLabel, ['configure', 'upload', 'map', 'validate', 'preview']))
                            <a href="{{ route('ingest.' . $statusLabel, $s->id) }}" class="btn btn-sm btn-outline-primary" title="Resume">
                                <i class="fas fa-play"></i>
                            </a>
                            <a href="{{ route('ingest.configure', $s->id) }}?cancel=1" class="btn btn-sm btn-outline-danger" title="Cancel" onclick="return confirm('Cancel this ingest session?')">
                                <i class="fas fa-times"></i>
                            </a>
                        @endif
                        @if($statusLabel === 'completed')
                            <a href="{{ route('ingest.commit', $s->id) }}" class="btn btn-sm btn-outline-success" title="View Report">
                                <i class="fas fa-chart-bar"></i>
                            </a>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
