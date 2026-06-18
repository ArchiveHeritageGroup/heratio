{{-- RepatriationClaimRegisteredMail body (#1207). Neutral, care-first tone. --}}
<p>Hello,</p>

<p>Thank you - your repatriation claim has been received and recorded.
Reference <strong>#{{ $claim->id ?? '' }}</strong>.</p>

@if(!empty($claim->claimant_community))
<p><strong>Claimant community:</strong> {{ $claim->claimant_community }}</p>
@endif
@if(!empty($claim->origin_place))
<p><strong>Place of origin:</strong> {{ $claim->origin_place }}</p>
@endif
@if(!empty($claim->current_holder))
<p><strong>Currently held by:</strong> {{ $claim->current_holder }}</p>
@endif

<p>Your claim is now <strong>registered</strong> and awaiting review. We will be in
touch as the dialogue progresses, and you will receive an update whenever its
status changes.</p>

<hr>
<p style="font-size:12px;color:#666;">{{ \AhgSemanticSearch\Services\RepatriationClaimService::DISCLAIMER }}</p>

<p>With care,<br>{{ config('app.name', 'Heratio') }}</p>
