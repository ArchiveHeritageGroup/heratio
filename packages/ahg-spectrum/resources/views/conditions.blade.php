@extends('theme::layouts.1col')

@section('title', __('Condition Checks Report'))

@section('content')

<h1><i class="fas fa-heartbeat"></i> {{ __('Condition Checks Report') }}</h1>

<div class="row">
    <div class="col-md-3">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __('By Condition') }}</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    @foreach($byCondition ?? [] as $c)
                    <li>{{ ucfirst($c->overall_condition ?? 'Unknown') }}: <strong>{{ $c->count }}</strong></li>
                    @endforeach
                </ul>
            </div>
            <div class="card-footer">
                <a href="{{ route('ahgspectrum.dashboard') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>{{ __('Back') }}</a>
            </div>
        </div>
    </div>
    <div class="col-md-9">
        <div class="alert alert-info"><strong>{{ $summary['total'] ?? 0 }}</strong> {{ __('condition checks recorded') }}</div>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead class="table-dark">
                    <tr><th>{{ __('Object') }}</th><th>{{ __('Date') }}</th><th>{{ __('Condition') }}</th><th>{{ __('Checked By') }}</th><th>{{ __('Notes') }}</th></tr>
                </thead>
                <tbody>
                @foreach($conditions ?? [] as $c)
                <tr>
                    <td>@if($c->slug)<a href="/{{ $c->slug }}">{{ $c->title ?? 'Untitled' }}</a>@else - @endif</td>
                    <td>{{ $c->check_date ?? '-' }}</td>
                    <td>
                        @php
                        $cond = $c->overall_condition ?? 'unknown';
                        $colors = ['excellent' => 'success', 'good' => 'info', 'fair' => 'warning', 'poor' => 'danger'];
                        @endphp
                        <span class="badge bg-{{ $colors[$cond] ?? 'secondary' }}">{{ ucfirst($cond) }}</span>
                    </td>
                    <td>{{ $c->checked_by ?? $c->assessor ?? '-' }}</td>
                    <td><small>{{ substr($c->notes ?? $c->condition_notes ?? '', 0, 50) }}</small></td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
