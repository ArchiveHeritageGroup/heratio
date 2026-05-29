{{--
    MARC merge / conflict review.

    Renders the field-level diff from AhgLibrary\Services\MarcMergeService::diff().
    Expects $report (array) and optionally $marcxml (string, incoming record).

    Each changed field shows master vs incoming side by side with a radio pair
    so a cataloguer can choose which value to keep before committing the merge.

    @author    Johan Pieterse <johan@plainsailingisystems.co.za>
    @copyright Plain Sailing Information Systems
    @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', 'MARC Conflict Review')

@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center mb-3">
        <a href="{{ route('library.marc-index') }}" class="btn btn-outline-secondary btn-sm me-3" title="{{ __('Back to MARC Editor') }}">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h2 class="mb-0">MARC Conflict Review</h2>
    </div>

    @php
        $report = $report ?? ['matched' => false, 'fields' => [], 'conflict_count' => 0, 'warnings' => []];
        $statusMeta = [
            'unchanged' => ['badge' => 'bg-secondary', 'label' => 'Unchanged'],
            'changed'   => ['badge' => 'bg-warning text-dark', 'label' => 'Conflict'],
            'added'     => ['badge' => 'bg-success', 'label' => 'Added'],
            'removed'   => ['badge' => 'bg-danger', 'label' => 'Removed'],
        ];
        $fmt = function ($v) {
            if (is_array($v)) {
                return $v === [] ? '(none)' : implode('; ', $v);
            }
            return ($v === null || $v === '') ? '(empty)' : $v;
        };
    @endphp

    <div class="alert {{ ($report['has_conflicts'] ?? false) ? 'alert-warning' : 'alert-info' }} d-flex align-items-center" role="alert">
        <i class="fas fa-code-branch me-3 fa-lg"></i>
        <div>
            @if ($report['matched'] ?? false)
                Matched master record IO #{{ (int) ($report['matched_io_id'] ?? 0) }}
                @if (! empty($report['control_number']))
                    (001: {{ $report['control_number'] }})
                @endif
                &mdash; <strong>{{ (int) ($report['conflict_count'] ?? 0) }} conflict(s)</strong> to resolve.
            @else
                No master record matched the incoming 001 control number.
                A commit would create a new record; every field is reported as an addition.
            @endif
        </div>
    </div>

    @if (! empty($report['warnings']))
        <div class="alert alert-secondary">
            <ul class="mb-0">
                @foreach ($report['warnings'] as $warn)
                    <li>{{ $warn }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('library.marc-import-commit') }}">
        @csrf
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 18%;">Field</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 36%;">Master (Heratio)</th>
                        <th style="width: 36%;">Incoming (edited)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (($report['fields'] ?? []) as $field)
                        @php
                            $st = $field['status'] ?? 'unchanged';
                            $meta = $statusMeta[$st] ?? $statusMeta['unchanged'];
                            $isConflict = $st === 'changed';
                        @endphp
                        <tr class="{{ $isConflict ? 'table-warning' : '' }}">
                            <td>
                                <strong>{{ $field['label'] ?? $field['field'] }}</strong>
                                <div class="text-muted small">{{ $field['field'] ?? '' }}</div>
                            </td>
                            <td><span class="badge {{ $meta['badge'] }}">{{ $meta['label'] }}</span></td>
                            <td>
                                @if ($isConflict)
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio"
                                               name="resolve[{{ $field['field'] }}]" value="master"
                                               id="m_{{ $field['field'] }}" checked>
                                        <label class="form-check-label" for="m_{{ $field['field'] }}">
                                            {{ $fmt($field['master'] ?? null) }}
                                        </label>
                                    </div>
                                @else
                                    {{ $fmt($field['master'] ?? null) }}
                                @endif
                            </td>
                            <td>
                                @if ($isConflict)
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio"
                                               name="resolve[{{ $field['field'] }}]" value="incoming"
                                               id="i_{{ $field['field'] }}">
                                        <label class="form-check-label" for="i_{{ $field['field'] }}">
                                            {{ $fmt($field['incoming'] ?? null) }}
                                        </label>
                                    </div>
                                @else
                                    {{ $fmt($field['incoming'] ?? null) }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if (! empty($marcxml))
            <input type="hidden" name="raw_marcxml" value="{{ $marcxml }}">
        @endif
    </form>

    @if (! empty($marcxml))
        <div class="card mt-4">
            <div class="card-header">Incoming MARCXML</div>
            <div class="card-body">
                <pre class="mb-0 small" style="max-height: 400px; overflow: auto;">{{ $marcxml }}</pre>
            </div>
        </div>
    @endif
</div>
@endsection
