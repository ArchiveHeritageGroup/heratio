{{-- AccessRequestApprovedMail body --}}
<p>Hi,</p>

<p>Your access request <strong>#{{ $request->id }}</strong> has been
<span style="color:#2e7d32; font-weight:bold;">approved</span>.</p>

@if(!empty($reviewNotes))
<p><strong>Reviewer note:</strong></p>
<blockquote>{!! nl2br(e($reviewNotes)) !!}</blockquote>
@endif

<p><strong>Approved at:</strong> {{ $request->reviewed_at ?? now() }}</p>

<p>You can now use the access this request grants. If you have follow-up
questions, reply to this email or contact your records manager.</p>

<p>Thanks,<br>{{ config('app.name', 'Heratio') }}</p>
