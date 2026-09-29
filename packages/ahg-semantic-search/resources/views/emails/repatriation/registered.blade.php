{{-- RepatriationClaimRegisteredMail body (#1207). Neutral, care-first tone. --}}
<p>{{ __('Hello,') }}</p>

<p>{{ __('Thank you - your repatriation claim has been received and recorded. Reference') }} <strong>#{{ $claim->id ?? '' }}</strong>.</p>

@if(!empty($claim->claimant_community))
<p><strong>{{ __('Claimant community:') }}</strong> {{ $claim->claimant_community }}</p>
@endif
@if(!empty($claim->origin_place))
<p><strong>{{ __('Place of origin:') }}</strong> {{ $claim->origin_place }}</p>
@endif
@if(!empty($claim->current_holder))
<p><strong>{{ __('Currently held by:') }}</strong> {{ $claim->current_holder }}</p>
@endif

<p>{{ __('Your claim is now') }} <strong>registered</strong> and awaiting review. We will be in
touch as the dialogue progresses, and you will receive an update whenever its
status changes.</p>

<hr>
<p style="font-size:12px;color:#666;">{{ \AhgSemanticSearch\Services\RepatriationClaimService::DISCLAIMER }}</p>

<p>{{ __('With care,') }}<br>{{ config('app.name', 'Heratio') }}</p>
