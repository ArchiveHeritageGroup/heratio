{{-- AccessRequestDeniedMail body --}}
<p>Hi,</p>

<p>Your access request <strong>#{{ $request->id }}</strong> has been
<span style="color:#c62828; font-weight:bold;">denied</span>.</p>

@if(!empty($reason))
<p><strong>Reason:</strong></p>
<blockquote>{!! nl2br(e($reason)) !!}</blockquote>
@endif

<p><strong>Reviewed at:</strong> {{ $request->reviewed_at ?? now() }}</p>

<p>If you'd like to discuss the decision or submit a revised request with
additional context, reply to this email or contact your records manager.</p>

<p>Thanks,<br>{{ config('app.name', 'Heratio') }}</p>
