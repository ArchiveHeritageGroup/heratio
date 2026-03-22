@extends('theme::layouts.1col')
@section('title', 'User Clearance')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-id-badge me-2"></i>User Clearance Details</h1><div class="card"><div class="card-header"><h5 class="mb-0">{{ e($user->display_name??$user->username??"") }}</h5></div><div class="card-body"><div class="row"><div class="col-md-6"><p><strong>Username:</strong> {{ e($user->username??"") }}</p><p><strong>Email:</strong> {{ e($user->email??"") }}</p><p><strong>Clearance:</strong> @if($clearance??null)<span class="badge" style="background-color:{{ $clearance->color??"#6c757d" }}">{{ e($clearance->name??"None") }}</span>@else None @endif</p></div></div></div></div>
</div>
@endsection
