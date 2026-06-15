@extends('theme::layouts.1col')
@section('title', __('Serials Bindery'))
@section('content')
<div class="container py-4">
  <h2 class="mb-4"><i class="fas fa-book me-2"></i>{{ __('Serials Bindery') }}</h2>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  {{-- Send received issues to a bindery as a batch --}}
  <div class="card mb-4">
    <div class="card-header bg-light"><h5 class="mb-0">{{ __('Send issues to bindery') }}</h5></div>
    <div class="card-body">
      @if(empty($bindable))
        <div class="alert alert-info mb-0">{{ __('No received issues awaiting binding.') }}</div>
      @else
        <form method="post" action="{{ route('library.serial-bindery-send') }}">
          @csrf
          <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
              <thead class="table-light">
                <tr>
                  <th style="width:2.5rem"></th>
                  <th>{{ __('Vol') }}</th>
                  <th>{{ __('Issue') }}</th>
                  <th>{{ __('Date') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($bindable as $i)
                  <tr>
                    <td><input type="checkbox" name="issue_ids[]" value="{{ (int) $i->id }}"></td>
                    <td>{{ $i->volume ?? '' }}</td>
                    <td>{{ $i->issue_number ?? '' }}</td>
                    <td>{{ $i->expected_date ?? $i->issue_date ?? '' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          <div class="row g-2 align-items-end">
            <div class="col-md-4">
              <label class="form-label">{{ __('Bindery vendor id (optional)') }}</label>
              <input type="number" name="vendor_id" class="form-control" min="1">
            </div>
            <div class="col-md-5">
              <label class="form-label">{{ __('Notes') }}</label>
              <input type="text" name="notes" class="form-control" maxlength="1000">
            </div>
            <div class="col-md-3">
              <button type="submit" class="btn btn-primary w-100"><i class="fas fa-paper-plane me-1"></i>{{ __('Send to bindery') }}</button>
            </div>
          </div>
        </form>
      @endif
    </div>
  </div>

  {{-- Existing batches --}}
  <div class="card">
    <div class="card-header bg-light"><h5 class="mb-0">{{ __('Bindery batches') }}</h5></div>
    @if(empty($batches))
      <div class="card-body"><div class="alert alert-info mb-0">{{ __('No bindery batches yet.') }}</div></div>
    @else
      <div class="table-responsive">
        <table class="table table-striped mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>{{ __('Batch') }}</th>
              <th>{{ __('Items') }}</th>
              <th>{{ __('Status') }}</th>
              <th>{{ __('Sent') }}</th>
              <th>{{ __('Returned') }}</th>
              <th class="text-end">{{ __('Action') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($batches as $b)
              @php
                $badge = $b->status === 'returned' ? 'success' : ($b->status === 'cancelled' ? 'secondary' : 'warning text-dark');
              @endphp
              <tr>
                <td><code>{{ $b->batch_number }}</code></td>
                <td>{{ (int) $b->item_count }}</td>
                <td><span class="badge bg-{{ $badge }}">{{ $b->status }}</span></td>
                <td>{{ $b->sent_date ?? '' }}</td>
                <td>{{ $b->returned_date ?? '' }}</td>
                <td class="text-end">
                  @if($b->status === 'sent')
                    <form method="post" action="{{ route('library.serial-bindery-receive') }}" class="d-inline">
                      @csrf
                      <input type="hidden" name="batch_id" value="{{ (int) $b->id }}">
                      <button type="submit" class="btn btn-sm btn-outline-success">{{ __('Receive') }}</button>
                    </form>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>
@endsection
