@extends('theme::layouts.1col')
@section('title', 'Getty Vocabulary Links')
@section('content')
<div class="container py-4">
<h1>Getty Vocabulary Links</h1><div class="row mb-4"><div class="col-md-3"><div class="card text-center"><div class="card-body"><h3>{{ $statistics["total"]??0 }}</h3><p class="mb-0">Total Links</p></div></div></div><div class="col-md-3"><div class="card text-center bg-success text-white"><div class="card-body"><h3>{{ $statistics["confirmed"]??0 }}</h3><p class="mb-0">Confirmed</p></div></div></div><div class="col-md-3"><div class="card text-center bg-warning"><div class="card-body"><h3>{{ $statistics["pending"]??0 }}</h3><p class="mb-0">Pending</p></div></div></div><div class="col-md-3"><div class="card text-center bg-info text-white"><div class="card-body"><h3>{{ $statistics["suggested"]??0 }}</h3><p class="mb-0">Suggested</p></div></div></div></div>
</div>
@endsection
