@extends('theme::layouts.1col')
@section('title', 'Patron Fines')
@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center mb-3">
        <a href="{{ route('library.circulation.patron', $patron->id) }}" class="btn btn-outline-secondary btn-sm me-3" title="{{ __('Back') }}"><i class="fas fa-arrow-left"></i></a>
        <h1 class="mb-0"><i class="fas fa-cash-register me-2"></i>{{ __('Patron Fines') }}</h1>
    </div>
    @include('ahg-library::cash-desk._alerts')

    <p>
        <strong>{{ trim(($patron->first_name ?? '') . ' ' . ($patron->last_name ?? '')) ?: __('(unnamed)') }}</strong>
        <code>{{ $patron->card_number ?? '' }}</code> -
        {{ __('Owed') }}: <strong>{{ number_format((float) ($patron->total_fines_owed ?? 0), 2) }}</strong>
    </p>

    @if(! $session)
        <div class="alert alert-warning">
            {{ __('You have no open cash session, so payments cannot be taken.') }}
            <a href="{{ route('library.cash-desk.index') }}">{{ __('Open one at the cash desk') }}</a>.
        </div>
    @endif

    @if($sessionElsewhere)
        <div class="alert alert-warning">{{ __('Your open cash session belongs to another branch, so payments cannot be taken here. Go back to that branch, or close the session.') }}
            <a href="{{ route('library.cash-desk.session', $session->id) }}">{{ __('View session') }}</a>.
        </div>
    @endif
    @if(! empty($unsetTenders))
        <div class="alert alert-warning">{{ __('These payment methods are unavailable until the Dropdown Manager says whether they are cash: :t', ['t' => implode(', ', $unsetTenders)]) }}</div>
    @endif
    @if(! empty($otherCurrencies))
        <div class="alert alert-warning">{{ __('Some fines are recorded in :c, not :s, so they cannot be paid in this session. Open a session in that currency if the amount is right. If the currency label is wrong, have it corrected before taking payment.', ['c' => implode(', ', $otherCurrencies), 's' => $currency]) }}</div>
    @endif

    @if(empty($fines))
        <p class="text-muted">{{ __('No outstanding fines.') }}</p>
    @else
        <form method="post" action="{{ route('library.fines.pay', $patron->id) }}" id="pay-form">
            @csrf
            <input type="hidden" name="form_key" value="{{ $formKey }}">
            <div class="card mb-4">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr>
                                <th scope="col"><span class="visually-hidden">{{ __('Select') }}</span></th>
                                <th scope="col">{{ __('Date') }}</th><th scope="col">{{ __('Type') }}</th><th scope="col">{{ __('Description') }}</th>
                                <th scope="col" class="text-end">{{ __('Amount') }}</th><th scope="col" class="text-end">{{ __('Paid') }}</th><th scope="col" class="text-end">{{ __('Balance') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($fines as $f)
                                <tr>
                                    <td><input class="form-check-input" type="checkbox" name="fine_ids[]" value="{{ $f->id }}" aria-label="{{ __('Select fine :id', ['id' => $f->id]) }}" @disabled(! $f->payable) @checked($f->payable && in_array($f->id, old('fine_ids', array_column($fines, 'id'))))></td>
                                    <td>{{ $f->fine_date }}</td>
                                    <td>{{ ucfirst($f->fine_type) }}</td>
                                    <td>{{ $f->description }}</td>
                                    <td class="text-end">{{ number_format((float) $f->amount, 2) }} {{ $f->currency }}@unless($f->payable) <span class="badge bg-warning text-dark">{{ __('Other currency') }}</span>@endunless</td>
                                    <td class="text-end">{{ number_format((float) $f->paid_amount, 2) }}</td>
                                    <td class="text-end fw-bold">{{ number_format($f->balance, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Take payment') }}</h5></div>
                <div class="card-body row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="amount">{{ __('Amount') }}</label>
                        <input class="form-control" id="amount" name="amount" type="number" step="0.01" min="0.01" required value="{{ old('amount', number_format($payableBalance, 2, '.', '')) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="payment_method">{{ __('Payment method') }}</label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            @foreach($tenders as $code => $t)
                                <option value="{{ $code }}" @selected(old('payment_method') === $code)>{{ $t['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="tendered">{{ __('Cash tendered') }}</label>
                        <input class="form-control" id="tendered" name="tendered" type="number" step="0.01" min="0" value="{{ old('tendered') }}">
                        <div class="form-text">{{ __('Cash only. Used to work out change.') }}</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="payment_reference">{{ __('Reference') }}</label>
                        <input class="form-control" id="payment_reference" name="payment_reference" maxlength="100" value="{{ old('payment_reference') }}">
                        <div class="form-text">{{ __('Required unless cash. Never a card number.') }}</div>
                    </div>
                    <div class="col-12">
                        <button class="btn atom-btn-outline-success" type="submit" @disabled(! $session || $sessionElsewhere || empty($tenders) || $payableBalance <= 0)><i class="fas fa-check me-1"></i>{{ __('Record payment') }}</button>
                    </div>
                </div>
            </div>
        </form>

        @if($canWaive)
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Waive a fine') }}</h5></div>
                <div class="card-body">
                    <p class="small text-secondary">{{ __('Writes off the remaining balance. Anything already paid stays paid.') }}</p>
                    @foreach(array_filter($fines, fn ($x) => $x->can_waive) as $f)
                        <form method="post" action="{{ route('library.fines.waive', $f->id) }}" class="row g-2 mb-2 align-items-center">
                            @csrf
                            <div class="col-md-3 small">{{ $f->fine_date }} - {{ number_format($f->balance, 2) }} {{ $f->currency }}</div>
                            <div class="col-md-7">
                                <input class="form-control form-control-sm" name="waive_reason" required minlength="3" maxlength="1000" placeholder="{{ __('Reason') }}" aria-label="{{ __('Reason for waiving fine :id', ['id' => $f->id]) }}">
                            </div>
                            <div class="col-md-2"><button class="btn btn-sm atom-btn-white w-100" type="submit">{{ __('Waive') }}</button></div>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
@endsection
