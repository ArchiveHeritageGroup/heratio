@extends('theme::layouts.1col')

@section('title', __('Upload limit exceeded'))
@section('body-class', 'repository upload-limit-exceeded')

@section('content')

<h1>{{ __('Upload limit exceeded') }}</h1>

<div class="alert alert-danger" role="alert">
  {!! __('The upload limit of :limit GB for <a href=":url">:name</a> has been exceeded (:usage GB currently used)', [
      'limit' => $repository->upload_limit ?? 0,
      'url' => route('repository.show', ['slug' => $repository->slug]),
      'name' => e($repository->authorized_form_of_name ?? ''),
      'usage' => round(($repository->disk_usage ?? 0) / 1000000000, 2),
  ]) !!}
</div>

@php
  $digitalObjectLabel = \AhgCore\Services\SettingHelper::get('ui_label_digitalobject', 'Digital object');
@endphp

<div>
  {{ __('To upload a new :type', ['type' => strtolower($digitalObjectLabel)]) }}
  <ul>
    <li>{!! __('Email your <a href="mailto::email">system administrator</a> and request a larger upload limit', ['email' => $adminEmail ?? '']) !!}</li>
    <li>{{ __('Delete an existing :type to reduce disk usage', ['type' => strtolower($digitalObjectLabel)]) }}</li>
  </ul>
</div>

<section class="actions mb-3">
  <a class="btn atom-btn-outline-light" href="#" onclick="history.back(); return false;">{{ __('Back') }}</a>
</section>

@endsection
