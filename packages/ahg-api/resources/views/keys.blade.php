{{-- /api/keys - a signed-in user's own API keys (#1558, #1566). --}}
@extends('theme::layouts.1col')
@section('title', __('API keys'))

@section('content')
<h1 class="h3 mb-3">{{ __('API keys') }}</h1>
<p class="text-muted">{{ __('A key lets a program or an AI assistant use the catalogue as you, with your permissions. Revoke a key and anything using it is disconnected at once.') }}</p>

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
  <div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif

@include('ahg-api::partials.key-manager')
@endsection
