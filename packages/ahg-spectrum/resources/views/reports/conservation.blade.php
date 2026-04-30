{{-- Conservation Report — cloned from AtoM. @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', 'Conservation Report')
@section('sidebar')<div class="sidebar-content"><a href="{{ route('ahgspectrum.reports') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>{{ __('Back') }}</a></div>@endsection
@section('title-block')<h1><i class="fas fa-tools me-2"></i>Conservation Report</h1>@endsection
@section('content')
<div class="alert alert-info"><strong>{{ count($items) }}</strong> conservation treatments found</div>
<div class="table-responsive"><table class="table table-striped table-hover"><thead class="table-dark"><tr><th>{{ __('Object') }}</th><th>{{ __('Date') }}</th><th>{{ __('Treatment') }}</th><th>{{ __('Conservator') }}</th><th>{{ __('Status') }}</th></tr></thead><tbody>
@forelse($items as $c)<tr><td><strong>{{ e($c->object_title ?? '-') }}</strong></td><td>{{ $c->treatment_date ? date('d M Y', strtotime($c->treatment_date)) : '-' }}</td><td>{{ e($c->treatment ?? '-') }}</td><td>{{ e($c->conservator ?? '-') }}</td><td><span class="badge bg-{{ ($c->status ?? '') === 'complete' ? 'success' : 'warning' }}">{{ ucfirst($c->status ?? '-') }}</span></td></tr>
@empty<tr><td colspan="5" class="text-muted text-center py-4">No conservation treatments found.</td></tr>@endforelse
</tbody></table></div>
@endsection
