{{-- My Access Requests - Migrated from AtoM: ahgAccessRequestPlugin/templates/myRequestsSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'My Access Requests')

@section('content')
<div class="container py-4">

  <nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
      <li class="breadcrumb-item active">My Access Requests</li>
    </ol>
  </nav>

  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- Current Status Card --}}
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-user-shield me-2"></i>{{ __('My Security Status') }}</h5>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6">
          <p class="mb-1"><strong>{{ __('Current Clearance Level:') }}</strong></p>
          @if($currentClearance)
            <span class="badge fs-6" style="background-color:{{ $currentClearance->color ?? '#6c757d' }};">
              {{ e($currentClearance->classification_name) }}
            </span>
            <small class="text-muted d-block mt-1">
              Granted: {{ $currentClearance->granted_at ? \Carbon\Carbon::parse($currentClearance->granted_at)->format('M j, Y') : '-' }}
            </small>
          @else
            <span class="badge bg-secondary fs-6">{{ __('No Clearance') }}</span>
          @endif
        </div>
        <div class="col-md-6 text-md-end">
          <a href="{{ route('accessRequest.create') }}" class="btn atom-btn-white">
            <i class="fas fa-arrow-up me-1"></i> {{ __('Request Higher Clearance') }}
          </a>
        </div>
      </div>
    </div>
  </div>

  {{-- Object Access Grants --}}
  @if($accessGrants->isNotEmpty())
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0"><i class="fas fa-key me-2"></i>{{ __('My Access Grants') }}</h5>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-hover mb-0">
            <thead>
              <tr>
                <th>{{ __('Object') }}</th>
                <th>{{ __('Type') }}</th>
                <th>{{ __('Scope') }}</th>
                <th>{{ __('Access') }}</th>
                <th>{{ __('Granted') }}</th>
                <th>{{ __('Expires') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($accessGrants as $grant)
                <tr>
                  <td><strong>{{ e($grant->object_title ?? 'Unknown') }}</strong></td>
                  <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $grant->object_type ?? '')) }}</span></td>
                  <td>
                    @if($grant->include_descendants ?? false)
                      <span class="badge bg-info">+ Children</span>
                    @else
                      <span class="badge bg-light text-dark">{{ __('Single') }}</span>
                    @endif
                  </td>
                  <td>
                    @php
                      $accessColor = match($grant->access_level ?? '') {
                        'edit' => 'danger', 'download' => 'warning', default => 'success'
                      };
                    @endphp
                    <span class="badge bg-{{ $accessColor }}">{{ ucfirst($grant->access_level ?? 'view') }}</span>
                  </td>
                  <td>
                    {{ $grant->granted_at ? \Carbon\Carbon::parse($grant->granted_at)->format('M j, Y') : '-' }}
                    <br><small class="text-muted">by {{ e($grant->granted_by_name ?? 'System') }}</small>
                  </td>
                  <td>
                    @if($grant->expires_at)
                      @php $isExpired = \Carbon\Carbon::parse($grant->expires_at)->isPast(); @endphp
                      <span class="{{ $isExpired ? 'text-danger' : '' }}">
                        {{ \Carbon\Carbon::parse($grant->expires_at)->format('M j, Y') }}
                      </span>
                    @else
                      <span class="text-muted">{{ __('Never') }}</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif

  {{-- Request History --}}
  <div class="card">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-history me-2"></i>{{ __('Request History') }}</h5>
    </div>
    <div class="card-body p-0">
      @if($requests->isEmpty())
        <div class="p-4 text-center text-muted">
          <i class="fas fa-inbox fa-3x mb-3"></i>
          <p>You haven't submitted any access requests yet.</p>
        </div>
      @else
        <div class="table-responsive">
          <table class="table table-bordered table-hover mb-0">
            <thead>
              <tr>
                <th>{{ __('Type') }}</th>
                <th>{{ __('Requested') }}</th>
                <th>{{ __('Urgency') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Submitted') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($requests as $req)
                <tr>
                  <td>
                    @php
                      $typeIcons = ['clearance' => 'fa-user-shield', 'object' => 'fa-file-alt', 'repository' => 'fa-building', 'authority' => 'fa-user-tie'];
                      $icon = $typeIcons[$req->request_type ?? ''] ?? 'fa-question';
                    @endphp
                    <i class="fas {{ $icon }} me-1"></i> {{ ucfirst($req->request_type ?? '-') }}
                  </td>
                  <td>
                    <strong>{{ e($req->requested_classification ?? $req->justification ?? 'N/A') }}</strong>
                  </td>
                  <td>
                    @php
                      $urgency = strtolower($req->priority ?? $req->urgency ?? 'normal');
                      $urgencyColor = match($urgency) {
                        'critical' => 'danger', 'high' => 'warning', 'normal' => 'info', default => 'secondary'
                      };
                    @endphp
                    <span class="badge bg-{{ $urgencyColor }}">{{ ucfirst($urgency) }}</span>
                  </td>
                  <td>
                    @php
                      $statusColors = ['pending' => 'warning', 'approved' => 'success', 'denied' => 'danger', 'cancelled' => 'secondary', 'expired' => 'dark'];
                    @endphp
                    <span class="badge bg-{{ $statusColors[$req->status ?? ''] ?? 'secondary' }}">{{ ucfirst($req->status ?? 'Pending') }}</span>
                  </td>
                  <td>{{ $req->created_at ? \Carbon\Carbon::parse($req->created_at)->format('M j, Y H:i') : '-' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>

</div>
@endsection
