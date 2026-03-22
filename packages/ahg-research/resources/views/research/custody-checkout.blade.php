{{-- Custody Checkout - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'retrievalQueue'])
@endsection
@section('title', 'Custody Checkout')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.retrievalQueue') }}">Retrieval Queue</a></li><li class="breadcrumb-item active">Checkout</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-box-open text-primary me-2"></i>Custody Checkout</h1>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
<div class="card">
    <div class="card-body">
        <form method="POST">
            @csrf
            <div class="row mb-3">
                <div class="col-md-6"><label class="form-label">Item <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label><input type="text" name="item_title" class="form-control" value="{{ e($item->title ?? '') }}" readonly></div>
                <input type="hidden" name="item_id" value="{{ $item->id ?? 0 }}">
                <div class="col-md-6"><label class="form-label">Researcher <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label><input type="text" class="form-control" value="{{ e(($researcher->first_name ?? '') . ' ' . ($researcher->last_name ?? '')) }}" readonly></div>
                <input type="hidden" name="researcher_id" value="{{ $researcher->id ?? 0 }}">
            </div>
            <div class="row mb-3">
                <div class="col-md-4"><label class="form-label">Checkout Date <span class="badge bg-secondary ms-1">Optional</span></label><input type="date" name="checkout_date" class="form-control" value="{{ date('Y-m-d') }}"></div>
                <div class="col-md-4"><label class="form-label">Expected Return <span class="badge bg-secondary ms-1">Optional</span></label><input type="date" name="expected_return" class="form-control" value="{{ date('Y-m-d', strtotime('+1 day')) }}"></div>
                <div class="col-md-4"><label class="form-label">Condition <span class="badge bg-secondary ms-1">Optional</span></label>
                    <select name="condition" class="form-select"><option value="good">Good</option><option value="fair">Fair</option><option value="fragile">Fragile</option></select>
                </div>
            </div>
            <div class="mb-3"><label class="form-label">Notes <span class="badge bg-secondary ms-1">Optional</span></label><textarea name="notes" class="form-control" rows="2"></textarea></div>
            <button type="submit" class="btn atom-btn-white"><i class="fas fa-check me-1"></i>Confirm Checkout</button>
            <a href="{{ route('research.retrievalQueue') }}" class="btn atom-btn-white">Cancel</a>
        </form>
    </div>
</div>
@endsection