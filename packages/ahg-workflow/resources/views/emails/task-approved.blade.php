<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Workflow task approved</title>
</head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #222;">
    <h2 style="color: #16a34a;">Workflow task approved</h2>

    <p>Hello {{ $context['recipient_name'] ?? 'colleague' }},</p>

    <p>Your task <strong>{{ $context['step_name'] ?? '-' }}</strong> in the workflow <strong>{{ $context['workflow_name'] ?? '-' }}</strong> has been approved by {{ $context['decision_by_name'] ?? 'a reviewer' }}.</p>

    <table style="width: 100%; border-collapse: collapse; background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">
        <tr><td style="padding: 6px 10px; width: 35%; color: #666;">Task</td><td style="padding: 6px 10px;">#{{ $context['task_id'] ?? '-' }}</td></tr>
        <tr><td style="padding: 6px 10px; color: #666;">Workflow</td><td style="padding: 6px 10px;">{{ $context['workflow_name'] ?? '-' }}</td></tr>
        <tr><td style="padding: 6px 10px; color: #666;">Step</td><td style="padding: 6px 10px;">{{ $context['step_name'] ?? '-' }}</td></tr>
        @if(!empty($context['object_type']) && !empty($context['object_id']))
            <tr><td style="padding: 6px 10px; color: #666;">Object</td><td style="padding: 6px 10px;">{{ $context['object_type'] }} #{{ $context['object_id'] }}</td></tr>
        @endif
        @if(!empty($context['decision_at']))
            <tr><td style="padding: 6px 10px; color: #666;">Decided at</td><td style="padding: 6px 10px;">{{ $context['decision_at'] }}</td></tr>
        @endif
        @if(!empty($context['comment']))
            <tr><td style="padding: 6px 10px; color: #666; vertical-align: top;">Comment</td><td style="padding: 6px 10px;">{{ $context['comment'] }}</td></tr>
        @endif
    </table>

    @if(!empty($context['has_next_step']))
        <p>The next step in the workflow has been created automatically.</p>
    @else
        <p>This was the final step in the workflow.</p>
    @endif

    <p style="color: #888; font-size: 12px; margin-top: 30px;">Sent by {{ config('app.name', 'Heratio') }}.</p>
</body>
</html>
