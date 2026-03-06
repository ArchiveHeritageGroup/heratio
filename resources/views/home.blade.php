@extends('theme::layouts.1col')

@section('title', ($themeData['siteTitle'] ?? 'Heratio') . ' - Home')
@section('body-class', 'homepage index')

@section('content')
  <h2>Welcome to {{ $themeData['siteTitle'] ?? 'Heratio' }}</h2>

  <div class="row mt-4">
    <div class="col-md-3">
      <div class="card text-center mb-3">
        <div class="card-body">
          <h3>{{ \AhgCore\Models\QubitInformationObject::where('id', '!=', 1)->count() }}</h3>
          <p class="text-muted mb-0">Descriptions</p>
        </div>
        <div class="card-footer">
          <a href="{{ url('/informationobject/browse') }}" class="text-decoration-none">Browse</a>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center mb-3">
        <div class="card-body">
          <h3>{{ \AhgCore\Models\QubitRepository::where('id', '!=', 6)->count() }}</h3>
          <p class="text-muted mb-0">Repositories</p>
        </div>
        <div class="card-footer">
          <a href="{{ url('/repository/browse') }}" class="text-decoration-none">Browse</a>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center mb-3">
        <div class="card-body">
          <h3>{{ \AhgCore\Models\QubitDigitalObject::count() }}</h3>
          <p class="text-muted mb-0">Digital Objects</p>
        </div>
        <div class="card-footer">
          <a href="{{ url('/display/browse?hasDigital=1&topLevel=0&view=grid') }}" class="text-decoration-none">Browse</a>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center mb-3">
        <div class="card-body">
          <h3>{{ \AhgCore\Models\QubitActor::where('id', '!=', 3)->where('id', '!=', 4)->count() }}</h3>
          <p class="text-muted mb-0">Authority Records</p>
        </div>
        <div class="card-footer">
          <a href="{{ url('/actor/browse') }}" class="text-decoration-none">Browse</a>
        </div>
      </div>
    </div>
  </div>

  @auth
    <div class="mt-3">
      <h5>Your Groups</h5>
      <ul>
        @foreach(auth()->user()->groups as $group)
          <li>{{ $group->getName('en') }}</li>
        @endforeach
      </ul>
    </div>
  @endauth
@endsection
