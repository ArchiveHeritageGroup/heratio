@extends('theme::layouts.1col')
@section('title', 'Create Contributor Account')
@section('body-class', 'heritage')

@section('content')
<div class="row justify-content-center">
  <div class="col-md-6 col-lg-5">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        @if($success ?? false)
        <div class="text-center py-4">
          <i class="fas fa-check-circle display-1 text-success"></i>
          <h2 class="h4 mt-3">Registration Successful!</h2>
          <p class="text-muted mb-4">We've sent a verification email to your address. Please check your inbox and click the verification link to activate your account.</p>
          <a href="{{ route('heritage.contributor-login') }}" class="btn atom-btn-secondary"><i class="fas fa-sign-in-alt me-2"></i>Go to Login</a>
        </div>
        @else
        <div class="text-center mb-4">
          <i class="fas fa-user-plus display-4" style="color:var(--ahg-primary)"></i>
          <h2 class="h4 mt-3">Join Our Community</h2>
          <p class="text-muted">Help preserve and share our heritage</p>
        </div>

        @if(!empty($error))<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>{{ $error }}</div>@endif

        <form method="post" action="{{ route('heritage.contributor-register') }}">@csrf
          <div class="mb-3"><label for="display_name" class="form-label">Display Name <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label><div class="input-group"><span class="input-group-text"><i class="fas fa-user"></i></span><input type="text" class="form-control" id="display_name" name="display_name" required minlength="2" maxlength="100" placeholder="How you want to be known" value="{{ old('display_name') }}"></div><div class="form-text">This will be shown alongside your contributions</div></div>
          <div class="mb-3"><label for="email" class="form-label">Email Address <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label><div class="input-group"><span class="input-group-text"><i class="fas fa-envelope"></i></span><input type="email" class="form-control" id="email" name="email" required placeholder="your@email.com" value="{{ old('email') }}"></div><div class="form-text">We'll send a verification email to this address</div></div>
          <div class="mb-3"><label for="password" class="form-label">Password <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label><div class="input-group"><span class="input-group-text"><i class="fas fa-lock"></i></span><input type="password" class="form-control" id="password" name="password" required minlength="8" placeholder="Minimum 8 characters"></div></div>
          <div class="mb-3"><label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label><div class="input-group"><span class="input-group-text"><i class="fas fa-lock"></i></span><input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8" placeholder="Re-enter your password"></div></div>
          <div class="mb-4"><div class="form-check"><input type="checkbox" class="form-check-input" id="agree_terms" name="agree_terms" required><label class="form-check-label" for="agree_terms">I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a> and understand that my contributions will be reviewed before publication <span class="badge bg-danger ms-1">Required</span></label></div></div>
          <div class="d-grid gap-2 mb-3"><button type="submit" class="btn atom-btn-secondary btn-lg"><i class="fas fa-user-plus me-2"></i>Create Account</button></div>
        </form>
        <hr class="my-4">
        <div class="text-center"><p class="mb-2">Already have an account?</p><a href="{{ route('heritage.contributor-login') }}" class="btn atom-btn-white"><i class="fas fa-sign-in-alt me-2"></i>Sign In</a></div>
        @endif
      </div>
    </div>
    <div class="text-center mt-4"><a href="{{ route('heritage.landing') }}" class="text-muted text-decoration-none"><i class="fas fa-arrow-left me-1"></i>Back to Heritage Portal</a></div>
  </div>
</div>

<div class="modal fade" id="termsModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Contributor Terms and Conditions</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <h6>1. Contribution Guidelines</h6><p>By contributing to this heritage collection, you agree to:</p><ul><li>Provide accurate and truthful information</li><li>Not submit copyrighted material without permission</li><li>Respect the privacy of individuals mentioned in records</li><li>Not submit offensive, defamatory, or inappropriate content</li></ul>
      <h6>2. Review Process</h6><p>All contributions are reviewed by our team before publication. We reserve the right to:</p><ul><li>Edit contributions for clarity and accuracy</li><li>Reject contributions that don't meet our guidelines</li><li>Request additional information or verification</li></ul>
      <h6>3. Intellectual Property</h6><p>By submitting a contribution, you grant us a non-exclusive license to use, display, and distribute your contribution as part of our heritage collection.</p>
      <h6>4. Privacy</h6><p>Your personal information will be used in accordance with our privacy policy. Your display name will be shown alongside your contributions.</p>
    </div>
    <div class="modal-footer"><button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Close</button></div>
  </div></div>
</div>
@endsection
