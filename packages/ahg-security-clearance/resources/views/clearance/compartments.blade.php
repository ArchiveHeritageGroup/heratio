@extends('ahg-theme-b5::layout')

@section('title', 'Security Compartments')

@section('content')
<div class="container-fluid mt-3">
  <nav aria-label="{{ __('breadcrumb') }}"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('security-clearance.dashboard') }}">Security Dashboard</a></li>
    <li class="breadcrumb-item active">Compartments</li>
  </ol></nav>

  <h1><i class="fas fa-project-diagram"></i> Security Compartments</h1>

<div class="card">
    <div class="card-body table-responsive">
      <table class="table table-striped table-hover">
        <thead>
          <tr><th>{{ __('Name') }}</th><th>{{ __('Code') }}</th><th>{{ __('Description') }}</th><th>{{ __('Users') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th></tr>
        </thead>
        <tbody>
          @forelse($compartments ?? [] as $comp)
          <tr>
            <td><strong>{{ e($comp->name) }}</strong></td>
            <td><code>{{ e($comp->code ?? '') }}</code></td>
            <td>{{ e($comp->description ?? '') }}</td>
            <td>{{ $userCounts[$comp->id] ?? 0 }}</td>
            <td>
              @if($comp->active ?? 1)
                <span class="badge bg-success">Active</span>
              @else
                <span class="badge bg-secondary">Inactive</span>
              @endif
            </td>
            <td>
              <a href="{{ route('security-clearance.compartment-access', ['compartment_id' => $comp->id]) }}" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-users"></i> Manage Access
              </a>
            </td>
          </tr>
          @empty
          <tr><td colspan="6" class="text-muted">No compartments defined.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
