@extends('theme::layouts.1col')

@section('title', 'Loan Management')
@section('body-class', 'browse loan')

@section('content')

  <div class="row">

    {{-- ===== MAIN COLUMN ===== --}}
    <div class="col-md-9">

      {{-- Breadcrumb --}}
      <nav aria-label="{{ __('breadcrumb') }}">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="{{ url('/') }}"><i class="fas fa-home"></i></a></li>
          @if(request('object_id'))
            @php
              $__bcSlug = \Illuminate\Support\Facades\DB::table('slug')->where('object_id', request('object_id'))->value('slug');
              $__bcTitle = \Illuminate\Support\Facades\DB::table('information_object_i18n')->where('id', request('object_id'))->where('culture', app()->getLocale())->value('title');
            @endphp
            @if($__bcSlug)
              <li class="breadcrumb-item"><a href="/{{ $__bcSlug }}">{{ \Illuminate\Support\Str::limit($__bcTitle ?? $__bcSlug, 40) }}</a></li>
            @endif
          @endif
          <li class="breadcrumb-item active" aria-current="page">Loans</li>
        </ol>
      </nav>

      {{-- Header --}}
      <div class="multiline-header d-flex align-items-center mb-3">
        <i class="fas fa-3x fa-exchange-alt me-3" aria-hidden="true"></i>
        <div class="d-flex flex-column">
          <h1 class="mb-0">{{ __('Loan Management') }}</h1>
          <span class="small text-muted">
            @if($loans->total())
              Showing {{ number_format($loans->total()) }} loan{{ $loans->total() !== 1 ? 's' : '' }}
            @else
              No loans found
            @endif
          </span>
        </div>
      </div>

      {{-- Filter + Table card --}}
      <div class="card mb-4">

        {{-- Filter bar in card-header --}}
        <div class="card-header">
          <form method="GET" action="{{ route('loan.index') }}" class="row g-2 align-items-center">
            @if(request('sector'))
              <input type="hidden" name="sector" value="{{ request('sector') }}">
            @endif
            @if(request('object_id'))
              <input type="hidden" name="object_id" value="{{ request('object_id') }}">
            @endif

            {{-- Search --}}
            <div class="col-auto">
              <input type="text" name="search" class="form-control form-control-sm"
                     placeholder="{{ __('Search loans...') }}" value="{{ $params['search'] ?? '' }}">
            </div>

            {{-- Type select --}}
            <div class="col-auto">
              <select name="type" class="form-select form-select-sm">
                <option value="">{{ __('All Types') }}</option>
                <option value="out" {{ ($params['type'] ?? '') === 'out' ? 'selected' : '' }}>{{ __('Loans Out') }}</option>
                <option value="in" {{ ($params['type'] ?? '') === 'in' ? 'selected' : '' }}>{{ __('Loans In') }}</option>
              </select>
            </div>

            {{-- Status select --}}
            <div class="col-auto">
              <select name="status" class="form-select form-select-sm">
                <option value="">{{ __('All Statuses') }}</option>
                <option value="pending" {{ ($params['status'] ?? '') === 'pending' ? 'selected' : '' }}>{{ __('Pending') }}</option>
                <option value="approved" {{ ($params['status'] ?? '') === 'approved' ? 'selected' : '' }}>{{ __('Approved') }}</option>
                <option value="on_loan" {{ ($params['status'] ?? '') === 'on_loan' ? 'selected' : '' }}>{{ __('Active') }}</option>
                <option value="returned" {{ ($params['status'] ?? '') === 'returned' ? 'selected' : '' }}>{{ __('Returned') }}</option>
              </select>
            </div>

            {{-- Overdue checkbox --}}
            <div class="col-auto">
              <div class="form-check mb-0">
                <input type="checkbox" name="overdue" value="1" id="filter-overdue" class="form-check-input"
                       {{ !empty($params['overdue']) ? 'checked' : '' }}>
                <label for="filter-overdue" class="form-check-label small">{{ __('Overdue') }}</label>
              </div>
            </div>

            {{-- Filter + Clear buttons --}}
            <div class="col-auto">
              <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>{{ __('Filter') }}</button>
              <a href="{{ route('loan.index', array_filter(['sector' => request('sector'), 'object_id' => request('object_id')])) }}" class="btn btn-sm btn-outline-secondary">Clear</a>
            </div>

            {{-- New Loan split button group (pushed right) --}}
            <div class="col-auto ms-auto">
              <div class="btn-group">
                <a href="{{ route('loan.create', array_filter(['type' => 'out', 'sector' => request('sector'), 'object_id' => request('object_id')])) }}"
                   class="btn btn-sm btn-success">
                  <i class="fas fa-plus me-1"></i>{{ __('New Loan Out') }}
                </a>
                <a href="{{ route('loan.create', array_filter(['type' => 'in', 'sector' => request('sector'), 'object_id' => request('object_id')])) }}"
                   class="btn btn-sm btn-info">
                  <i class="fas fa-plus me-1"></i>{{ __('New Loan In') }}
                </a>
              </div>
            </div>
          </form>
        </div>

        {{-- Table --}}
        <div class="card-body p-0">
          @if($loans->total())
            <div class="table-responsive">
              <table class="table table-bordered table-striped table-hover mb-0">
                <thead>
                  <tr>
                    <th>{{ __('Loan #') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Partner Institution') }}</th>
                    <th>{{ __('Purpose') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('End Date') }}</th>
                    <th class="text-center">{{ __('Objects') }}</th>
                    <th class="text-center">{{ __('Actions') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($loans as $loan)
                    @php
                      $isOverdue = $loan->end_date && $loan->end_date < now()->toDateString()
                                   && in_array($loan->status, ['on_loan','dispatched','in_transit','received']);

                      $statusColours = [
                          'draft'            => 'secondary',
                          'submitted'        => 'info',
                          'under_review'     => 'warning',
                          'approved'         => 'primary',
                          'rejected'         => 'danger',
                          'preparing'        => 'primary',
                          'dispatched'       => 'primary',
                          'in_transit'       => 'primary',
                          'received'         => 'info',
                          'on_loan'          => 'success',
                          'return_requested' => 'warning',
                          'returned'         => 'dark',
                          'closed'           => 'dark',
                          'cancelled'        => 'danger',
                          'pending'          => 'secondary',
                      ];
                      $badgeColour = $statusColours[$loan->status] ?? 'secondary';
                    @endphp
                    <tr class="{{ $isOverdue ? 'table-danger' : '' }}">
                      <td>
                        <a href="{{ route('loan.show', $loan->id) }}" class="fw-bold text-decoration-none">
                          {{ $loan->loan_number }}
                        </a>
                        @if($loan->title)
                          <br><small class="text-muted">{{ $loan->title }}</small>
                        @endif
                      </td>
                      <td>
                        @if($loan->loan_type === 'out')
                          <span class="badge bg-warning text-dark"><i class="fas fa-arrow-right me-1"></i>{{ __('Out') }}</span>
                        @else
                          <span class="badge bg-info"><i class="fas fa-arrow-left me-1"></i>{{ __('In') }}</span>
                        @endif
                      </td>
                      <td>{{ $loan->partner_institution }}</td>
                      <td>{{ $purposes[$loan->purpose] ?? ucfirst($loan->purpose ?? '-') }}</td>
                      <td>
                        <span class="badge bg-{{ $badgeColour }}">
                          {{ ucwords(str_replace('_', ' ', $loan->status)) }}
                        </span>
                        @if($isOverdue)
                          <span class="badge bg-danger ms-1"><i class="fas fa-exclamation-triangle me-1"></i>{{ __('Overdue') }}</span>
                        @endif
                      </td>
                      <td>{{ $loan->end_date ? \Carbon\Carbon::parse($loan->end_date)->format('Y-m-d') : '-' }}</td>
                      <td class="text-center">
                        <span class="badge bg-secondary">{{ $loan->objects_count }}</span>
                      </td>
                      <td class="text-center">
                        <div class="btn-group btn-group-sm">
                          <a href="{{ route('loan.show', $loan->id) }}" class="btn btn-outline-primary" title="{{ __('View') }}">
                            <i class="fas fa-eye"></i>
                          </a>
                          <a href="{{ route('loan.edit', $loan->id) }}" class="btn btn-outline-secondary" title="{{ __('Edit') }}">
                            <i class="fas fa-pencil-alt"></i>
                          </a>
                        </div>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @else
            <div class="text-center py-5">
              <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
              <h5 class="text-muted">{{ __('No loans found') }}</h5>
              <p class="text-muted">Create a new loan to get started.</p>
            </div>
          @endif
        </div>

        {{-- Pagination in card-footer --}}
        @if($loans->total())
          <div class="card-footer">
            <div class="d-flex justify-content-center">
              {{ $loans->withQueryString()->links() }}
            </div>
          </div>
        @endif

      </div>

    </div>

    {{-- ===== SIDEBAR ===== --}}
    <div class="col-md-3">

      {{-- Statistics card --}}
      <div class="card mb-3">
        <div class="card-header"><i class="fas fa-chart-bar me-1"></i> Statistics</div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item d-flex justify-content-between">
            Total Loans
            <span class="fw-bold">{{ number_format($stats['total']) }}</span>
          </li>
          <li class="list-group-item d-flex justify-content-between">
            <span class="text-warning">{{ __('Active Loans Out') }}</span>
            <span class="fw-bold text-warning">{{ number_format($stats['active_out']) }}</span>
          </li>
          <li class="list-group-item d-flex justify-content-between">
            <span class="text-info">{{ __('Active Loans In') }}</span>
            <span class="fw-bold text-info">{{ number_format($stats['active_in']) }}</span>
          </li>
          <li class="list-group-item d-flex justify-content-between">
            <span class="text-danger">{{ __('Overdue') }}</span>
            <span class="fw-bold text-danger">{{ number_format($stats['overdue']) }}</span>
          </li>
          <li class="list-group-item d-flex justify-content-between">
            Due This Month
            <span class="fw-bold">{{ number_format($stats['due_this_month']) }}</span>
          </li>
        </ul>
        @if($stats['total_insurance_value'] > 0)
          <div class="card-body border-top">
            <small class="text-muted">{{ __('Total Insurance Value') }}</small>
            <div class="fw-bold">R {{ number_format($stats['total_insurance_value'], 2) }}</div>
          </div>
        @endif
      </div>

      {{-- Overdue Loans card --}}
      @if($overdue->count())
        <div class="card mb-3 border-danger">
          <div class="card-header bg-danger text-white">
            <i class="fas fa-exclamation-triangle me-1"></i> Overdue Loans
          </div>
          <ul class="list-group list-group-flush">
            @foreach($overdue as $ol)
              <a href="{{ route('loan.show', $ol->id) }}" class="list-group-item list-group-item-action list-group-item-danger">
                <div class="fw-bold">{{ $ol->loan_number }}</div>
                <small>{{ $ol->partner_institution }}</small>
                <br><small class="text-muted">Due: {{ \Carbon\Carbon::parse($ol->end_date)->format('Y-m-d') }}</small>
              </a>
            @endforeach
          </ul>
          @if($stats['overdue'] > 5)
            <div class="card-footer text-center">
              <a href="{{ route('loan.index', ['overdue' => 1]) }}" class="text-danger">
                View all {{ $stats['overdue'] }} overdue <i class="fas fa-arrow-right ms-1"></i>
              </a>
            </div>
          @endif
        </div>
      @endif

      {{-- Due Within 30 Days card --}}
      @if($dueSoon->count())
        <div class="card mb-3 border-warning">
          <div class="card-header bg-warning text-dark">
            <i class="fas fa-clock me-1"></i> Due Within 30 Days
          </div>
          <ul class="list-group list-group-flush">
            @foreach($dueSoon as $dl)
              <a href="{{ route('loan.show', $dl->id) }}" class="list-group-item list-group-item-action">
                <div class="fw-bold">{{ $dl->loan_number }}</div>
                <small>{{ $dl->partner_institution }}</small>
                <br><small class="text-muted">Due: {{ \Carbon\Carbon::parse($dl->end_date)->format('Y-m-d') }}</small>
              </a>
            @endforeach
          </ul>
          @if($stats['due_this_month'] > 5)
            <div class="card-footer text-center">
              <a href="{{ route('loan.index', ['status' => 'on_loan']) }}" class="text-warning">
                View all {{ $stats['due_this_month'] }} due soon <i class="fas fa-arrow-right ms-1"></i>
              </a>
            </div>
          @endif
        </div>
      @endif

      {{-- Quick Actions card --}}
      <div class="card mb-3">
        <div class="card-header"><i class="fas fa-bolt me-1"></i> Quick Actions</div>
        <div class="list-group list-group-flush">
          <a href="{{ route('loan.create', array_filter(['type' => 'out', 'sector' => request('sector'), 'object_id' => request('object_id')])) }}"
             class="list-group-item list-group-item-action">
            <i class="fas fa-arrow-right text-success me-2"></i> {{ __('New Loan Out') }}
          </a>
          <a href="{{ route('loan.create', array_filter(['type' => 'in', 'sector' => request('sector'), 'object_id' => request('object_id')])) }}"
             class="list-group-item list-group-item-action">
            <i class="fas fa-arrow-left text-info me-2"></i> {{ __('New Loan In') }}
          </a>
          @if(\Illuminate\Support\Facades\Route::has('exhibition.index'))
            <a href="{{ route('exhibition.index') }}" class="list-group-item list-group-item-action">
              <i class="fas fa-university text-primary me-2"></i> {{ __('View Exhibitions') }}
            </a>
          @endif
        </div>
      </div>

    </div>

  </div>

@endsection
