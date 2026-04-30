{{-- Edit Researcher Type - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'adminTypes'])@endsection
@section('title', ($isNew ?? true) ? 'Add Researcher Type' : 'Edit Researcher Type')
@section('content')
<nav aria-label="{{ __('breadcrumb') }}"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.adminTypes') }}">Researcher Types</a></li><li class="breadcrumb-item active">{{ ($isNew ?? true) ? 'Add' : 'Edit' }}</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-user-tag text-primary me-2"></i>{{ ($isNew ?? true) ? 'Add Researcher Type' : 'Edit Researcher Type' }}</h1>
<div class="card">
    <div class="card-body">
        <form method="POST">
            @csrf
            <div class="row mb-3">
                <div class="col-md-6"><label class="form-label">Name <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label><input type="text" name="name" class="form-control" required value="{{ e($type->name ?? '') }}"></div>
                <div class="col-md-3"><label class="form-label">Code <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label><input type="text" name="code" class="form-control" required value="{{ e($type->code ?? '') }}"></div>
                <div class="col-md-3"><label class="form-label">Sort Order <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><input type="number" name="sort_order" class="form-control" value="{{ $type->sort_order ?? 0 }}"></div>
            </div>
            <div class="mb-3"><label class="form-label">Description <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><textarea name="description" class="form-control" rows="2">{{ e($type->description ?? '') }}</textarea></div>
            <h5 class="border-bottom pb-2 mt-4 mb-3">{{ __('Privileges') }}</h5>
            <div class="row mb-3">
                <div class="col-md-4"><label class="form-label">Max Advance Booking Days <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><input type="number" name="max_advance_days" class="form-control" value="{{ $type->max_advance_days ?? 30 }}"></div>
                <div class="col-md-4"><label class="form-label">Max Hours per Day <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><input type="number" name="max_hours_per_day" class="form-control" value="{{ $type->max_hours_per_day ?? 8 }}"></div>
                <div class="col-md-4"><label class="form-label">Max Materials <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><input type="number" name="max_materials" class="form-control" value="{{ $type->max_materials ?? 5 }}"></div>
            </div>
            <div class="form-check mb-3"><input type="checkbox" name="auto_approve" class="form-check-input" id="autoApprove" {{ ($type->auto_approve ?? false) ? 'checked' : '' }}><label class="form-check-label" for="autoApprove">Auto-approve registrations of this type <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label></div>
            <div class="form-check mb-3"><input type="checkbox" name="is_active" class="form-check-input" id="isActive" {{ ($type->is_active ?? true) ? 'checked' : '' }}><label class="form-check-label" for="isActive">Active <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label></div>
            <button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button>
            <a href="{{ route('research.adminTypes') }}" class="btn atom-btn-white">Cancel</a>
        </form>
    </div>
</div>
@endsection