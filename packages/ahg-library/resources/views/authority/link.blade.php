@extends('theme::layouts.1col')
@section('title', 'Link Subject Heading')

@section('content')
<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12 d-flex align-items-center">
            <a href="{{ route('library.authority-view', $authority->id) }}"
               class="btn btn-outline-secondary btn-sm me-3">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h2 class="mb-0">{{ __('Link Subject Heading') }}</h2>
        </div>
    </div>

    <div class="alert alert-info">
        Linking authority: <strong>{{ $authority->heading }}</strong>
        <span class="badge bg-light text-dark ms-2">{{ $authority->subject_type }}</span>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif

    <div class="card shadow-sm" style="max-width:600px">
        <div class="card-header">
            <i class="fas fa-link me-2"></i>Link to Library Item
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('library.authority-store-link') }}">
                @csrf

                <input type="hidden" name="authority_id" value="{{ $authority->id }}">

                <div class="mb-3">
                    <label for="library_item_id" class="form-label">
                        Library Item <span class="text-danger">*</span>
                    </label>
                    <select name="library_item_id" id="library_item_id" class="form-select" required>
                        <option value="">— select library item —</option>
                        @if(isset($items) && $items->count())
                            @foreach($items as $item)
                                <option value="{{ $item->id }}">
                                    {{ $item->title ?? 'Item #' . $item->id }}
                                    ({{ $item->isbn ?? $item->call_number ?? 'no id' }})
                                </option>
                            @endforeach
                        @endif
                    </select>
                    <div class="form-text">
                        Search for a library item in the catalogue or enter the item ID directly.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="library_item_id_manual" class="form-label">{{ __('Or enter Library Item ID') }}</label>
                    <input type="number" class="form-control" id="library_item_id_manual"
                           placeholder="{{ __('e.g. 123') }}" min="1"
                           oninput="
                               var v = this.value;
                               var sel = document.getElementById('library_item_id');
                               if (v) sel.value = v;
                           ">
                    @error('library_item_id')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="source_tag" class="form-label">{{ __('Source MARC Tag') }}</label>
                    <select name="source_tag" id="source_tag" class="form-select">
                        <option value="650">650 — Topical subject</option>
                        <option value="651">651 — Geographic name subject</option>
                        <option value="655">655 — Genre/Form subject</option>
                        <option value="656">656 — Occupation subject</option>
                        <option value="657">657 — Function subject</option>
                        <option value="658">658 — Main entry-subject</option>
                        <option value="600">600 — Personal name subject</option>
                        <option value="610">610 — Corporate name subject</option>
                        <option value="611">611 — Meeting name subject</option>
                    </select>
                    <div class="form-text">The MARC tag where this heading appears in the record.</div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-link me-2"></i>Create Link
                    </button>
                    <a href="{{ route('library.authority-view', $authority->id) }}"
                       class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
