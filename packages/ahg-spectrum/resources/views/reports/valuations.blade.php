{{-- Valuations Report — cloned from AtoM. @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', 'Valuations Report')
@section('sidebar')<div class="sidebar-content"><a href="{{ route('ahgspectrum.reports') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>Back</a></div>@endsection
@section('title-block')<h1><i class="fas fa-dollar-sign me-2"></i>Valuations Report</h1>@endsection
@section('content')
<div class="alert alert-info"><strong>{{ count($items) }}</strong> valuations found</div>
<div class="table-responsive"><table class="table table-striped table-hover"><thead class="table-dark"><tr><th>{{ __('Object') }}</th><th>{{ __('Date') }}</th><th>{{ __('Value') }}</th><th>{{ __('Type') }}</th><th>{{ __('Valuator') }}</th></tr></thead><tbody>
@forelse($items as $v)<tr><td><strong>{{ e($v->object_title ?? '-') }}</strong></td><td>{{ $v->valuation_date ? date('d M Y', strtotime($v->valuation_date)) : '-' }}</td><td class="text-end">R {{ number_format($v->value ?? 0, 2) }}</td><td>{{ e($v->valuation_type ?? '-') }}</td><td>{{ e($v->valuator ?? '-') }}</td></tr>
@empty<tr><td colspan="5" class="text-muted text-center py-4">No valuations found.</td></tr>@endforelse
</tbody></table></div>
@endsection
