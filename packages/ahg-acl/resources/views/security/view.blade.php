{{-- View Classification - Migrated from AtoM: ahgSecurityClearancePlugin/modules/securityClearance/templates/viewSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'User Clearance - ' . e($targetUser->username ?? ''))

@section('content')

<div class="container mt-4">
  <div class="row">
    <div class="col-lg-10 mx-auto">
      <nav aria-label="{{ __('breadcrumb') }}">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
          <li class="breadcrumb-item"><a href="{{ route('acl.security-index') }}">Security Clearances</a></li>
          <li class="breadcrumb-item active">{{ e($targetUser->username ?? '') }}</li>
        </ol>
      </nav>

      @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
          {{ session('success') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif

      @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
          {{ session('error') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif

      <div class="row">
        {{-- User Info Card --}}
        <div class="col-md-4 mb-4">
          <div class="card">
            <div class="card-header bg-dark text-white">
              <h5 class="mb-0"><i class="fas fa-user me-2"></i>User Profile</h5>
            </div>
            <div class="card-body text-center">
              <div class="mb-3">
                <i class="fas fa-user-circle fa-5x text-muted"></i>
              </div>
              <h4>{{ e($targetUser->username ?? '') }}</h4>
              <p class="text-muted">{{ e($targetUser->email ?? '') }}</p>

              @if($clearance ?? null)
                @php
                $levelClass = 'secondary';
                if (($clearance->level ?? 0) >= 4) $levelClass = 'danger';
                elseif (($clearance->level ?? 0) >= 2) $levelClass = 'warning';
                else $levelClass = 'success';
                $isExpired = !empty($clearance->is_expired);
                @endphp
                <span class="badge bg-{{ $isExpired ? 'secondary' : $levelClass }} fs-5 mb-2">
                  {{ e($clearance->classification_name ?? $clearance->name ?? '') }}
                </span>
                @if($isExpired)
                  <br><span class="badge bg-danger fs-6 mb-2">{{ __('EXPIRED') }}</span>
                @endif
                <p class="small text-muted mb-0">Level {{ $clearance->level ?? 0 }}</p>
              @else
                <span class="badge bg-secondary fs-5">{{ __('No Clearance') }}</span>
              @endif
            </div>
            <div class="card-footer">
              <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#grantModal">
                <i class="fas fa-key me-1"></i>
                {{ ($clearance ?? null) ? 'Change Clearance' : 'Grant Clearance' }}
              </button>
            </div>
          </div>

          {{-- Current Clearance Details --}}
          @if($clearance ?? null)
            <div class="card mt-3">
              <div class="card-header">
                <h6 class="mb-0">{{ __('Clearance Details') }}</h6>
              </div>
              <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between">
                  <span>{{ __('Granted:') }}</span>
                  <strong>{{ ($clearance->granted_at ?? null) ? date('M j, Y', strtotime($clearance->granted_at)) : '-' }}</strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                  <span>{{ __('Expires:') }}</span>
                  <strong class="{{ !empty($clearance->is_expired) ? 'text-danger' : '' }}">
                    {{ ($clearance->expires_at ?? null) ? date('M j, Y', strtotime($clearance->expires_at)) : 'Never' }}
                    @if(!empty($clearance->is_expired)) (Expired)@endif
                  </strong>
                </li>
                @if($clearance->notes ?? null)
                  <li class="list-group-item">
                    <small class="text-muted">{{ e($clearance->notes) }}</small>
                  </li>
                @endif
              </ul>
            </div>
          @endif
        </div>

        {{-- Right Column --}}
        <div class="col-md-8">
          {{-- Object Access Grants --}}
          <div class="card mb-4">
            <div class="card-header bg-success text-white">
              <h5 class="mb-0"><i class="fas fa-folder-open me-2"></i>Object Access Grants</h5>
            </div>
            <div class="card-body p-0">
              @if(empty($accessGrants))
                <div class="p-4 text-center text-muted">
                  <p class="mb-0">No specific object access grants.</p>
                </div>
              @else
                <div class="table-responsive">
                  <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>{{ __('Object') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Scope') }}</th>
                        <th>{{ __('Access') }}</th>
                        <th>{{ __('Granted') }}</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($accessGrants as $grant)
                        <tr>
                          <td>
                            <strong>{{ e($grant->object_title ?? 'Unknown') }}</strong>
                          </td>
                          <td>
                            <span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $grant->object_type ?? '')) }}</span>
                          </td>
                          <td>
                            @if($grant->include_descendants ?? false)
                              <span class="badge bg-info">+ Children</span>
                            @else
                              <span class="badge bg-light text-dark">{{ __('Single') }}</span>
                            @endif
                          </td>
                          <td>
                            <span class="badge bg-{{ ($grant->access_level ?? '') === 'edit' ? 'danger' : (($grant->access_level ?? '') === 'download' ? 'warning' : 'success') }}">
                              {{ ucfirst($grant->access_level ?? '') }}
                            </span>
                          </td>
                          <td>{{ ($grant->granted_at ?? null) ? date('M j, Y', strtotime($grant->granted_at)) : '-' }}</td>
                          <td>
                            <form action="{{ route('acl.set-clearance') }}" method="POST" style="display:inline;"
                                  onsubmit="return confirm('Revoke this access?');">
                              @csrf
                              <input type="hidden" name="action_type" value="revoke_access_grant">
                              <input type="hidden" name="grant_id" value="{{ $grant->id ?? '' }}">
                              <input type="hidden" name="user_id" value="{{ $targetUser->id }}">
                              <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-times"></i>
                              </button>
                            </form>
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              @endif
            </div>
          </div>

          {{-- Clearance History --}}
          <div class="card">
            <div class="card-header">
              <h5 class="mb-0"><i class="fas fa-history me-2"></i>Clearance History</h5>
            </div>
            <div class="card-body p-0">
              @if(empty($history))
                <div class="p-4 text-center text-muted">
                  <p class="mb-0">No clearance history.</p>
                </div>
              @else
                <ul class="list-group list-group-flush">
                  @foreach($history as $entry)
                    <li class="list-group-item">
                      <div class="d-flex justify-content-between align-items-center">
                        <div>
                          <span class="badge bg-{{ ($entry->action ?? '') === 'granted' ? 'success' : (($entry->action ?? '') === 'revoked' ? 'danger' : 'info') }} me-2">
                            {{ ucfirst($entry->action ?? '') }}
                          </span>
                          @if($entry->classification_name ?? null)
                            <strong>{{ e($entry->classification_name) }}</strong>
                          @endif
                          @if($entry->notes ?? null)
                            <br><small class="text-muted">{{ e($entry->notes) }}</small>
                          @endif
                        </div>
                        <small class="text-muted">
                          {{ $entry->changed_by_name ?? 'System' }}<br>
                          {{ ($entry->created_at ?? null) ? date('M j, Y H:i', strtotime($entry->created_at)) : '' }}
                        </small>
                      </div>
                    </li>
                  @endforeach
                </ul>
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Grant Modal --}}
<div class="modal fade" id="grantModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="{{ route('acl.set-clearance') }}">
        @csrf
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title"><i class="fas fa-key me-2"></i>Grant Security Clearance</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="user_id" value="{{ $targetUser->id ?? '' }}">
          <p>Granting clearance to: <strong>{{ e($targetUser->username ?? '') }}</strong></p>

          <div class="mb-3">
            <label for="classification_id" class="form-label">{{ __('Clearance Level') }}</label>
            <select class="form-select" name="classification_id" required>
              <option value="0">-- Revoke Clearance --</option>
              @foreach($classifications ?? [] as $c)
                <option value="{{ $c->id }}" {{ ($clearance && ($clearance->classification_id ?? null) == $c->id) ? 'selected' : '' }}>
                  {{ e($c->name) }} (Level {{ $c->level }})
                </option>
              @endforeach
            </select>
          </div>

          <div class="mb-3">
            <label for="expires_at" class="form-label">{{ __('Expires (optional)') }}</label>
            <input type="date" class="form-control" name="expires_at"
                   value="{{ ($clearance && ($clearance->expires_at ?? null)) ? date('Y-m-d', strtotime($clearance->expires_at)) : '' }}">
          </div>

          <div class="mb-3">
            <label for="notes" class="form-label">{{ __('Notes') }}</label>
            <textarea class="form-control" name="notes" rows="2" placeholder="{{ __('Reason for change...') }}"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-success">
            <i class="fas fa-check me-1"></i> {{ __('Save Changes') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

@endsection
