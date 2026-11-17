@extends('theme::layouts.1col')
@section('title', __('Add Compliance Rule'))
@section('body-class', 'admin heritage')

@section('content')
@include('ahg-heritage-manage::heritage-admin._rule-form')
@endsection
