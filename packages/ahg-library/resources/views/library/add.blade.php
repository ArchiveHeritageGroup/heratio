@extends('theme::layouts.1col')
@section('title', 'Add Library Item')
@section('content')
<div class="container py-4">
@include("ahg-library::library.edit", ["item" => null])
</div>
@endsection
