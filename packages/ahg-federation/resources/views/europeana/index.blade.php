{{--
  Europeana EDM publish - admin dashboard.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio (AGPL-3.0-or-later).
--}}
@extends('theme::layout')

@section('title', __('Europeana EDM Publish'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">
                <i class="bi bi-globe-europe-africa me-2"></i>{{ __('Europeana EDM Publish') }}
            </h4>
            <p class="text-muted mb-0">
                {{ __('Publish published records as a Europeana Data Model (EDM) RDF/XML bundle for the Europeana harvester.') }}
            </p>
        </div>
        <div>
            <a href="{{ route('federation.index') }}" class="atom-btn-white">
                <i class="bi bi-arrow-left me-1"></i>{{ __('Federation Dashboard') }}
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row mb-4 g-3">
        <div class="col-md-3 col-sm-6">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h2 class="mb-0" style="color: var(--ahg-primary);">
                        {{ $last->record_count ?? 0 }}
                    </h2>
                    <p class="text-muted mb-0">{{ __('Records in last export') }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h2 class="mb-0 text-info">
                        {{ $last && $last->bundle_size_bytes ? number_format($last->bundle_size_bytes / 1024, 1).' KiB' : '-' }}
                    </h2>
                    <p class="text-muted mb-0">{{ __('Bundle size') }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h2 class="mb-0 text-success">
                        {{ $last->finished_at ?? '-' }}
                    </h2>
                    <p class="text-muted mb-0">{{ __('Last finished at (UTC)') }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h2 class="mb-0 {{ ($last->status ?? '') === 'success' ? 'text-success' : 'text-warning' }}">
                        {{ $last->status ?? '-' }}
                    </h2>
                    <p class="text-muted mb-0">{{ __('Last run status') }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-play-circle me-1"></i>{{ __('Actions') }}</span>
        </div>
        <div class="card-body d-flex flex-wrap gap-2">
            <form action="{{ route('federation.europeana.run') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="atom-btn-white">
                    <i class="bi bi-arrow-clockwise me-1"></i>{{ __('Generate now') }}
                </button>
            </form>
            <a href="{{ route('federation.europeana.download') }}" class="atom-btn-white">
                <i class="bi bi-cloud-download me-1"></i>{{ __('Download bundle') }}
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <i class="bi bi-clock-history me-1"></i>{{ __('Recent runs') }}
        </div>
        <div class="card-body p-0">
            @if (empty($history))
                <p class="text-muted m-3 mb-3">{{ __('No exports have been run yet.') }}</p>
            @else
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Started (UTC)') }}</th>
                            <th>{{ __('Finished (UTC)') }}</th>
                            <th class="text-end">{{ __('Records') }}</th>
                            <th class="text-end">{{ __('Size (KiB)') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Bundle') }}</th>
                            <th>{{ __('Error') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($history as $row)
                            <tr>
                                <td>{{ $row->started_at }}</td>
                                <td>{{ $row->finished_at ?? '-' }}</td>
                                <td class="text-end">{{ $row->record_count }}</td>
                                <td class="text-end">{{ $row->bundle_size_bytes ? number_format($row->bundle_size_bytes / 1024, 1) : '-' }}</td>
                                <td>
                                    @if ($row->status === 'success')
                                        <span class="badge bg-success">{{ $row->status }}</span>
                                    @elseif ($row->status === 'error')
                                        <span class="badge bg-danger">{{ $row->status }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ $row->status }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if (! empty($row->bundle_path))
                                        <code class="small">{{ basename($row->bundle_path) }}</code>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-danger small">
                                    {{ $row->error ?? '' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
@endsection
