{{-- RepatriationClaimStatusMail body (#1207). Neutral, care-first tone. --}}
<p>Hello,</p>

<p>There is an update on your repatriation claim
<strong>#{{ $claim->id ?? '' }}</strong>.</p>

<p>The status has moved from <strong>{{ $fromLabel }}</strong> to
<strong>{{ $toLabel }}</strong>.</p>

@if(!empty($claim->claimant_community))
<p><strong>Claimant community:</strong> {{ $claim->claimant_community }}</p>
@endif
@if(!empty($claim->current_holder))
<p><strong>Currently held by:</strong> {{ $claim->current_holder }}</p>
@endif

<p>This reflects where the dialogue around your claim now stands. You will
receive a further update at the next change.</p>

<hr>
<p style="font-size:12px;color:#666;">{{ \AhgSemanticSearch\Services\RepatriationClaimService::DISCLAIMER }}</p>

<p>With care,<br>{{ config('app.name', 'Heratio') }}</p>
