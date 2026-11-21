{{--
  Interface translation coverage (admin, heratio#1416). Per locale: how much of
  the coded interface (__() keys) shows translated, counting lang/*.json with
  the ui_string overrides on top. South African languages are listed first and
  marked. Read-only.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems.
  Licensed under the GNU Affero General Public License v3 or later.
--}}
@extends('theme::layouts.1col')
@section('title', __('Interface translation coverage'))

@section('content')
<div class="container-fluid py-3">
  <div class="d-flex flex-wrap align-items-baseline gap-2 mb-1">
    <h1 class="h4 mb-0"><i class="fas fa-language me-2 text-primary"></i>{{ __('Interface translation coverage') }}</h1>
    <span class="ms-auto"></span>
    @if($saOnly)
      <a href="{{ route('admin.ui-translation-coverage') }}" class="btn btn-sm btn-outline-secondary">{{ __('All languages') }}</a>
    @else
      <a href="{{ route('admin.ui-translation-coverage', ['sa' => 1]) }}" class="btn btn-sm btn-outline-secondary">{{ __('South African languages only') }}</a>
    @endif
    <a href="{{ route('admin.ui-translation-coverage', array_filter(['refresh' => 1, 'sa' => $saOnly ? 1 : null])) }}" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-rotate me-1"></i>{{ __('Recount') }}
    </a>
  </div>
  <p class="text-muted small mb-3" style="max-width:880px">
    {{ __('Share of the interface strings in the code that each language shows translated. It counts the language file and the interface strings stored in the database, which take precedence over the file. Strings that correctly read the same as English count as done.') }}
    {{ __('Counted at :time.', ['time' => $generatedAt]) }}
  </p>

  @if(empty($rows))
    <div class="alert alert-info">{{ __('No language files found.') }}</div>
  @else
    <div class="table-responsive">
      <table class="table table-sm table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th>{{ __('Language') }}</th>
            <th class="text-end">{{ __('Translated') }}</th>
            <th class="text-end">{{ __('Same as English') }}</th>
            <th class="text-end">{{ __('Missing') }}</th>
            <th class="text-end">{{ __('Reviewed or held') }}</th>
            <th class="text-end" title="{{ __('Database strings that hide a different translation in the language file') }}">{{ __('Database hides file') }}</th>
            <th style="min-width:180px">{{ __('Coverage') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rows as $r)
            @php
              $name = class_exists(\Locale::class) ? \Locale::getDisplayName(str_replace('@valencia', '', $r['locale']), app()->getLocale()) : $r['locale'];
              $bar = $r['pct'] >= 90 ? 'bg-success' : ($r['pct'] >= 50 ? 'bg-warning' : 'bg-danger');
            @endphp
            <tr @class(['table-warning' => $r['sa']])>
              <td>
                <strong>{{ $name ?: $r['locale'] }}</strong> <code class="small">{{ $r['locale'] }}</code>
                @if($r['sa'])<span class="badge bg-primary ms-1">{{ __('South Africa') }}</span>@endif
                @if($r['machine'])<span class="badge bg-secondary ms-1" title="{{ __('Machine translated in part; native-speaker review required before production') }}">{{ __('Machine') }}</span>@endif
                @if($r['malformed'])<span class="badge bg-danger ms-1">{{ __('File is not a JSON object') }}</span>@endif
              </td>
              <td class="text-end">{{ number_format($r['translated']) }}</td>
              <td class="text-end">{{ number_format($r['same_as_source']) }}</td>
              <td class="text-end">{{ number_format($r['missing']) }}</td>
              <td class="text-end">{{ number_format($r['reviewed']) }}</td>
              <td class="text-end">{{ number_format($r['db_hides_file'] + $r['db_differs']) }}</td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="progress flex-grow-1" style="height:8px" role="progressbar" aria-valuenow="{{ (int) $r['pct'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Coverage') }}">
                    <div class="progress-bar {{ $bar }}" style="width: {{ $r['pct'] }}%"></div>
                  </div>
                  <span class="small text-nowrap">{{ $r['pct'] }}%</span>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <p class="text-muted small">{{ __('Reference: :n interface strings in the code.', ['n' => number_format($rows[0]['reference'])]) }}</p>
  @endif
</div>
@endsection
