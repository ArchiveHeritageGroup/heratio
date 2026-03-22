@extends('theme::layouts.1col')
@section('title', 'Add Gallery Artwork')
@section('body-class', 'gallery add')
@section('content')
  @include('ahg-gallery::gallery.edit', ['artwork' => null, 'isNew' => true])
@endsection
