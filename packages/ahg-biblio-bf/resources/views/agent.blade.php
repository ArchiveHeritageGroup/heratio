{{-- ahg-biblio-bf/agent.blade.php — Browse BIBFRAME Agents --}}
@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h4 mb-0"><i class="bi bi-person"></i> BIBFRAME Agents</h1>
      <p class="small text-muted mb-0">Authority records for persons and corporate bodies linked to BIBFRAME works</p>
    </div>
    <a href="{{ route('bibframe.index') }}" class="btn btn-outline-secondary btn-sm">&larr; Back</a>
  </div>

  @if($agents->isEmpty())
    <div class="alert alert-info">No agents found. Agents are the contributors recorded on catalogue records - add creators to a library record, or import a BIBFRAME document.</div>
  @else
    <div class="table-responsive">
      <table class="table table-sm table-striped">
        <thead>
          <tr>
            <th>{{ __('ID') }}</th>
            <th>{{ __('Name') }}</th>
            <th>{{ __('Type') }}</th>
            <th>{{ __('Created') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($agents as $agent)
            <tr>
              <td>{{ $agent->id }}</td>
              <td>{{ $agent->name }}</td>
              <td>
                <span class="badge bg-secondary">{{ $agent->type ?? 'aut' }}</span>
              </td>
              <td>{{ $agent->created_at ? $agent->created_at->toDateString() : '-' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

</div>
@endsection
