@extends('theme::layouts.1col')
@section('title', __('Add Accounting Standard'))
@section('body-class', 'admin heritage')

@section('content')
@include('ahg-heritage-manage::heritage-admin._standard-form')
@endsection
