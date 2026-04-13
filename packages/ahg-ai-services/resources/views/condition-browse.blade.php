{{--
  AI Condition Browse - Heratio clone of PSIS ahgAiCondition browseSuccess.php
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3 or later.
--}}
@extends('theme::layouts.2col')

@section('title', __('AI Condition Assessments'))
@section('body-class', 'ai ai-condition browse')

@section('sidebar')
<div class="sidebar-content">
    <div class="card mb-3">
        <div class="card-header bg-success text-white py-2">
            <h6 class="mb-0"><i class="fas fa-robot me-1"></i>{{ __('AI Condition') }}</h6>
        </div>
        <div class="card-body py-2">
            <a href="{{ route('admin.ai.condition.assess') }}" class="btn btn-success btn-sm w-100 mb-2">
                <i class="fas fa-camera me-1"></i>{{ __('New Assessment') }}
            </a>
            <a href="{{ route('admin.ai.condition.bulk') }}" class="btn btn-outline-primary btn-sm w-100 mb-2">
                <i class="fas fa-layer-group me-1"></i>{{ __('Bulk Scan') }}
            </a>
            <a href="{{ route('admin.ai.index') }}" class="btn btn-outline-secondary btn-sm w-100">
                <i class="fas fa-cog me-1"></i>{{ __('Settings') }}
            </a>
        </div>
    </div>
    {{-- Stats --}}
    <div class="card mb-3">
        <div class="card-header py-2"><h6 class="mb-0">{{ __('Statistics') }}</h6></div>
        <div class="card-body py-2 small">
            <div class="d-flex justify-content-between mb-1">
                <span>{{ __('Total Assessments') }}</span>
                <strong>{{ $stats['total'] ?? 0 }}</strong>
            </div>
            <div class="d-flex justify-content-between mb-1">
                <span>{{ __('Confirmed') }}</span>
                <strong class="text-success">{{ $stats['confirmed'] ?? 0 }}</strong>
            </div>
            <div class="d-flex justify-content-between mb-1">
                <span>{{ __('Pending Review') }}</span>
                <strong class="text-warning">{{ $stats['pending'] ?? 0 }}</strong>
            </div>
            <div class="d-flex justify-content-between">
                <span>{{ __('Avg Score') }}</span>
                <strong>{{ $stats['avg_score'] ?? '--' }}</strong>
            </div>
        </div>
    </div>
</div>
@endsection

@section('title-block')
<h1 class="h3 mb-0"><i class="fas fa-robot me-2"></i>{{ __('AI Condition Assessments') }}</h1>
<p class="text-muted small mb-3">{{ __('Browse AI-powered damage detection results') }}</p>
@endsection

@section('content')
{{-- Filters --}}
<div class="d-flex gap-2 mb-3 flex-wrap">
    <form method="get" action="{{ route('admin.ai.condition.browse') }}" class="d-flex gap-2 flex-wrap">
        <input type="text" name="q" class="form-control form-control-sm" style="width:200px"
               placeholder="{{ __('Search...') }}" value="{{ $filters['search'] ?? '' }}">
        <select name="grade" class="form-select form-select-sm" style="width:150px">
            <option value="">{{ __('All grades') }}</option>
            @foreach(['excellent','good','fair','poor','critical'] as $g)
            <option value="{{ $g }}" @selected(($filters['condition_grade'] ?? '') === $g)>{{ ucfirst($g) }}</option>
            @endforeach
        </select>
        <select name="confirmed" class="form-select form-select-sm" style="width:150px">
            <option value="">{{ __('All status') }}</option>
            <option value="1" @selected(($filters['is_confirmed'] ?? '') === '1')>{{ __('Confirmed') }}</option>
            <option value="0" @selected(($filters['is_confirmed'] ?? '') === '0')>{{ __('Pending') }}</option>
        </select>
        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i></button>
    </form>
</div>

@if(empty($assessments))
<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>
    {{ __('No assessments found.') }}
    <a href="{{ route('admin.ai.condition.assess') }}" class="btn btn-sm btn-success ms-2">
        {{ __('Run first assessment') }}
    </a>
</div>
@else
<div class="table-responsive">
    <table class="table table-hover table-sm align-middle">
        <thead class="table-light">
            <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Object') }}</th>
                <th class="text-center">{{ __('Score') }}</th>
                <th class="text-center">{{ __('Grade') }}</th>
                <th class="text-center">{{ __('Damages') }}</th>
                <th class="text-center">{{ __('Source') }}</th>
                <th class="text-center">{{ __('Status') }}</th>
                <th class="text-end">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($assessments as $a)
            <tr>
                <td class="small">{{ $a->created_at ? date('Y-m-d H:i', strtotime($a->created_at)) : '' }}</td>
                <td>
                    @if(!empty($a->object_title))
                    <a href="{{ route('admin.ai.condition.view', ['id' => $a->id]) }}">{{ \Illuminate\Support\Str::limit($a->object_title, 50) }}</a>
                    @else
                    <span class="text-muted">{{ __('Standalone') }}</span>
                    @endif
                </td>
                <td class="text-center">
                    @php
                        $score = (float) ($a->overall_score ?? 0);
                        $scoreClass = $score >= 80 ? 'text-success' : ($score >= 60 ? 'text-warning' : 'text-danger');
                    @endphp
                    <span class="fw-bold {{ $scoreClass }}">{{ number_format($score, 1) }}</span>
                </td>
                <td class="text-center">
                    @php
                        $grade = (string) ($a->condition_grade ?? '');
                        $gradeMap = [
                            'excellent' => 'bg-success',
                            'good'      => 'bg-primary',
                            'fair'      => 'bg-info text-dark',
                            'poor'      => 'bg-warning text-dark',
                            'critical'  => 'bg-danger',
                        ];
                        $gradeClass = $gradeMap[$grade] ?? 'bg-secondary';
                    @endphp
                    @if($grade !== '')
                    <span class="badge {{ $gradeClass }}">{{ ucfirst($grade) }}</span>
                    @else
                    <span class="text-muted">--</span>
                    @endif
                </td>
                <td class="text-center"><span class="badge bg-secondary">{{ $a->damage_count ?? 0 }}</span></td>
                <td class="text-center"><span class="badge bg-light text-dark">{{ ucfirst((string) ($a->source ?? '')) }}</span></td>
                <td class="text-center">
                    @if($a->is_confirmed)
                    <span class="badge bg-success"><i class="fas fa-check me-1"></i>{{ __('Confirmed') }}</span>
                    @else
                    <span class="badge bg-warning text-dark">{{ __('Pending') }}</span>
                    @endif
                </td>
                <td class="text-end">
                    <a href="{{ route('admin.ai.condition.view', ['id' => $a->id]) }}" class="btn btn-sm btn-outline-primary" title="{{ __('View') }}">
                        <i class="fas fa-eye"></i>
                    </a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if(($pages ?? 1) > 1)
<nav>
    <ul class="pagination pagination-sm justify-content-center">
        @for($p = 1; $p <= $pages; $p++)
        <li class="page-item {{ $p === $page ? 'active' : '' }}">
            <a class="page-link" href="{{ route('admin.ai.condition.browse', array_merge(request()->query(), ['page' => $p])) }}">{{ $p }}</a>
        </li>
        @endfor
    </ul>
</nav>
@endif
@endif
@endsection
