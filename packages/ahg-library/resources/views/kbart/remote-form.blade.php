@extends('theme::layouts.1col')
@section('title', $feed ? "Edit Feed — {$feed->name}" : 'Add KBART Feed')

@section('content')
<div class="container py-4">

    <div class="mb-3">
        <a href="{{ route('library.kbart-remote') }}" class="text-decoration-none small">
            <i class="fas fa-arrow-left me-1"></i>Remote Feeds
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-7">

            <div class="card">
                <div class="card-header" style="background: var(--ahg-primary); color: #fff;">
                    <h6 class="mb-0">
                        <i class="fas fa-rss me-2"></i>
                        {{ $feed ? 'Edit Feed' : 'Add KBART Remote Feed' }}
                    </h6>
                </div>
                <div class="card-body">

                    {{-- Inline URL test result --}}
                    <div id="url-test-result" class="alert alert-info mb-3" style="display:none;"></div>

                    <form method="POST" action="{{ $url }}" id="feed-form">
                        @csrf
                        @if($feed)
                            @method('PUT')
                        @endif

                        <div class="mb-3">
                            <label for="name" class="form-label">Feed name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name"
                                   class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $feed->name ?? '') }}"
                                   maxlength="255" required
                                   placeholder="{{ __('e.g. ProQuest KBART Feed Q1 2026') }}">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="url" class="form-label">
                                Feed URL <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="url" name="url" id="url"
                                       class="form-control @error('url') is-invalid @enderror"
                                       value="{{ old('url', $feed->url ?? '') }}"
                                       maxlength="1000" required
                                       placeholder="{{ __('https://vendor.example.com/kbart/titles.tsv') }}">
                                <button type="button" id="test-url-btn" class="btn btn-outline-secondary">
                                    <i class="fas fa-plug me-1"></i>Test URL
                                </button>
                            </div>
                            <div class="form-text">
                                A publicly accessible URL returning a NISO KBART TSV file.
                            </div>
                            @error('url')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="vendor" class="form-label">{{ __('Vendor / platform') }}</label>
                                <input type="text" name="vendor" id="vendor"
                                       class="form-control"
                                       value="{{ old('vendor', $feed->vendor ?? '') }}"
                                       maxlength="255"
                                       placeholder="{{ __('e.g. ProQuest, EBSCO, JSTOR') }}">
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check">
                                    <input type="checkbox" name="active" id="active"
                                           class="form-check-input"
                                           value="1"
                                           {{ old('active', $feed->active ?? true) ? 'checked' : '' }}>
                                    <label for="active" class="form-check-label">
                                        Active <span class="text-muted small">(include in scheduled runs)</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">{{ __('Internal notes') }}</label>
                            <textarea name="notes" id="notes" rows="3"
                                      class="form-control"
                                      maxlength="2000"
                                      placeholder="{{ __('Licence notes, feed quirks, contact info …') }}">{{ old('notes', $feed->notes ?? '') }}</textarea>
                            <div class="form-text">Not shown to patrons.</div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('library.kbart-remote') }}"
                               class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" id="submit-btn"
                                    class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>
                                {{ $feed ? 'Update Feed' : 'Add Feed' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const testBtn = document.getElementById('test-url-btn');
    const urlInput = document.getElementById('url');
    const resultBox = document.getElementById('url-test-result');
    const submitBtn = document.getElementById('submit-btn');

    testBtn.addEventListener('click', async function () {
        const url = urlInput.value.trim();
        if (!url) {
            showResult('Please enter a URL first.', 'warning');
            return;
        }

        testBtn.disabled = true;
        testBtn.innerHTML = '<i class="fas fa-spin fa-spinner me-1"></i>Testing…';
        showResult('', 'info', '<i class="fas fa-spin fa-spinner me-1"></i>Probing URL…');

        try {
            const resp = await fetch '{{ route("library.kbart-remote-test-url") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ url }),
            });
            const data = await resp.json();

            if (data.ok) {
                showResult(
                    '<i class="fas fa-check-circle me-1"></i>' + data.hint +
                    ' (HTTP ' + data.status + ', ' + data.size.toLocaleString() + ' bytes)',
                    'success'
                );
            } else {
                showResult(
                    '<i class="fas fa-exclamation-triangle me-1"></i>' + data.hint,
                    'warning'
                );
            }
        } catch (e) {
            showResult('<i class="fas fa-exclamation-triangle me-1"></i>Request failed: ' + e.message, 'danger');
        } finally {
            testBtn.disabled = false;
            testBtn.innerHTML = '<i class="fas fa-plug me-1"></i>Test URL';
        }
    });

    function showResult(msg, type, initialMsg) {
        if (initialMsg !== undefined) {
            resultBox.style.display = '';
            resultBox.className = 'alert alert-' + type + ' mb-3';
            resultBox.innerHTML = initialMsg;
            return;
        }
        if (!msg) {
            resultBox.style.display = 'none';
            return;
        }
        resultBox.style.display = '';
        resultBox.className = 'alert alert-' + type + ' mb-3';
        resultBox.innerHTML = msg;
    }
})();
</script>
@endpush
