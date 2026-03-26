@extends('ahg-theme-b5::layout_1col')

@section('title')

  @if(isset($preview))
    <div class="copyright-statement-preview alert alert-info">
      {{ __('Copyright statement preview') }}
    </div>
  @endif

  <h1>{{ $resource->authorized_form_of_name ?? $resource->title ?? '' }}</h1>

@endsection

<div class="page">

  <div class="p-3">
    {!! $copyrightStatement ?? '' !!}
  </div>

</div>

@section('after-content')
  <form method="get">
    <input type="hidden" name="token" value="{{ $accessToken ?? '' }}">
    @if(isset($preview))
      <ul class="actions mb-3 nav gap-2">
        <li><button class="btn atom-btn-outline-success" type="submit" disabled="disabled">{{ __('Agree') }}</button></li>
        <li><a href="{{ route('settings.permissions') }}" class="btn atom-btn-outline-light" role="button">{{ __('Close') }}</a></li>
      </ul>
    @else
      <section class="actions mb-3">
        <button class="btn atom-btn-outline-success" type="submit">{{ __('Agree') }}</button>
      </section>
    @endif
  </form>
@endsection
