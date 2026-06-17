{{-- Partial: order line rows (AJAX-refreshable). Data: $lines, $order, $editable --}}
@php
    // #1311 multi-fund splitting: preload split portions per line so the table
    // can show a fund-split summary and the editor opens pre-populated.
    $acqSvc = app(\AhgLibrary\Services\LibraryAcquisitionService::class);
    $lineSplits = [];
    foreach (($lines ?? []) as $__l) {
        $lineSplits[$__l->id] = $acqSvc->getLineFundSplits($__l->id);
    }
@endphp
<table class="table table-sm table-striped mb-0" id="acq-lines-table">
<thead><tr><th>{{ __('Title') }}</th><th>{{ __('ISBN') }}</th><th>{{ __('Author') }}</th><th class="text-end">{{ __('Qty') }}</th><th class="text-end">{{ __('Recd') }}</th><th class="text-end">{{ __('Unit') }}</th><th class="text-end">{{ __('Disc %') }}</th><th class="text-end">{{ __('Line Total') }}</th><th>{{ __('Funds') }}</th><th>{{ __('Status') }}</th></tr></thead>
<tbody>
@forelse($lines??[] as $l)
@php $splits = $lineSplits[$l->id] ?? []; @endphp
<tr data-line-id="{{ $l->id }}">
    <td>{{ e($l->title??'') }}</td>
    <td>{{ e($l->isbn??'') }}</td>
    <td>{{ e($l->author??'') }}</td>
    <td class="text-end">{{ (int)($l->quantity??0) }}</td>
    <td class="text-end">{{ (int)($l->quantity_received??0) }}</td>
    <td class="text-end">{{ number_format((float)($l->unit_price??0),2) }}</td>
    <td class="text-end">{{ number_format((float)($l->discount_percent??0),2) }}</td>
    <td class="text-end">{{ number_format((float)($l->line_total??0),2) }}</td>
    <td>
        @if(count($splits) > 0)
            <span class="badge bg-info" title="{{ __('Split across :n funds', ['n' => count($splits)]) }}"><i class="fas fa-code-branch me-1"></i>{{ count($splits) }}</span>
            <ul class="list-unstyled small mb-0">
                @foreach($splits as $sp)
                    <li><span class="text-muted">{{ e($sp->fund_code) }}</span>: {{ number_format((float)$sp->amount, 2) }}</li>
                @endforeach
            </ul>
        @elseif(!empty($l->fund_code))
            <span class="text-muted small">{{ e($l->fund_code) }}</span>
        @else
            <span class="text-muted small">{{ __('-') }}</span>
        @endif
        @if($editable ?? false)
            <button type="button" class="btn btn-link btn-sm p-0 acq-split-btn" data-line-id="{{ $l->id }}" data-line-total="{{ (float)($l->line_total??0) }}" data-line-title="{{ e($l->title??'') }}"><i class="fas fa-code-branch me-1"></i>{{ __('Split funds') }}</button>
        @endif
    </td>
    <td><span class="badge bg-secondary">{{ e($l->status??'') }}</span></td>
</tr>
@empty
<tr><td colspan="10" class="text-muted text-center py-3">{{ __('No lines yet.') }}</td></tr>
@endforelse
</tbody>
</table>
