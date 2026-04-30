@extends('theme::layout')

@section('title', 'Provenance Records')

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-4"><i class="bi bi-clock-history me-2"></i>{{ __('Provenance Records') }}</h4>

    <div class="card">
        <div class="card-body p-0">
            @if($records->isEmpty())
                <div class="p-4 text-center text-muted">No provenance records found.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Record') }}</th>
                                <th>{{ __('Events') }}</th>
                                <th>{{ __('Earliest') }}</th>
                                <th>{{ __('Latest') }}</th>
                                <th>{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($records as $record)
                                <tr>
                                    <td>
                                        <a href="{{ route('informationobject.show', $record->slug) }}">{{ $record->title ?? $record->slug }}</a>
                                    </td>
                                    <td>{{ $record->event_count }}</td>
                                    <td>{{ $record->earliest_event ?? 'N/A' }}</td>
                                    <td>{{ $record->latest_event ?? 'N/A' }}</td>
                                    <td>
                                        <a href="{{ route('provenance.view', $record->slug) }}" class="atom-btn-white btn-sm me-1">View</a>
                                        <a href="{{ route('provenance.timeline', $record->slug) }}" class="atom-btn-white btn-sm">Timeline</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3">{{ $records->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
