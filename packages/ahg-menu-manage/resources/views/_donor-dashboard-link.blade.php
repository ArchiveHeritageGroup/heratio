@if($sf_user->isAuthenticated())
<li class="nav-item">
  <a class="nav-link" href="@php echo route('donor.dashboard') @endphp">
    <i class="fas fa-hand-holding-heart me-1"></i>
    @php echo __('Donor Dashboard') @endphp
  </a>
</li>
@endif
