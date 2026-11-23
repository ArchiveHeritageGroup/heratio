@extends('theme::layouts.1col')
@section('title', 'End of Day')
@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 d-print-none">
        <div class="d-flex align-items-center">
            <a href="{{ route('library.cash-desk.index') }}" class="btn btn-outline-secondary btn-sm me-3" title="{{ __('Back') }}"><i class="fas fa-arrow-left"></i></a>
            <h1 class="mb-0">{{ __('End of Day') }}</h1>
        </div>
        <form method="get" class="d-flex gap-2 align-items-center">
            <label for="date" class="form-label mb-0">{{ __('Date') }}</label>
            <input id="date" type="date" name="date" value="{{ $date }}" class="form-control form-control-sm w-auto">
            <button class="btn btn-sm atom-btn-white" type="submit">{{ __('Show') }}</button>
            <button class="btn btn-sm atom-btn-white" type="button" onclick="window.print()">{{ __('Print') }}</button>
        </form>
    </div>
    @include('ahg-library::cash-desk._alerts')
    <p class="small text-secondary">{{ $date }} - {{ $branchName ?: __('All branches') }}</p>

    @if($report['unfinished'] > 0)
        <div class="alert alert-warning">{{ __(':n session(s) from this day or earlier are still open or awaiting reconciliation, so these figures are not final.', ['n' => $report['unfinished']]) }}</div>
    @endif

    @forelse($report['totals'] as $code => $t)
        <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">{{ $code }}</h2></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">{{ __('Cash takings') }}</dt><dd class="col-sm-8">{{ number_format($t['cash'] / 100, 2) }}</dd>
                    <dt class="col-sm-4">{{ __('Other takings') }}</dt><dd class="col-sm-8">{{ number_format($t['other'] / 100, 2) }}</dd>
                    @foreach($t['methods'] as $method => $cents)
                        <dt class="col-sm-4 fw-normal ps-4">{{ $tenders[$method]['label'] ?? $method }}</dt><dd class="col-sm-8">{{ number_format($cents / 100, 2) }}</dd>
                    @endforeach
                    <dt class="col-sm-4">{{ __('Net variance') }}</dt><dd class="col-sm-8 {{ $t['variance'] !== 0 ? 'text-danger fw-bold' : '' }}">{{ number_format($t['variance'] / 100, 2) }}</dd>
                    <dt class="col-sm-4">{{ __('Fines waived') }}</dt><dd class="col-sm-8">{{ number_format($t['waived'] / 100, 2) }}</dd>
                </dl>
            </div>
        </div>
    @empty
        <p class="text-muted">{{ __('No money was taken or waived on this day.') }}</p>
    @endforelse

    <h2 class="h5">{{ __('Sessions') }}</h2>
    @if($report['sessions']->isEmpty())
        <p class="text-muted">{{ __('No cash sessions on this day.') }}</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th scope="col">#</th><th scope="col">{{ __('Status') }}</th><th scope="col" class="text-end">{{ __('Float') }}</th><th scope="col" class="text-end">{{ __('Cash this day') }}</th><th scope="col" class="text-end">{{ __('Other this day') }}</th><th scope="col" class="text-end">{{ __('Counted') }}</th><th scope="col" class="text-end">{{ __('Variance') }}</th></tr></thead>
                <tbody>
                    @foreach($report['sessions'] as $s)
                        <tr>
                            <td><a href="{{ route('library.cash-desk.session', $s->id) }}">{{ $s->id }}</a></td>
                            <td>{{ $statuses[$s->status] ?? $s->status }}</td>
                            <td class="text-end">{{ number_format((float) $s->opening_float, 2) }} {{ $s->currency }}</td>
                            <td class="text-end">{{ number_format($s->takings['cash'] / 100, 2) }}</td>
                            <td class="text-end">{{ number_format($s->takings['other'] / 100, 2) }}</td>
                            <td class="text-end">{{ $s->counted_cash !== null ? number_format((float) $s->counted_cash, 2) : '-' }}</td>
                            <td class="text-end">{{ $s->variance !== null ? number_format((float) $s->variance, 2) : '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($report['waivers']->isNotEmpty())
        <h2 class="h5">{{ __('Waivers') }}</h2>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th scope="col">{{ __('Time') }}</th><th scope="col">{{ __('Fine') }}</th><th scope="col" class="text-end">{{ __('Amount') }}</th><th scope="col">{{ __('Reason') }}</th></tr></thead>
                <tbody>
                    @foreach($report['waivers'] as $w)
                        <tr><td>{{ $w->waived_at }}</td><td>#{{ $w->fine_id }}</td><td class="text-end">{{ number_format((float) $w->amount, 2) }} {{ $w->currency }}</td><td>{{ $w->reason }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
