{{-- New Reproduction Request - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'reproductions'])@endsection
@section('title', 'New Reproduction Request')
@section('content')
<nav aria-label="{{ __('breadcrumb') }}"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.reproductions') }}">Reproductions</a></li><li class="breadcrumb-item active">New Request</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-copy text-primary me-2"></i>New Reproduction Request</h1>
<div class="card"><div class="card-body">
    <form method="POST">@csrf
        <div class="mb-3"><label class="form-label">Item Reference <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label><input type="text" name="item_reference" class="form-control" required placeholder="{{ __('Enter item identifier or title') }}"></div>
        <div class="row mb-3">
            <div class="col-md-6"><label class="form-label">Reproduction Type <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label><select name="reproduction_type" class="form-select" required>
                <option value="photocopy">{{ __('Photocopy') }}</option><option value="digital_scan">{{ __('Digital Scan') }}</option><option value="photograph">{{ __('Photograph') }}</option><option value="microfilm">{{ __('Microfilm') }}</option><option value="certified_copy">{{ __('Certified Copy') }}</option>
            </select></div>
            <div class="col-md-3"><label class="form-label">Format <span class="badge bg-danger ms-1">Required</span></label><select name="format" class="form-select"><option value="pdf">PDF</option><option value="tiff">TIFF</option><option value="jpeg">JPEG</option><option value="paper">{{ __('Paper') }}</option></select></div>
            <div class="col-md-3"><label class="form-label">Quantity <span class="badge bg-danger ms-1">Required</span></label><input type="number" name="quantity" class="form-control" value="1" min="1"></div>
        </div>
        <div class="mb-3"><label class="form-label">Purpose <span class="badge bg-danger ms-1">Required</span></label><select name="purpose" class="form-select"><option value="personal_research">{{ __('Personal Research') }}</option><option value="publication">{{ __('Publication') }}</option><option value="exhibition">{{ __('Exhibition') }}</option><option value="legal">{{ __('Legal') }}</option><option value="other">{{ __('Other') }}</option></select></div>
        <div class="mb-3"><label class="form-label">Special Instructions <span class="badge bg-danger ms-1">Required</span></label><textarea name="notes" class="form-control" rows="3" placeholder="{{ __('Page ranges, specific sections, quality requirements...') }}"></textarea></div>
        <div class="form-check mb-3"><input type="checkbox" name="agree_terms" class="form-check-input" id="agreeTerms" required><label class="form-check-label" for="agreeTerms">I agree to the reproduction terms and copyright conditions <span class="badge bg-secondary ms-1">Optional</span></label></div>
        <button type="submit" class="btn atom-btn-white"><i class="fas fa-paper-plane me-1"></i>Submit Request</button>
        <a href="{{ route('research.reproductions') }}" class="btn atom-btn-white">Cancel</a>
    </form>
</div></div>
@endsection