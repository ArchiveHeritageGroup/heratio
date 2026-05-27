{{--
    Preview Data view - Heratio data-migration

    Copyright (C) 2026 Johan Pieterse
    Plain Sailing Information Systems
    Email: johan@plainsailingisystems.co.za

    Issue #740 - Data-migration exports parity.
    PSIS twin: atom-ahg-plugins#86.
--}}
@extends('theme::layouts.1col')
@section('title', __('Preview data'))
@section('body-class', 'browse data-migration preview-data')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-search me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('Preview data') }}</h1>
      <small class="text-muted">{{ __('First rows projected through the current mapping.') }}</small>
    </div>
  </div>

  @php
    $rows = isset($rows) ? $rows : collect();
    $headers = $headers ?? [];
    $headerSet = [];
    foreach ($rows as $row) {
        foreach (array_keys((array) $row) as $k) {
            $headerSet[$k] = true;
        }
    }
    $columnList = array_keys($headerSet);
  @endphp

  @if ($rows->isNotEmpty())
    <div class="card">
      <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-table me-2"></i>{{ __('Preview') }} ({{ $rows->count() }})
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-striped table-hover mb-0">
            <thead>
              <tr>
                <th style="width:4rem">{{ __('Row') }}</th>
                @foreach ($columnList as $col)
                  <th>{{ $col }}</th>
                @endforeach
              </tr>
            </thead>
            <tbody>
              @foreach ($rows as $i => $row)
                <tr>
                  <td><code>{{ $i + 1 }}</code></td>
                  @foreach ($columnList as $col)
                    <td>{{ is_array(($row[$col] ?? null)) ? implode('|', $row[$col]) : ($row[$col] ?? '') }}</td>
                  @endforeach
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @else
    <div class="alert alert-info">
      <i class="fas fa-info-circle me-2"></i>{{ __('No preview rows. Upload a source file and save a mapping first.') }}
    </div>
  @endif
@endsection
