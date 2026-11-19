{{-- Access request status (heratio#1773: conditional release; #1774: quoted). --}}
@switch($status ?? 'pending')
    @case('approved')<span class="badge bg-success">{{ __('Approved') }}</span>@break
    @case('conditional')<span class="badge bg-success">{{ __('Released with conditions') }}</span>@break
    @case('quoted')<span class="badge bg-info text-dark">{{ __('Quotation sent') }}</span>@break
    @case('denied')<span class="badge bg-danger">{{ __('Denied') }}</span>@break
    @case('cancelled')<span class="badge bg-secondary">{{ __('Cancelled') }}</span>@break
    @default<span class="badge bg-warning text-dark">{{ __('Pending') }}</span>
@endswitch
