@extends('theme::layouts.1col')
@section('title', 'My Holds')
@section('content')
<div class="container py-4">

    <div class="d-flex align-items-center mb-3">
        <a href="{{ route('opac.patron.account') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="mb-0"><i class="fas fa-bookmark me-2"></i>{{ __('My Holds') }}</h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($holds->isNotEmpty())
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>{{ __('Title') }}</th>
                                <th class="text-center">{{ __('Queue Position') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Hold Date') }}</th>
                                <th>{{ __('Expires') }}</th>
                                <th class="text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($holds as $h)
                                <tr class="{{ ($h->status ?? '') === 'ready' ? 'table-success' : '' }}">
                                    <td>
                                        {{ $h->title ?? __('(unknown item)') }}
                                        @if($h->call_number ?? null)
                                            <br><small class="text-muted">{{ $h->call_number }}</small>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $h->queue_position ?? '—' }}</td>
                                    <td>
                                        @if(($h->status ?? '') === 'ready')
                                            <span class="badge bg-success">{{ __('Ready for Pickup') }}</span>
                                        @else
                                            <span class="badge bg-info">{{ __('Waiting') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $h->hold_date ?? '—' }}</td>
                                    <td>{{ $h->expiry_date ?? '—' }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('opac.patron.holds.cancel') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="hold_id" value="{{ $h->id }}">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    title="{{ __('Cancel hold') }}">
                                                <i class="fas fa-times me-1"></i>{{ __('Cancel') }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Ready holds pickup notice --}}
        @if($holds->contains('status', 'ready'))
            <div class="alert alert-success mt-3">
                <i class="fas fa-check-circle me-2"></i>
                <strong>{{ __('Item(s) ready for pickup!') }}</strong>
                {{ __('Please visit the library during opening hours to collect your reserved item(s).') }}
            </div>
        @endif
    @else
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>{{ __('You have no active holds.') }}
        </div>
    @endif

</div>
@endsection
