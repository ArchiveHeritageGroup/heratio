@extends('theme::layouts.1col')
@section('title', 'Database Usage Report (DR)')

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h3 mb-1">
                <i class="fas fa-database me-2"></i>Database Usage Report (DR)
            </h1>
            <p class="text-muted small mb-0">
                COUNTER 5 Database Report &mdash;
                @if($fromDate && $toDate)
                    {{ \Carbon\Carbon::parse($fromDate)->format('M Y') }}
                    to {{ \Carbon\Carbon::parse($toDate)->format('M Y') }}
                @endif
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('library.usage-export', ['type' => 'DR', 'from' => $fromDate, 'to' => $toDate]) }}"
               class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-download me-1"></i>Export TSV
            </a>
            <a href="{{ route('library.usage') }}" class="btn btn-outline-dark btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Platform Report
            </a>
        </div>
    </div>

    {{-- Date range filter --}}
    <form method="GET" class="row g-2 mb-4">
        <div class="col-auto">
            <label class="form-label small text-muted mb-0">{{ __('From') }}</label>
            <input type="date" name="from" class="form-control form-control-sm" value="{{ $fromDate ?? '' }}">
        </div>
        <div class="col-auto">
            <label class="form-label small text-muted mb-0">{{ __('To') }}</label>
            <input type="date" name="to" class="form-control form-control-sm" value="{{ $toDate ?? '' }}">
        </div>
        <div class="col-auto d-flex align-items-end">
            <button type="submit" class="btn btn-primary btn-sm">{{ __('Filter') }}</button>
        </div>
    </form>

    @if(empty($report['Items']))
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            No database usage data available. Run a
            <a href="{{ route('library.usage-harvest') }}">SUSHI harvest</a> first.
        </div>
    @else
        <div class="card">
            <div class="table-responsive">
                <table class="table table-striped table-sm mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Metric') }}</th>
                            <th class="text-end">{{ __('Count') }}</th>
                            <th style="width: 30%;">{{ __('Share') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $totalCount = collect($report['Items'])->sum('Count');
                        @endphp
                        @foreach($report['Items'] as $item)
                            @if($item['Count'] > 0)
                                <tr>
                                    <td><span class="badge bg-secondary">{{ $item['Metric_Type'] }}</span></td>
                                    <td class="text-end fw-bold">{{ number_format($item['Count']) }}</td>
                                    <td>
                                        @php $pct = $totalCount > 0 ? round($item['Count'] / $totalCount * 100, 1) : 0; @endphp
                                        <div class="progress" style="height: 18px;">
                                            <div class="progress-bar bg-primary" role="progressbar"
                                                 style="width: {{ $pct }}%">{{ $pct }}%</div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="table-secondary fw-bold">
                            <td>Total</td>
                            <td class="text-end">{{ number_format($totalCount) }}</td>
                            <td>100%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="card-footer small text-muted">
                Report generated {{ now()->format('d M Y H:i') }} &middot;
                Period: {{ $fromDate ?? '—' }} to {{ $toDate ?? '—' }}
            </div>
        </div>
    @endif

</div>
@endsection