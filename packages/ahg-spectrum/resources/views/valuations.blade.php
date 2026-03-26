@extends('theme::layouts.1col')

@section('title', __('Valuations Report'))

@section('content')

<h1><i class="fas fa-dollar-sign"></i> {{ __('Valuations Report') }}</h1>

<div class="row">
    <div class="col-md-3">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Summary') }}</h5>
            </div>
            <div class="card-body">
                <p><strong>{{ $summary['total'] ?? 0 }}</strong> {{ __('valuations') }}</p>
                <p><strong>R {{ number_format($summary['totalValue'] ?? 0, 2) }}</strong><br><small>{{ __('Total Value') }}</small></p>
            </div>
            <div class="card-footer">
                <a href="{{ route('ahgspectrum.dashboard') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>{{ __('Back') }}</a>
            </div>
        </div>
    </div>
    <div class="col-md-9">
        @if(empty($valuations))
        <div class="alert alert-info">{{ __('No valuations recorded.') }}</div>
        @else
        <div class="table-responsive">
            <table class="table table-striped">
                <thead class="table-dark">
                    <tr><th>{{ __('Object') }}</th><th>{{ __('Date') }}</th><th>{{ __('Value') }}</th><th>{{ __('Type') }}</th><th>{{ __('Valuator') }}</th></tr>
                </thead>
                <tbody>
                @foreach($valuations as $v)
                <tr>
                    <td>@if($v->slug)<a href="/{{ $v->slug }}">{{ $v->title ?? 'Untitled' }}</a>@else - @endif</td>
                    <td>{{ $v->valuation_date ?? '-' }}</td>
                    <td><strong>R {{ number_format($v->valuation_amount ?? 0, 2) }}</strong></td>
                    <td>{{ $v->valuation_type ?? '-' }}</td>
                    <td>{{ $v->valuer_name ?? $v->valued_by ?? '-' }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

@endsection
