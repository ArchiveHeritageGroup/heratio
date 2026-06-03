@extends('theme::layouts.1col')
@section('title', 'Overdue Claims')
@section('content')
<div class="container py-4">
    <h2 class="mb-1"><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Overdue Claims</h2>
    <p class="text-muted mb-4">Active serials with issues overdue past 1.5x the expected interval.</p>

    @if(session('serial_success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('serial_success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif

    @if($claims->isEmpty())
        <div class="alert alert-success mb-0">
            <i class="fas fa-check-circle me-2"></i>No overdue claims at this time. All active serials are on schedule.
        </div>
    @else
        <div class="card shadow-sm mb-4">
            <div class="card-body p-0">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Title') }}</th>
                            <th>{{ __('ISSN') }}</th>
                            <th>{{ __('Frequency') }}</th>
                            <th>{{ __('Predicted Date') }}</th>
                            <th>{{ __('Days Late') }}</th>
                            <th>{{ __('Subscription Ends') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($claims as $claim)
                            @php
                                $ser = $claim['serial'];
                                $daysLate = $claim['days_late'] ?? 0;
                            @endphp
                            <tr class="{{ $daysLate > 30 ? 'table-danger' : ($daysLate > 14 ? 'table-warning' : '') }}">
                                <td>
                                    <a href="{{ route('library.serial-view', $ser->id) }}">
                                        <strong>{{ e($ser->title ?? '') }}</strong>
                                    </a>
                                </td>
                                <td><code>{{ e($ser->issn ?? '—') }}</code></td>
                                <td>{{ e($ser->frequency ?? '') }}</td>
                                <td>{{ $claim['predicted_date'] ? \Carbon\Carbon::parse($claim['predicted_date'])->format('d M Y') : '—' }}</td>
                                <td>
                                    <span class="badge bg-danger">{{ $daysLate }}d late</span>
                                </td>
                                <td>
                                    @if($claim['subscription_end'])
                                        {{ \Carbon\Carbon::parse($claim['subscription_end'])->format('d M Y') }}
                                    @else
                                        <span class="text-muted">no subscription</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('library.serial-view', $ser->id) }}"
                                           class="btn btn-outline-primary btn-sm" title="{{ __('View serial') }}">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <form method="POST"
                                              action="{{ route('library.serial-subscription', $ser->id) }}"
                                              class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-secondary btn-sm"
                                                    title="{{ __('View subscription') }}">
                                                <i class="fas fa-calendar-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="d-flex gap-2">
        <a href="{{ route('library.serials') }}" class="btn btn-outline-secondary">
            <i class="fas fa-newspaper me-2"></i>Back to Serials
        </a>
        <a href="{{ route('library.serial-create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Serial
        </a>
    </div>
</div>
@endsection
