@extends('theme::layouts.1col')
@section('title', 'Usage Statistics — ' . $reportType)

@section('content')
<div class="container py-4">

    {{-- Page header --}}
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div class="d-flex align-items-start">
            <a href="{{ route('library.index') }}" class="btn btn-outline-secondary btn-sm me-3 mt-1" title="{{ __('Back to Library') }}"><i class="fas fa-arrow-left"></i></a>
            <div>
            <h1 class="h3 mb-1">
                <i class="fas fa-chart-bar me-2"></i>
                {{ $reportType === 'PR' ? 'Platform Usage Report' : "{$reportType} Report" }}
            </h1>
            <p class="text-muted small mb-0">
                COUNTER 5 aggregated statistics
                @if($fromDate && $toDate)
                    &mdash; {{ \Carbon\Carbon::parse($fromDate)->format('M Y') }}
                    to {{ \Carbon\Carbon::parse($toDate)->format('M Y') }}
                @endif
            </p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-download me-1"></i> Export
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('library.usage-export', ['type' => 'PR']) }}">PR (Platform)</a></li>
                    <li><a class="dropdown-item" href="{{ route('library.usage-export', ['type' => 'TR']) }}">TR (Title)</a></li>
                    <li><a class="dropdown-item" href="{{ route('library.usage-export', ['type' => 'DR']) }}">DR (Database)</a></li>
                </ul>
            </div>
            <a href="{{ route('library.usage-harvest') }}" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-sync me-1"></i> Harvest
            </a>
            <a href="{{ route('library.usage-subscriptions') }}" class="btn btn-outline-dark btn-sm">
                <i class="fas fa-server me-1"></i> Partners
            </a>
        </div>
    </div>

    {{-- Report type tabs --}}
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link @if($reportType === 'PR') active @endif" data-bs-toggle="tab"
                    data-bs-target="#tab-pr" type="button" role="tab">
                PR — Platform
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link @if($reportType === 'TR') active @endif"
               href="{{ route('library.usage-tr', ['from' => $fromDate, 'to' => $toDate]) }}"
               role="tab">
                TR — Title
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link @if($reportType === 'DR') active @endif"
               href="{{ route('library.usage-dr', ['from' => $fromDate, 'to' => $toDate]) }}"
               role="tab">
                DR — Database
            </a>
        </li>
    </ul>

    <div class="tab-content" id="myTabContent">
        <div class="tab-pane fade show active" id="tab-pr" role="tabpanel">

            {{-- Period selector --}}
            <form method="GET" action="{{ route('library.usage') }}" class="row g-2 mb-4">
                <div class="col-auto">
                    <label class="form-label small text-muted mb-0">{{ __('From') }}</label>
                    <input type="date" name="from" class="form-control form-control-sm"
                           value="{{ $fromDate ?? '' }}">
                </div>
                <div class="col-auto">
                    <label class="form-label small text-muted mb-0">{{ __('To') }}</label>
                    <input type="date" name="to" class="form-control form-control-sm"
                           value="{{ $toDate ?? '' }}">
                </div>
                <div class="col-auto">
                    <label class="form-label small text-muted mb-0">{{ __('Period') }}</label>
                    <select name="period" class="form-select form-select-sm">
                        <option value="weekly"   @if($period === 'weekly')   selected @endif>{{ __('Weekly') }}</option>
                        <option value="monthly"  @if($period === 'monthly')  selected @endif>{{ __('Monthly') }}</option>
                        <option value="quarterly" @if($period === 'quarterly') selected @endif>{{ __('Quarterly') }}</option>
                        <option value="yearly"   @if($period === 'yearly')   selected @endif>{{ __('Yearly') }}</option>
                    </select>
                </div>
                <div class="col-auto d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Filter') }}</button>
                </div>
            </form>

            {{-- Empty state --}}
            @if(empty($stats['periods']))
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No usage statistics recorded yet.
                    <a href="{{ route('library.usage-harvest') }}">Run a SUSHI harvest</a>
                    to collect data from your content providers.
                </div>
            @else
                {{-- Totals cards --}}
                <div class="row g-3 mb-4">
                    @php
                        $metricLabels = [
                            'Total_Item_Requests'       => 'Total Requests',
                            'Unique_Item_Requests'      => 'Unique Requests',
                            'Total_Item_Investigations'  => 'Investigations',
                            'Access_Denied'              => 'Access Denied',
                            'Access_Denied_GBV'          => 'GBV Denied',
                            'Open_Access_Count'         => 'Open Access',
                        ];
                    @endphp
                    @foreach($stats['totals'] as $metric => $total)
                        @if($total > 0)
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="card text-center">
                                    <div class="card-body py-2">
                                        <div class="h5 mb-0">{{ number_format($total) }}</div>
                                        <small class="text-muted">{{ $metricLabels[$metric] ?? $metric }}</small>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                {{-- Period table --}}
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">{{ __('Period Breakdown') }}</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Period') }}</th>
                                    @foreach(array_keys($stats['periods'][0]['data'] ?? []) as $metric)
                                        <th class="text-end">{{ $metricLabels[$metric] ?? $metric }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stats['periods'] as $periodRow)
                                    <tr>
                                        <td><strong>{{ $periodRow['label'] }}</strong></td>
                                        @foreach($periodRow['data'] as $count)
                                            <td class="text-end">{{ $count > 0 ? number_format($count) : '—' }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="table-secondary fw-bold">
                                    <td>Total</td>
                                    @foreach($stats['totals'] as $total)
                                        <td class="text-end">{{ number_format($total) }}</td>
                                    @endforeach
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {{-- Partners summary --}}
                @if(!empty($subscriptions))
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="mb-0">{{ __('Active SUSHI Partners') }}</h6>
                        </div>
                        <ul class="list-group list-group-flush small">
                            @foreach($subscriptions as $sub)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>
                                        <code>{{ $sub['partner_code'] }}</code>
                                        &mdash; {{ $sub['base_url'] }}
                                    </span>
                                    <span class="badge bg-success">Active</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endif

        </div>
    </div>
</div>
@endsection