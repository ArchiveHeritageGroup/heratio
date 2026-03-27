@extends('theme::layout_2col')

@section('sidebar')
  @include('ahg-information-object-manage::_context-menu')
@endsection

@section('title')
  <h1>{{ __('Physical storage locations') }}</h1>
  <h2>{{ __('No results') }}</h2>
@endsection

<fieldset class="single">

  <div class="fieldset-wrapper">

    <p>{{ __('Oops, we couldn\'t find any physical storage locations for the current resource.') }}</p>

  </div>

</fieldset>

@section('after-content')
  <section class="actions mb-3">
    <a href="{{ route('informationobject.reports', $resource->slug) }}" class="btn atom-btn-outline-light">{{ __('Back') }}</a>
  </section>
@endsection
