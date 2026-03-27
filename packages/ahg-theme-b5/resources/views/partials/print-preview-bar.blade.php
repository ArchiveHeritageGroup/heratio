{{-- Print preview bar --}}
@if(request('media') === 'print')
  <div id="preview-message">
    {{ __('Print preview') }}
    <a href="{{ url()->current() }}">{{ __('Close') }}</a>
  </div>
@endif
