@extends('theme::layouts.1col')

@section('title', 'DOI Details')
@section('body-class', 'admin doi view')

@section('content')
  @if(!($tablesExist ?? false))
    <div class="alert alert-warning">
      <i class="fas fa-exclamation-triangle me-2"></i>
      The DOI management tables have not been created yet. Please run the database migration to set up DOI management.
    </div>
  @else
    <div class="multiline-header d-flex align-items-center mb-3">
      <i class="fas fa-3x fa-fingerprint me-3" aria-hidden="true"></i>
      <div class="d-flex flex-column">
        <h1 class="mb-0">{{ $doi->doi }}</h1>
        <span class="small text-muted">DOI Details</span>
      </div>
      <div class="ms-auto">
        <a href="{{ route('doi.browse') }}" class="btn btn-sm atom-btn-white">
          <i class="fas fa-arrow-left me-1"></i> Back to Browse
        </a>
      </div>
    </div>

    {{-- DOI Details card --}}
    <div class="card mb-4">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">DOI Information</div>
      <div class="card-body">
        <table class="table table-bordered mb-0">
          <tbody>
            <tr>
              <th style="width: 200px;">DOI</th>
              <td><code>{{ $doi->doi }}</code></td>
            </tr>
            <tr>
              <th>Record</th>
              <td>
                @if($doi->information_object_id)
                  <a href="{{ route('informationobject.show', $doi->information_object_id) }}">
                    {{ $doi->record_title ?: '[Untitled]' }}
                  </a>
                @else
                  {{ $doi->record_title ?: '[Untitled]' }}
                @endif
              </td>
            </tr>
            <tr>
              <th>Status</th>
              <td>
                @if($doi->status === 'findable')
                  <span class="badge bg-success">Findable</span>
                @elseif($doi->status === 'registered')
                  <span class="badge bg-info">Registered</span>
                @else
                  <span class="badge bg-secondary">Draft</span>
                @endif
              </td>
            </tr>
            <tr>
              <th>Minted</th>
              <td>{{ $doi->minted_at ? \Carbon\Carbon::parse($doi->minted_at)->format('Y-m-d H:i:s') : 'Not yet minted' }}</td>
            </tr>
            <tr>
              <th>Created</th>
              <td>{{ $doi->created_at ? \Carbon\Carbon::parse($doi->created_at)->format('Y-m-d H:i:s') : '' }}</td>
            </tr>
            <tr>
              <th>Last Updated</th>
              <td>{{ $doi->updated_at ? \Carbon\Carbon::parse($doi->updated_at)->format('Y-m-d H:i:s') : '' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    {{-- Activity Log --}}
    <h3 class="mb-3">Activity Log</h3>
    @if(count($logs))
      <div class="table-responsive mb-3">
        <table class="table table-bordered table-striped mb-0">
          <thead>
            <tr style="background:var(--ahg-primary);color:#fff">
              <th>Event Type</th>
              <th>Details</th>
              <th>Performed At</th>
            </tr>
          </thead>
          <tbody>
            @foreach($logs as $log)
              <tr>
                <td><span class="badge bg-secondary">{{ $log['event_type'] }}</span></td>
                <td>{{ $log['details'] }}</td>
                <td>{{ $log['performed_at'] ? \Carbon\Carbon::parse($log['performed_at'])->format('Y-m-d H:i:s') : '' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div class="alert alert-info">No activity log entries for this DOI.</div>
    @endif
  @endif
@endsection
