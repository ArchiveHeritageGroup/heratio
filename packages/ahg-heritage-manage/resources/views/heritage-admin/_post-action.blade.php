{{--
  A state-changing admin action rendered as a small form (POST/DELETE + CSRF),
  never a plain link. The theme binds data-confirm prompts CSP-safely.
  Params: url, method (POST|DELETE), class, icon, label, title, confirm.
--}}
<form method="post" action="{{ $url }}" class="d-inline" @if(!empty($confirm)) data-confirm="{{ $confirm }}" @endif>
  @csrf
  @if(($method ?? 'POST') !== 'POST') @method($method) @endif
  <button type="submit" class="{{ $class ?? 'btn btn-sm btn-outline-secondary' }}" @if(!empty($title)) title="{{ $title }}" aria-label="{{ $title }}" @endif>
    @if(!empty($icon))<i class="{{ $icon }}" aria-hidden="true"></i>@endif
    {{ $label ?? '' }}
  </button>
</form>
