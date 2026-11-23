@extends('theme::layouts.1col')
@section('title', 'Cash Desk')
@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="d-flex align-items-center">
            <a href="{{ route('library.circulation.index') }}" class="btn btn-outline-secondary btn-sm me-3" title="{{ __('Back') }}"><i class="fas fa-arrow-left"></i></a>
            <h1 class="mb-0"><i class="fas fa-cash-register me-2"></i>{{ __('Cash Desk') }}</h1>
        </div>
        <a href="{{ route('library.cash-desk.end-of-day') }}" class="btn btn-sm atom-btn-white">{{ __('End of day') }}</a>
    </div>
    @include('ahg-library::cash-desk._alerts')
    <p class="small text-secondary">{{ $branchName ? __('Branch: :b', ['b' => $branchName]) : __('All branches') }}</p>

    <div class="card mb-4">
        <div class="card-body">
            @if($session)
                {{ __('Your session is open with a float of :f.', ['f' => number_format((float) $session->opening_float, 2) . ' ' . $session->currency]) }}
                <a href="{{ route('library.cash-desk.session', $session->id) }}" class="btn btn-sm atom-btn-white ms-2">{{ __('View or close') }}</a>
                @if($sessionElsewhere)
                    <div class="alert alert-warning mt-3 mb-0">{{ __('This session belongs to another branch. Go back to it to take payments, or close it here.') }}</div>
                @endif
            @else
                <form method="post" action="{{ route('library.cash-desk.open') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-sm-4">
                        <label for="opening_float" class="form-label">{{ __('Opening float') }}</label>
                        <input id="opening_float" name="opening_float" class="form-control" type="number" step="0.01" min="0" required value="{{ old('opening_float') }}">
                    </div>
                    @if(count($currencies) > 1)
                        <div class="col-sm-4">
                            <label for="currency" class="form-label">{{ __('Currency') }}</label>
                            <select id="currency" name="currency" class="form-select">
                                @foreach($currencies as $c)
                                    <option value="{{ $c }}" @selected(old('currency', $defaultCurrency) === $c)>{{ $c }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-sm-4"><button class="btn atom-btn-outline-success" type="submit">{{ __('Open session') }}</button></div>
                </form>
            @endif
        </div>
    </div>

    <h2 class="h5">{{ __('Recent sessions') }}</h2>
    @if(empty($sessions))
        <p class="text-muted">{{ __('No cash sessions yet.') }}</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th scope="col">{{ __('Opened') }}</th><th scope="col">{{ __('Status') }}</th><th scope="col" class="text-end">{{ __('Float') }}</th><th scope="col" class="text-end">{{ __('Counted') }}</th><th scope="col" class="text-end">{{ __('Variance') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach($sessions as $s)
                        <tr>
                            <td>{{ $s->opened_at }}</td>
                            <td>{{ $statuses[$s->status] ?? $s->status }}</td>
                            <td class="text-end">{{ number_format((float) $s->opening_float, 2) }}</td>
                            <td class="text-end">{{ $s->counted_cash !== null ? number_format((float) $s->counted_cash, 2) : '-' }}</td>
                            <td class="text-end">{{ $s->variance !== null ? number_format((float) $s->variance, 2) : '-' }}</td>
                            <td><a href="{{ route('library.cash-desk.session', $s->id) }}">{{ __('View') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
