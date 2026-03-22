@extends('theme::layouts.1col')

@section('title', 'Manage Setup Guides')

@section('content')
<h1>Manage Setup Guides</h1>

<form method="POST">
  @csrf

  <div class="accordion mb-3">
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#main-collapse" aria-expanded="true">Manage Setup Guides</button>
      </h2>
      <div id="main-collapse" class="accordion-collapse collapse show">
        <div class="accordion-body">
        </div>
      </div>
    </div>
  </div>

  <ul class="actions mb-3 nav gap-2">
    <li><a href="{{ url()->previous() }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
    <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
  </ul>
</form>
@endsection
