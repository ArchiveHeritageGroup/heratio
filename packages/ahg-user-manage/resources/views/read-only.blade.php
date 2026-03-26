<div style="text-align: center;">

  <img src="{{ asset('images/lock48.png') }}" alt="{{ __('Read only') }}">

  <h2 style="font-size: 20px;">{{ __('The system is currently in read-only mode. Please try again later.') }}</h2>

  <a href="javascript:history.go(-1)">{{ __('Back to previous page') }}</a>

  <br/>

  <a href="{{ route('home') }}">{{ __('Go to homepage') }}</a>

</div>
