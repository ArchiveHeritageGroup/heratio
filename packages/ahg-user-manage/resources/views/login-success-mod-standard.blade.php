@php decorate_with('layout_1col'); @endphp
@php use_helper('Javascript'); @endphp
@php slot('content'); @endphp
  @php echo $form->renderGlobalErrors(); @endphp
  @php echo $form->renderFormTag(route('user.login')); @endphp
    @php echo $form->renderHiddenFields(); @endphp
    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="login-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#login-collapse" aria-expanded="true" aria-controls="login-collapse">
            @if('user' != $sf_request->module || 'login' != $sf_request->action)
              {{ __('Please log in to access that page') }}
            @php } else { @endphp
              {{ __('Log in') }}
            @endforeach
          </button>
        </h2>
        <div id="login-collapse" class="accordion-collapse collapse show" aria-labelledby="login-heading">
          <div class="accordion-body">
            @php echo render_field($form->email, null, ['type' => 'email', 'autofocus' => 'autofocus', 'required' => 'required']); @endphp
            @php echo render_field($form->password, null, ['type' => 'password', 'autocomplete' => 'off', 'required' => 'required']); @endphp
          </div>
        </div>
      </div>
    </div>
    <div class="alert alert-info py-2 mb-3">
      <i class="fas fa-info-circle me-1"></i><strong>{{ __('Demo') }}:</strong>
      <code>louise@theahg.co.za</code> / <code>Password@123</code>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <button type="submit" class="btn atom-btn-outline-success">
        <i class="fas fa-sign-in-alt me-1"></i>{{ __('Log in') }}
      </button>
      <a href="@php echo route('user.passwordReset'); @endphp" class="text-muted">
        <i class="fas fa-key me-1"></i>{{ __('Forgot password?') }}
      </a>
    </div>
  </form>

  <hr class="my-4">

  @php $routing = sfContext::getInstance()->getRouting();
  $hasRegistration = $routing->hasRouteName('user_register');
  $hasResearch = $routing->hasRouteName('research_workspace'); @endphp

  @if($hasRegistration)
  <!-- User Registration -->
  <div class="card border-primary mb-3">
    <div class="card-body text-center">
      <h5 class="card-title"><i class="fas fa-user-plus text-primary me-2"></i>{{ __('New User?') }}</h5>
      <p class="card-text text-muted">
        {{ __('Register for an account to access archival materials and services.') }}
      </p>
      <a href="@php echo url_for('@user_register'); @endphp" class="btn atom-btn-white">
        <i class="fas fa-user-plus me-2"></i>{{ __('Register') }}
      </a>
    </div>
  </div>
  @endif

  @if($hasResearch)
  <!-- Researcher Registration -->
  <div class="card border-success mb-3">
    <div class="card-body text-center">
      <h5 class="card-title"><i class="fas fa-user-graduate text-success me-2"></i>{{ __('New Researcher?') }}</h5>
      <p class="card-text text-muted">
        {{ __('Register to access the reading room, request archival materials, and save your research.') }}
      </p>
      <a href="@php echo route('research.publicRegister'); @endphp" class="btn btn-success">
        <i class="fas fa-user-plus me-2"></i>{{ __('Register as Researcher') }}
      </a>
    </div>
  </div>

  <!-- Research Services Link -->
  <div class="text-center mt-3">
    <a href="@php echo route('research.dashboard'); @endphp" class="text-muted">
      <i class="fas fa-book-reader me-1"></i>{{ __('View Research Services') }}
    </a>
  </div>
  @endif
@php end_slot(); @endphp
