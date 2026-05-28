@extends('theme::layouts.1col')
@section('title', 'Patrons')
@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0"><i class="fas fa-users me-2"></i>{{ __('Patrons') }}</h1>
        <a href="{{ route('library.patron-create') }}" class="btn btn-primary"><i class="fas fa-user-plus me-1"></i>{{ __('Add Patron') }}</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Card #') }}</th>
                        <th>{{ __('Email') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($patrons ?? [] as $p)
                        @php $name = trim(($p->last_name ?? '') . ', ' . ($p->first_name ?? '')); @endphp
                        <tr>
                            <td><a href="{{ route('library.patron-view', $p->id ?? 0) }}"><strong>{{ $name !== ',' ? $name : __('(unnamed)') }}</strong></a></td>
                            <td>{{ ucfirst($p->patron_type ?? '') }}</td>
                            <td><code>{{ $p->card_number ?? '' }}</code></td>
                            <td>{{ $p->email ?? '' }}</td>
                            <td>
                                @php $st = $p->borrowing_status ?? 'active'; @endphp
                                <span class="badge bg-{{ $st === 'active' ? 'success' : ($st === 'suspended' ? 'danger' : 'secondary') }}">{{ ucfirst($st) }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('library.patron-edit', $p->id) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Edit') }}"><i class="fas fa-pen"></i></a>
                                <a href="{{ route('library.checkout-form', ['patron' => $p->id]) }}" class="btn btn-sm btn-outline-primary" title="{{ __('Check out') }}"><i class="fas fa-exchange-alt"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted text-center py-3">{{ __('No patrons.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
