{{-- Partial: order line rows (AJAX-refreshable). Data: $lines, $order, $editable --}}
<table class="table table-sm table-striped mb-0" id="acq-lines-table">
<thead><tr><th>{{ __('Title') }}</th><th>{{ __('ISBN') }}</th><th>{{ __('Author') }}</th><th class="text-end">{{ __('Qty') }}</th><th class="text-end">{{ __('Recd') }}</th><th class="text-end">{{ __('Unit') }}</th><th class="text-end">{{ __('Disc %') }}</th><th class="text-end">{{ __('Line Total') }}</th><th>{{ __('Status') }}</th></tr></thead>
<tbody>
@forelse($lines??[] as $l)
<tr data-line-id="{{ $l->id }}"><td>{{ e($l->title??'') }}</td><td>{{ e($l->isbn??'') }}</td><td>{{ e($l->author??'') }}</td><td class="text-end">{{ (int)($l->quantity??0) }}</td><td class="text-end">{{ (int)($l->quantity_received??0) }}</td><td class="text-end">{{ number_format((float)($l->unit_price??0),2) }}</td><td class="text-end">{{ number_format((float)($l->discount_percent??0),2) }}</td><td class="text-end">{{ number_format((float)($l->line_total??0),2) }}</td><td><span class="badge bg-secondary">{{ e($l->status??'') }}</span></td></tr>
@empty
<tr><td colspan="9" class="text-muted text-center py-3">{{ __('No lines yet.') }}</td></tr>
@endforelse
</tbody>
</table>
