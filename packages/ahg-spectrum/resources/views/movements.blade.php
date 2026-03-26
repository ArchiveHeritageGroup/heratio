@extends('theme::layouts.1col')

@section('title', __('Movements Report'))

@section('content')

<h1><i class="fas fa-truck"></i> {{ __('Movements Report') }}</h1>

<div class="row">
    <div class="col-md-3">
        <div class="card mb-4">
            <div class="card-footer">
                <a href="{{ route('ahgspectrum.dashboard') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>{{ __('Back') }}</a>
            </div>
        </div>
    </div>
    <div class="col-md-9">
        @if(empty($movements))
        <div class="alert alert-info">{{ __('No movements recorded.') }}</div>
        @else
        <div class="table-responsive">
            <table class="table table-striped">
                <thead class="table-dark"><tr><th>{{ __('Object') }}</th><th>{{ __('Date') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th>{{ __('Reason') }}</th></tr></thead>
                <tbody>
                @foreach($movements as $m)
                <tr>
                    <td>@if($m->slug)<a href="/{{ $m->slug }}">{{ $m->title ?? 'Untitled' }}</a>@else - @endif</td>
                    <td>{{ $m->movement_date ?? '-' }}</td>
                    <td>{{ $m->from_location ?? '-' }}</td>
                    <td>{{ $m->to_location ?? '-' }}</td>
                    <td>{{ $m->reason ?? '-' }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

@endsection
