@extends('theme::layouts.1col')
@section('title', 'Circulation Desk')
@section('content')
<div class="container py-4">

    {{-- Header + datetime --}}
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="d-flex align-items-center">
            <a href="{{ route('library.index') }}" class="btn btn-outline-secondary btn-sm me-3" title="{{ __('Back') }}">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="mb-0"><i class="fas fa-desktop me-2"></i>{{ __('Circulation Desk') }}</h1>
        </div>
        <div class="text-end">
            <div id="clock" class="fw-bold text-secondary small"></div>
        </div>
    </div>

    {{-- Quick-action links --}}
    <div class="d-flex gap-2 flex-wrap mb-3">
        <a href="{{ route('library.circulation.index') . '?new_patron=1' }}" class="btn btn-sm atom-btn-white">
            <i class="fas fa-user-plus me-1"></i>{{ __('New Patron') }}
        </a>
        <a href="{{ route('library.overdue') }}" class="btn btn-sm atom-btn-white">
            <i class="fas fa-clock me-1"></i>{{ __('View Overdues') }}
        </a>
        <a href="{{ route('library.loan-rules') }}" class="btn btn-sm atom-btn-white">
            <i class="fas fa-cog me-1"></i>{{ __('Loan Rules') }}
        </a>
        <a href="{{ route('library.patrons') }}" class="btn btn-sm atom-btn-white">
            <i class="fas fa-users me-1"></i>{{ __('Manage Patrons') }}
        </a>
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
    @if(isset($scanError) && $scanError)
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-search me-2"></i>{{ $scanError }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Scan box --}}
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-barcode me-2"></i>{{ __('Scan or Enter Barcode') }}</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('library.circulation.scan') }}" autocomplete="off" id="scanForm">
                @csrf
                <div class="input-group input-group-lg">
                    <input
                        type="text"
                        name="barcode"
                        id="barcodeInput"
                        class="form-control"
                        placeholder="{{ __('Barcode or card number…') }}"
                        autofocus
                        required
                        minlength="3"
                    >
                    <button type="submit" class="btn btn-dark">
                        <i class="fas fa-search me-1"></i>{{ __('Look Up') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Scan result --}}
    @if(isset($scanResult) && $scanResult)
        @if($scanResult->type === 'copy')
            <div class="card mb-4 border-{{ $scanResult->copy_status === 'available' ? 'success' : ($scanResult->copy_status === 'checked_out' ? 'warning' : 'info') }}">
                <div class="card-header bg-{{ $scanResult->copy_status === 'available' ? 'success' : ($scanResult->copy_status === 'checked_out' ? 'warning' : 'info') }} text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-book me-2"></i>{{ __('Item Details') }}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">{{ __('Title') }}</dt>
                                <dd class="col-sm-8"><strong>{{ $scanResult->title ?: __('(untitled)') }}</strong></dd>
                                <dt class="col-sm-4">{{ __('Call Number') }}</dt>
                                <dd class="col-sm-8">{{ $scanResult->call_number ?: '—' }}</dd>
                                <dt class="col-sm-4">{{ __('Shelf Location') }}</dt>
                                <dd class="col-sm-8">{{ $scanResult->shelf_location ?: '—' }}</dd>
                                <dt class="col-sm-4">{{ __('Barcode') }}</dt>
                                <dd class="col-sm-8"><code>{{ $scanResult->barcode }}</code></dd>
                                <dt class="col-sm-4">{{ __('Status') }}</dt>
                                <dd class="col-sm-8">
                                    @if($scanResult->copy_status === 'available')
                                        <span class="badge bg-success">{{ __('Available') }}</span>
                                    @elseif($scanResult->copy_status === 'checked_out')
                                        <span class="badge bg-warning text-dark">{{ __('Checked Out') }}</span>
                                    @else
                                        <span class="badge bg-info">{{ ucfirst($scanResult->copy_status) }}</span>
                                    @endif
                                </dd>
                                @if($scanResult->hold_count > 0)
                                    <dt class="col-sm-4">{{ __('Hold Queue') }}</dt>
                                    <dd class="col-sm-8">{{ $scanResult->hold_count }} {{ __('patron(s) waiting') }}</dd>
                                @endif
                            </dl>
                        </div>

                        @if($scanResult->checkout)
                            <div class="col-md-6">
                                <dl class="row mb-0">
                                    <dt class="col-sm-4">{{ __('Due Date') }}</dt>
                                    <dd class="col-sm-8">
                                        @if(strToTime($scanResult->checkout->due_date) < time())
                                            <span class="text-danger fw-bold">
                                                {{ $scanResult->checkout->due_date }} {{ __('(OVERDUE)') }}
                                            </span>
                                        @else
                                            {{ $scanResult->checkout->due_date }}
                                        @endif
                                    </dd>
                                    <dt class="col-sm-4">{{ __('Patron') }}</dt>
                                    <dd class="col-sm-8">
                                        <a href="{{ route('library.circulation.patron', $scanResult->checkout->patron_id) }}">
                                            {{ $scanResult->checkout->patron_name }}
                                        </a>
                                    </dd>
                                    <dt class="col-sm-4">{{ __('Renewals') }}</dt>
                                    <dd class="col-sm-8">{{ $scanResult->checkout->renewed_count ?? 0 }}</dd>
                                </dl>
                            </div>
                        @endif
                    </div>

                    <div class="mt-3 d-flex gap-2 flex-wrap">
                        @if($scanResult->copy_status === 'available')
                            <a href="{{ route('library.circulation.checkout', $scanResult->copy_id) }}"
                               class="btn btn-success">
                                <i class="fas fa-exchange-alt me-1"></i>{{ __('Check Out') }}
                            </a>
                        @elseif($scanResult->copy_status === 'checked_out' && $scanResult->checkout)
                            <a href="{{ route('library.circulation.return', $scanResult->checkout->id) }}"
                               class="btn btn-warning">
                                <i class="fas fa-undo me-1"></i>{{ __('Return') }}
                            </a>
                            <form method="POST" action="{{ route('library.circulation.renew') }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="checkout_id" value="{{ $scanResult->checkout->id }}">
                                <button type="submit" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo me-1"></i>{{ __('Renew') }}
                                </button>
                            </form>
                        @endif
                        @if($scanResult->checkout)
                            <a href="{{ route('library.circulation.patron', $scanResult->checkout->patron_id) }}"
                               class="btn btn-outline-info">
                                <i class="fas fa-user me-1"></i>{{ __('Patron History') }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @elseif($scanResult->type === 'patron')
            <div class="card mb-4 border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-id-card me-2"></i>{{ __('Patron Account') }}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">{{ __('Name') }}</dt>
                                <dd class="col-sm-8"><strong>{{ $scanResult->name ?: __('(unknown)') }}</strong></dd>
                                <dt class="col-sm-4">{{ __('Card Number') }}</dt>
                                <dd class="col-sm-8"><code>{{ $scanResult->card_number }}</code></dd>
                                <dt class="col-sm-4">{{ __('Type') }}</dt>
                                <dd class="col-sm-8">{{ ucfirst($scanResult->patron_type ?: 'adult') }}</dd>
                                <dt class="col-sm-4">{{ __('Status') }}</dt>
                                <dd class="col-sm-8">
                                    <span class="badge bg-{{ $scanResult->status === 'active' ? 'success' : ($scanResult->status === 'suspended' ? 'danger' : 'secondary') }}">
                                        {{ ucfirst($scanResult->status ?: 'active') }}
                                    </span>
                                </dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">{{ __('Items Out') }}</dt>
                                <dd class="col-sm-8">{{ $scanResult->loans_count }}</dd>
                                @if($scanResult->overdue_count > 0)
                                    <dt class="col-sm-4">{{ __('Overdue') }}</dt>
                                    <dd class="col-sm-8"><span class="text-danger fw-bold">{{ $scanResult->overdue_count }}</span></dd>
                                @endif
                                <dt class="col-sm-4">{{ __('Holds') }}</dt>
                                <dd class="col-sm-8">{{ $scanResult->holds_count }}</dd>
                                <dt class="col-sm-4">{{ __('Fines Owed') }}</dt>
                                <dd class="col-sm-8">{{ number_format($scanResult->fines, 2) }}</dd>
                            </dl>
                        </div>
                    </div>

                    <div class="mt-3 d-flex gap-2 flex-wrap">
                        <a href="{{ route('library.circulation.patron', $scanResult->patron_id) }}"
                           class="btn btn-primary">
                            <i class="fas fa-history me-1"></i>{{ __('Full Account History') }}
                        </a>
                        <a href="{{ route('library.checkout-form', ['patron' => $scanResult->patron_id]) }}"
                           class="btn btn-success">
                            <i class="fas fa-exchange-alt me-1"></i>{{ __('Check Out Item') }}
                        </a>
                    </div>
                </div>
            </div>
        @endif
    @endif

    {{-- Active loans mini-table --}}
    @if(($loans ?? collect())->isNotEmpty())
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>{{ __('Active Checkouts') }} ({{ $loans->count() }})</h5>
                <span class="text-muted small">{{ __('Left to right: overdue highlighted') }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>{{ __('Title') }}</th>
                                <th>{{ __('Due Date') }}</th>
                                <th>{{ __('Patron') }}</th>
                                <th class="text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loans as $l)
                                @php
                                    $isOverdue = strToTime($l->due_date ?? '') < time();
                                @endphp
                                <tr class="{{ $isOverdue ? 'table-danger' : '' }}">
                                    <td>
                                        {{ $l->title ?? ($l->barcode ?? '') }}
                                        @if($l->call_number ?? null)
                                            <br><small class="text-muted">{{ $l->call_number }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $l->due_date ?? '' }}
                                        @if($isOverdue)
                                            <i class="fas fa-exclamation-circle text-danger ms-1" title="{{ __('Overdue') }}"></i>
                                        @endif
                                    </td>
                                    <td>{{ $l->first_name ?? '' }} {{ $l->last_name ?? '' }}</td>
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
    @endif

</div>
@endsection

@push('scripts')
<script>
(function () {
    // Live clock display
    function updateClock() {
        var el = document.getElementById('clock');
        if (el) {
            el.textContent = new Date().toLocaleString('{{ app()->getLocale() }}', {
                weekday: 'short', year: 'numeric', month: 'short', day: 'numeric',
                hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
        }
    }
    updateClock();
    setInterval(updateClock, 1000);

    // Auto-focus barcode input on load
    document.getElementById('barcodeInput') && document.getElementById('barcodeInput').focus();
})();
</script>
@endpush
