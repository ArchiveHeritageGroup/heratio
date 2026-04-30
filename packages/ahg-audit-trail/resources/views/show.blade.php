@extends('theme::layouts.1col')

@section('title', 'Audit entry #' . $entry->id)
@section('body-class', 'show audit')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-clipboard-check me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Audit entry #{{ $entry->id }}</h1>
      <span class="small text-muted">{{ __('Audit trail details') }}</span>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <table class="table table-bordered mb-0">
        <tbody>
          @if($table === 'ahg_audit_log')
            <tr>
              <th style="width: 200px;">{{ __('ID') }}</th>
              <td>{{ $entry->id }}</td>
            </tr>
            <tr>
              <th>UUID</th>
              <td>{{ $entry->uuid ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('Action') }}</th>
              <td>
                @php
                  $badgeClass = match($entry->action ?? '') {
                    'create' => 'bg-success',
                    'update' => 'bg-primary',
                    'delete' => 'bg-danger',
                    default => 'bg-secondary',
                  };
                @endphp
                <span class="badge {{ $badgeClass }}">{{ $entry->action }}</span>
              </td>
            </tr>
            <tr>
              <th>{{ __('Entity type') }}</th>
              <td>{{ $entry->entity_type ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('Entity ID') }}</th>
              <td>{{ $entry->entity_id ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('Entity slug') }}</th>
              <td>{{ $entry->entity_slug ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('Entity title') }}</th>
              <td>{{ $entry->entity_title ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('User') }}</th>
              <td>{{ $entry->username ?? 'N/A' }} ({{ $entry->user_email ?? '' }})</td>
            </tr>
            <tr>
              <th>{{ __('User ID') }}</th>
              <td>{{ $entry->user_id ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('IP address') }}</th>
              <td>{{ $entry->ip_address ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('User agent') }}</th>
              <td class="text-break small">{{ $entry->user_agent ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('Session ID') }}</th>
              <td>{{ $entry->session_id ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('Module') }}</th>
              <td>{{ $entry->module ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('Action name') }}</th>
              <td>{{ $entry->action_name ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('Request method') }}</th>
              <td>{{ $entry->request_method ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('Request URI') }}</th>
              <td class="text-break">{{ $entry->request_uri ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('Security classification') }}</th>
              <td>{{ $entry->security_classification ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('Status') }}</th>
              <td>
                @if($entry->status)
                  @php
                    $statusClass = match($entry->status) {
                      'success' => 'bg-success',
                      'error', 'failed' => 'bg-danger',
                      default => 'bg-secondary',
                    };
                  @endphp
                  <span class="badge {{ $statusClass }}">{{ $entry->status }}</span>
                @else
                  N/A
                @endif
              </td>
            </tr>
            <tr>
              <th>{{ __('Error message') }}</th>
              <td>{{ $entry->error_message ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('Culture') }}</th>
              <td>{{ $entry->culture_id ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('Created at') }}</th>
              <td>{{ $entry->created_at ? \Carbon\Carbon::parse($entry->created_at)->format('Y-m-d H:i:s') : 'N/A' }}</td>
            </tr>
          @else
            {{-- audit_log fallback --}}
            <tr>
              <th style="width: 200px;">{{ __('ID') }}</th>
              <td>{{ $entry->id }}</td>
            </tr>
            <tr>
              <th>{{ __('Action') }}</th>
              <td>
                @php
                  $badgeClass = match($entry->action ?? '') {
                    'create' => 'bg-success',
                    'update' => 'bg-primary',
                    'delete' => 'bg-danger',
                    default => 'bg-secondary',
                  };
                @endphp
                <span class="badge {{ $badgeClass }}">{{ $entry->action }}</span>
              </td>
            </tr>
            <tr>
              <th>{{ __('Table name') }}</th>
              <td>{{ $entry->table_name ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('Record ID') }}</th>
              <td>{{ $entry->record_id ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('Field name') }}</th>
              <td>{{ $entry->field_name ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('User') }}</th>
              <td>{{ $entry->username ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('User ID') }}</th>
              <td>{{ $entry->user_id ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('IP address') }}</th>
              <td>{{ $entry->ip_address ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('User agent') }}</th>
              <td class="text-break small">{{ $entry->user_agent ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('Module') }}</th>
              <td>{{ $entry->module ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('Action description') }}</th>
              <td>{{ $entry->action_description ?? 'N/A' }}</td>
            </tr>
            <tr>
              <th>{{ __('Created at') }}</th>
              <td>{{ $entry->created_at ? \Carbon\Carbon::parse($entry->created_at)->format('Y-m-d H:i:s') : 'N/A' }}</td>
            </tr>
          @endif
        </tbody>
      </table>
    </div>
  </div>

  {{-- JSON value display --}}
  @if($table === 'ahg_audit_log')
    @if($entry->old_values)
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0">{{ __('Old values') }}</h5>
        </div>
        <div class="card-body p-0">
          <pre class="mb-0 p-3" style="max-height: 400px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;">{{ json_encode(json_decode($entry->old_values), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: $entry->old_values }}</pre>
        </div>
      </div>
    @endif

    @if($entry->new_values)
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0">{{ __('New values') }}</h5>
        </div>
        <div class="card-body p-0">
          <pre class="mb-0 p-3" style="max-height: 400px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;">{{ json_encode(json_decode($entry->new_values), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: $entry->new_values }}</pre>
        </div>
      </div>
    @endif

    @if($entry->changed_fields)
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0">{{ __('Changed fields') }}</h5>
        </div>
        <div class="card-body p-0">
          <pre class="mb-0 p-3" style="max-height: 400px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;">{{ json_encode(json_decode($entry->changed_fields), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: $entry->changed_fields }}</pre>
        </div>
      </div>
    @endif

    @if($entry->metadata)
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0">{{ __('Metadata') }}</h5>
        </div>
        <div class="card-body p-0">
          <pre class="mb-0 p-3" style="max-height: 400px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;">{{ json_encode(json_decode($entry->metadata), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: $entry->metadata }}</pre>
        </div>
      </div>
    @endif
  @else
    @if($entry->old_value)
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0">{{ __('Old value') }}</h5>
        </div>
        <div class="card-body p-0">
          <pre class="mb-0 p-3" style="max-height: 400px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;">{{ $entry->old_value }}</pre>
        </div>
      </div>
    @endif

    @if($entry->new_value)
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0">{{ __('New value') }}</h5>
        </div>
        <div class="card-body p-0">
          <pre class="mb-0 p-3" style="max-height: 400px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;">{{ $entry->new_value }}</pre>
        </div>
      </div>
    @endif

    @if($entry->old_record)
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0">{{ __('Old record (full)') }}</h5>
        </div>
        <div class="card-body p-0">
          <pre class="mb-0 p-3" style="max-height: 400px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;">{{ json_encode(json_decode($entry->old_record), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: $entry->old_record }}</pre>
        </div>
      </div>
    @endif

    @if($entry->new_record)
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0">{{ __('New record (full)') }}</h5>
        </div>
        <div class="card-body p-0">
          <pre class="mb-0 p-3" style="max-height: 400px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;">{{ json_encode(json_decode($entry->new_record), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: $entry->new_record }}</pre>
        </div>
      </div>
    @endif
  @endif

  <div>
    <a href="{{ route('audit.browse') }}" class="btn atom-btn-white">
      <i class="fas fa-arrow-left me-1"></i> {{ __('Back to audit trail') }}
    </a>
  </div>
@endsection
