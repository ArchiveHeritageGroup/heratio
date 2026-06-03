{{-- Chatbot low-grounding review queue --}}
@extends('layouts/admin')

@section('title', 'Chatbot Review — Admin')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>{{ __('Chatbot Review Queue') }}</h2>
        <a href="{{ route('admin.chatbot.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            @if (!empty($rows))
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Session') }}</th>
                            <th>{{ __('Score') }}</th>
                            <th>{{ __('Model') }}</th>
                            <th>{{ __('Sources') }}</th>
                            <th>{{ __('Response') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td class="small text-muted" style="width: 140px;">{{ $row['created_at'] }}</td>
                                <td class="small text-muted" style="max-width: 100px;">
                                    <span class="text-truncate d-inline-block" style="max-width: 90px;">
                                        {{ substr($row['session_id'] ?? '', 0, 12) }}…
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $row['grounding_score'] >= 0.5 ? 'bg-success' : 'bg-warning' }}">
                                        {{ number_format($row['grounding_score'], 3) }}
                                    </span>
                                </td>
                                <td class="small">{{ $row['model'] ?? '–' }}</td>
                                <td class="small" style="max-width: 200px;">
                                    @if (!empty($row['sources']))
                                        @foreach (array_slice($row['sources'], 0, 3) as $src)
                                            <div>{{ $src['ref'] ?? '' }} {{ Str::limit($src['title'] ?? '', 40) }}</div>
                                        @endforeach
                                    @else
                                        <span class="text-muted">none</span>
                                    @endif
                                </td>
                                <td class="small" style="max-width: 350px;">
                                    {{ Str::limit($row['content'] ?? '', 200) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="p-4 text-center text-success">
                    <i class="fas fa-check-circle me-2"></i>
                    No responses requiring review. All groundings are above threshold.
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
