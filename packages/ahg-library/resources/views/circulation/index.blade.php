@extends('theme::layouts.1col')
@section('title', 'Circulation')
@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center">
            <a href="{{ route('library.index') }}" class="btn btn-outline-secondary btn-sm me-3" title="{{ __('Back to Library') }}"><i class="fas fa-arrow-left"></i></a>
            <h1 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>{{ __('Circulation') }}</h1>
        </div>
        <a href="{{ route('library.checkout-form') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>{{ __('Check Out Item') }}</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert"><i class="fas fa-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Patron') }}</th>
                        <th>{{ __('Item') }}</th>
                        <th>{{ __('Checkout') }}</th>
                        <th>{{ __('Due') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($loans ?? [] as $l)
                        @php $pname = trim(($l->last_name ?? '') . ', ' . ($l->first_name ?? '')); @endphp
                        <tr>
                            <td>{{ $pname !== ',' ? $pname : '' }}@if($l->card_number ?? null)<br><small class="text-muted"><code>{{ $l->card_number }}</code></small>@endif</td>
                            <td>{{ $l->title ?? ($l->barcode ?? '') }}</td>
                            <td>{{ $l->checkout_date ?? '' }}</td>
                            <td>{{ $l->due_date ?? '' }}</td>
                            <td><span class="badge bg-{{ ($l->status ?? '') === 'overdue' ? 'danger' : 'success' }}">{{ ucfirst($l->status ?? 'active') }}</span></td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('library.checkout-renew', $l->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-secondary" title="{{ __('Renew') }}"><i class="fas fa-redo"></i></button></form>
                                <form method="POST" action="{{ route('library.checkout-return', $l->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-success" title="{{ __('Return') }}"><i class="fas fa-undo"></i></button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted text-center py-3">{{ __('No circulations.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
