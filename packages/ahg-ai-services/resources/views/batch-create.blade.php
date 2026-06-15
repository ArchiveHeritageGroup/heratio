{{--
    AI batch-create form.

    Rendered by AiController::batchCreate() on GET. Posts (fetch JSON) back to
    the same route (admin.ai.batch.create) which inserts an ahg_ai_batch row
    plus one ahg_ai_job per selected object x task type.

    Copyright (C) 2026 Johan Pieterse
    AGPL-3.0
--}}
@extends('theme::layouts.1col')

@section('title', 'Create AI Batch')
@section('body-class', 'browse ai')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-robot me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">{{ __('Create AI Batch') }}</h1></div>
  </div>

  <div id="batchAlert" class="alert d-none" role="alert"></div>

  <form id="batchForm" class="card">
    <div class="card-body">
      <div class="mb-3">
        <label for="batchName" class="form-label">{{ __('Batch name') }} <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="batchName" name="name" required>
      </div>

      <div class="mb-3">
        <label for="batchDescription" class="form-label">{{ __('Description') }}</label>
        <textarea class="form-control" id="batchDescription" name="description" rows="2"></textarea>
      </div>

      <div class="mb-3">
        <label class="form-label">{{ __('Task types') }} <span class="text-danger">*</span></label>
        <div class="row">
          @foreach([
            'ner'        => 'Named-entity recognition',
            'ocr'        => 'OCR',
            'htr'        => 'Handwritten text recognition',
            'summarize'  => 'Summarize',
            'translate'  => 'Translate',
            'spellcheck' => 'Spell check',
          ] as $value => $label)
            <div class="col-md-4">
              <div class="form-check">
                <input class="form-check-input task-type" type="checkbox" value="{{ $value }}" id="task_{{ $value }}">
                <label class="form-check-label" for="task_{{ $value }}">{{ __($label) }}</label>
              </div>
            </div>
          @endforeach
        </div>
      </div>

      <hr>
      <h5 class="mb-3">{{ __('Scope') }}</h5>

      <div class="mb-3">
        <label for="repositoryId" class="form-label">{{ __('Repository') }}</label>
        <select class="form-select" id="repositoryId" name="repository_id">
          <option value="">{{ __('— Select a repository —') }}</option>
          @foreach(($repositories ?? collect()) as $repo)
            <option value="{{ $repo->id }}">{{ $repo->name }}</option>
          @endforeach
        </select>
        <div class="form-text">{{ __('Queue every archival description in the chosen repository.') }}</div>
      </div>

      <div class="mb-3">
        <label for="objectIds" class="form-label">{{ __('Or specific object IDs') }}</label>
        <input type="text" class="form-control" id="objectIds" name="object_ids" placeholder="{{ __('Comma-separated IDs (overrides repository)') }}">
      </div>

      <div class="mb-3">
        <label for="batchLimit" class="form-label">{{ __('Limit') }}</label>
        <input type="number" class="form-control" id="batchLimit" name="limit" value="1000" min="1">
      </div>

      <hr>
      <h5 class="mb-3">{{ __('Options') }}</h5>

      <div class="row">
        <div class="col-md-3 mb-3">
          <label for="priority" class="form-label">{{ __('Priority') }}</label>
          <input type="number" class="form-control" id="priority" name="priority" value="5" min="1" max="10">
        </div>
        <div class="col-md-3 mb-3">
          <label for="maxConcurrent" class="form-label">{{ __('Max concurrent') }}</label>
          <input type="number" class="form-control" id="maxConcurrent" name="max_concurrent" value="5" min="1">
        </div>
        <div class="col-md-3 mb-3">
          <label for="delayBetween" class="form-label">{{ __('Delay between (ms)') }}</label>
          <input type="number" class="form-control" id="delayBetween" name="delay_between_ms" value="1000" min="0">
        </div>
        <div class="col-md-3 mb-3">
          <label for="maxRetries" class="form-label">{{ __('Max retries') }}</label>
          <input type="number" class="form-control" id="maxRetries" name="max_retries" value="3" min="0">
        </div>
      </div>

      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" value="1" id="autoStart" name="auto_start">
        <label class="form-check-label" for="autoStart">{{ __('Start immediately after creation') }}</label>
      </div>
    </div>

    <div class="card-footer">
      <button type="submit" class="btn atom-btn-secondary" id="batchSubmit">
        <i class="fas fa-plus me-1"></i>{{ __('Create batch') }}
      </button>
    </div>
  </form>
@endsection

@push('js')
<script>
document.getElementById('batchForm').addEventListener('submit', function(e) {
    e.preventDefault();

    var taskTypes = Array.prototype.slice
        .call(document.querySelectorAll('.task-type:checked'))
        .map(function(el) { return el.value; });

    var alertBox = document.getElementById('batchAlert');
    var show = function(cls, msg) {
        alertBox.className = 'alert ' + cls;
        alertBox.textContent = msg;
    };

    if (!document.getElementById('batchName').value.trim()) {
        show('alert-danger', '{{ __('Name is required') }}');
        return;
    }
    if (!taskTypes.length) {
        show('alert-danger', '{{ __('Select at least one task type') }}');
        return;
    }

    var payload = {
        name:             document.getElementById('batchName').value,
        description:      document.getElementById('batchDescription').value,
        task_types:       taskTypes,
        repository_id:    document.getElementById('repositoryId').value || null,
        object_ids:       document.getElementById('objectIds').value || null,
        limit:            document.getElementById('batchLimit').value || null,
        priority:         document.getElementById('priority').value || 5,
        max_concurrent:   document.getElementById('maxConcurrent').value || 5,
        delay_between_ms: document.getElementById('delayBetween').value || 1000,
        max_retries:      document.getElementById('maxRetries').value || 3,
        auto_start:       document.getElementById('autoStart').checked ? 1 : 0
    };

    var btn = document.getElementById('batchSubmit');
    btn.disabled = true;

    fetch('{{ route('admin.ai.batch.create') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(payload)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        btn.disabled = false;
        if (data.success) {
            show('alert-success', (data.message || 'Batch created') + '.');
            document.getElementById('batchForm').reset();
        } else {
            show('alert-danger', data.error || '{{ __('Could not create batch') }}');
        }
    })
    .catch(function() {
        btn.disabled = false;
        show('alert-danger', '{{ __('Network error') }}');
    });
});
</script>
@endpush
