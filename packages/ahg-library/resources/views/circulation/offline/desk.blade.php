@extends('theme::layouts.1col')
@section('title', __('Offline Circulation'))
@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center mb-3 flex-wrap gap-2">
        <a href="{{ route('library.circulation.index') }}" class="btn btn-outline-secondary btn-sm me-2" title="{{ __('Back') }}">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="mb-0"><i class="fas fa-plug me-2"></i>{{ __('Offline Circulation') }}</h1>
    </div>
    <p class="small text-secondary">
        {{ $branchName ? __('Working at :branch.', ['branch' => $branchName]) : __('Working across every branch.') }}
        <a href="{{ route('library.circulation.offline.conflicts') }}">{{ __('Conflicts report') }}</a>
    </p>
    @include('ahg-library::circulation.offline._desk', ['branchId' => $branchId, 'userId' => (int) auth()->id()])
</div>
<script src="{{ route('library.circulation.offline.script') }}" defer></script>
@endsection
