@extends('theme::layouts.1col')
@section('title', __('Loan Rules'))
@section('body-class', 'admin library')

@section('content')
<h1><i class="fas fa-gavel me-2"></i>{{ __('Loan Rules') }}</h1>

{{--
  The columns below bind the real library_loan_rule column names - #1477.
  This table previously read $r->loan_days and $r->max_items, neither of which
  exists: the column is loan_period_days, and there is no per-rule item cap at
  all. Both fell through to `?? 14` and `?? 5`, which happen to equal the
  library_default_loan_days and library_patron.max_checkouts defaults, so every
  rule rendered as "14 days / 5 max items" and looked plausible while
  contradicting what circulation actually enforced.

  Max Items is gone rather than fixed. A per-rule item cap is a missing
  concept, not a misnamed column, and displaying a number nothing enforces is
  what caused the problem in the first place. #1475 covers adding it properly.
--}}
<div class="card">
  <div class="card-body p-0">
    <table class="table table-striped mb-0">
      <thead>
        <tr>
          @if($branchAware ?? false)<th>{{ __('Branch') }}</th>@endif
          <th>{{ __('Patron Type') }}</th>
          <th>{{ __('Material Type') }}</th>
          <th>{{ __('Loan (days)') }}</th>
          <th>{{ __('Renewals') }}</th>
          <th>{{ __('Grace (days)') }}</th>
          <th>{{ __('Fine/Day') }}</th>
          <th>{{ __('Loanable') }}</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rules ?? [] as $r)
          <tr>
            @if($branchAware ?? false)
              @php $branchId = isset($r->branch_id) ? (int) $r->branch_id : null; @endphp
              <td>
                @if($branchId === null || $branchId === 0)
                  <span class="text-muted">{{ __('All branches') }}</span>
                @else
                  {{ $branchLabels[$branchId] ?? ('#' . $branchId) }}
                @endif
              </td>
            @endif
            <td>{{ ($r->patron_type ?? '*') === '*' ? __('All') : $r->patron_type }}</td>
            <td>{{ $r->material_type ?? __('All') }}</td>
            <td>{{ $r->loan_period_days ?? '-' }}</td>
            <td>{{ $r->max_renewals ?? '-' }}</td>
            <td>{{ $r->grace_period_days ?? '-' }}</td>
            <td>{{ number_format((float) ($r->fine_per_day ?? 0), 2) }}</td>
            <td>
              @if(($r->is_loanable ?? 1))
                <span class="badge bg-success">{{ __('Yes') }}</span>
              @else
                <span class="badge bg-secondary">{{ __('No') }}</span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="{{ ($branchAware ?? false) ? 8 : 7 }}" class="text-muted text-center py-3">
              {{ __('No rules.') }}
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
