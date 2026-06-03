{{--
    MARC validation report.

    Renders the structured report from AhgLibrary\Services\MarcValidationService.
    Expects $report (array) and optionally $marcxml (string, the submitted body).

    @author    Johan Pieterse <johan@plainsailingisystems.co.za>
    @copyright Plain Sailing Information Systems
    @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', 'MARC Validation Report')

@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center mb-3">
        <a href="{{ route('library.marc-index') }}" class="btn btn-outline-secondary btn-sm me-3" title="{{ __('Back to MARC Editor') }}">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h2 class="mb-0">{{ __('MARC Validation Report') }}</h2>
    </div>

    @php
        $report = $report ?? ['valid' => false, 'records' => [], 'error_count' => 0, 'warning_count' => 0, 'parse_error' => 'No report supplied.'];
        $isValid = (bool) ($report['valid'] ?? false);
    @endphp

    @if (! empty($report['parse_error']))
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-times-circle me-2"></i>
            <strong>Document error:</strong> {{ $report['parse_error'] }}
        </div>
    @else
        <div class="alert {{ $isValid ? 'alert-success' : 'alert-danger' }} d-flex align-items-center" role="alert">
            <i class="fas {{ $isValid ? 'fa-check-circle' : 'fa-exclamation-triangle' }} me-3 fa-lg"></i>
            <div>
                <strong>{{ $isValid ? 'Valid' : 'Invalid' }}.</strong>
                {{ (int) ($report['error_count'] ?? 0) }} error(s),
                {{ (int) ($report['warning_count'] ?? 0) }} warning(s)
                across {{ count($report['records'] ?? []) }} record(s).
            </div>
        </div>

        @foreach (($report['records'] ?? []) as $rec)
            @php
                $errors = $rec['errors'] ?? [];
                $warnings = $rec['warnings'] ?? [];
                $recValid = empty($errors);
            @endphp
            <div class="card mb-3 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>
                        <strong>Record #{{ (int) ($rec['index'] ?? 0) + 1 }}</strong>
                        @if (! empty($rec['title']))
                            &mdash; {{ $rec['title'] }}
                        @endif
                        @if (! empty($rec['control_number']))
                            <span class="text-muted small">(001: {{ $rec['control_number'] }})</span>
                        @endif
                    </span>
                    <span class="badge {{ $recValid ? 'bg-success' : 'bg-danger' }}">
                        {{ $recValid ? 'OK' : count($errors) . ' error(s)' }}
                    </span>
                </div>
                <div class="card-body">
                    @if (empty($errors) && empty($warnings))
                        <p class="text-success mb-0"><i class="fas fa-check me-2"></i>No problems found.</p>
                    @endif

                    @if (! empty($errors))
                        <h6 class="text-danger"><i class="fas fa-times-circle me-1"></i>Errors</h6>
                        <ul class="mb-3">
                            @foreach ($errors as $err)
                                <li class="text-danger">{{ $err }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if (! empty($warnings))
                        <h6 class="text-warning"><i class="fas fa-exclamation-circle me-1"></i>Warnings</h6>
                        <ul class="mb-0">
                            @foreach ($warnings as $warn)
                                <li class="text-warning-emphasis">{{ $warn }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endforeach
    @endif

    @if (! empty($marcxml))
        <div class="card mt-4">
            <div class="card-header">Submitted MARCXML</div>
            <div class="card-body">
                <pre class="mb-0 small" style="max-height: 400px; overflow: auto;">{{ $marcxml }}</pre>
            </div>
        </div>
    @endif
</div>
@endsection
