<div class="text-center">
  <div id="content" class="d-inline-block mt-5 text-start" role="alert">
    <h1 class="h2 mb-0 p-3 border-bottom d-flex align-items-center">
      <i class="fas fa-fw fa-lg fa-exclamation-circle me-3" aria-hidden="true"></i>
      {{ __('Sorry, an internal server error occurred') }}
    </h1>

    <div class="p-3">
      <p>
        {{ __('The server encountered an unexpected condition.') }}<br>
        {{ __('Please try again later or contact the administrator.') }}
      </p>

      <p class="mb-0">
        <a href="javascript:history.go(-1)">
          {{ __('Back to previous page.') }}
        </a><br>
        <a href="{{ url('/') }}">{{ __('Go to homepage.') }}</a>
      </p>
    </div>
  </div>
</div>
