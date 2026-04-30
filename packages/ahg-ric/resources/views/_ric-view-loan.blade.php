{{--
  RiC View: Loan — Activity context with borrower, lender, and items on loan.

  @copyright  Johan Pieterse / Plain Sailing Information Systems
  @license    AGPL-3.0-or-later
--}}
@php
  $culture = app()->getLocale();

  $loanTitle = $loan->title ?? ('Loan #' . ($loan->id ?? '?'));
@endphp

<div class="ric-view">
  <div class="card mb-3 border-success">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
      <div><i class="fas fa-handshake me-2"></i><strong>{{ $loanTitle }}</strong></div>
      @include('ahg-ric::components.type-pill', ['type' => 'Activity', 'qualifier' => 'Loan'])
    </div>
    <div class="card-body">
      <table class="table table-sm mb-0">
        <tr><th class="text-muted" style="width:35%">{{ __('RiC Role') }}</th><td>Loan event / temporary custody transfer</td></tr>
        @if(! empty($loan->loan_type))<tr><th class="text-muted">{{ __('Loan type') }}</th><td>{{ $loan->loan_type }}</td></tr>@endif
        @if(! empty($loan->start_date))<tr><th class="text-muted">{{ __('rico:beginningDate') }}</th><td>{{ $loan->start_date }}</td></tr>@endif
        @if(! empty($loan->end_date))<tr><th class="text-muted">{{ __('rico:endDate') }}</th><td>{{ $loan->end_date }}</td></tr>@endif
        @if(! empty($loan->borrower))<tr><th class="text-muted">{{ __('Borrower') }}</th><td>{{ $loan->borrower }}</td></tr>@endif
        @if(! empty($loan->lender))<tr><th class="text-muted">{{ __('Lender') }}</th><td>{{ $loan->lender }}</td></tr>@endif
        @if(! empty($loan->status))<tr><th class="text-muted">{{ __('Status') }}</th><td>{{ $loan->status }}</td></tr>@endif
      </table>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-info-circle me-1"></i> {{ __('RiC Context') }}</div>
    <div class="card-body small text-muted">
      A loan is modelled as <code>rico:Activity</code> — an event that links one or more <code>rico:RecordResource</code> instances to a borrowing <code>rico:Agent</code> for a bounded time. Open the explorer to see the full graph of items, borrower, and lender.
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-bolt me-1"></i> {{ __('Actions') }}</div>
    <div class="card-body">
      <a href="/explorer" class="btn btn-sm btn-outline-success w-100 mb-2"><i class="fas fa-project-diagram me-1"></i>{{ __('Open in Graph Explorer') }}</a>
    </div>
  </div>
</div>
