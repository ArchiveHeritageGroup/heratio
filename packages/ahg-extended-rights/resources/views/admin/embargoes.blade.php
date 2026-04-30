@extends('theme::layouts.2col')

@section('title', 'Embargo Management')
@section('body-class', 'admin rights-admin embargoes')

@section('sidebar')
  @include('ahg-extended-rights::admin._sidebar')
@endsection

@section('title-block')
  <div class="d-flex justify-content-between align-items-center">
    <h1 class="mb-0"><i class="fas fa-clock me-2"></i>Embargo Management</h1>
    <a href="{{ route('ext-rights-admin.embargo-new') }}" class="btn btn-sm btn-primary">
      <i class="fas fa-plus me-1"></i> {{ __('New Embargo') }}
    </a>
  </div>
@endsection

@section('content')
  {{-- Status Filter --}}
  <div class="card mb-4">
    <div class="card-body py-2">
      <div class="btn-group" role="group">
        <a href="{{ route('ext-rights-admin.embargoes', ['status' => 'active']) }}"
           class="btn btn-{{ ($status ?? 'active') === 'active' ? 'primary' : 'outline-primary' }}">Active</a>
        <a href="{{ route('ext-rights-admin.embargoes', ['status' => 'lifted']) }}"
           class="btn btn-{{ ($status ?? '') === 'lifted' ? 'success' : 'outline-success' }}">Lifted</a>
        <a href="{{ route('ext-rights-admin.embargoes', ['status' => 'expired']) }}"
           class="btn btn-{{ ($status ?? '') === 'expired' ? 'secondary' : 'outline-secondary' }}">Expired</a>
        <a href="{{ route('ext-rights-admin.embargoes', ['status' => 'all']) }}"
           class="btn btn-{{ ($status ?? '') === 'all' ? 'dark' : 'outline-dark' }}">All</a>
      </div>
    </div>
  </div>

  {{-- Embargoes Table --}}
  <div class="card">
    <div class="card-body p-0">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>{{ __('Object') }}</th>
            <th>{{ __('Type') }}</th>
            <th>{{ __('Reason') }}</th>
            <th>{{ __('Start') }}</th>
            <th>{{ __('End') }}</th>
            <th>{{ __('Status') }}</th>
            <th>{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($embargoes as $embargo)
          <tr>
            <td>
              <a href="{{ $embargo->slug ? url($embargo->slug) : '#' }}">
                {{ $embargo->object_title ?: 'ID: ' . $embargo->object_id }}
              </a>
            </td>
            <td>
              @php
                $typeColor = match($embargo->embargo_type ?? 'full') {
                  'full' => 'danger', 'metadata_only' => 'warning', 'digital_only' => 'info', 'partial' => 'secondary', default => 'light'
                };
              @endphp
              <span class="badge bg-{{ $typeColor }}">{{ ucfirst(str_replace('_', ' ', $embargo->embargo_type ?? '')) }}</span>
            </td>
            <td>{{ ucfirst(str_replace('_', ' ', $embargo->reason ?? '')) }}</td>
            <td>{{ $embargo->start_date ? \Carbon\Carbon::parse($embargo->start_date)->format('d M Y') : '-' }}</td>
            <td>
              @if($embargo->end_date)
                @php $daysLeft = (int) now()->diffInDays(\Carbon\Carbon::parse($embargo->end_date), false); @endphp
                <span class="{{ $daysLeft <= 30 && $daysLeft > 0 ? 'text-danger fw-bold' : '' }}">
                  {{ \Carbon\Carbon::parse($embargo->end_date)->format('d M Y') }}
                </span>
                @if($daysLeft > 0 && $daysLeft <= 30)
                  <br><small class="text-danger">{{ $daysLeft }} days left</small>
                @endif
              @else
                <span class="text-muted">{{ __('Indefinite') }}</span>
              @endif
            </td>
            <td>
              @php
                $statusColor = match($embargo->status ?? 'active') {
                  'active' => 'warning', 'lifted' => 'success', 'expired' => 'secondary', 'extended' => 'info', default => 'light'
                };
              @endphp
              <span class="badge bg-{{ $statusColor }}">{{ ucfirst($embargo->status ?? '') }}</span>
            </td>
            <td>
              <div class="btn-group btn-group-sm">
                <a href="{{ route('ext-rights-admin.embargo-edit', $embargo->id) }}" class="btn btn-outline-secondary" title="{{ __('Edit') }}">
                  <i class="fas fa-edit"></i>
                </a>
                @if(($embargo->status ?? '') === 'active')
                <button type="button" class="btn btn-outline-success" title="{{ __('Lift') }}"
                        data-bs-toggle="modal" data-bs-target="#liftModal{{ $embargo->id }}">
                  <i class="fas fa-unlock"></i>
                </button>
                <button type="button" class="btn btn-outline-warning" title="{{ __('Extend') }}"
                        data-bs-toggle="modal" data-bs-target="#extendModal{{ $embargo->id }}">
                  <i class="fas fa-calendar-plus"></i>
                </button>
                @endif
              </div>

              {{-- Lift Modal --}}
              <div class="modal fade" id="liftModal{{ $embargo->id }}" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form action="{{ route('ext-rights-admin.embargo-lift', $embargo->id) }}" method="post">
                      @csrf
                      <div class="modal-header">
                        <h5 class="modal-title">{{ __('Lift Embargo') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <p>Are you sure you want to lift this embargo?</p>
                        <div class="mb-3">
                          <label class="form-label">{{ __('Reason for lifting') }}</label>
                          <textarea name="lift_reason" class="form-control" rows="3"></textarea>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-success">{{ __('Lift Embargo') }}</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>

              {{-- Extend Modal --}}
              <div class="modal fade" id="extendModal{{ $embargo->id }}" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form action="{{ route('ext-rights-admin.embargo-extend', $embargo->id) }}" method="post">
                      @csrf
                      <div class="modal-header">
                        <h5 class="modal-title">{{ __('Extend Embargo') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <div class="mb-3">
                          <label class="form-label">{{ __('New End Date') }}</label>
                          <input type="date" name="new_end_date" class="form-control" required
                                 min="{{ date('Y-m-d') }}"
                                 value="{{ $embargo->end_date ? \Carbon\Carbon::parse($embargo->end_date)->addYear()->format('Y-m-d') : \Carbon\Carbon::now()->addYear()->format('Y-m-d') }}">
                        </div>
                        <div class="mb-3">
                          <label class="form-label">{{ __('Reason for extension') }}</label>
                          <textarea name="extend_reason" class="form-control" rows="3"></textarea>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-warning">{{ __('Extend Embargo') }}</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="7" class="text-center text-muted py-4">No embargoes found.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
