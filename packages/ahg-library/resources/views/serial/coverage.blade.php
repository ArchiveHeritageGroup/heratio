@extends('theme::layouts.1col')
@section('title', 'Coverage: ' . ($serial->title ?? ''))
@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('library.serial-view', $serial->id ?? '') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="mb-0">Coverage Statistics</h2>
            <span class="badge bg-primary mt-1">{{ e($serial->title ?? '') }}</span>
        </div>
    </div>

    {{-- Stats overview --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-center border-primary">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-primary">{{ $stats['received_count'] ?? 0 }}</h4>
                    <small class="text-muted">Received Issues</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-success">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-success">{{ $stats['claimed_count'] ?? 0 }}</h4>
                    <small class="text-muted">Claimed Issues</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-danger">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-danger">{{ $stats['missing_count'] ?? 0 }}</h4>
                    <small class="text-muted">Missing Issues</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-secondary">
                <div class="card-body py-3">
                    <h4 class="mb-0">{{ $stats['total_count'] ?? 0 }}</h4>
                    <small class="text-muted">Total Issues</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="mb-1">{{ $stats['active_years'] ?? 0 }}</h3>
                    <p class="text-muted mb-0">Active Years</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="mb-1">{{ $stats['complete_pct'] ?? 0 }}%</h3>
                    <p class="text-muted mb-0">Completeness</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="mb-1">{{ e($serial->frequency ?? '') }}</h3>
                    <p class="text-muted mb-0">Frequency</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Issue history with gap analysis --}}
    <div class="card shadow-sm">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Issue History (Gap Analysis)</h5>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Volume</th>
                        <th>Issue No.</th>
                        <th>Issue Date</th>
                        <th>Received At</th>
                        <th>Status</th>
                        <th>Gap (days)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($history ?? [] as $h)
                        @php
                            $gapDays = $h['gap_days'] ?? null;
                            $gapClass = $gapDays === null ? '' : ($gapDays > 90 ? 'table-danger' : ($gapDays > 45 ? 'table-warning' : ''));
                        @endphp
                        <tr class="{{ $gapClass }}">
                            <td>{{ e($h['issue']->volume ?? '') }}</td>
                            <td><code>{{ e($h['issue']->issue_number ?? '') }}</code></td>
                            <td>{{ $h['issue']->issue_date ? \Carbon\Carbon::parse($h['issue']->issue_date)->format('d M Y') : '—' }}</td>
                            <td>{{ $h['issue']->received_at ? \Carbon\Carbon::parse($h['issue']->received_at)->format('d M Y') : '—' }}</td>
                            <td>
                                @if(($h['issue']->status ?? '') === 'received')
                                    <span class="badge bg-success">received</span>
                                @elseif(($h['issue']->status ?? '') === 'claimed')
                                    <span class="badge bg-danger">claimed</span>
                                @else
                                    <span class="badge bg-secondary">missing</span>
                                @endif
                            </td>
                            <td>
                                @if($gapDays !== null)
                                    <span class="{{ $gapDays > 90 ? 'text-danger fw-bold' : ($gapDays > 45 ? 'text-warning' : 'text-muted') }}">
                                        {{ $gapDays }}d
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-muted text-center py-4">No issues recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
