{{-- Condition Checks Report — cloned from AtoM. @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', 'Condition Checks Report')
@section('sidebar')<div class="sidebar-content"><a href="{{ route('ahgspectrum.reports') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>Back</a></div>@endsection
@section('title-block')<h1><i class="fas fa-heartbeat me-2"></i>Condition Checks Report</h1>@endsection
@section('content')
<div class="alert alert-info"><strong>{{ count($items) }}</strong> condition checks found</div>
<div class="table-responsive"><table class="table table-striped table-hover"><thead class="table-dark"><tr><th>{{ __('Object') }}</th><th>{{ __('Date') }}</th><th>{{ __('Condition') }}</th><th>{{ __('Checked By') }}</th><th>{{ __('Notes') }}</th></tr></thead><tbody>
@forelse($items as $c)<tr><td><strong>{{ e($c->object_title ?? '-') }}</strong></td><td>{{ $c->check_date ? date('d M Y', strtotime($c->check_date)) : '-' }}</td><td><span class="badge bg-{{ in_array($c->condition ?? '', ['poor','critical']) ? 'danger' : 'success' }}">{{ ucfirst($c->condition ?? '-') }}</span></td><td>{{ e($c->checked_by ?? '-') }}</td><td>{{ Str::limit($c->notes ?? '-', 60) }}</td></tr>
@empty<tr><td colspan="5" class="text-muted text-center py-4">No condition checks found.</td></tr>@endforelse
</tbody></table></div>
@endsection
