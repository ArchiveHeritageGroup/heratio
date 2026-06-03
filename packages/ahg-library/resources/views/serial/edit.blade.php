@extends('theme::layouts.1col')
@section('title', 'Edit Serial: ' . ($serial->title ?? ''))
@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('library.serial-view', $serial->id ?? '') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="mb-0">{{ __('Edit Serial') }}</h2>
            <span class="badge bg-primary mt-1">{{ e($serial->title ?? '') }}</span>
        </div>
    </div>

    @if(session('serial_success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('serial_success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('library.serial-update', $serial->id ?? 0) }}">
        @csrf
        @method('PUT')
        <div class="card shadow-sm mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                <h5 class="mb-0"><i class="fas fa-newspaper me-2"></i>Serial Title</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="title" class="form-label required">Title <span class="badge bg-danger ms-1">Required</span></label>
                    <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror"
                           value="{{ old('title', $serial->title ?? '') }}" required>
                    @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="issn" class="form-label">{{ __('ISSN') }}</label>
                        <input type="text" name="issn" id="issn" class="form-control @error('issn') is-invalid @enderror"
                               value="{{ old('issn', $serial->issn ?? '') }}" placeholder="1234-5678">
                        @error('issn') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="frequency" class="form-label">{{ __('Frequency') }}</label>
                        <select name="frequency" id="frequency" class="form-select @error('frequency') is-invalid @enderror">
                            <option value="">-- Select --</option>
                            @foreach($frequencies as $value => $label)
                                <option value="{{ $value }}" @selected(old('frequency', $serial->frequency ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('frequency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="status" class="form-label">{{ __('Status') }}</label>
                        <select name="status" id="status" class="form-select">
                            <option value="active" @selected(old('status', $serial->status ?? 'active') === 'active')>Active</option>
                            <option value="ceased" @selected(old('status', $serial->status ?? '') === 'ceased')>Ceased</option>
                            <option value="suspended" @selected(old('status', $serial->status ?? '') === 'suspended')>Suspended</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="publisher" class="form-label">{{ __('Publisher') }}</label>
                    <input type="text" name="publisher" id="publisher" class="form-control"
                           value="{{ old('publisher', $serial->publisher ?? '') }}">
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">{{ __('Notes') }}</label>
                    <textarea name="notes" id="notes" class="form-control" rows="3">{{ old('notes', $serial->notes ?? '') }}</textarea>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save me-2"></i>Save Changes
            </button>
            <a href="{{ route('library.serial-view', $serial->id ?? '') }}" class="btn btn-outline-secondary">
                <i class="fas fa-times me-2"></i>Cancel
            </a>
        </div>
    </form>

    <hr class="my-5">

    {{-- Danger zone: delete --}}
    <div class="card border-danger">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0"><i class="fas fa-trash-alt me-2"></i>Danger Zone</h5>
        </div>
        <div class="card-body">
            <p class="text-muted mb-2">Permanently delete this serial and all its issue records.</p>
            <form method="POST" action="{{ route('library.serial-delete', $serial->id ?? 0) }}" class="d-inline"
                  onsubmit="return confirm('Delete serial "{{ e($serial->title ?? '') }}"? This cannot be undone.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger btn-sm">
                    <i class="fas fa-trash-alt me-1"></i>Delete Serial
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
