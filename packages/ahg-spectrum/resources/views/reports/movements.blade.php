{{-- Movements Report — cloned from AtoM. @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', 'Movements Report')
@section('sidebar')<div class="sidebar-content"><a href="{{ route('ahgspectrum.reports') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>Back</a></div>@endsection
@section('title-block')<h1><i class="fas fa-truck me-2"></i>Movements Report</h1>@endsection
@section('content')
<div class="alert alert-info"><strong>{{ count($items) }}</strong> movements found</div>
<div class="table-responsive"><table class="table table-striped table-hover"><thead class="table-dark"><tr><th>Object</th><th>Date</th><th>From</th><th>To</th><th>Reason</th></tr></thead><tbody>
@forelse($items as $m)<tr><td><strong>{{ e($m->object_title ?? '-') }}</strong></td><td>{{ $m->movement_date ? date('d M Y', strtotime($m->movement_date)) : '-' }}</td><td>{{ e($m->location_from ?? '-') }}</td><td>{{ e($m->location_to ?? '-') }}</td><td>{{ e($m->reason ?? '-') }}</td></tr>
@empty<tr><td colspan="5" class="text-muted text-center py-4">No movements found.</td></tr>@endforelse
</tbody></table></div>
@endsection
