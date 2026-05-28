@extends('theme::layouts.1col')
@section('title', 'Patron Details')
@section('content')
@php
    $name = trim(($patron->first_name ?? '') . ' ' . ($patron->last_name ?? ''));
    $st = $patron->borrowing_status ?? 'active';
@endphp
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <div class="d-flex align-items-center">
            <a href="{{ route('library.patrons') }}" class="btn btn-outline-secondary btn-sm me-3"><i class="fas fa-arrow-left"></i></a>
            <h1 class="mb-0"><i class="fas fa-user me-2"></i>{{ $name !== '' ? $name : __('(unnamed patron)') }}</h1>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('library.checkout-form', ['patron' => $patron->id]) }}" class="btn btn-primary"><i class="fas fa-exchange-alt me-1"></i>{{ __('Check Out') }}</a>
            <a href="{{ route('library.patron-edit', $patron->id) }}" class="btn btn-outline-secondary"><i class="fas fa-pen me-1"></i>{{ __('Edit') }}</a>
            @if($st === 'active')
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#suspendModal"><i class="fas fa-ban me-1"></i>{{ __('Suspend') }}</button>
            @else
                <form method="POST" action="{{ route('library.patron-reactivate', $patron->id) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-success"><i class="fas fa-check me-1"></i>{{ __('Reactivate') }}</button>
                </form>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert"><i class="fas fa-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="row">
        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Details') }}</h5></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">{{ __('Type') }}</dt><dd class="col-sm-7">{{ ucfirst($patron->patron_type ?? '') }}</dd>
                        <dt class="col-sm-5">{{ __('Card #') }}</dt><dd class="col-sm-7"><code>{{ $patron->card_number ?? '' }}</code></dd>
                        <dt class="col-sm-5">{{ __('Email') }}</dt><dd class="col-sm-7">{{ $patron->email ?? '' }}</dd>
                        <dt class="col-sm-5">{{ __('Phone') }}</dt><dd class="col-sm-7">{{ $patron->phone ?? '' }}</dd>
                        <dt class="col-sm-5">{{ __('Membership Expiry') }}</dt><dd class="col-sm-7">{{ $patron->membership_expiry ?? '' }}</dd>
                        <dt class="col-sm-5">{{ __('Status') }}</dt>
                        <dd class="col-sm-7"><span class="badge bg-{{ $st === 'active' ? 'success' : ($st === 'suspended' ? 'danger' : 'secondary') }}">{{ ucfirst($st) }}</span></dd>
                        @if($st === 'suspended' && ($patron->suspension_reason ?? ''))
                            <dt class="col-sm-5">{{ __('Reason') }}</dt><dd class="col-sm-7">{{ $patron->suspension_reason }}</dd>
                            @if($patron->suspension_until ?? null)<dt class="col-sm-5">{{ __('Until') }}</dt><dd class="col-sm-7">{{ $patron->suspension_until }}</dd>@endif
                        @endif
                        <dt class="col-sm-5">{{ __('Fines Owed') }}</dt><dd class="col-sm-7">{{ number_format((float) ($patron->total_fines_owed ?? 0), 2) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Current Loans') }}</h5></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 align-middle">
                        <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Due') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                        <tbody>
                            @forelse($loans ?? [] as $l)
                                <tr>
                                    <td>{{ $l->title ?? ($l->barcode ?? '') }}@if($l->call_number ?? null)<br><small class="text-muted">{{ $l->call_number }}</small>@endif</td>
                                    <td>{{ $l->due_date ?? '' }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('library.checkout-renew', $l->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-secondary" title="{{ __('Renew') }}"><i class="fas fa-redo"></i></button></form>
                                        <form method="POST" action="{{ route('library.checkout-return', $l->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-success" title="{{ __('Return') }}"><i class="fas fa-undo"></i></button></form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted text-center py-2">{{ __('No active loans') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ __('Holds') }}</h5></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 align-middle">
                        <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Queue') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                        <tbody>
                            @forelse($holds ?? [] as $h)
                                <tr>
                                    <td>{{ $h->title ?? '' }}</td>
                                    <td>{{ $h->queue_position ?? '' }}</td>
                                    <td><span class="badge bg-info text-dark">{{ ucfirst($h->status ?? '') }}</span></td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('library.hold-cancel', $h->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-danger" title="{{ __('Cancel hold') }}"><i class="fas fa-times"></i></button></form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted text-center py-2">{{ __('No active holds') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@if($st === 'active')
<div class="modal fade" id="suspendModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('library.patron-suspend', $patron->id) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">{{ __('Suspend Patron') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="suspension_reason" class="form-label">{{ __('Reason') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                    <textarea name="suspension_reason" id="suspension_reason" rows="2" class="form-control" required></textarea>
                </div>
                <div class="mb-0">
                    <label for="suspension_until" class="form-label">{{ __('Suspend Until') }}</label>
                    <input type="date" name="suspension_until" id="suspension_until" class="form-control">
                    <div class="form-text">{{ __('Leave blank for an indefinite suspension.') }}</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-danger">{{ __('Suspend') }}</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
