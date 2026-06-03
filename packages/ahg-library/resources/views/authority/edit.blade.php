@extends('theme::layouts.1col')
@section('title', 'Edit Authority Record #' . ($record->id ?? ''))
@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('library.marc-authority.show', $record->id ?? 0) }}"
           class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h2 class="mb-0">Edit Authority Record #{{ $record->id ?? '' }}</h2>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('library.marc-authority.update', $record->id ?? 0) }}">
        @csrf
        @method('PUT')
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-3">
                    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                        <h5 class="mb-0"><i class="fas fa-tag me-2"></i>Authority Labels</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="lc_label" class="form-label small fw-semibold">{{ __('LC Label') }}</label>
                            <input type="text" name="lc_label" id="lc_label" class="form-control"
                                   value="{{ old('lc_label', $record->lc_label ?? '') }}" maxlength="500">
                            @error('lc_label') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label for="rda_label" class="form-label small fw-semibold">{{ __('RDA Label') }}</label>
                            <input type="text" name="rda_label" id="rda_label" class="form-control"
                                   value="{{ old('rda_label', $record->rda_label ?? '') }}" maxlength="500">
                            @error('rda_label') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label for="authorized_form" class="form-label small fw-semibold">{{ __('Authorized Form') }}</label>
                            <input type="text" name="authorized_form" id="authorized_form" class="form-control"
                                   value="{{ old('authorized_form', $record->authorized_form ?? '') }}" maxlength="500">
                            @error('authorized_form') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-3">
                    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                        <h5 class="mb-0"><i class="fas fa-sitemap me-2"></i>Vocabulary / Classification</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="subject_type" class="form-label small fw-semibold">{{ __('Subject Type') }}</label>
                                <select name="subject_type" id="subject_type" class="form-select">
                                    @foreach(['topic','person','family','corporate_body','title','geographic','event','uniform_title'] as $t)
                                        <option value="{{ $t }}" {{ old('subject_type', $record->subject_type ?? 'topic') === $t ? 'selected' : '' }}>{{ $t }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="vocab_code" class="form-label small fw-semibold">{{ __('Vocabulary Code') }}</label>
                                <input type="text" name="vocab_code" id="vocab_code" class="form-control"
                                       value="{{ old('vocab_code', $record->vocab_code ?? '') }}" maxlength="50">
                                @error('vocab_code') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-12">
                                <label for="vocab_uri" class="form-label small fw-semibold">{{ __('Vocabulary URI') }}</label>
                                <input type="url" name="vocab_uri" id="vocab_uri" class="form-control"
                                       value="{{ old('vocab_uri', $record->vocab_uri ?? '') }}" maxlength="500">
                                @error('vocab_uri') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-3">
                    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                        <h5 class="mb-0"><i class="fas fa-globe me-2"></i>URI / Source</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="uri" class="form-label small fw-semibold">{{ __('Authority URI') }}</label>
                                <input type="url" name="uri" id="uri" class="form-control"
                                       value="{{ old('uri', $record->uri ?? '') }}" maxlength="500">
                                @error('uri') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="source" class="form-label small fw-semibold">{{ __('Source') }}</label>
                                <input type="text" name="source" id="source" class="form-control"
                                       value="{{ old('source', $record->source ?? '') }}" maxlength="100">
                                @error('source') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-3">
                    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                        <h5 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Notes</h5>
                    </div>
                    <div class="card-body">
                        <textarea name="notes" id="notes" class="form-control" rows="3"
                                  maxlength="1000">{{ old('notes', $record->notes ?? '') }}</textarea>
                        @error('notes') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm sticky-top" style="top:1rem">
                    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                        <h5 class="mb-0"><i class="fas fa-save me-2"></i>Actions</h5>
                    </div>
                    <div class="card-body">
                        <button type="submit" class="btn btn-success w-100 mb-2">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                        <a href="{{ route('library.marc-authority.show', $record->id ?? 0) }}"
                           class="btn btn-outline-secondary w-100">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
