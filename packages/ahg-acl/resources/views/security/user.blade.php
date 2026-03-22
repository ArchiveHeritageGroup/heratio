@extends('theme::layouts.1col')
@section('title', 'User Security')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-user-shield me-2"></i>User Security Profile</h1><div class="card"><div class="card-body"><p><strong>User:</strong> {{ e($user->display_name??$user->username??"") }}</p><p><strong>Clearance:</strong> @if($clearance??null)<span class="badge" style="background-color:{{ $clearance->color??"#6c757d" }}">{{ e($clearance->name) }}</span>@else None @endif</p></div></div>
</div>
@endsection
