{{-- Audit Entry Detail - Migrated from AtoM: audit/viewSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'Audit Entry #' . ($entry->id ?? ''))

@section('content')
<h1><i class="fas fa-history text-primary me-2"></i>Audit Entry #{{ $entry->id ?? '' }}</h1>

<div class="row">
  <div class="col-md-6">
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-info-circle me-2"></i>Entry Details</div>
      <div class="card-body">
        @php $ac = match($entry->action ?? '') { 'create' => 'success', 'update' => 'warning', 'delete' => 'danger', default => 'secondary' }; @endphp
        <table class="table table-bordered mb-0">
          <tr><th style="width:30%;">Date/Time</th><td>{{ $entry->created_at ?? '' }}</td></tr>
          <tr><th>User</th><td>{{ e($entry->user_name ?? 'System') }}</td></tr>
          <tr><th>Action</th><td><span class="badge bg-{{ $ac }}">{{ ucfirst($entry->action ?? '') }}</span></td></tr>
          <tr><th>Table</th><td><code>{{ $entry->table_name ?? '' }}</code></td></tr>
          <tr><th>Record ID</th><td>#{{ $entry->record_id ?? '' }}</td></tr>
          @if($entry->field_name ?? false)<tr><th>Field</th><td><code>{{ $entry->field_name }}</code></td></tr>@endif
          <tr><th>IP Address</th><td>{{ $entry->ip_address ?? 'N/A' }}</td></tr>
          <tr><th>Description</th><td>{{ e($entry->action_description ?? 'N/A') }}</td></tr>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    @if($entry->field_name ?? false)
      <div class="card mb-4">
        <div class="card-header"><i class="fas fa-exchange-alt me-2"></i>Value Change</div>
        <div class="card-body">
          <div class="row">
            <div class="col-6">
              <label class="text-muted">Old Value</label>
              <div class="bg-light border rounded p-2"><pre class="mb-0 text-danger">{{ e($entry->old_value ?? '(empty)') }}</pre></div>
            </div>
            <div class="col-6">
              <label class="text-muted">New Value</label>
              <div class="bg-light border rounded p-2"><pre class="mb-0 text-success">{{ e($entry->new_value ?? '(empty)') }}</pre></div>
            </div>
          </div>
        </div>
      </div>
    @endif
    @if(!empty($changes))
      <div class="card mb-4">
        <div class="card-header"><i class="fas fa-list me-2"></i>All Changes</div>
        <div class="card-body p-0">
          <table class="table table-striped mb-0">
            <thead><tr><th>Field</th><th>Old</th><th>New</th></tr></thead>
            <tbody>
              @foreach($changes as $field => $change)
                <tr>
                  <td><code>{{ $field }}</code></td>
                  <td class="text-danger small">{{ e(Str::limit((string)($change['old'] ?? ''), 50)) }}</td>
                  <td class="text-success small">{{ e(Str::limit((string)($change['new'] ?? ''), 50)) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endif
  </div>
</div>
<a href="{{ route('audit.index') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back to Audit Log</a>
@endsection
