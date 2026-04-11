{{-- Acquisitions Report — cloned from AtoM. @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', 'Acquisitions Report')
@section('sidebar')<div class="sidebar-content"><a href="{{ route('ahgspectrum.reports') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>Back</a></div>@endsection
@section('title-block')<h1><i class="fas fa-hand-holding me-2"></i>Acquisitions Report</h1>@endsection
@section('content')
<div class="alert alert-info"><strong>{{ count($items) }}</strong> acquisitions found</div>
<div class="table-responsive"><table class="table table-striped table-hover"><thead class="table-dark"><tr><th>Object</th><th>Date</th><th>Method</th><th>Source</th></tr></thead><tbody>
@forelse($items as $a)<tr><td><strong>{{ e($a->object_title ?? '-') }}</strong></td><td>{{ $a->acquisition_date ? date('d M Y', strtotime($a->acquisition_date)) : '-' }}</td><td>{{ e($a->method ?? '-') }}</td><td>{{ e($a->source ?? '-') }}</td></tr>
@empty<tr><td colspan="4" class="text-muted text-center py-4">No acquisitions found.</td></tr>@endforelse
</tbody></table></div>
@endsection
