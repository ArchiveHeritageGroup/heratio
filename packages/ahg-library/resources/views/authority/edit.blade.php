@extends('theme::layouts.1col')
@section('title', 'Edit Authority Record #' . ($record->id ?? ''))
@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('library.authority-view', $record->id) }}"
           class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h2 class="mb-0">{{ __('Edit Authority Record #:id', ['id' => $record->id ?? '']) }}</h2>
    </div>

    @if($errors->any())
        <div class="alert alert-danger" role="alert">{{ __('Please fix the errors below.') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('library.authority-update', $record->id) }}" autocomplete="off">
        @csrf
        @method('PUT')
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-3">
                    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                        <h5 class="mb-0"><i class="fas fa-tag me-2"></i>{{ __('Authority Labels') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="heading" class="form-label small fw-semibold">{{ __('Heading') }} <span class="text-danger">*</span></label>
                            <input type="text" name="heading" id="heading" class="form-control" required
                                   value="{{ old('heading', $record->heading ?? '') }}" maxlength="500">
                            @error('heading') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label for="lc_label" class="form-label small fw-semibold">{{ __('LC Label') }}</label>
                            <input type="text" name="lc_label" id="lc_label" class="form-control"
                                   autocomplete="off"
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
                        <h5 class="mb-0"><i class="fas fa-sitemap me-2"></i>{{ __('Vocabulary / Classification') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="subject_type" class="form-label small fw-semibold">{{ __('Subject Type') }}</label>
                                @php $currentType = old('subject_type', $record->subject_type ?? 'topic'); @endphp
                                <select name="subject_type" id="subject_type" class="form-select" required>
                                    @unless(isset($subjectTypes[$currentType]))
                                        <option value="{{ $currentType }}" selected>{{ $currentType }}</option>
                                    @endunless
                                    @foreach($subjectTypes as $code => $label)
                                        <option value="{{ $code }}" {{ $currentType === $code ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('subject_type') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="record_type" class="form-label small fw-semibold">{{ __('Record Type') }}</label>
                                @php $currentRecordType = old('record_type', $recordType); @endphp
                                <select name="record_type" id="record_type" class="form-select" required>
                                    @unless(isset($recordTypes[$currentRecordType]))
                                        <option value="{{ $currentRecordType }}" selected>{{ $currentRecordType }}</option>
                                    @endunless
                                    @foreach($recordTypes as $code => $label)
                                        <option value="{{ $code }}" {{ $currentRecordType === $code ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('record_type') <div class="text-danger small">{{ $message }}</div> @enderror
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
                        <h5 class="mb-0"><i class="fas fa-globe me-2"></i>{{ __('URI / Source') }}</h5>
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
                                @php $currentSource = old('source', $record->source ?? 'local'); @endphp
                                <select name="source" id="source" class="form-select" required>
                                    @unless(isset($sources[$currentSource]))
                                        <option value="{{ $currentSource }}" selected>{{ $currentSource }}</option>
                                    @endunless
                                    @foreach($sources as $code => $label)
                                        <option value="{{ $code }}" {{ $currentSource === $code ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('source') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-3">
                    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                        <h5 class="mb-0"><i class="fas fa-sticky-note me-2"></i>{{ __('Notes') }}</h5>
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
                        <h5 class="mb-0"><i class="fas fa-save me-2"></i>{{ __('Actions') }}</h5>
                    </div>
                    <div class="card-body">
                        <button type="submit" class="btn btn-success w-100 mb-2">
                            <i class="fas fa-save me-2"></i>{{ __('Save Changes') }}
                        </button>
                        <a href="{{ route('library.authority-view', $record->id) }}"
                           class="btn btn-outline-secondary w-100">
                            {{ __('Cancel') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
