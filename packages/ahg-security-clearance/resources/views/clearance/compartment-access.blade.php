@extends('ahg-theme-b5::layout')

@section('title', 'Compartment Access')

@section('content')
<div class="container-fluid mt-3">
  <nav aria-label="{{ __('breadcrumb') }}"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('security-clearance.dashboard') }}">Security Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('security-clearance.compartments') }}">Compartments</a></li>
    <li class="breadcrumb-item active">Access Grants</li>
  </ol></nav>

  <h1><i class="fas fa-key"></i> Compartment Access Grants</h1>

<div class="card">
    <div class="card-body table-responsive">
      <table class="table table-striped table-hover">
        <thead>
          <tr><th>{{ __('User') }}</th><th>{{ __('Compartment') }}</th><th>{{ __('Granted By') }}</th><th>{{ __('Granted At') }}</th><th>{{ __('Expires') }}</th><th>{{ __('Actions') }}</th></tr>
        </thead>
        <tbody>
          @forelse($grants ?? [] as $grant)
          <tr>
            <td>{{ e($grant->username ?? '') }}</td>
            <td><code>{{ e($grant->compartment_name ?? '') }}</code></td>
            <td>{{ e($grant->granted_by_name ?? '') }}</td>
            <td>{{ $grant->granted_at ?? '' }}</td>
            <td>{{ $grant->expires_at ?? 'Never' }}</td>
            <td>
              <a href="{{ route('security-clearance.view', ['id' => $grant->user_id ?? 0]) }}" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-user"></i> View User
              </a>
            </td>
          </tr>
          @empty
          <tr><td colspan="6" class="text-muted">No compartment access grants.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
