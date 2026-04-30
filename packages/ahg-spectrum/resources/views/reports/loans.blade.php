{{-- Loans Report — cloned from AtoM. @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', 'Loans Report')
@section('sidebar')<div class="sidebar-content"><a href="{{ route('ahgspectrum.reports') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>{{ __('Back') }}</a></div>@endsection
@section('title-block')<h1><i class="fas fa-exchange-alt me-2"></i>{{ __('Loans Report') }}</h1>@endsection
@section('content')
<h5>{{ __('Loans In') }}</h5>
<div class="table-responsive mb-4"><table class="table table-striped table-hover"><thead class="table-dark"><tr><th>{{ __('Object') }}</th><th>{{ __('Lender') }}</th><th>{{ __('Start') }}</th><th>{{ __('End') }}</th><th>{{ __('Status') }}</th></tr></thead><tbody>
@forelse($loansIn as $l)<tr><td><strong>{{ e($l->object_title ?? '-') }}</strong></td><td>{{ e($l->lender ?? '-') }}</td><td>{{ $l->start_date ? date('d M Y', strtotime($l->start_date)) : '-' }}</td><td>{{ $l->end_date ? date('d M Y', strtotime($l->end_date)) : '-' }}</td><td><span class="badge bg-{{ ($l->status ?? '') === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($l->status ?? '-') }}</span></td></tr>
@empty<tr><td colspan="5" class="text-muted text-center py-4">No loans in.</td></tr>@endforelse
</tbody></table></div>
<h5>{{ __('Loans Out') }}</h5>
<div class="table-responsive"><table class="table table-striped table-hover"><thead class="table-dark"><tr><th>{{ __('Object') }}</th><th>{{ __('Borrower') }}</th><th>{{ __('Start') }}</th><th>{{ __('End') }}</th><th>{{ __('Status') }}</th></tr></thead><tbody>
@forelse($loansOut as $l)<tr><td><strong>{{ e($l->object_title ?? '-') }}</strong></td><td>{{ e($l->borrower ?? '-') }}</td><td>{{ $l->start_date ? date('d M Y', strtotime($l->start_date)) : '-' }}</td><td>{{ $l->end_date ? date('d M Y', strtotime($l->end_date)) : '-' }}</td><td><span class="badge bg-{{ ($l->status ?? '') === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($l->status ?? '-') }}</span></td></tr>
@empty<tr><td colspan="5" class="text-muted text-center py-4">No loans out.</td></tr>@endforelse
</tbody></table></div>
@endsection
