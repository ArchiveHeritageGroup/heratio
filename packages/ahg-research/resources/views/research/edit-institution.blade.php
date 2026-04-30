{{-- Edit Institution - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'institutions'])@endsection
@section('title', ($isNew ?? true) ? 'Add Institution' : 'Edit Institution')
@section('content')
<nav aria-label="{{ __('breadcrumb') }}"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.institutions') }}">Institutions</a></li><li class="breadcrumb-item active">{{ ($isNew ?? true) ? 'Add' : 'Edit' }}</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-university text-primary me-2"></i>{{ ($isNew ?? true) ? 'Add Institution' : 'Edit Institution' }}</h1>
<div class="card">
    <div class="card-body">
        <form method="POST">
            @csrf
            <div class="row mb-3">
                <div class="col-md-8"><label class="form-label">Name <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label><input type="text" name="name" class="form-control" required value="{{ e($institution->name ?? '') }}"></div>
                <div class="col-md-4"><label class="form-label">Code <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><input type="text" name="code" class="form-control" value="{{ e($institution->code ?? '') }}"></div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6"><label class="form-label">Contact Email <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><input type="email" name="email" class="form-control" value="{{ e($institution->email ?? '') }}"></div>
                <div class="col-md-6"><label class="form-label">Phone <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><input type="text" name="phone" class="form-control" value="{{ e($institution->phone ?? '') }}"></div>
            </div>
            <div class="mb-3"><label class="form-label">Address <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><textarea name="address" class="form-control" rows="2">{{ e($institution->address ?? '') }}</textarea></div>
            <div class="mb-3"><label class="form-label">Website <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><input type="url" name="website" class="form-control" value="{{ e($institution->website ?? '') }}"></div>
            <div class="mb-3"><label class="form-label">Notes <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><textarea name="notes" class="form-control" rows="3">{{ e($institution->notes ?? '') }}</textarea></div>
            <div class="form-check mb-3"><input type="checkbox" name="is_active" class="form-check-input" id="isActive" {{ ($institution->is_active ?? true) ? 'checked' : '' }}><label class="form-check-label" for="isActive">Active <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label></div>
            <button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button>
            <a href="{{ route('research.institutions') }}" class="btn atom-btn-white">Cancel</a>
        </form>
    </div>
</div>
@endsection