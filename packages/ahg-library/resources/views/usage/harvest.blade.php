@extends('theme::layouts.1col')
@section('title', 'SUSHI Harvest')

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h3 mb-1">
                <i class="fas fa-sync me-2"></i>SUSHI Harvest
            </h1>
            <p class="text-muted small mb-0">
                ISO 18626 — fetch COUNTER 5 usage statistics from all active SUSHI partners.
            </p>
        </div>
        <a href="{{ route('library.usage') }}" class="btn btn-outline-dark btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Back to Usage
        </a>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row g-4">

        {{-- Harvest form --}}
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Trigger Harvest</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted">
                        Fetches COUNTER 5 reports (PR, TR) from all active SUSHI partners
                        for the selected date range and stores results in
                        <code>library_usage_stats</code>.
                    </p>
                    <form method="GET" action="{{ route('library.usage-harvest') }}">
                        <div class="mb-2">
                            <label class="form-label small">From Date</label>
                            <input type="date" name="from"
                                   class="form-control form-control-sm"
                                   value="{{ now()->subMonth()->toDateString() }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">To Date</label>
                            <input type="date" name="to"
                                   class="form-control form-control-sm"
                                   value="{{ now()->toDateString() }}">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-play me-1"></i>Run Harvest
                        </button>
                    </form>
                </div>
            </div>

            {{-- Cron note --}}
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Automated Scheduling</h6>
                </div>
                <div class="card-body small">
                    <p>Add to your system crontab for automatic monthly harvests:</p>
                    <pre class="bg-dark text-light p-2 rounded small mb-2"><code>0 3 1 * * cd /usr/share/nginx/heratio && php artisan library:usage:harvest</code></pre>
                    <p class="text-muted mb-0">
                        Or configure the scheduler in <code>routes/console.php</code> to
                        run <code>library:usage:harvest</code> monthly.
                    </p>
                </div>
            </div>
        </div>

        {{-- How it works --}}
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">How SUSHI Harvesting Works</h6>
                </div>
                <div class="card-body p-0">
                    <ol class="mb-0">
                        <li class="p-3 border-bottom">
                            <strong>Credentials</strong> — partner base URLs, API keys, and customer IDs
                            are resolved from <code>ahg_settings</code> and/or the
                            <code>library_sushi_subscription</code> table.
                        </li>
                        <li class="p-3 border-bottom">
                            <strong>SUSHI request</strong> — a standards-compliant JSON body is POSTed to
                            <code>/sushi/v5/reports/{PR|TR|DR}</code>.
                        </li>
                        <li class="p-3 border-bottom">
                            <strong>Response parsing</strong> — COUNTER 5 JSON metrics are extracted and
                            normalised into <code>library_usage_stats</code> rows.
                        </li>
                        <li class="p-3 border-bottom">
                            <strong>Audit trail</strong> — raw JSON responses are stored in
                            <code>library_sushi_raw_responses</code> for debugging and reprocessing.
                        </li>
                        <li class="p-3">
                            <strong>Connection test</strong> — use the
                            <a href="{{ route('library.usage-subscriptions') }}">Partners page</a>
                            to verify each endpoint before running a full harvest.
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection