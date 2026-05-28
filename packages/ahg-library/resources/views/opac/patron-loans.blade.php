@extends('theme::layouts.1col')
@section('title', 'My Loans')
@section('content')
<div class="container py-4">

    <div class="d-flex align-items-center mb-3">
        <a href="{{ route('opac.patron.account') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="mb-0"><i class="fas fa-book me-2"></i>{{ __('My Loans') }}</h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($loans->isNotEmpty())
        {{-- Renew All --}}
        <form method="POST" action="{{ route('opac.patron.renew-all') }}" class="mb-3">
            @csrf
            <button type="submit" class="btn btn-sm atom-btn-white">
                <i class="fas fa-redo me-1"></i>{{ __('Renew All') }}
            </button>
        </form>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>{{ __('Title') }}</th>
                                <th>{{ __('Due Date') }}</th>
                                <th class="text-center">{{ __('Days Left') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th class="text-end">{{ __('Renew') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loans as $l)
                                @php
                                    $dueTs = strToTime($l->due_date ?? '');
                                    $daysLeft = $dueTs !== false ? floor(($dueTs - time()) / 86400) : null;
                                    $isOverdue = $dueTs !== false && $dueTs < time();
                                @endphp
                                <tr class="{{ $isOverdue ? 'table-danger' : '' }}">
                                    <td>
                                        {{ $l->title ?? __('(unknown item)') }}
                                        @if($l->call_number ?? null)
                                            <br><small class="text-muted">{{ $l->call_number }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $l->due_date ?? '—' }}
                                    </td>
                                    <td class="text-center">
                                        @if($daysLeft !== null)
                                            <span class="{{ $daysLeft < 0 ? 'text-danger fw-bold' : ($daysLeft <= 3 ? 'text-warning fw-bold' : '') }}">
                                                {{ $daysLeft }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($isOverdue)
                                            <span class="badge bg-danger">{{ __('Overdue') }}</span>
                                        @elseif($daysLeft !== null && $daysLeft <= 3)
                                            <span class="badge bg-warning text-dark">{{ __('Due Soon') }}</span>
                                        @else
                                            <span class="badge bg-success">{{ __('On Time') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('opac.patron.renew-one', $l->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-redo me-1"></i>{{ __('Renew') }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>{{ __('You have no items currently checked out.') }}
        </div>
    @endif

</div>
@endsection
