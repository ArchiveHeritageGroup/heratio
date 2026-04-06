@extends('theme::layouts.1col')

@section('title', 'Disposal History')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3">Disposal History</h1>
        <a href="{{ route('records.disposal.queue') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-list"></i> Back to Queue
        </a>
    </div>

    <div class="card">
        <div class="card-body p-0">
            @if (count($items) > 0)
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>IO Title</th>
                            <th>Action</th>
                            <th>Status</th>
                            <th>Executed Date</th>
                            <th>Certificate</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $item->io_title ?? 'Untitled (IO #' . $item->information_object_id . ')' }}</td>
                                <td>
                                    @php
                                        $typeLabels = [
                                            'destroy' => 'Destroy',
                                            'transfer_archives' => 'Transfer to Archives',
                                            'transfer_external' => 'Transfer External',
                                            'retain_permanent' => 'Retain Permanently',
                                            'review' => 'Review',
                                        ];
                                    @endphp
                                    {{ $typeLabels[$item->action_type] ?? $item->action_type }}
                                </td>
                                <td>
                                    @php
                                        $statusBadges = [
                                            'executed' => 'dark',
                                            'rejected' => 'danger',
                                            'cancelled' => 'warning',
                                            'retained' => 'success',
                                        ];
                                    @endphp
                                    <span class="badge bg-{{ $statusBadges[$item->status] ?? 'secondary' }}">{{ ucfirst($item->status) }}</span>
                                </td>
                                <td>{{ $item->executed_at ? \Carbon\Carbon::parse($item->executed_at)->format('Y-m-d H:i') : '' }}</td>
                                <td>
                                    @if ($item->certificate_id)
                                        <a href="{{ route('records.disposal.verify', $item->id) }}">View</a>
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('records.disposal.show', $item->id) }}" class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="p-4 text-center text-muted">No completed disposal actions found.</div>
            @endif
        </div>
    </div>

    {{-- Pagination --}}
    @if ($total > $perPage)
        <nav class="mt-3">
            <ul class="pagination justify-content-center">
                @for ($i = 1; $i <= ceil($total / $perPage); $i++)
                    <li class="page-item {{ $i == $page ? 'active' : '' }}">
                        <a class="page-link" href="{{ route('records.disposal.history', ['page' => $i]) }}">{{ $i }}</a>
                    </li>
                @endfor
            </ul>
        </nav>
    @endif
</div>
@endsection
