@extends('theme::layouts.1col')
@section('title', __('Contribute a correction'))

@section('content')
<div class="row justify-content-center">
  <div class="col-lg-8">
    <h1 class="h3 mb-3"><i class="fas fa-people-group me-2"></i>{{ __('Contribute a correction') }}</h1>
    <p class="text-muted">
      {{ __('Have a shared offline package with corrections or additions? Submit them here for a curator to review. You do not need an account - your suggestions go into a review queue and nothing changes on the record until a curator approves it.') }}
    </p>

    @if(session('success'))
      <div class="alert alert-success"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
    @endif
    @if(session('info'))
      <div class="alert alert-info">{{ session('info') }}</div>
    @endif
    @if(session('error'))
      <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-1"></i>{{ session('error') }}</div>
    @endif

    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-upload me-1"></i>{{ __('Submit for review') }}
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('research.communitySync') }}" enctype="multipart/form-data">
          @csrf
          <div class="mb-3">
            <label for="submitter_name" class="form-label">{{ __('Your name') }} <span class="text-danger">*</span></label>
            <input type="text" name="submitter_name" id="submitter_name" class="form-control" value="{{ old('submitter_name') }}" required maxlength="191">
          </div>
          <div class="mb-3">
            <label for="submitter_email" class="form-label">{{ __('Your email') }} <span class="text-danger">*</span></label>
            <input type="email" name="submitter_email" id="submitter_email" class="form-control" value="{{ old('submitter_email') }}" required maxlength="191">
            <div class="form-text">{{ __('So a curator can follow up if needed. Not published.') }}</div>
          </div>
          <div class="mb-3">
            <label for="sync_file" class="form-label">{{ __('Your package file') }} <span class="text-danger">*</span></label>
            <input type="file" name="sync_file" id="sync_file" class="form-control" accept=".json,application/json" required>
            <div class="form-text">{{ __('The researcher-sync.json file from inside your shared offline package (25 MB maximum).') }}</div>
          </div>
          <button type="submit" class="btn atom-btn-outline-success">
            <i class="fas fa-paper-plane me-1"></i>{{ __('Submit for curator review') }}
          </button>
        </form>
      </div>
    </div>

    <p class="text-muted small mt-3">
      <i class="fas fa-shield-halved me-1"></i>{{ __('Submissions are verified against the shared package and moderated. Nothing is applied automatically.') }}
    </p>
  </div>
</div>
@endsection
