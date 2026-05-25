{{ __('Workflow task overdue') }}
======================================

{{ __('Hello :name,', ['name' => $ctx['assignee_name'] ?? '']) }}

{{ __('The following workflow task is overdue and needs your attention:') }}

  {{ __('Workflow') }}:   {{ $ctx['workflow_name'] ?? '' }}
  {{ __('Due') }}:        {{ $ctx['due_at'] ?? '' }}
  {{ __('Overdue by') }}: {{ $ctx['overdue_days'] ?? 0 }} {{ __('days') }}

@if (! empty($ctx['task_url']))
{{ __('Open task:') }} {{ $ctx['task_url'] }}
@endif

--
{{ __('You are receiving this because you are the assignee.') }}
