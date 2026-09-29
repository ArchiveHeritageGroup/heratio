{{-- RepatriationClaimStatusMail body (#1207). Neutral, care-first tone. --}}
<p>{{ __('Hello,') }}</p>

<p>{{ __('There is an update on your repatriation claim') }}
<strong>#{{ $claim->id ?? '' }}</strong>.</p>

<p>{{ __('The status has moved from') }} <strong>{{ $fromLabel }}</strong> to
<strong>{{ $toLabel }}</strong>.</p>

@if(!empty($claim->claimant_community))
<p><strong>{{ __('Claimant community:') }}</strong> {{ $claim->claimant_community }}</p>
@endif
@if(!empty($claim->current_holder))
<p><strong>{{ __('Currently held by:') }}</strong> {{ $claim->current_holder }}</p>
@endif

<p>{{ __('This reflects where the dialogue around your claim now stands. You will receive a further update at the next change.') }}</p>

<hr>
<p style="font-size:12px;color:#666;">{{ \AhgSemanticSearch\Services\RepatriationClaimService::DISCLAIMER }}</p>

<p>{{ __('With care,') }}<br>{{ config('app.name', 'Heratio') }}</p>
