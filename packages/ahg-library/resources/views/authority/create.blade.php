@extends('theme::layouts.1col')
@section('title', 'Create Subject Authority')

@section('content')
<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12 d-flex align-items-center">
            <a href="{{ route('library.authority-index') }}" class="btn btn-outline-secondary btn-sm me-3">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h2 class="mb-0">{{ __('New Subject Authority Record') }}</h2>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>{{ __('Please fix the errors below.') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif

    <div class="card shadow-sm" style="max-width:640px">
        <div class="card-body">
            <form method="POST" action="{{ route('library.authority-store') }}" autocomplete="off">
                @csrf

                <div class="mb-3">
                    <label for="heading" class="form-label">{{ __('Heading') }} <span class="text-danger">*</span></label>
                    <input type="text" name="heading" id="heading" class="form-control"
                           autocomplete="off"
                           value="{{ old('heading') }}" required maxlength="500">
                    @error('heading')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="record_type" class="form-label">{{ __('Record Type') }} <span class="text-danger">*</span></label>
                    <select name="record_type" id="record_type" class="form-select" required>
                        @foreach($recordTypes as $code => $label)
                            <option value="{{ $code }}" {{ old('record_type', 'subject') === $code ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('record_type') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="subject_type" class="form-label">{{ __('Subject Type') }} <span class="text-danger">*</span></label>
                        <select name="subject_type" id="subject_type" class="form-select" required>
                            @foreach($subjectTypes as $code => $label)
                                <option value="{{ $code }}" {{ old('subject_type', 'topic') === $code ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="source" class="form-label">{{ __('Source') }} <span class="text-danger">*</span></label>
                        <select name="source" id="source" class="form-select" required>
                            @foreach($sources as $code => $label)
                                <option value="{{ $code }}" {{ old('source', 'local') === $code ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="uri" class="form-label">URI</label>
                    <input type="url" name="uri" id="uri" class="form-control"
                           value="{{ old('uri') }}" placeholder="{{ __('https://id.loc.gov/authorities/subjects/…') }}"
                           maxlength="1000">
                    @error('uri')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-2"></i>{{ __('Save') }}
                    </button>
                    <a href="{{ route('library.authority-index') }}" class="btn btn-outline-secondary">
                        {{ __('Cancel') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
