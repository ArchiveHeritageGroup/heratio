@extends('theme::layouts.1col')

@section('title', 'Edit Feedback')
@section('body-class', 'feedback edit')

@section('content')
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-2x fa-comment-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Edit Feedback</h1>
      <span class="small text-muted">#{{ $feedback->id }}</span>
    </div>
  </div>

  <form method="POST" action="{{ route('feedback.update', $feedback->id) }}">
    @csrf

    <div class="row justify-content-center">
      <div class="col-lg-8">

        {{-- Related record --}}
        @if($feedback->parent_id)
        <div class="card shadow-sm mb-3">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <i class="fas fa-link me-2"></i>Related Record
          </div>
          <div class="card-body">
            <a href="{{ url('/' . $feedback->parent_id) }}" class="btn atom-btn-white w-100">
              <i class="fas fa-file me-2"></i>View information object: {{ $feedback->parent_id }}
            </a>
          </div>
        </div>
        @endif

        {{-- Feedback Details (readonly) --}}
        <div class="card shadow-sm mb-3">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <i class="fas fa-comment-alt me-2"></i>Feedback Details
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label fw-semibold">Subject <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" class="form-control" value="{{ $feedback->name }}" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Remarks <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea class="form-control" rows="5" readonly>{{ $feedback->remarks }}</textarea>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Type <span class="badge bg-secondary ms-1">Optional</span></label>
                @php
                  $typeMap = [
                      0 => 'General',
                      1 => 'Error',
                      2 => 'Suggestion',
                      3 => 'Correction',
                      4 => 'Assistance',
                  ];
                @endphp
                <input type="text" class="form-control" value="{{ $typeMap[(int)$feedback->feed_type_id] ?? 'Unknown' }}" readonly>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Submitted <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control"
                       value="{{ $feedback->created_at ? \Carbon\Carbon::parse($feedback->created_at)->format('d M Y H:i') : '' }}" readonly>
              </div>
            </div>
          </div>
        </div>

        {{-- Contact Info (readonly) --}}
        <div class="card shadow-sm mb-3">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <i class="fas fa-address-card me-2"></i>Contact Information
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Name <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control" value="{{ $feedback->feed_name }} {{ $feedback->feed_surname }}" readonly>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Email <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control" value="{{ $feedback->feed_email }}" readonly>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Phone <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control" value="{{ $feedback->feed_phone }}" readonly>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Relationship <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control" value="{{ $feedback->feed_relationship }}" readonly>
              </div>
            </div>
          </div>
        </div>

        {{-- Admin Actions --}}
        <div class="card shadow-sm mb-3">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <i class="fas fa-cog me-2"></i>Admin Actions
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label fw-semibold">Status <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
              <select name="status" id="statusSelect" class="form-select" required>
                <option value="pending" @selected(old('status', $feedback->status) === 'pending')>Pending</option>
                <option value="completed" @selected(old('status', $feedback->status) === 'completed')>Completed</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Admin Notes <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="admin_notes" class="form-control" rows="4"
                        placeholder="Internal notes about this feedback...">{{ old('admin_notes', $feedback->unique_identifier) }}</textarea>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Completed At <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="datetime-local" name="completed_at" id="completedAt" class="form-control"
                     value="{{ old('completed_at', $feedback->completed_at ? \Carbon\Carbon::parse($feedback->completed_at)->format('Y-m-d\TH:i') : '') }}">
              <small class="text-muted">Auto-filled when status is set to Completed (if left blank).</small>
            </div>
          </div>
        </div>

        {{-- Actions --}}
        <div class="card shadow-sm">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
              <a href="{{ route('feedback.browse') }}" class="btn atom-btn-white">
                <i class="fas fa-arrow-left me-1"></i>Back
              </a>
              <button type="submit" class="btn atom-btn-outline-success">
                <i class="fas fa-save me-1"></i>Save
              </button>
            </div>
          </div>
        </div>

      </div>
    </div>
  </form>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const statusSelect = document.getElementById('statusSelect');
      const completedAt = document.getElementById('completedAt');

      statusSelect.addEventListener('change', function () {
        if (this.value === 'completed' && !completedAt.value) {
          // Auto-fill completed_at with current datetime
          const now = new Date();
          const pad = (n) => String(n).padStart(2, '0');
          completedAt.value = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate())
            + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
        }
      });
    });
  </script>
@endsection
