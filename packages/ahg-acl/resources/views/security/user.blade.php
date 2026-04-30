{{-- User Security Clearance - Migrated from AtoM: ahgSecurityClearancePlugin/modules/securityClearance/templates/userSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'User Security Clearance')

@section('content')

<div class="row">
  <div class="col-md-12">

    <h1 class="multiline">
      User security clearance
      <span class="sub">{{ e($user->username ?? '') }}</span>
    </h1>

    @if(session('success'))
      <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    @if(request('success'))
      <div class="alert alert-success alert-dismissible fade show">
        @if(request('success') === 'updated')
          Security clearance has been updated successfully.
        @elseif(request('success') === 'revoked')
          Security clearance has been revoked.
        @endif
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    <section id="content">

      <div class="row mb-4">
        {{-- Current Clearance --}}
        <div class="col-md-8">
          <div class="card">
            <div class="card-header bg-primary text-white">
              <h5 class="mb-0">
                <i class="fas fa-shield-alt me-2"></i>{{ __('Security Clearance Settings') }}
              </h5>
            </div>
            <div class="card-body">

              @if($clearance ?? null)
                <div class="alert alert-info mb-4">
                  <div class="d-flex align-items-center">
                    <i class="fas fa-info-circle fa-2x me-3"></i>
                    <div>
                      <strong>{{ __('Current Clearance:') }}</strong>
                      <span class="badge fs-6" style="background-color: {{ $clearance->classificationColor ?? $clearance->color ?? '#666' }};">
                        {{ $clearance->classificationName ?? $clearance->name ?? '' }}
                      </span>
                      <br>
                      <small class="text-muted">
                        Granted by {{ $clearance->grantedByUsername ?? $clearance->granted_by_username ?? 'System' }}
                        on {{ ($clearance->grantedAt ?? $clearance->granted_at ?? null) ? date('F j, Y', strtotime($clearance->grantedAt ?? $clearance->granted_at)) : 'Unknown' }}
                        @if($clearance->expiresAt ?? $clearance->expires_at ?? null)
                          | Expires: {{ date('F j, Y', strtotime($clearance->expiresAt ?? $clearance->expires_at)) }}
                        @endif
                      </small>
                    </div>
                  </div>
                </div>
              @else
                <div class="alert alert-warning mb-4">
                  <i class="fas fa-exclamation-triangle me-2"></i>
                  {{ __('This user does not have a security clearance. They can only access public documents.') }}
                </div>
              @endif

              <form method="post" action="{{ route('acl.set-clearance') }}">
                @csrf
                <input type="hidden" name="user_id" value="{{ $user->id ?? '' }}">

                <div class="mb-4">
                  <label for="classification_id" class="form-label fw-bold">
                    <i class="fas fa-lock me-1"></i>Clearance Level
                  </label>
                  <select name="classification_id" id="classification_id" class="form-select form-select-lg">
                    <option value="">-- Select Classification --</option>
                    @foreach($classifications ?? [] as $c)
                      <option value="{{ $c->id }}"
                              {{ ($clearance && ($clearance->classificationId ?? $clearance->classification_id ?? null) == $c->id) ? 'selected' : '' }}
                              style="background-color: {{ $c->color }}20;">
                        {{ $c->name }} (Level {{ $c->level }})
                      </option>
                    @endforeach
                  </select>
                  <div class="form-text">
                    Select the maximum classification level this user should be able to access.
                  </div>
                </div>

                <div class="mb-4">
                  <label for="expires_at" class="form-label fw-bold">
                    <i class="fas fa-calendar-times me-1"></i>Expiry Date
                  </label>
                  <input type="date" name="expires_at" id="expires_at" class="form-control"
                         value="{{ ($clearance && ($clearance->expiresAt ?? $clearance->expires_at ?? null)) ? date('Y-m-d', strtotime($clearance->expiresAt ?? $clearance->expires_at)) : '' }}"
                         min="{{ date('Y-m-d', strtotime('+1 day')) }}">
                  <div class="form-text">
                    Leave empty for no automatic expiry.
                  </div>
                </div>

                <div class="mb-4">
                  <label for="notes" class="form-label fw-bold">
                    <i class="fas fa-sticky-note me-1"></i>Notes
                  </label>
                  <textarea name="notes" id="notes" class="form-control" rows="3"
                            placeholder="{{ __('Enter any notes about this clearance...') }}">{{ $clearance->notes ?? '' }}</textarea>
                </div>

                <div class="d-flex justify-content-between border-top pt-3">
                  <div>
                    @if($clearance ?? null)
                      <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#revokeModal">
                        <i class="fas fa-times me-1"></i>{{ __('Revoke Clearance') }}
                      </button>
                    @endif
                  </div>
                  <button type="submit" name="action_type" value="update" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>
                    {{ ($clearance ?? null) ? 'Update Clearance' : 'Grant Clearance' }}
                  </button>
                </div>

              </form>

            </div>
          </div>
        </div>

        {{-- Sidebar --}}
        <div class="col-md-4">
          {{-- Classification Guide --}}
          <div class="card mb-4">
            <div class="card-header bg-light">
              <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>{{ __('Classification Levels') }}</h6>
            </div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item d-flex justify-content-between"><span class="badge bg-success">{{ __('Public') }}</span><small class="text-muted">{{ __('Level 0') }}</small></li>
              <li class="list-group-item d-flex justify-content-between"><span class="badge bg-info">{{ __('Internal') }}</span><small class="text-muted">{{ __('Level 1') }}</small></li>
              <li class="list-group-item d-flex justify-content-between"><span class="badge bg-warning text-dark">{{ __('Restricted') }}</span><small class="text-muted">{{ __('Level 2') }}</small></li>
              <li class="list-group-item d-flex justify-content-between"><span class="badge" style="background-color:#fd7e14;color:white;">{{ __('Confidential') }}</span><small class="text-muted">{{ __('Level 3') }}</small></li>
              <li class="list-group-item d-flex justify-content-between"><span class="badge bg-danger">{{ __('Secret') }}</span><small class="text-muted">{{ __('Level 4') }}</small></li>
              <li class="list-group-item d-flex justify-content-between"><span class="badge" style="background-color:#6f42c1;color:white;">{{ __('Top Secret') }}</span><small class="text-muted">{{ __('Level 5') }}</small></li>
            </ul>
          </div>

          {{-- History --}}
          @if(!empty($history))
            <div class="card">
              <div class="card-header bg-light">
                <h6 class="mb-0"><i class="fas fa-history me-2"></i>{{ __('Change History') }}</h6>
              </div>
              <ul class="list-group list-group-flush">
                @foreach(array_slice(is_array($history) ? $history : $history->toArray(), 0, 5) as $record)
                  @php $record = is_array($record) ? (object)$record : $record; @endphp
                  <li class="list-group-item small">
                    <div class="d-flex justify-content-between">
                      <span class="badge {{ ($record->action ?? '') === 'revoked' ? 'bg-danger' : (($record->action ?? '') === 'granted' ? 'bg-success' : 'bg-info') }}">
                        {{ ucfirst($record->action ?? '') }}
                      </span>
                      <small class="text-muted">{{ date('Y-m-d', strtotime($record->created_at ?? '')) }}</small>
                    </div>
                    @if($record->new_name ?? null)
                      <small>{{ ($record->previous_name ?? 'None') }} &rarr; {{ $record->new_name }}</small>
                    @endif
                  </li>
                @endforeach
              </ul>
            </div>
          @endif
        </div>
      </div>

    </section>

  </div>
</div>

{{-- Revoke Modal --}}
@if($clearance ?? null)
<div class="modal fade" id="revokeModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="{{ route('acl.set-clearance') }}">
        @csrf
        <input type="hidden" name="user_id" value="{{ $user->id ?? '' }}">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>{{ __('Revoke Clearance') }}</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to revoke the security clearance for <strong>{{ e($user->username ?? '') }}</strong>?</p>
          <div class="mb-3">
            <label class="form-label">Reason <span class="text-danger">*</span></label>
            <textarea name="revoke_reason" class="form-control" rows="2" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" name="action_type" value="revoke" class="btn btn-danger">{{ __('Revoke Clearance') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif

@endsection
