@extends('theme::layouts.1col')

@section('title', 'Review Publication Request')
@section('body-class', 'request-publish edit')

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
    <i class="fas fa-paper-plane fa-2x text-primary me-3" aria-hidden="true"></i>
    <div>
      <h1 class="h3 mb-0">Review Publication Request</h1>
      <p class="text-muted mb-0">
        Submitted: {{ $record->created_at ? \Carbon\Carbon::parse($record->created_at)->format('d M Y H:i') : '' }}
      </p>
    </div>
  </div>

  <form method="POST" action="{{ route('request-publish.update', $record->id) }}">
    @csrf

    <div class="row">
      <div class="col-lg-8">
        {{-- Request Information (readonly) --}}
        <div class="card shadow-sm mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="card-title mb-0">
              <i class="fas fa-info-circle me-2"></i>Request Information
            </h5>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label text-muted small">Requester Name</label>
                <p class="fw-semibold mb-0">
                  {{ ($record->rtp_name ?? '') . ' ' . ($record->rtp_surname ?? '') }}
                </p>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted small">Institution</label>
                <p class="fw-semibold mb-0">
                  {{ $record->rtp_institution ?: '-' }}
                </p>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted small">Email</label>
                <p class="mb-0">
                  @if(!empty($record->rtp_email))
                    <a href="mailto:{{ $record->rtp_email }}">
                      <i class="fas fa-envelope me-1"></i>{{ $record->rtp_email }}
                    </a>
                  @else
                    -
                  @endif
                </p>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted small">Phone</label>
                <p class="mb-0">
                  @if(!empty($record->rtp_phone))
                    <a href="tel:{{ $record->rtp_phone }}">
                      <i class="fas fa-phone me-1"></i>{{ $record->rtp_phone }}
                    </a>
                  @else
                    -
                  @endif
                </p>
              </div>
            </div>
          </div>
        </div>

        {{-- Requested Item --}}
        @if(!empty($record->object_title) || !empty($record->object_id))
        <div class="card shadow-sm mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="card-title mb-0">
              <i class="fas fa-file-alt me-2"></i>Requested Item
            </h5>
          </div>
          <div class="card-body">
            <div class="d-flex align-items-center">
              <div class="flex-grow-1">
                @if(!empty($record->object_title))
                  <h6 class="mb-1">
                    <a href="/{{ $record->object_slug ?? '' }}">
                      {{ $record->object_title }}
                    </a>
                  </h6>
                  @if(!empty($record->object_identifier))
                    <small class="text-muted">Identifier: {{ $record->object_identifier }}</small>
                  @endif
                @else
                  <span class="text-muted">Object #{{ $record->object_id }}</span>
                @endif
              </div>
              @if(!empty($record->object_slug))
                <a href="/{{ $record->object_slug }}" class="btn btn-sm atom-btn-white" target="_blank">
                  <i class="fas fa-external-link-alt me-1"></i>View Item
                </a>
              @endif
            </div>
          </div>
        </div>
        @endif

        {{-- Request Details (readonly) --}}
        <div class="card shadow-sm mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="card-title mb-0">
              <i class="fas fa-clipboard-list me-2"></i>Request Details
            </h5>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label text-muted small">Planned Use</label>
              <p class="mb-0">{!! !empty($record->rtp_planned_use) ? nl2br(e($record->rtp_planned_use)) : '-' !!}</p>
            </div>
            @if(!empty($record->rtp_motivation))
            <div class="mb-3">
              <label class="form-label text-muted small">Motivation</label>
              <p class="mb-0">{!! nl2br(e($record->rtp_motivation)) !!}</p>
            </div>
            @endif
            @if(!empty($record->rtp_need_image_by))
            <div>
              <label class="form-label text-muted small">Need Image By</label>
              <p class="mb-0">
                <span class="badge bg-info text-dark">
                  <i class="fas fa-calendar me-1"></i>{{ \Carbon\Carbon::parse($record->rtp_need_image_by)->format('d M Y') }}
                </span>
              </p>
            </div>
            @endif
          </div>
        </div>

        {{-- Admin Response --}}
        <div class="card shadow-sm mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="card-title mb-0">
              <i class="fas fa-reply me-2"></i>Admin Response
            </h5>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label for="rtp_admin_notes" class="form-label fw-semibold">Admin Notes</label>
              <textarea name="rtp_admin_notes" id="rtp_admin_notes" class="form-control" rows="4"
                        placeholder="Add notes for internal reference or to communicate with the requester...">{{ old('rtp_admin_notes', $record->rtp_admin_notes ?? '') }}</textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        {{-- Status Card --}}
        <div class="card shadow-sm mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="card-title mb-0">
              <i class="fas fa-flag me-2"></i>Status
            </h5>
          </div>
          <div class="card-body">
            <div class="text-center mb-3">
              @php
                $currentStatusId = (int) $record->status_id;
              @endphp
              <span class="badge {{ \AhgRequestPublish\Controllers\RequestPublishController::getStatusBadgeClass($currentStatusId) }} fs-5 px-4 py-2">
                {{ \AhgRequestPublish\Controllers\RequestPublishController::getStatusLabel($currentStatusId) }}
              </span>
            </div>
            <hr>
            <div class="mb-3">
              <label for="status_id" class="form-label fw-semibold">Change Status <span class="text-danger">*</span></label>
              <select name="status_id" id="status_id" class="form-select" required>
                <option value="220" @selected(old('status_id', $record->status_id) == 220)>In Review</option>
                <option value="219" @selected(old('status_id', $record->status_id) == 219)>Approved</option>
                <option value="221" @selected(old('status_id', $record->status_id) == 221)>Rejected</option>
              </select>
            </div>
            @if(!empty($record->completed_at))
              <p class="text-muted small mb-0">
                Completed on:<br>
                <strong>{{ \Carbon\Carbon::parse($record->completed_at)->format('d M Y H:i') }}</strong>
              </p>
            @endif
          </div>
        </div>

        {{-- Actions --}}
        <div class="card shadow-sm mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="card-title mb-0">
              <i class="fas fa-cogs me-2"></i>Actions
            </h5>
          </div>
          <div class="card-body">
            <div class="d-grid gap-2">
              <button type="submit" class="btn atom-btn-outline-success">
                <i class="fas fa-save me-1"></i>Save Changes
              </button>
              <a href="{{ route('request-publish.browse') }}" class="btn atom-btn-white">
                <i class="fas fa-arrow-left me-1"></i>Back to List
              </a>
            </div>
          </div>
        </div>

        {{-- Timeline --}}
        <div class="card shadow-sm">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="card-title mb-0">
              <i class="fas fa-history me-2"></i>Timeline
            </h5>
          </div>
          <div class="card-body">
            <ul class="list-unstyled mb-0">
              <li class="mb-2">
                <i class="fas fa-plus-circle text-primary me-2"></i>
                <small class="text-muted">Submitted</small><br>
                <strong>{{ \Carbon\Carbon::parse($record->created_at)->format('d M Y H:i') }}</strong>
              </li>
              @if(!empty($record->completed_at))
              <li>
                <i class="fas fa-check-circle text-success me-2"></i>
                <small class="text-muted">Completed</small><br>
                <strong>{{ \Carbon\Carbon::parse($record->completed_at)->format('d M Y H:i') }}</strong>
              </li>
              @endif
            </ul>
          </div>
        </div>
      </div>
    </div>
  </form>
@endsection
