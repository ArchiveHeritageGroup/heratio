{{-- AccessRequestPendingMail body --}}
<p>{{ __('Hi,') }}</p>

<p>A new access request needs your review.</p>

<p><strong>{{ __('Request:') }}</strong> #{{ $request->id }}<br>
@if(!empty($requesterName))
<strong>{{ __('Requester:') }}</strong> {{ $requesterName }}<br>
@endif
<strong>{{ __('Priority:') }}</strong> {{ $request->priority ?? 'normal' }}<br>
<strong>{{ __('Submitted:') }}</strong> {{ $request->created_at ?? now() }}</p>

@if(!empty($request->justification))
<p><strong>{{ __('Justification:') }}</strong></p>
<blockquote>{!! nl2br(e($request->justification)) !!}</blockquote>
@endif

<p>Open the pending-requests queue in the admin panel to approve or deny.</p>

<p>{{ __('Thanks,') }}<br>{{ config('app.name', 'Heratio') }}</p>
