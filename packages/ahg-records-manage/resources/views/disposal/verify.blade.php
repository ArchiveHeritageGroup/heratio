@extends('theme::layouts.1col')

@section('title', 'DoD 5015.2 Verification - Disposal #' . $action->id)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">DoD 5015.2 Destruction Verification</h1>
        <a href="{{ route('records.disposal.show', $action->id) }}" class="btn btn-outline-secondary btn-sm">Back to Disposal Action</a>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <strong>Disposal Action #{{ $action->id }}</strong> &mdash;
            {{ $action->io_title ?? 'Untitled (IO #' . $action->information_object_id . ')' }}
        </div>
        <div class="card-body">
            {{-- Overall Result --}}
            <div class="text-center mb-4">
                @if ($result['verified'])
                    <div class="display-4 text-success mb-2">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2 class="text-success">VERIFIED</h2>
                    <p class="text-muted">All DoD 5015.2 destruction verification checks passed.</p>
                @else
                    <div class="display-4 text-danger mb-2">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <h2 class="text-danger">FAILED</h2>
                    <p class="text-muted">One or more verification checks failed. See details below.</p>
                @endif
            </div>

            {{-- Checklist --}}
            <h5 class="mb-3">Verification Checklist</h5>
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">Result</th>
                        <th>Check</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($result['checks'] as $check)
                        <tr>
                            <td class="text-center">
                                @if ($check['passed'])
                                    <span class="text-success fs-5"><i class="fas fa-check"></i></span>
                                @else
                                    <span class="text-danger fs-5"><i class="fas fa-times"></i></span>
                                @endif
                            </td>
                            <td>{{ $check['name'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Failures --}}
            @if (count($result['failures']) > 0)
                <h5 class="mt-4 mb-2 text-danger">Failures</h5>
                <ul class="list-group">
                    @foreach ($result['failures'] as $failure)
                        <li class="list-group-item list-group-item-danger">{{ $failure }}</li>
                    @endforeach
                </ul>
            @endif

            {{-- Certificate Details --}}
            @if ($action->certificate)
                <h5 class="mt-4 mb-2">Destruction Certificate</h5>
                <table class="table table-sm table-bordered">
                    <tr>
                        <th style="width: 200px;">Certificate Number</th>
                        <td>{{ $action->certificate->certificate_number }}</td>
                    </tr>
                    <tr>
                        <th>Destruction Date</th>
                        <td>{{ $action->certificate->destruction_date ? \Carbon\Carbon::parse($action->certificate->destruction_date)->format('Y-m-d H:i') : '' }}</td>
                    </tr>
                    <tr>
                        <th>Destruction Method</th>
                        <td>{{ $action->certificate->destruction_method }}</td>
                    </tr>
                    <tr>
                        <th>Authorized By</th>
                        <td>{{ $action->certificate->authorized_by }}</td>
                    </tr>
                    <tr>
                        <th>Content Hash (SHA-256)</th>
                        <td><code>{{ $action->certificate->content_hash ?? 'N/A' }}</code></td>
                    </tr>
                    @if ($action->certificate->witness)
                        <tr>
                            <th>Witness</th>
                            <td>{{ $action->certificate->witness }}</td>
                        </tr>
                    @endif
                </table>
            @endif

            {{-- Re-verify Button --}}
            <div class="mt-4">
                <a href="{{ route('records.disposal.verify', $action->id) }}" class="btn btn-outline-primary">
                    Re-verify
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
