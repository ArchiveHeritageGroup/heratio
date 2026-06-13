@extends('theme::layouts.1col')
@section('title', 'Add Serial')
@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('library.serials') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="mb-0">{{ __('Add Serial') }}</h2>
            <span class="badge bg-primary mt-1">Serials</span>
        </div>
    </div>

    @if(session('serial_success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('serial_success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('library.serial-store') }}" autocomplete="off">
        @csrf
        <div class="card shadow-sm mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                <h5 class="mb-0"><i class="fas fa-newspaper me-2"></i>Serial Title</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="title" class="form-label required">Title <span class="badge bg-danger ms-1">Required</span></label>
                    <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror"
                           autocomplete="off"
                           value="{{ old('title') }}" required>
                    @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="issn" class="form-label">{{ __('ISSN') }}</label>
                        <input type="text" name="issn" id="issn" class="form-control @error('issn') is-invalid @enderror"
                               value="{{ old('issn') }}" placeholder="1234-5678">
                        @error('issn') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">International Standard Serial Number</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="frequency" class="form-label">{{ __('Frequency') }}</label>
                        <select name="frequency" id="frequency" class="form-select @error('frequency') is-invalid @enderror">
                            <option value="">-- Select --</option>
                            @foreach($frequencies as $value => $label)
                                <option value="{{ $value }}" @selected(old('frequency') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('frequency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="status" class="form-label">{{ __('Status') }}</label>
                        <select name="status" id="status" class="form-select">
                            <option value="active" @selected(old('status', 'active') === 'active')>{{ __('Active') }}</option>
                            <option value="ceased" @selected(old('status') === 'ceased')>{{ __('Ceased') }}</option>
                            <option value="suspended" @selected(old('status') === 'suspended')>{{ __('Suspended') }}</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="publisher" class="form-label">{{ __('Publisher') }}</label>
                    <input type="text" name="publisher" id="publisher" class="form-control"
                           value="{{ old('publisher') }}">
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">{{ __('Notes') }}</label>
                    <textarea name="notes" id="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save me-2"></i>Create Serial
            </button>
            <a href="{{ route('library.serials') }}" class="btn btn-outline-secondary">
                <i class="fas fa-times me-2"></i>Cancel
            </a>
        </div>
    </form>
</div>
@endsection
