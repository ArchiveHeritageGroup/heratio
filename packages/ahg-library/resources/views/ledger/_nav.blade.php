@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('info'))<div class="alert alert-info">{{ session('info') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}@if(session('ledger_variances'))<ul class="mb-0 mt-2">@foreach(session('ledger_variances') as $v)<li>{{ $v }}</li>@endforeach</ul>@endif</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<div class="mb-3 d-flex flex-wrap gap-1">
  <a class="btn btn-sm btn-outline-secondary" href="{{ route('library.ledger.index') }}">{{ __('Documents') }}</a>
  <a class="btn btn-sm btn-outline-secondary" href="{{ route('library.ledger.aged') }}">{{ __('Aged creditors') }}</a>
  <a class="btn btn-sm btn-outline-secondary" href="{{ route('library.ledger.budget-match') }}">{{ __('Budget match') }}</a>
  <a class="btn btn-sm btn-outline-secondary" href="{{ route('library.ledger.exports') }}">{{ __('Finance export') }}</a>
  @if(\AhgCore\Services\AclService::isAdministrator())<a class="btn btn-sm btn-outline-secondary" href="{{ route('library.ledger.rates') }}">{{ __('Tax rates') }}</a>@endif
  <a class="btn btn-sm btn-outline-secondary" href="{{ route('library.acquisitions') }}">{{ __('Orders') }}</a>
</div>
