{{--
  Library section layout component.

  Wraps circulation / ILL / trading-partner views in the central theme
  one-column layout. Views render their body as the default slot and may
  set the page title with @section('title', '...').

  @author Johan Pieterse
  @copyright Plain Sailing Information Systems
  @license AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')

@section('content')
<div class="container py-4">
{{ $slot }}
</div>
@endsection
