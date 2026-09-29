{{-- AccessRequestSubmittedMail body --}}
<p>{{ __('Hi,') }}</p>

<p>{{ __('Your access request') }} <strong>#{{ $request->id }}</strong> has been received and is now in the
pending queue. You'll get another email once an approver reviews it.</p>

@if(!empty($request->justification))
<p><strong>{{ __('Your justification:') }}</strong></p>
<blockquote>{!! nl2br(e($request->justification)) !!}</blockquote>
@endif

<p><strong>{{ __('Priority:') }}</strong> {{ $request->priority ?? 'normal' }}<br>
<strong>{{ __('Submitted:') }}</strong> {{ $request->created_at ?? now() }}</p>

<p>{{ __('You can review the status of all your requests at any time on the "My access requests" page.') }}</p>

<p>{{ __('Thanks,') }}<br>{{ config('app.name', 'Heratio') }}</p>
