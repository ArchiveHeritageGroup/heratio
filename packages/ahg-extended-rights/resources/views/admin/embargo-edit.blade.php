@extends('theme::layouts.2col')

@section('title', isset($embargo) ? 'Edit Embargo' : 'New Embargo')
@section('body-class', 'admin rights-admin embargo-edit')

@section('sidebar')
  @include('ahg-extended-rights::admin._sidebar')
@endsection

@section('title-block')
  <h1 class="mb-0">
    <i class="fas fa-clock me-2"></i>
    {{ isset($embargo) ? 'Edit Embargo' : 'New Embargo' }}
  </h1>
@endsection

@section('content')
  <form method="post" action="{{ isset($embargo) ? route('ext-rights-admin.embargo-update', $embargo->id) : route('ext-rights-admin.embargo-create') }}">
    @csrf
    <div class="row">
      <div class="col-lg-8">

        {{-- Object Selection --}}
        <div class="card mb-4">
          <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
            <h5 class="mb-0">Target Object</h5>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Information Object ID <span class="text-danger">*</span></label>
              <input type="number" name="object_id" class="form-control" required
                     value="{{ old('object_id', $embargo->object_id ?? request('object_id', '')) }}"
                     {{ isset($embargo) ? 'readonly' : '' }}>
              <small class="form-text text-muted">Enter the ID of the information object to embargo</small>
            </div>
          </div>
        </div>

        {{-- Embargo Details --}}
        <div class="card mb-4">
          <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
            <h5 class="mb-0">Embargo Details</h5>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Embargo Type <span class="text-danger">*</span></label>
                <select name="embargo_type" class="form-select" required>
                  @foreach($formOptions['embargo_type_options'] as $value => $label)
                  <option value="{{ $value }}" {{ (old('embargo_type', $embargo->embargo_type ?? 'full')) === $value ? 'selected' : '' }}>
                    {{ $label }}
                  </option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Reason <span class="text-danger">*</span></label>
                <select name="reason" class="form-select" required>
                  @foreach($formOptions['embargo_reason_options'] as $value => $label)
                  <option value="{{ $value }}" {{ (old('reason', $embargo->reason ?? '')) === $value ? 'selected' : '' }}>
                    {{ $label }}
                  </option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Start Date <span class="text-danger">*</span></label>
                <input type="date" name="start_date" class="form-control" required
                       value="{{ old('start_date', $embargo->start_date ?? date('Y-m-d')) }}">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-control"
                       value="{{ old('end_date', $embargo->end_date ?? '') }}">
                <small class="form-text text-muted">Leave empty for indefinite embargo</small>
              </div>
            </div>

            <div class="mb-3">
              <div class="form-check">
                <input type="checkbox" name="auto_release" class="form-check-input" id="auto_release" value="1"
                       {{ old('auto_release', $embargo->auto_release ?? 1) ? 'checked' : '' }}>
                <label class="form-check-label" for="auto_release">
                  Automatically lift embargo when end date is reached
                </label>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Reason Note</label>
              <textarea name="reason_note" class="form-control" rows="3">{{ old('reason_note', $embargo->reason_note ?? '') }}</textarea>
            </div>
          </div>
        </div>

        {{-- Review Settings --}}
        <div class="card mb-4">
          <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
            <h5 class="mb-0">Review Settings</h5>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Review Date</label>
                <input type="date" name="review_date" class="form-control"
                       value="{{ old('review_date', $embargo->review_date ?? '') }}">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Review Interval (months)</label>
                <input type="number" name="review_interval_months" class="form-control" min="1" max="120"
                       value="{{ old('review_interval_months', $embargo->review_interval_months ?? 12) }}">
              </div>
            </div>
          </div>
        </div>

        {{-- Notification Settings --}}
        <div class="card mb-4">
          <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
            <h5 class="mb-0">Notifications</h5>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">Notify Before (days)</label>
                <input type="number" name="notify_before_days" class="form-control" min="1" max="365"
                       value="{{ old('notify_before_days', $embargo->notify_before_days ?? 30) }}">
              </div>
              <div class="col-md-8 mb-3">
                <label class="form-label">Notification Emails</label>
                <input type="text" name="notify_emails" class="form-control" placeholder="email1@example.com, email2@example.com"
                       value="{{ old('notify_emails', isset($embargo->notify_emails) ? implode(', ', json_decode($embargo->notify_emails, true) ?? []) : '') }}">
                <small class="form-text text-muted">Comma-separated list of emails</small>
              </div>
            </div>
          </div>
        </div>

        {{-- Internal Note --}}
        <div class="card mb-4">
          <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
            <h5 class="mb-0">Internal Note</h5>
          </div>
          <div class="card-body">
            <textarea name="internal_note" class="form-control" rows="3"
                      placeholder="Internal notes not visible to users">{{ old('internal_note', $embargo->internal_note ?? '') }}</textarea>
          </div>
        </div>

      </div>

      {{-- Sidebar --}}
      <div class="col-lg-4">
        @if(isset($embargo) && isset($embargoLog) && count($embargoLog) > 0)
        <div class="card mb-4">
          <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
            <h5 class="mb-0">History</h5>
          </div>
          <div class="card-body p-0">
            <ul class="list-group list-group-flush">
              @foreach($embargoLog as $log)
              <li class="list-group-item">
                <strong>{{ ucfirst($log->action) }}</strong>
                <br>
                <small class="text-muted">{{ \Carbon\Carbon::parse($log->performed_at)->format('d M Y H:i') }}</small>
                @if($log->notes)
                  <br><small>{{ $log->notes }}</small>
                @endif
              </li>
              @endforeach
            </ul>
          </div>
        </div>
        @endif

        <div class="card">
          <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
            <h5 class="mb-0">Embargo Types</h5>
          </div>
          <div class="card-body">
            <dl class="mb-0">
              <dt>Full</dt>
              <dd class="text-muted small">Complete restriction - no access to metadata or digital objects</dd>

              <dt>Metadata Only</dt>
              <dd class="text-muted small">Metadata visible, digital objects hidden</dd>

              <dt>Digital Only</dt>
              <dd class="text-muted small">Digital objects hidden, metadata visible</dd>

              <dt>Partial</dt>
              <dd class="text-muted small mb-0">Custom restrictions based on user roles</dd>
            </dl>
          </div>
        </div>
      </div>
    </div>

    <section class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;">
      <a href="{{ route('ext-rights-admin.embargoes') }}" class="btn atom-btn-outline-light">Cancel</a>
      <button type="submit" class="btn atom-btn-outline-light">
        <i class="fas fa-save me-1"></i>Save Embargo
      </button>
    </section>
  </form>
@endsection
