{{-- Batch Checkout - Migrated from AtoM --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'retrievalQueue'])
@endsection

@section('title', 'Batch Checkout')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.retrievalQueue') }}">Retrieval Queue</a></li>
        <li class="breadcrumb-item active">Batch Checkout</li>
    </ol>
</nav>

<h1 class="h2 mb-4"><i class="fas fa-box-open text-primary me-2"></i>Batch Checkout</h1>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<form method="POST">
    @csrf
    <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">Select Items for Checkout</div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th><input type="checkbox" id="selectAll"></th><th>Item</th><th>Researcher</th><th>Requested</th><th>Location</th></tr>
                </thead>
                <tbody>
                    @foreach($pendingItems ?? [] as $item)
                    <tr>
                        <td><input type="checkbox" name="item_ids[]" value="{{ $item->id }}"></td>
                        <td><strong>{{ e($item->title ?? 'Item #' . $item->id) }}</strong></td>
                        <td>{{ e(($item->researcher_first_name ?? '') . ' ' . ($item->researcher_last_name ?? '')) }}</td>
                        <td class="small">{{ $item->requested_at ?? '' }}</td>
                        <td>{{ e($item->location ?? '-') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn atom-btn-white"><i class="fas fa-box-open me-1"></i>Checkout Selected</button>
            <a href="{{ route('research.retrievalQueue') }}" class="btn atom-btn-white">Cancel</a>
        </div>
    </div>
</form>
<script>document.getElementById('selectAll')?.addEventListener('change', function() { document.querySelectorAll('input[name="item_ids[]"]').forEach(cb => cb.checked = this.checked); });</script>
@endsection