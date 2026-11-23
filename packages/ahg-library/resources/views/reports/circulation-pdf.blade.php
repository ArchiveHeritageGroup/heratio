{{-- Circulation statistics PDF (#1476). @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<title>{{ __('Circulation statistics') }}</title>
<style>
  body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 10px; color: #262626; }
  h1 { font-size: 18px; color: #10373E; margin: 0 0 4px; }
  h2 { font-size: 12px; color: #10373E; margin: 14px 0 4px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #C3CDCE; padding: 3px 5px; text-align: left; }
  th { background: #10373E; color: #fff; font-weight: bold; }
  td.n, th.n { text-align: right; }
</style>
</head>
<body>
<h1>{{ __('Circulation statistics') }}</h1>
<p>{{ $report['period']['from'] }} - {{ $report['period']['to'] }} | {{ $report['branch_label'] }}</p>

<h2>{{ __('Summary') }}</h2>
<table><thead><tr><th>{{ __('Measure') }}</th><th class="n">{{ __('Value') }}</th></tr></thead><tbody>
@foreach($report['summary'] as $key => $value)
<tr><td>{{ __(\AhgLibrary\Services\LibraryCirculationStatsService::measureLabel($key)) }}</td><td class="n">{{ $value ?? 'n/a' }}</td></tr>
@endforeach
</tbody></table>

@if($report['stock_note'])<p>{{ __($report['stock_note']) }}</p>@endif

<h2>{{ __('Issues per month') }}</h2>
<table><thead><tr><th>{{ __('Month') }}</th><th class="n">{{ __('Issues') }}</th><th class="n">{{ __('Returns') }}</th></tr></thead><tbody>
@foreach($report['monthly'] as $r)<tr><td>{{ $r['month'] }}</td><td class="n">{{ $r['issues'] }}</td><td class="n">{{ $r['returns'] }}</td></tr>@endforeach
</tbody></table>

@if($report['by_branch'])
<h2>{{ __('By branch') }}</h2>
<table><thead><tr><th>{{ __('Branch') }}</th><th class="n">{{ __('Issues') }}</th><th class="n">{{ __('Returns') }}</th><th class="n">{{ __('Copies') }}</th><th class="n">{{ __('Turnover') }}</th></tr></thead><tbody>
@foreach($report['by_branch'] as $r)<tr><td>{{ $r['name'] }}</td><td class="n">{{ $r['issues'] }}</td><td class="n">{{ $r['returns'] }}</td><td class="n">{{ $r['copies'] }}</td><td class="n">{{ $r['turnover'] ?? 'n/a' }}</td></tr>@endforeach
</tbody></table>
@endif

<h2>{{ __('Busiest titles') }}</h2>
<table><thead><tr><th>{{ __('Title') }}</th><th>{{ __('Call number') }}</th><th class="n">{{ __('Issues') }}</th></tr></thead><tbody>
@forelse($report['top_titles'] as $r)<tr><td>{{ $r['title'] }}</td><td>{{ $r['call_number'] }}</td><td class="n">{{ $r['issues'] }}</td></tr>@empty<tr><td colspan="3">{{ __('No loans in this period.') }}</td></tr>@endforelse
</tbody></table>

<h2>{{ __('Dead stock') }} ({{ $report['dead_stock']['total'] }})</h2>
<table><thead><tr><th>{{ __('Title') }}</th><th>{{ __('Barcode') }}</th><th>{{ __('Call number') }}</th><th>{{ __('Last loan') }}</th></tr></thead><tbody>
@forelse($report['dead_stock']['rows'] as $r)<tr><td>{{ $r['title'] }}</td><td>{{ $r['barcode'] }}</td><td>{{ $r['call_number'] }}</td><td>{{ $r['last_loan'] ?? __('Never') }}</td></tr>@empty<tr><td colspan="4">{{ __('Every copy in stock was borrowed.') }}</td></tr>@endforelse
</tbody></table>

<h2>{{ __('Patron activity') }}</h2>
<table><thead><tr><th>{{ __('Loans in period') }}</th><th class="n">{{ __('Patrons') }}</th></tr></thead><tbody>
@foreach($report['patron_activity'] as $r)<tr><td>{{ $r['band'] }}</td><td class="n">{{ $r['patrons'] }}</td></tr>@endforeach
</tbody></table>

<h2>{{ __('Fines') }}</h2>
<table><thead><tr><th>{{ __('Currency') }}</th><th class="n">{{ __('Assessed') }}</th>@if($report['fines_settled'])<th class="n">{{ __('Paid') }}</th><th class="n">{{ __('Waived') }}</th>@endif<th class="n">{{ __('Outstanding') }}</th></tr></thead><tbody>
@forelse($report['fines'] as $r)<tr><td>{{ $r['currency'] }}</td><td class="n">{{ $r['assessed'] }}</td>@if($report['fines_settled'])<td class="n">{{ $r['paid'] }}</td><td class="n">{{ $r['waived'] }}</td>@endif<td class="n">{{ $r['outstanding'] }}</td></tr>@empty<tr><td colspan="3">{{ __('No fines in this period.') }}</td></tr>@endforelse
</tbody></table>
@if($report['fines_note'])<p>{{ __($report['fines_note']) }}</p>@endif
</body>
</html>
