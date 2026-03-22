@extends('theme::layouts.1col')
@section('title', 'Add Museum Object')
@section('content')
<div class="container py-4">
@include("ahg-museum::museum.edit", ["museum" => null, "isNew" => true])
</div>
@endsection
