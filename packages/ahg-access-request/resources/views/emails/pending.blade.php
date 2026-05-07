{{-- AccessRequestPendingMail body --}}
<p>Hi,</p>

<p>A new access request needs your review.</p>

<p><strong>Request:</strong> #{{ $request->id }}<br>
@if(!empty($requesterName))
<strong>Requester:</strong> {{ $requesterName }}<br>
@endif
<strong>Priority:</strong> {{ $request->priority ?? 'normal' }}<br>
<strong>Submitted:</strong> {{ $request->created_at ?? now() }}</p>

@if(!empty($request->justification))
<p><strong>Justification:</strong></p>
<blockquote>{!! nl2br(e($request->justification)) !!}</blockquote>
@endif

<p>Open the pending-requests queue in the admin panel to approve or deny.</p>

<p>Thanks,<br>{{ config('app.name', 'Heratio') }}</p>
