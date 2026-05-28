@extends('theme::layouts.1col')
@section('title', 'Patron Account')
@section('content')
@php
    $st = $patron->borrowing_status ?? 'active';
    $stClass = $st === 'active' ? 'success' : ($st === 'suspended' ? 'danger' : 'secondary');
    $activeTab = old('active_tab', 'loans');
@endphp
<div class="container py-4">

    {{-- Back bar --}}
    <div class="d-flex align-items-center mb-3">
        <a href="{{ route('library.circulation.index') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="mb-0">
            <i class="fas fa-user me-2"></i>{{ __('Patron Account') }}
        </h1>
    </div>

    {{-- Alerts --}}
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

    {{-- Patron header --}}
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <strong>{{ trim(($patron->first_name ?? '') . ' ' . ($patron->last_name ?? '')) ?: __('(unnamed)') }}</strong>
                &nbsp;<code>{{ $patron->card_number ?? '' }}</code>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <span class="badge bg-{{ $stClass }}">{{ ucfirst($st) }}</span>
                <span class="badge bg-secondary">{{ ucfirst($patron->patron_type ?? 'adult') }}</span>
                @if($patron->membership_expiry ?? null)
                    <span class="badge bg-{{ strToTime($patron->membership_expiry) < time() ? 'danger' : 'info' }}">
                        {{ __('Expires') }}: {{ $patron->membership_expiry }}
                    </span>
                @endif
            </div>
        </div>
        <div class="card-body p-0">
            <dl class="row mb-0 p-3">
                <div class="col-md-4">
                    <dt>{{ __('Email') }}</dt><dd>{{ $patron->email ?? '—' }}</dd>
                    <dt>{{ __('Phone') }}</dt><dd>{{ $patron->phone ?? '—' }}</dd>
                </div>
                <div class="col-md-4">
                    <dt>{{ __('Items Out') }}</dt><dd>× {{ $loans->count() }}</dd>
                    <dt>{{ __('Holds') }}</dt><dd>× {{ $holds->count() }}</dd>
                </div>
                <div class="col-md-4">
                    <dt>{{ __('Fines Owed') }}</dt>
                    <dd class="{{ (float) ($patron->total_fines_owed ?? 0) > 0 ? 'text-danger fw-bold' : '' }}">
                        {{ number_format((float) ($patron->total_fines_owed ?? 0), 2) }}
                    </dd>
                    <dt>{{ __('Total Checkouts') }}</dt><dd>× {{ $patron->total_checkouts ?? 0 }}</dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link{{ $activeTab === 'loans' ? ' active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-loans" type="button" role="tab">
                {{ __('Current Checkouts') }} <span class="badge bg-dark ms-1">{{ $loans->count() }}</span></button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link{{ $activeTab === 'holds' ? ' active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-holds" type="button" role="tab">
                {{ __('Holds') }} <span class="badge bg-info ms-1">{{ $holds->count() }}</span></button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link{{ $activeTab === 'fines' ? ' active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-fines" type="button" role="tab">
                {{ __('Fine History') }} <span class="badge bg-warning text-dark ms-1">{{ $fines->count() }}</span></button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link{{ $activeTab === 'history' ? ' active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-history" type="button" role="tab">
                {{ __('Loan History') }} <span class="badge bg-secondary ms-1">{{ $pastLoans->count() }}</span></button>
        </li>
    </ul>

    <div class="tab-content">

        {{-- Tab: Current Checkouts --}}
        <div class="tab-pane{{ $activeTab === 'loans' ? ' show active' : '' }}" id="tab-loans" role="tabpanel">
            @if($loans->isNotEmpty())
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>{{ __('Title / Barcode') }}</th>
                                        <th>{{ __('Call Number') }}</th>
                                        <th>{{ __('Checked Out') }}</th>
                                        <th>{{ __('Due Date') }}</th>
                                        <th class="text-center">{{ __('Days Left') }}</th>
                                        <th class="text-end">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($loans as $l)
                                        @php
                                            $dueTs = strToTime($l->due_date ?? '');
                                            $daysLeft = $dueTs !== false ? floor(($dueTs - time{}) / 86400) : null;
                                            $isOverdue = $dueTs !== false && $dueTs < time();
                                        @endphp
                                        <tr class="{{ $isOverdue ? 'table-danger' : '' }}">
                                            <td>
                                                {{ $l->title ?? ($l->barcode ?? __('(unknown)')) }}
                                                @if($l->barcode ?? null)
                                                    <br><small class="text-muted">{{ $l->barcode }}</small>
                                                @endif
                                            </td>
                                            <td>{{ $l->call_number ?? '—' }}</td>
                                            <td>{{ $l->checkout_date ?? '—' }}</td>
                                            <td>
                                                {{ $l->due_date ?? '—' }}
                                                @if($isOverdue)
                                                    <span class="badge bg-danger ms-1">{{ __('OVERDUE') }}</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if($daysLeft !== null)
                                                    <span class="{{ $daysLeft < 0 ? 'text-danger fw-bold' : ($daysLeft <= 3 ? 'text-warning fw-bold' : '') }}">
                                                        {{ $daysLeft }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <form method="POST" action="{{ route('library.circulation.renew') }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="checkout_id" value="{{ $l->id }}">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ __('Renew') }}">
                                                        <i class="fas fa-redo"></i>
                                                    </button>
                                                </form>
                                                <a href="{{ route('library.circulation.return', $l->id) }}"
                                                   class="btn btn-sm btn-outline-success" title="{{ __('Return') }}">
                                                    <i class="fas fa-undo"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @else
                <p class="text-muted">{{ __('No active checkouts.') }}</p>
            @endif
        </div>

        {{-- Tab: Holds --}}
        <div class="tab-pane{{ $activeTab === 'holds' ? ' show active' : '' }}" id="tab-holds" role="tabpanel">
            @if($holds->isNotEmpty())
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>{{ __('Title') }}</th>
                                        <th>{{ __('Queue Position') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Hold Date') }}</th>
                                        <th>{{ __('Expires') }}</th>
                                        <th class="text-end">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($holds as $h)
                                        <tr class="{{ ($h->status ?? '') === 'ready' ? 'table-success' : '' }}">
                                            <td>{{ $h->title ?? __('(unknown)') }}</td>
                                            <td class="text-center">{{ $h->queue_position ?? '—' }}</td>
                                            <td>
                                                <span class="badge bg-{{ ($h->status ?? '') === 'ready' ? 'success' : 'info' }}">
                                                    {{ ucfirst($h->status ?? 'pending') }}
                                                </span>
                                            </td>
                                            <td>{{ $h->hold_date ?? '—' }}</td>
                                            <td>{{ $h->expiry_date ?? '—' }}</td>
                                            <td class="text-end">
                                                <form method="POST" action="{{ route('library.hold-cancel', $h->id) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Cancel') }}">
                                                        <i class="fas fa-times"></i>
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
                <p class="text-muted">{{ __('No active holds.') }}</p>
            @endif
        </div>

        {{-- Tab: Fine History --}}
        <div class="tab-pane{{ $activeTab === 'fines' ? ' show active' : '' }}" id="tab-fines" role="tabpanel">
            @if($fines->isNotEmpty())
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>{{ __('Date') }}</th>
                                        <th>{{ __('Type') }}</th>
                                        <th>{{ __('Description') }}</th>
                                        <th class="text-end">{{ __('Amount') }}</th>
                                        <th class="text-end">{{ __('Paid') }}</th>
                                        <th>{{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($fines as $f)
                                        <tr>
                                            <td>{{ $f->fine_date ?? '' }}</td>
                                            <td>{{ ucfirst($f->fine_type ?? '') }}</td>
                                            <td>{{ \AhgCore\Support\EncryptionService::decrypt($f->description ?? '') }}</td>
                                            <td class="text-end">{{ number_format((float) ($f->amount ?? 0), 2) }}</td>
                                            <td class="text-end">{{ number_format((float) ($f->paid_amount ?? 0), 2) }}</td>
                                            <td>
                                                <span class="badge bg-{{ ($f->status ?? '') === 'paid' ? 'success' : 'warning text-dark' }}">
                                                    {{ ucfirst($f->status ?? 'outstanding') }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @else
                <p class="text-muted">{{ __('No fine records.') }}</p>
            @endif
        </div>

        {{-- Tab: Loan History --}}
        <div class="tab-pane{{ $activeTab === 'history' ? ' show active' : '' }}" id="tab-history" role="tabpanel">
            @if($pastLoans->isNotEmpty())
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>{{ __('Title') }}</th>
                                        <th>{{ __('Barcode') }}</th>
                                        <th>{{ __('Checked Out') }}</th>
                                        <th>{{ __('Due Date') }}</th>
                                        <th>{{ __('Returned') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pastLoans as $l)
                                        <tr>
                                            <td>{{ $l->title ?? __('(unknown)') }}</td>
                                            <td><code>{{ $l->barcode ?? '' }}</code></td>
                                            <td>{{ $l->checkout_date ?? '' }}</td>
                                            <td>{{ $l->due_date ?? '' }}</td>
                                            <td>{{ $l->return_date ?? '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @else
                <p class="text-muted">{{ __('No returned items on record.') }}</p>
            @endif
        </div>

    </div>
</div>
@endsection
