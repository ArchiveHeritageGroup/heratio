{{-- AccessRequestSubmittedMail body --}}
<p>Hi,</p>

<p>Your access request <strong>#{{ $request->id }}</strong> has been received and is now in the
pending queue. You'll get another email once an approver reviews it.</p>

@if(!empty($request->justification))
<p><strong>Your justification:</strong></p>
<blockquote>{!! nl2br(e($request->justification)) !!}</blockquote>
@endif

<p><strong>Priority:</strong> {{ $request->priority ?? 'normal' }}<br>
<strong>Submitted:</strong> {{ $request->created_at ?? now() }}</p>

<p>You can review the status of all your requests at any time on the
"My access requests" page.</p>

<p>Thanks,<br>{{ config('app.name', 'Heratio') }}</p>
