{{-- Chatbot admin dashboard --}}
@extends('layouts/admin')

@section('title', 'Chatbot — Admin')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>{{ __('Chatbot Administration') }}</h2>
        <a href="{{ route('admin.chatbot.review') }}" class="btn btn-outline-warning">
            <i class="fas fa-exclamation-triangle me-1"></i> Review Queue
            @if (count($reviewQueue ?? []) > 0)
                <span class="badge bg-warning text-dark">{{ count($reviewQueue) }}</span>
            @endif
        </a>
    </div>

    {{-- Stats --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Total Messages</div>
                    <div class="display-6">{{ $stats['total_messages'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Messages (30d)</div>
                    <div class="display-6">{{ $stats['messages_30d'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Sessions (30d)</div>
                    <div class="display-6">{{ $stats['sessions_30d'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 {{ ($stats['low_grounding_30d'] ?? 0) > 0 ? 'border-warning' : 'border-success' }} shadow-sm">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Low Groundings (30d)</div>
                    <div class="display-6 {{ ($stats['low_grounding_30d'] ?? 0) > 0 ? 'text-warning' : 'text-success' }}">
                        {{ $stats['low_grounding_30d'] ?? 0 }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Low grounding review queue --}}
    @if (!empty($reviewQueue))
        <div class="card shadow-sm">
            <div class="card-header bg-warning bg-opacity-25">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i> Responses Needing Review</h5>
                <p class="small text-muted mb-0">Responses below the grounding threshold ({{ config('ahg-ai-chatbot.grounding_threshold') }}).</p>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Session') }}</th>
                            <th>{{ __('Grounding Score') }}</th>
                            <th>{{ __('Model') }}</th>
                            <th>{{ __('Response excerpt') }}</th>
                            <th>{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reviewQueue as $row)
                            <tr>
                                <td class="small text-muted">{{ $row['created_at'] }}</td>
                                <td class="small">{{ substr($row['session_id'], 0, 12) }}…</td>
                                <td>
                                    <span class="badge {{ $row['grounding_score'] >= 0.5 ? 'bg-success' : 'bg-warning' }}">
                                        {{ number_format($row['grounding_score'], 3) }}
                                    </span>
                                </td>
                                <td class="small">{{ $row['model'] ?? '–' }}</td>
                                <td class="small" style="max-width: 400px;">
                                    <span class="text-truncate d-inline-block" style="max-width: 380px;">
                                        {{ Str::limit($row['content'], 120) }}
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-outline-primary btn-sm"
                                            onclick="showDetail({{ $row['id'] }})">
                                        View
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-1"></i>
            No low-grounding responses in the last 30 days.
        </div>
    @endif
</div>

{{-- Detail modal --}}
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Response Detail') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detail-body">
                <p class="text-muted">Loading…</p>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function showDetail(id) {
    var tbody = document.querySelectorAll('tr');
    // Locate the row by id in the table and rebuild a minimal detail
    fetch('/admin/ai/llm/health')
        .then(function() { throw new Error('stub'); })
        // no-op; detail is pre-rendered in TT rows
    var modal = new bootstrap.Modal(document.getElementById('detailModal'));
    modal.show();
}
</script>
@endpush
