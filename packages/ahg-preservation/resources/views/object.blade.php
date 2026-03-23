@extends('theme::layouts.1col')
@section('title', 'Preservation Object Details')
@section('body-class', 'admin preservation')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-preservation::_menu')</div>
  <div class="col-md-9">
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('preservation.index') }}">Preservation</a></li>
        <li class="breadcrumb-item active">{{ $digitalObject->name ?? 'Object' }}</li>
      </ol>
    </nav>

    <h1><i class="fas fa-file me-2"></i>Preservation Object Details</h1>

    {{-- Object Info --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-info-circle me-2"></i>Digital Object Information</div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <table class="table table-sm">
              <tr><th width="150">ID</th><td>{{ $digitalObject->id ?? '' }}</td></tr>
              <tr><th>Filename</th><td>{{ $digitalObject->name ?? 'Unknown' }}</td></tr>
              <tr><th>Parent Object</th><td>
                @if($digitalObject->slug ?? null) <a href="{{ route('informationobject.show', $digitalObject->slug) }}">{{ $digitalObject->object_title ?? 'View' }}</a>
                @else - @endif
              </td></tr>
              <tr><th>File Size</th><td>{{ number_format($digitalObject->byte_size ?? 0) }} bytes</td></tr>
              <tr><th>MIME Type</th><td>{{ $digitalObject->mime_type ?? 'Unknown' }}</td></tr>
            </table>
          </div>
          <div class="col-md-6">
            @if($formatInfo ?? null)
            <div class="alert {{ ($formatInfo->risk_level ?? '') === 'low' ? 'alert-success' : (in_array($formatInfo->risk_level ?? '', ['high','critical']) ? 'alert-danger' : 'alert-warning') }}">
              <h6><i class="fas fa-file-code me-1"></i>Format Information</h6>
              <p class="mb-1"><strong>{{ $formatInfo->format_name ?? '' }}</strong></p>
              <p class="mb-1">Risk: <strong>{{ ucfirst($formatInfo->risk_level ?? 'unknown') }}</strong></p>
              @if($formatInfo->is_preservation_format ?? false) <span class="badge bg-success">Preservation Format</span> @endif
            </div>
            @endif
            <div class="d-grid gap-2">
              <button class="btn atom-btn-white" onclick="if(confirm('Generate checksums?')){window.location='{{ route('preservation.api.checksum.generate', $digitalObject->id ?? 0) }}'}">
                <i class="fas fa-sync me-1"></i>Regenerate Checksums
              </button>
              <button class="btn btn-outline-primary" onclick="if(confirm('Verify fixity?')){window.location='{{ route('preservation.api.fixity.verify', $digitalObject->id ?? 0) }}'}">
                <i class="fas fa-check-circle me-1"></i>Verify Fixity Now
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Checksums --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-fingerprint me-2"></i>Checksums</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-sm mb-0">
            <thead><tr><th>Algorithm</th><th>Value</th><th>Status</th><th>Generated</th><th>Last Verified</th></tr></thead>
            <tbody>
              @forelse($checksums ?? [] as $cs)
              <tr>
                <td><strong>{{ strtoupper($cs->algorithm ?? '') }}</strong></td>
                <td><code class="small">{{ $cs->checksum_value ?? $cs->value ?? '' }}</code></td>
                <td>
                    @if(($cs->verification_status ?? '') === 'verified' || ($cs->verification_status ?? '') === 'valid')
                        <span class="badge bg-success">Valid</span>
                    @elseif(($cs->verification_status ?? '') === 'failed' || ($cs->verification_status ?? '') === 'invalid')
                        <span class="badge bg-danger">Invalid</span>
                    @else
                        <span class="badge bg-secondary">{{ ucfirst($cs->verification_status ?? 'pending') }}</span>
                    @endif
                </td>
                <td><small>{{ $cs->generated_at ?? $cs->created_at ?? '' }}</small></td>
                <td><small>{{ $cs->verified_at ?? '-' }}</small></td>
              </tr>
              @empty
              <tr><td colspan="5" class="text-center text-muted py-3">No checksums generated</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Fixity Check History --}}
    <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-check-double me-2"></i>Fixity Check History</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-sm table-striped mb-0">
                    <thead><tr><th>Algorithm</th><th>Status</th><th>Expected</th><th>Actual</th><th>Checked By</th><th>Duration</th><th>Checked At</th></tr></thead>
                    <tbody>
                        @php $fixityHistory = $fixityHistory ?? collect(); @endphp
                        @forelse($fixityHistory as $check)
                        <tr>
                            <td>{{ strtoupper($check->algorithm ?? '') }}</td>
                            <td>
                                @if($check->status === 'pass')
                                    <span class="badge bg-success"><i class="fas fa-check"></i> Pass</span>
                                @elseif($check->status === 'fail')
                                    <span class="badge bg-danger"><i class="fas fa-times"></i> Fail</span>
                                @else
                                    <span class="badge bg-warning text-dark">{{ ucfirst($check->status ?? '') }}</span>
                                @endif
                            </td>
                            <td><code class="small">{{ Str::limit($check->expected_value ?? '', 16) }}</code></td>
                            <td><code class="small">{{ ($check->actual_value ?? null) ? Str::limit($check->actual_value, 16) : '-' }}</code></td>
                            <td>{{ $check->checked_by ?? '' }}</td>
                            <td>{{ ($check->duration_ms ?? null) ? $check->duration_ms . 'ms' : '-' }}</td>
                            <td><small>{{ $check->checked_at ?? '' }}</small></td>
                        </tr>
                        @if($check->error_message ?? null)
                        <tr>
                            <td colspan="7" class="bg-danger bg-opacity-10 border-0 py-1 ps-4">
                                <small class="text-danger"><i class="fas fa-exclamation-circle"></i> {{ $check->error_message }}</small>
                            </td>
                        </tr>
                        @endif
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No fixity checks performed yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- PREMIS Events --}}
    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-history me-2"></i>PREMIS Events</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-sm table-striped mb-0">
            <thead><tr><th>Date</th><th>Type</th><th>Outcome</th><th>Detail</th><th>Agent</th></tr></thead>
            <tbody>
              @forelse($events ?? [] as $event)
              <tr>
                <td><small>{{ $event->event_datetime ?? '' }}</small></td>
                <td><span class="badge bg-secondary">{{ $event->event_type ?? '' }}</span></td>
                <td>
                  @if(($event->event_outcome ?? '') === 'success') <span class="badge bg-success">Success</span>
                  @elseif(($event->event_outcome ?? '') === 'failure') <span class="badge bg-danger">Failure</span>
                  @else <span class="badge bg-warning text-dark">{{ ucfirst($event->event_outcome ?? 'unknown') }}</span> @endif
                </td>
                <td><small class="text-muted">{{ Str::limit($event->event_detail ?? '', 80) }}</small></td>
                <td><small>{{ $event->linking_agent_value ?? $event->linking_agent_type ?? '-' }}</small></td>
              </tr>
              @if($event->event_outcome_detail ?? null)
              <tr>
                <td colspan="5" class="bg-light border-0 py-1 ps-4">
                    <small class="text-muted"><i class="fas fa-info-circle"></i> {{ Str::limit($event->event_outcome_detail, 200) }}</small>
                </td>
              </tr>
              @endif
              @empty
              <tr><td colspan="5" class="text-center text-muted py-3">No events recorded</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
