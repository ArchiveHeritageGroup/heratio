@extends('theme::layouts.1col')
@section('title', 'Cash Session')
@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center mb-3">
        <a href="{{ route('library.cash-desk.index') }}" class="btn btn-outline-secondary btn-sm me-3" title="{{ __('Back') }}"><i class="fas fa-arrow-left"></i></a>
        <h1 class="mb-0">{{ __('Cash Session') }} #{{ $session->id }}</h1>
    </div>
    @include('ahg-library::cash-desk._alerts')

    <div class="card mb-4"><div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-4">{{ __('Status') }}</dt><dd class="col-sm-8">{{ $statuses[$session->status] ?? $session->status }}</dd>
            <dt class="col-sm-4">{{ __('Opened') }}</dt><dd class="col-sm-8">{{ $session->opened_at }} - {{ $names[$session->opened_by] ?? '#' . $session->opened_by }}</dd>
            <dt class="col-sm-4">{{ __('Opening float') }}</dt><dd class="col-sm-8">{{ number_format((float) $session->opening_float, 2) }} {{ $session->currency }}</dd>
            <dt class="col-sm-4">{{ __('Cash takings') }}</dt><dd class="col-sm-8">{{ number_format($takings['cash'] / 100, 2) }}</dd>
            <dt class="col-sm-4">{{ __('Other takings') }}</dt><dd class="col-sm-8">{{ number_format($takings['other'] / 100, 2) }}</dd>
            <dt class="col-sm-4">{{ __('Expected in drawer') }}</dt><dd class="col-sm-8">{{ number_format((float) $session->opening_float + $takings['cash'] / 100, 2) }}</dd>
            @if($session->closed_at)
                <dt class="col-sm-4">{{ __('Counted') }}</dt><dd class="col-sm-8">{{ number_format((float) $session->counted_cash, 2) }}</dd>
                <dt class="col-sm-4">{{ __('Variance') }}</dt><dd class="col-sm-8 {{ (float) $session->variance != 0 ? 'text-danger fw-bold' : '' }}">{{ number_format((float) $session->variance, 2) }}</dd>
                <dt class="col-sm-4">{{ __('Closed') }}</dt><dd class="col-sm-8">{{ $session->closed_at }} - {{ $names[$session->closed_by] ?? '#' . $session->closed_by }}</dd>
                @if($session->close_notes)<dt class="col-sm-4">{{ __('Close notes') }}</dt><dd class="col-sm-8">{{ $session->close_notes }}</dd>@endif
            @endif
            @if($session->reconciled_at)
                <dt class="col-sm-4">{{ __('Reconciled') }}</dt><dd class="col-sm-8">{{ $session->reconciled_at }} - {{ $names[$session->reconciled_by] ?? '#' . $session->reconciled_by }}</dd>
                @if($session->reconcile_notes)<dt class="col-sm-4">{{ __('Review notes') }}</dt><dd class="col-sm-8">{{ $session->reconcile_notes }}</dd>@endif
            @endif
        </dl>
    </div></div>

    @if($canClose)
        <form method="post" action="{{ route('library.cash-desk.close', $session->id) }}" class="card card-body mb-4">
            @csrf
            <h2 class="h5">{{ __('Count and close') }}</h2>
            <div class="mb-2">
                <label class="form-label" for="counted_cash">{{ __('Cash counted in drawer') }}</label>
                <input class="form-control" id="counted_cash" name="counted_cash" type="number" step="0.01" min="0" required value="{{ old('counted_cash') }}">
            </div>
            <div class="mb-2">
                <label class="form-label" for="close_notes">{{ __('Notes (required if the count differs)') }}</label>
                <textarea class="form-control" id="close_notes" name="close_notes" rows="2" maxlength="2000">{{ old('close_notes') }}</textarea>
            </div>
            <div><button class="btn atom-btn-outline-success" type="submit">{{ __('Close session') }}</button></div>
        </form>
    @endif

    @if($canReconcile)
        <form method="post" action="{{ route('library.cash-desk.reconcile', $session->id) }}" class="card card-body mb-4">
            @csrf
            <h2 class="h5">{{ __('Reconcile') }}</h2>
            <div class="mb-2">
                <label class="form-label" for="reconcile_notes">{{ __('Review notes (required if there is a variance)') }}</label>
                <textarea class="form-control" id="reconcile_notes" name="reconcile_notes" rows="2" maxlength="2000"></textarea>
            </div>
            <div><button class="btn atom-btn-outline-success" type="submit">{{ __('Mark reconciled') }}</button></div>
        </form>
    @endif

    <h2 class="h5">{{ __('Receipts') }}</h2>
    @if($receipts->isEmpty())
        <p class="text-muted">{{ __('No payments in this session.') }}</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th scope="col">{{ __('Receipt') }}</th><th scope="col">{{ __('Time') }}</th><th scope="col">{{ __('Method') }}</th><th scope="col" class="text-end">{{ __('Amount') }}</th></tr></thead>
                <tbody>
                    @foreach($receipts as $r)
                        <tr>
                            <td><a href="{{ route('library.fines.receipt', $r->id) }}">{{ $r->receipt_number }}</a></td>
                            <td>{{ $r->issued_at }}</td>
                            <td>{{ $tenders[$r->payment_method]['label'] ?? $r->payment_method }}</td>
                            <td class="text-end">{{ number_format((float) $r->total_amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
