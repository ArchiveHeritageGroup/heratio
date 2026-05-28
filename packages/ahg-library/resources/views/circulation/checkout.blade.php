@extends('theme::layouts.1col')
@section('title', __('Check Out Item'))
@section('content')
@php
    $patrons = \Illuminate\Support\Facades\DB::table('library_patron')
        ->where('borrowing_status', 'active')
        ->orderBy('last_name')->orderBy('first_name')
        ->get(['id', 'first_name', 'last_name', 'card_number']);
@endphp
<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('library.circulation') }}" class="btn btn-outline-secondary btn-sm me-3"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h2 class="mb-0">{{ __('Check Out Item') }}</h2>
            <span class="badge bg-primary mt-1">{{ __('Circulation') }}</span>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('library.checkout-store') }}">
        @csrf
        <div class="card shadow-sm mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                <h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>{{ __('Loan Details') }}</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="patron_id" class="form-label">{{ __('Patron') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                    <select name="patron_id" id="patron_id" class="form-select @error('patron_id') is-invalid @enderror" required>
                        <option value="">{{ __('Select a patron…') }}</option>
                        @foreach($patrons as $p)
                            <option value="{{ $p->id }}" @selected((string) old('patron_id', $patronId ?? '') === (string) $p->id)>
                                {{ trim($p->last_name . ', ' . $p->first_name) }} ({{ $p->card_number }})
                            </option>
                        @endforeach
                    </select>
                    @error('patron_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-0">
                    <label for="copy_barcode" class="form-label">{{ __('Copy Barcode') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                    <input type="text" name="copy_barcode" id="copy_barcode" autofocus
                           class="form-control @error('copy_barcode') is-invalid @enderror" value="{{ old('copy_barcode') }}"
                           placeholder="{{ __('Scan or type the item barcode') }}">
                    @error('copy_barcode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">{{ __('The barcode resolves to a specific copy of a library item.') }}</div>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1"></i>{{ __('Check Out') }}</button>
            <a href="{{ route('library.circulation') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</div>
@endsection
