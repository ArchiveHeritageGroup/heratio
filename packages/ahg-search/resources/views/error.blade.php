@extends('theme::layout_1col')

@section('title')
  <h1>{{ __('Search error encountered') }}</h1>
@endsection

@section('content')

  <div class="messages error">
    <div>
      <strong>{{ $reason }}</strong>
      @if(!empty($error))
        <pre>{{ $error }}</pre>
      @endif
    </div>
  </div>

  <p><a href="javascript:history.go(-1)">{{ __('Back to previous page.') }}</a></p>

@endsection
