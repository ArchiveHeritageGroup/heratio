{{--
  embedded-findings.blade.php - admin UI for ahg_pii_finding_embedded rows.

  Heratio Issue #751. Lists PII findings detected over embedded image
  metadata (EXIF / IPTC / XMP) populated by ahg-metadata-extraction.
  Bootstrap 5 + bi-* icons per Heratio admin theme.
--}}
@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="bi bi-shield-exclamation me-2"></i>{{ __('Embedded PII Findings') }}
        </h1>
        <a href="{{ route('ahgprivacy.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>{{ __('Back to Privacy Dashboard') }}
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(! $schemaReady)
        <div class="alert alert-warning">
            <i class="bi bi-info-circle me-1"></i>
            {{ __('The ahg_pii_finding_embedded table has not been installed yet. Boot the application once to auto-install the schema, or run the artisan backfill command.') }}
        </div>
    @endif

    {{-- Summary cards: one per pii_type with pending + resolved + total counts. --}}
    @if(!empty($summary))
        <div class="row mb-4">
            @foreach($summary as $row)
                <div class="col-md-3">
                    <div class="card border-{{ $row['pending'] > 0 ? 'warning' : 'secondary' }}">
                        <div class="card-body">
                            <div class="text-muted small text-uppercase">
                                @if($row['pii_type'] === 'gps_coordinate') <i class="bi bi-geo-alt"></i>
                                @elseif($row['pii_type'] === 'person_name') <i class="bi bi-person"></i>
                                @elseif($row['pii_type'] === 'person_contact') <i class="bi bi-person-vcard"></i>
                                @elseif($row['pii_type'] === 'sensitive_date') <i class="bi bi-calendar-event"></i>
                                @else <i class="bi bi-tag"></i>
                                @endif
                                {{ str_replace('_', ' ', $row['pii_type']) }}
                            </div>
                            <div class="d-flex justify-content-between mt-2">
                                <div>
                                    <div class="h4 mb-0 text-warning">{{ number_format($row['pending']) }}</div>
                                    <div class="small text-muted">{{ __('Pending') }}</div>
                                </div>
                                <div class="text-end">
                                    <div class="h4 mb-0 text-success">{{ number_format($row['resolved']) }}</div>
                                    <div class="small text-muted">{{ __('Resolved') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Filter bar. --}}
    <form method="GET" action="{{ route('ahgprivacy.embedded-findings.index') }}" class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">{{ __('PII type') }}</label>
                    <select name="pii_type" class="form-select form-select-sm">
                        <option value="">{{ __('All types') }}</option>
                        @foreach($piiTypes as $opt)
                            <option value="{{ $opt['code'] }}" @if($filterType === $opt['code']) selected @endif>
                                {{ $opt['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">{{ __('Resolution status') }}</label>
                    <select name="resolution_status" class="form-select form-select-sm">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach($resolutions as $opt)
                            <option value="{{ $opt['code'] }}" @if($filterStatus === $opt['code']) selected @endif>
                                {{ $opt['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 text-end">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-funnel me-1"></i>{{ __('Filter') }}
                    </button>
                    <a href="{{ route('ahgprivacy.embedded-findings.index') }}" class="btn btn-sm btn-outline-secondary">
                        {{ __('Reset') }}
                    </a>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-list-ul me-2"></i>{{ __('Findings') }}
                @if($pagination)
                    <span class="badge bg-secondary ms-2">{{ $pagination->total() }}</span>
                @endif
            </h5>
        </div>
        <div class="card-body p-0">
            @if($findings->isEmpty())
                <div class="p-4 text-muted text-center">
                    <i class="bi bi-check-circle me-1"></i>{{ __('No findings match the current filter.') }}
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('DO') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Source') }}</th>
                                <th>{{ __('Value') }}</th>
                                <th>{{ __('Confidence') }}</th>
                                <th>{{ __('Scanned') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th class="text-end">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($findings as $f)
                                <tr>
                                    <td>
                                        <code>#{{ $f->digital_object_id }}</code>
                                        @if(!empty($f->digital_object_name))
                                            <div class="small text-muted">{{ Str::limit($f->digital_object_name, 40) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark">{{ $f->pii_type }}</span>
                                    </td>
                                    <td>
                                        <code class="small">{{ $f->source_table }}.{{ $f->source_field }}</code>
                                    </td>
                                    <td>
                                        <span title="{{ $f->source_value }}">{{ Str::limit((string) $f->source_value, 60) }}</span>
                                    </td>
                                    <td>{{ number_format((float) $f->confidence, 2) }}</td>
                                    <td class="small text-muted">{{ $f->scanned_at }}</td>
                                    <td>
                                        @php
                                            $badge = match ($f->resolution_status) {
                                                'pending'    => 'bg-warning text-dark',
                                                'redacted'   => 'bg-success',
                                                'cleared'    => 'bg-secondary',
                                                'escalated'  => 'bg-danger',
                                                default      => 'bg-light text-dark',
                                            };
                                        @endphp
                                        <span class="badge {{ $badge }}">{{ $f->resolution_status }}</span>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal" data-bs-target="#resolve-modal-{{ $f->id }}">
                                            <i class="bi bi-pencil"></i> {{ __('Resolve') }}
                                        </button>
                                    </td>
                                </tr>

                                {{-- Per-row resolution modal. --}}
                                <div class="modal fade" id="resolve-modal-{{ $f->id }}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <form method="POST" action="{{ route('ahgprivacy.embedded-findings.resolve', $f->id) }}">
                                            @csrf
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">
                                                        <i class="bi bi-shield-check me-1"></i>{{ __('Resolve finding') }} #{{ $f->id }}
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="small text-muted mb-3">
                                                        {{ $f->source_table }}.{{ $f->source_field }} = <code>{{ Str::limit((string) $f->source_value, 80) }}</code>
                                                    </p>
                                                    <div class="mb-3">
                                                        <label class="form-label">{{ __('New resolution status') }}</label>
                                                        <select name="resolution_status" class="form-select" required>
                                                            @foreach($resolutions as $opt)
                                                                <option value="{{ $opt['code'] }}" @if($f->resolution_status === $opt['code']) selected @endif>
                                                                    {{ $opt['label'] }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">{{ __('Notes (optional)') }}</label>
                                                        <textarea name="notes" rows="3" class="form-control" maxlength="4000">{{ $f->notes }}</textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="bi bi-save me-1"></i>{{ __('Save') }}
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($pagination)
                    <div class="card-footer">
                        {{ $pagination->onEachSide(1)->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection
