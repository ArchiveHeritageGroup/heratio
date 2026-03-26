@extends('theme::layouts.1col')

@section('title', __('Acquisitions Report'))

@section('content')

<h1><i class="fas fa-hand-holding"></i> {{ __('Acquisitions Report') }}</h1>

<div class="row">
    <div class="col-md-3">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __('By Method') }}</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                @foreach($byMethod ?? [] as $m)
                <li>{{ ucfirst($m->acquisition_method ?? 'Unknown') }}: <strong>{{ $m->count }}</strong></li>
                @endforeach
                </ul>
            </div>
            <div class="card-footer">
                <a href="{{ route('ahgspectrum.dashboard') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>{{ __('Back') }}</a>
            </div>
        </div>
    </div>
    <div class="col-md-9">
        @if(empty($acquisitions))
        <div class="alert alert-info">{{ __('No acquisitions recorded.') }}</div>
        @else
        <div class="table-responsive">
            <table class="table table-striped">
                <thead class="table-dark"><tr><th>{{ __('Object') }}</th><th>{{ __('Date') }}</th><th>{{ __('Method') }}</th><th>{{ __('Source') }}</th></tr></thead>
                <tbody>
                @foreach($acquisitions as $a)
                <tr>
                    <td>@if($a->slug)<a href="/{{ $a->slug }}">{{ $a->title ?? 'Untitled' }}</a>@else - @endif</td>
                    <td>{{ $a->acquisition_date ?? '-' }}</td>
                    <td>{{ $a->acquisition_method ?? '-' }}</td>
                    <td>{{ $a->source ?? $a->acquired_from ?? '-' }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

@endsection
