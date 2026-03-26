<div class="text-center">
  <div id="content" class="d-inline-block mt-5 text-start" role="alert">
    <h1 class="h2 mb-0 p-3 border-bottom d-flex align-items-center">
      <i class="fas fa-fw fa-lg fa-exclamation-triangle me-3" aria-hidden="true"></i>
      @if(null === ($use ?? null))
        {{ __('Sorry, this Term is locked and cannot be deleted') }}
      @else
        {{ __('Sorry, this Term is locked') }}
      @endif
    </h1>

    <div class="p-3">
      <p>
        @if(null === ($use ?? null))
          {{ __('The existing term values are required by the application to operate correctly') }}
        @else
          {!! __(
              'This is a non-preferred term and cannot be edited - please use <a href="%1%">%2%</a>.',
              ['%1%' => route('term.show', $use->slug ?? ''), '%2%' => $use->name ?? $use->getName(['cultureFallback' => true]) ?? '']
          ) !!}
        @endif
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
