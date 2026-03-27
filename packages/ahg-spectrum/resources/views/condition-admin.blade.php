@extends('theme::layouts.1col')

@section('title', __('Condition Management'))

@section('content')

@php
$stats = $stats ?? ['total_checks' => 0, 'critical' => 0, 'poor' => 0];
$recentEvents = $recentEvents ?? [];
@endphp

<h1 class="h3 mb-4"><i class="fas fa-heartbeat me-2"></i>{{ __('Condition Management') }}</h1>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h4>{{ $stats['total_checks'] ?? 0 }}</h4>
                <small>{{ __('Total Checks') }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-danger">
            <div class="card-body">
                <h4>{{ $stats['critical'] ?? 0 }}</h4>
                <small>{{ __('Critical') }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <h4>{{ $stats['poor'] ?? 0 }}</h4>
                <small>{{ __('Poor') }}</small>
            </div>
        </div>
    </div>
</div>

<div class="mb-3">
    <a href="{{ route('ahgspectrum.condition-risk') }}" class="btn btn-outline-danger">{{ __('Risk Assessment') }}</a>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">{{ __('Recent Condition Checks') }}</h5></div>
    <div class="card-body">
        @if(!empty($recentEvents))
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Object') }}</th>
                        <th>{{ __('Condition') }}</th>
                        <th>{{ __('Assessor') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($recentEvents as $e)
                    <tr>
                        <td>{{ $e->check_date ?? '' }}</td>
                        <td><a href="/{{ $e->slug ?? '' }}">{{ substr($e->title ?? 'Untitled', 0, 40) }}</a></td>
                        <td><span class="badge bg-{{ in_array($e->overall_condition ?? '', ['critical','poor']) ? 'danger' : 'success' }}">{{ ucfirst($e->overall_condition ?? '') }}</span></td>
                        <td>{{ $e->checked_by ?? '' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p class="text-muted text-center py-4">{{ __('No condition checks') }}</p>
        @endif
    </div>
</div>

@endsection
