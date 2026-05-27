@extends('theme::layouts.1col')

@section('title', __('Create FRBR override'))

@section('content')
<div class="container py-4" style="max-width: 720px;">
    <h1 class="h3">{{ __('Create FRBR override') }}</h1>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.frbr.overrides.store') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">{{ __('Library item ID') }}</label>
            <input type="number" name="library_item_id" class="form-control" required value="{{ old('library_item_id') }}">
            <small class="form-text text-muted">{{ __('The library_item.id to override.') }}</small>
        </div>
        <div class="mb-3">
            <label class="form-label">{{ __('Mode') }}</label>
            <select name="mode" class="form-select" required>
                <option value="force_group" {{ old('mode')==='force_group' ? 'selected' : '' }}>{{ __('Force-group (pin to another work)') }}</option>
                <option value="force_split" {{ old('mode')==='force_split' ? 'selected' : '' }}>{{ __('Force-split (pull onto its own work)') }}</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">{{ __('Override work-key') }}</label>
            <input type="text" name="override_key" class="form-control" required maxlength="64" value="{{ old('override_key') }}">
            <small class="form-text text-muted">{{ __('For force-group: paste the target item\'s work_key. For force-split: any unique string (e.g. split:item-12345).') }}</small>
        </div>
        <div class="mb-3">
            <label class="form-label">{{ __('Reason') }}</label>
            <textarea name="reason" class="form-control" rows="3" maxlength="500">{{ old('reason') }}</textarea>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ __('Save override') }}</button>
            <a href="{{ route('admin.frbr.overrides.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</div>
@endsection
