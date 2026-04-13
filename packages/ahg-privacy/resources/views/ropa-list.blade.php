{{--
    Record of Processing Activities (ROPA) — list page
    Cloned from PSIS ahgPrivacyPlugin/modules/privacyAdmin/templates/ropaListSuccess.blade.php
    Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems — AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')

@section('title', 'Record of Processing Activities (ROPA)')

@section('content')
@php $rawBases = $lawfulBases ?? []; @endphp

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('ahgprivacy.index') }}" class="btn btn-outline-secondary btn-sm me-2">
                <i class="fas fa-arrow-left"></i>
            </a>
            <span class="h2"><i class="fas fa-clipboard-list me-2"></i>{{ __('Record of Processing Activities (ROPA)') }}</span>
        </div>
        <a href="{{ route('ahgprivacy.ropa-add') }}" class="btn btn-success">
            <i class="fas fa-plus me-1"></i>{{ __('Add Activity') }}
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Purpose') }}</th>
                        <th>{{ __('Lawful Basis') }}</th>
                        <th>{{ __('DPIA') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Next Review') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @if($activities->isEmpty())
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No processing activities recorded') }}</td></tr>
                    @else
                    @foreach($activities as $activity)
                    @php
                    $statusClasses = ['draft' => 'secondary', 'pending_review' => 'warning', 'approved' => 'success', 'archived' => 'dark'];
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ route('ahgprivacy.ropa-view', ['id' => $activity->id]) }}">
                                <strong>{{ $activity->name }}</strong>
                            </a>
                        </td>
                        <td>{{ mb_substr($activity->purpose ?? '', 0, 50) }}...</td>
                        <td>{{ isset($rawBases[$activity->lawful_basis]) ? $rawBases[$activity->lawful_basis]['label'] : $activity->lawful_basis }}</td>
                        <td>
                            @if($activity->dpia_required)
                                @if($activity->dpia_completed)
                                <span class="text-success"><i class="fas fa-check-circle"></i> {{ __('Complete') }}</span>
                                @else
                                <span class="text-warning"><i class="fas fa-exclamation-circle"></i> {{ __('Required') }}</span>
                                @endif
                            @else
                            <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $statusClasses[$activity->status] ?? 'secondary' }}">
                                {{ ucfirst(str_replace('_', ' ', $activity->status)) }}
                            </span>
                        </td>
                        <td>{{ $activity->next_review_date ?? '-' }}</td>
                        <td>
                            <a href="{{ route('ahgprivacy.ropa-edit', ['id' => $activity->id]) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-edit"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
