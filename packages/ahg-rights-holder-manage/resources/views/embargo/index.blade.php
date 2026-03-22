@extends('theme::layouts.1col')

@section('title', 'Embargo Management')
@section('body-class', 'embargo index')

@section('title-block')
  <h1 class="mb-0">Embargo Management</h1>
@endsection

@section('content')

  {{-- Expiring Soon Alert --}}
  @if(isset($expiringEmbargoes) && count($expiringEmbargoes) > 0)
    <div class="alert alert-warning">
      <h5><i class="fas fa-exclamation-triangle"></i> Embargoes Expiring Within 30 Days</h5>
      <ul class="mb-0">
        @foreach($expiringEmbargoes->take(5) as $embargo)
          <li>
            <a href="{{ route('embargo.show', $embargo->id) }}">Object #{{ $embargo->object_id }}</a>
            - Expires: {{ $embargo->end_date }}
            @if(isset($embargo->days_remaining))
              ({{ $embargo->days_remaining }} days)
            @endif
          </li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- Active Embargoes --}}
  <div class="card">
    <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <h4 class="mb-0">Active Embargoes</h4>
    </div>
    <div class="card-body p-0">
      @if(isset($activeEmbargoes) && count($activeEmbargoes) > 0)
        <table class="table table-striped table-hover mb-0">
          <thead>
            <tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
              <th>Object</th>
              <th>Type</th>
              <th>Start Date</th>
              <th>End Date</th>
              <th>Reason</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($activeEmbargoes as $embargo)
              <tr>
                <td>#{{ $embargo->object_id }}</td>
                <td>
                  <span class="badge bg-{{ ($embargo->embargo_type ?? 'full') === 'full' ? 'danger' : 'warning' }}">
                    {{ ucfirst(str_replace('_', ' ', $embargo->embargo_type ?? 'full')) }}
                  </span>
                </td>
                <td>{{ $embargo->start_date ?? '-' }}</td>
                <td>
                  @if($embargo->is_perpetual ?? false)
                    <span class="text-danger">Perpetual</span>
                  @elseif($embargo->end_date ?? null)
                    {{ $embargo->end_date }}
                  @else
                    -
                  @endif
                </td>
                <td>{{ $embargo->reason ?? '-' }}</td>
                <td>
                  <a href="{{ route('embargo.show', $embargo->id) }}" class="btn btn-sm atom-btn-white">View</a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @else
        <p class="text-muted text-center py-4">No active embargoes.</p>
      @endif
    </div>
  </div>
@endsection
