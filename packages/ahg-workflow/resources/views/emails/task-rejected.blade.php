<!DOCTYPE html>
<html lang="{{ ahg_html_lang() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Workflow task rejected') }}</title>
</head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #222;">
    <h2 style="color: #dc2626;">{{ __('Workflow task rejected') }}</h2>

    <p>{{ __('Hello :name,', ['name' => $context['recipient_name'] ?? __('colleague')]) }}</p>

    <p>{{ __('Your task :step in the workflow :workflow has been rejected by :reviewer. Please review the comment below and resubmit if appropriate.', ['step' => $context['step_name'] ?? '-', 'workflow' => $context['workflow_name'] ?? '-', 'reviewer' => $context['decision_by_name'] ?? __('a reviewer')]) }}</p>

    <table style="width: 100%; border-collapse: collapse; background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">
        <tr><td style="padding: 6px 10px; width: 35%; color: #666;">{{ __('Task') }}</td><td style="padding: 6px 10px;">#{{ $context['task_id'] ?? '-' }}</td></tr>
        <tr><td style="padding: 6px 10px; color: #666;">{{ __('Workflow') }}</td><td style="padding: 6px 10px;">{{ $context['workflow_name'] ?? '-' }}</td></tr>
        <tr><td style="padding: 6px 10px; color: #666;">{{ __('Step') }}</td><td style="padding: 6px 10px;">{{ $context['step_name'] ?? '-' }}</td></tr>
        @if(!empty($context['object_type']) && !empty($context['object_id']))
            <tr><td style="padding: 6px 10px; color: #666;">{{ __('Object') }}</td><td style="padding: 6px 10px;">{{ $context['object_type'] }} #{{ $context['object_id'] }}</td></tr>
        @endif
        @if(!empty($context['decision_at']))
            <tr><td style="padding: 6px 10px; color: #666;">{{ __('Decided at') }}</td><td style="padding: 6px 10px;">{{ $context['decision_at'] }}</td></tr>
        @endif
        <tr><td style="padding: 6px 10px; color: #666; vertical-align: top;">{{ __('Reviewer comment') }}</td><td style="padding: 6px 10px;">{{ $context['comment'] ?? '-' }}</td></tr>
    </table>

    <p style="color: #888; font-size: 12px; margin-top: 30px;">{{ __('Sent by :app.', ['app' => config('app.name', 'Heratio')]) }}</p>
</body>
</html>
