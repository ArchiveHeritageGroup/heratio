{{-- Object Entry Report — cloned from AtoM. @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', 'Object Entry Report')
@section('sidebar')<div class="sidebar-content"><a href="{{ route('ahgspectrum.reports') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>Back</a></div>@endsection
@section('title-block')<h1><i class="fas fa-sign-in-alt me-2"></i>Object Entry Report</h1>@endsection
@section('content')
<div class="alert alert-info"><strong>{{ count($items) }}</strong> entries found</div>
<div class="table-responsive"><table class="table table-striped table-hover"><thead class="table-dark"><tr><th>Object</th><th>Entry Date</th><th>Entry Number</th><th>Depositor</th><th>Reason</th></tr></thead><tbody>
@forelse($items as $e)<tr><td><strong>{{ e($e->object_title ?? '-') }}</strong></td><td>{{ $e->entry_date ? date('d M Y', strtotime($e->entry_date)) : '-' }}</td><td>{{ e($e->entry_number ?? '-') }}</td><td>{{ e($e->depositor ?? '-') }}</td><td>{{ e($e->reason ?? '-') }}</td></tr>
@empty<tr><td colspan="5" class="text-muted text-center py-4">No entries found.</td></tr>@endforelse
</tbody></table></div>
@endsection
