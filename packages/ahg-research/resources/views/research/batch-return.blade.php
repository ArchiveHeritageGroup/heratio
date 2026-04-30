{{-- Batch Return - Migrated from AtoM --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'retrievalQueue'])
@endsection

@section('title', 'Batch Return')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.retrievalQueue') }}">Retrieval Queue</a></li>
        <li class="breadcrumb-item active">Batch Return</li>
    </ol>
</nav>

<h1 class="h2 mb-4"><i class="fas fa-undo text-primary me-2"></i>Batch Return</h1>

<form method="POST">
    @csrf
    <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">Select Items to Return</div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th><input type="checkbox" id="selectAll"></th><th>{{ __('Item') }}</th><th>{{ __('Researcher') }}</th><th>{{ __('Checked Out') }}</th><th>{{ __('Condition') }}</th></tr>
                </thead>
                <tbody>
                    @foreach($checkedOutItems ?? [] as $item)
                    <tr>
                        <td><input type="checkbox" name="item_ids[]" value="{{ $item->id }}"></td>
                        <td><strong>{{ e($item->title ?? 'Item #' . $item->id) }}</strong></td>
                        <td>{{ e(($item->researcher_first_name ?? '') . ' ' . ($item->researcher_last_name ?? '')) }}</td>
                        <td class="small">{{ $item->checked_out_at ?? '' }}</td>
                        <td>
                            <select name="condition_{{ $item->id }}" class="form-select form-select-sm">
                                <option value="good">{{ __('Good') }}</option>
                                <option value="fair">{{ __('Fair') }}</option>
                                <option value="damaged">{{ __('Damaged') }}</option>
                            </select>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn atom-btn-white"><i class="fas fa-undo me-1"></i>Return Selected</button>
            <a href="{{ route('research.retrievalQueue') }}" class="btn atom-btn-white">Cancel</a>
        </div>
    </div>
</form>
<script>document.getElementById('selectAll')?.addEventListener('change', function() { document.querySelectorAll('input[name="item_ids[]"]').forEach(cb => cb.checked = this.checked); });</script>
@endsection