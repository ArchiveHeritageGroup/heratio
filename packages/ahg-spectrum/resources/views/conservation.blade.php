@extends('theme::layouts.1col')

@section('title', __('Conservation Report'))

@section('content')

<h1><i class="fas fa-tools"></i> {{ __('Conservation Report') }}</h1>

<div class="row">
    <div class="col-md-3">
        <div class="card mb-4">
            <div class="card-footer">
                <a href="{{ route('ahgspectrum.dashboard') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>{{ __('Back') }}</a>
            </div>
        </div>
    </div>
    <div class="col-md-9">
        @if(empty($treatments))
        <div class="alert alert-info">{{ __('No conservation treatments recorded.') }}</div>
        @else
        <div class="table-responsive">
            <table class="table table-striped">
                <thead class="table-dark"><tr><th>{{ __('Object') }}</th><th>{{ __('Date') }}</th><th>{{ __('Treatment') }}</th><th>{{ __('Conservator') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                @foreach($treatments as $t)
                <tr>
                    <td>@if($t->slug)<a href="/{{ $t->slug }}">{{ $t->title ?? 'Untitled' }}</a>@else - @endif</td>
                    <td>{{ $t->treatment_date ?? $t->created_at ?? '-' }}</td>
                    <td>{{ $t->treatment_type ?? '-' }}</td>
                    <td>{{ $t->conservator ?? '-' }}</td>
                    <td><span class="badge bg-info">{{ $t->status ?? 'Complete' }}</span></td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

@endsection
