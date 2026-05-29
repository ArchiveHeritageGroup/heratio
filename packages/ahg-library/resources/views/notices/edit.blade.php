@extends('theme::layouts.1col')
@section('title', __('Edit Notice Template'))
@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('library.notice-templates.index') }}"
           class="btn btn-outline-secondary btn-sm me-3"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h2 class="mb-0">{{ __('Edit Notice Template') }}</h2>
            <span class="badge bg-primary mt-1"><code>{{ e($template->notice_type) }}</code> / {{ e($template->channel) }}</span>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul></div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('library.notice-templates.update', $template->id) }}" id="notice-form">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="subject" class="form-label">{{ __('Subject') }}</label>
                    <input type="text" name="subject" id="subject" class="form-control"
                           maxlength="255" required value="{{ old('subject', $template->subject) }}">
                </div>

                <div class="mb-3">
                    <label for="body" class="form-label">{{ __('Body') }}</label>
                    <textarea name="body" id="body" class="form-control" rows="14" required>{{ old('body', $template->body) }}</textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="trigger_days_overdue" class="form-label">{{ __('Trigger (days overdue)') }}</label>
                        <input type="number" name="trigger_days_overdue" id="trigger_days_overdue"
                               class="form-control" min="0" max="3650"
                               value="{{ old('trigger_days_overdue', $template->trigger_days_overdue) }}">
                        <div class="form-text">{{ __('Minimum days past due before this tier fires (0 for hold-ready).') }}</div>
                    </div>
                    <div class="col-md-6 mb-3 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1"
                                   @checked(old('is_active', $template->is_active))>
                            <label for="is_active" class="form-check-label">{{ __('Active') }}</label>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button>
                    <button type="button" class="btn btn-outline-secondary" id="preview-btn">
                        <i class="fas fa-eye me-1"></i>{{ __('Preview') }}
                    </button>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">{{ __('Placeholder Tokens') }}</div>
                <div class="card-body small">
                    @foreach($tokens as $tok)
                        <div><code>&#123;&#123;{{ $tok }}&#125;&#125;</code></div>
                    @endforeach
                </div>
            </div>
            <div class="card" id="preview-card" style="display:none;">
                <div class="card-header">{{ __('Preview') }}</div>
                <div class="card-body">
                    <strong id="preview-subject"></strong>
                    <hr>
                    <pre id="preview-body" class="mb-0" style="white-space:pre-wrap;"></pre>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('preview-btn').addEventListener('click', function () {
    var data = new FormData();
    data.append('subject', document.getElementById('subject').value);
    data.append('body', document.getElementById('body').value);
    data.append('_token', document.querySelector('input[name=_token]').value);

    fetch('{{ route('library.notice-templates.preview', $template->id) }}', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: data
    })
    .then(function (r) { return r.json(); })
    .then(function (j) {
        if (!j.success) { return; }
        document.getElementById('preview-subject').textContent = j.rendered.subject;
        document.getElementById('preview-body').textContent = j.rendered.body;
        document.getElementById('preview-card').style.display = 'block';
    })
    .catch(function () {});
});
</script>
@endsection
