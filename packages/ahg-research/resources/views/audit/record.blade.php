{{-- Audit Record History - Migrated from AtoM: audit/recordSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'History: ' . ($tableName ?? '') . ' #' . ($recordId ?? ''))

@section('content')
<h1><i class="fas fa-history text-primary me-2"></i>History: {{ $tableName ?? '' }} #{{ $recordId ?? '' }}</h1>

@if(empty($timeline))
  <div class="alert alert-info">No history found for this record.</div>
@else
  @foreach($timeline as $date => $entries)
    <div class="card mb-3">
      <div class="card-header bg-light"><i class="fas fa-calendar me-2"></i>{{ $date }}</div>
      <ul class="list-group list-group-flush">
        @foreach($entries as $entry)
          <li class="list-group-item">
            @php
              $bc = match($entry->action) { 'create' => 'success', 'update' => 'warning', 'delete' => 'danger', default => 'secondary' };
            @endphp
            <span class="badge bg-{{ $bc }}">{{ ucfirst($entry->action) }}</span>
            <small class="text-muted ms-2">{{ date('H:i:s', strtotime($entry->created_at)) }}</small>
            <span class="ms-2">by <strong>{{ e($entry->user_name ?? 'System') }}</strong></span>
            @if($entry->field_name)
              <br><small><code>{{ $entry->field_name }}</code>: <span class="text-danger">{{ e(Str::limit($entry->old_value ?? '', 30)) }}</span> &rarr; <span class="text-success">{{ e(Str::limit($entry->new_value ?? '', 30)) }}</span></small>
            @endif
          </li>
        @endforeach
      </ul>
    </div>
  @endforeach
@endif
<a href="{{ route('audit.index') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
@endsection
