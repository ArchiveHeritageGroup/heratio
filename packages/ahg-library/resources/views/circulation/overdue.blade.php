@extends('theme::layouts.1col')
@section('title', 'Overdue Items')
@section('content')
<div class="container py-4">

<h1><i class="fas fa-exclamation-triangle me-2"></i>{{ __('Overdue Items') }}</h1>

{{-- Say whose overdues these are. A scoped list that does not announce its
     scope reads as the whole service and understates the problem (#1473). --}}
<p class="text-secondary small">
  @if(!empty($branchName))
    {{ __('Loans made at :branch.', ['branch' => $branchName]) }}
  @else
    {{ __('Loans from every branch.') }}
  @endif
</p>

{{-- patron_name, days_overdue and fine_amount are derived in
     LibraryCirculationService::listOverdue(). They were previously bound here
     against properties the query never selected, so this table showed a blank
     patron, 0 days overdue and 0.00 for every row - the #1477 defect again. --}}
<div class="card">
  <div class="card-body p-0">
    <table class="table table-striped mb-0">
      <thead>
        <tr>
          <th>{{ __('Patron') }}</th>
          <th>{{ __('Item') }}</th>
          <th>{{ __('Due') }}</th>
          <th>{{ __('Days Overdue') }}</th>
          <th>{{ __('Fine') }}</th>
        </tr>
      </thead>
      <tbody>
        @forelse($overdueItems ?? [] as $o)
          <tr>
            <td>{{ $o->patron_name ?? '' }}</td>
            <td>{{ $o->title ?? $o->call_number ?? $o->barcode ?? '' }}</td>
            <td>{{ $o->due_date ?? '' }}</td>
            <td><span class="badge bg-danger">{{ $o->days_overdue ?? 0 }}</span></td>
            <td>
              @if(($o->fine_amount ?? null) === null)
                <span class="text-muted" title="{{ __('A fine is calculated on return or by the nightly run.') }}">
                  {{ __('Not yet calculated') }}
                </span>
              @else
                {{ number_format((float) $o->fine_amount, 2) }}
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-muted text-center py-3">{{ __('No overdue items.') }}</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

</div>
@endsection
