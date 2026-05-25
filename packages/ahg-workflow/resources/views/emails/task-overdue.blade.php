@extends('emails._layout', ['subject' => __('Workflow task overdue')])

@section('content')
    <h2 style="margin-top:0;">{{ __('Workflow task overdue') }}</h2>

    <p>{{ __('Hello :name,', ['name' => $ctx['assignee_name'] ?? '']) }}</p>

    <p>{{ __('The following workflow task is overdue and needs your attention:') }}</p>

    <ul>
        <li><strong>{{ __('Workflow') }}:</strong> {{ $ctx['workflow_name'] ?? '' }}</li>
        <li><strong>{{ __('Due') }}:</strong> {{ $ctx['due_at'] ?? '' }}</li>
        <li><strong>{{ __('Overdue by') }}:</strong> {{ $ctx['overdue_days'] ?? 0 }} {{ __('days') }}</li>
    </ul>

    @if (! empty($ctx['task_url']))
        <p style="text-align: center; margin: 24px 0;">
            <a href="{{ $ctx['task_url'] }}" class="btn">{{ __('Open task') }}</a>
        </p>
    @endif

    <p class="muted">{{ __('You are receiving this because you are the assignee. Adjust your preferences in your profile to change notification settings.') }}</p>
@endsection
