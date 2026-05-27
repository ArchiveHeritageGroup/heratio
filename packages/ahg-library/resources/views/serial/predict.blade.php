@extends('theme::layouts.1col')
@section('title', 'Predicted Issues: ' . ($serial->title ?? ''))
@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('library.serial-view', $serial->id ?? '') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="mb-0">Predicted Issues</h2>
            <span class="badge bg-primary mt-1">{{ e($serial->title ?? '') }}</span>
            <span class="badge bg-secondary mt-1 ms-1">{{ $frequencyLabel ?? '' }}</span>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="mb-1" id="next-date">{{ $next_expected ?? '—' }}</h3>
                    <p class="text-muted mb-0 small">Next expected issue</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="mb-1 {{ ($days_until_next ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                        {{ abs($days_until_next ?? 0) }} day(s)
                    </h3>
                    @if(($days_until_next ?? 0) < 0)
                        <p class="text-danger mb-0 small">Already overdue by {{ abs($days_until_next ?? 0) }} day(s)</p>
                    @else
                        <p class="text-muted mb-0 small">Days until next issue</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="mb-1">{{ count($predictions ?? []) }}</h3>
                    <p class="text-muted mb-0 small">Predictions shown (6-month window)</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Upcoming Issues</h5>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Volume</th>
                        <th>Issue Number</th>
                        <th>Expected Date</th>
                        <th>Days Until</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($predictions ?? [] as $pred)
                        <tr>
                            <td><strong>{{ e($pred['volume'] ?? '') }}</strong></td>
                            <td><code>{{ e($pred['issue_number'] ?? '') }}</code></td>
                            <td>{{ \Carbon\Carbon::parse($pred['expected_date'])->format('d M Y') }}</td>
                            <td>
                                @if(($pred['days_until'] ?? 0) < 0)
                                    <span class="badge bg-danger">{{ abs($pred['days_until']) }}d overdue</span>
                                @elseif(($pred['days_until'] ?? 0) <= 7)
                                    <span class="badge bg-warning text-dark">{{ $pred['days_until'] }}d</span>
                                @else
                                    <span class="badge bg-secondary">{{ $pred['days_until'] }}d</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">predicted</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-muted text-center py-4">
                                No predictions available. Add at least one received issue to generate predictions.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        <a href="{{ route('library.serial-view', $serial->id ?? '') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Serial
        </a>
    </div>
</div>
@endsection
