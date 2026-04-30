{{-- Custody Return Verify - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'retrievalQueue'])
@endsection
@section('title', 'Verify Return')
@section('content')
<nav aria-label="{{ __('breadcrumb') }}"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.retrievalQueue') }}">Retrieval Queue</a></li><li class="breadcrumb-item active">Verify Return</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-clipboard-check text-primary me-2"></i>Verify Return</h1>
<div class="card">
    <div class="card-body">
        <form method="POST">
            @csrf
            <input type="hidden" name="checkout_id" value="{{ $checkout->id ?? 0 }}">
            <div class="row mb-3">
                <div class="col-md-6"><label class="form-label">Item <span class="badge bg-danger ms-1">Required</span></label><input type="text" class="form-control" value="{{ e($checkout->item_title ?? '') }}" readonly></div>
                <div class="col-md-6"><label class="form-label">Researcher <span class="badge bg-danger ms-1">Required</span></label><input type="text" class="form-control" value="{{ e(($checkout->first_name ?? '') . ' ' . ($checkout->last_name ?? '')) }}" readonly></div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4"><label class="form-label">Checked Out <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" value="{{ $checkout->checkout_date ?? '' }}" readonly></div>
                <div class="col-md-4"><label class="form-label">Expected Return <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" value="{{ $checkout->expected_return ?? '' }}" readonly></div>
                <div class="col-md-4"><label class="form-label">Return Condition <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
                    <select name="return_condition" class="form-select" required><option value="good">{{ __('Good') }}</option><option value="fair">{{ __('Fair') }}</option><option value="damaged">{{ __('Damaged') }}</option><option value="missing_pages">{{ __('Missing Pages') }}</option></select>
                </div>
            </div>
            <div class="mb-3"><label class="form-label">Return Notes <span class="badge bg-danger ms-1">Required</span></label><textarea name="return_notes" class="form-control" rows="2"></textarea></div>
            <div class="form-check mb-3"><input type="checkbox" name="confirm_return" class="form-check-input" id="confirmReturn" required><label class="form-check-label" for="confirmReturn">I confirm this item has been physically returned and inspected <span class="badge bg-secondary ms-1">Optional</span></label></div>
            <button type="submit" class="btn atom-btn-white"><i class="fas fa-check-double me-1"></i>Verify Return</button>
            <a href="{{ route('research.retrievalQueue') }}" class="btn atom-btn-white">Cancel</a>
        </form>
    </div>
</div>
@endsection