{{--
  Z39.50 section layout component.

  Wraps the Z39.50 client / server views in the central theme one-column
  layout. Views render their body as the default slot and may set the page
  title with @section('title', '...').

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  @license AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')

@section('content')
<div class="container py-4">
{{ $slot }}
</div>
@endsection
