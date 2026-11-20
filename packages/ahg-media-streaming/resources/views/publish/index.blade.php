{{-- Distribution log, all records (heratio#1761). Staff only (acl:update). --}}
@extends('theme::layouts.1col')
@section('title', __('Publish packages'))

@section('content')
<h1 class="h3"><i class="fas fa-share-nodes me-2"></i>{{ __('Publish packages') }}</h1>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

@include('ahg-media-streaming::publish._roadmap-note')

<form method="get" class="d-flex gap-2 mb-3 align-items-center">
  <label for="pl-status" class="small">{{ __('Status') }}</label>
  <select name="status" id="pl-status" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
    <option value="">{{ __('All') }}</option>
    @foreach($statusLabels as $code => $label)<option value="{{ $code }}" @selected($status === $code)>{{ $label }}</option>@endforeach
  </select>
</form>

@include('ahg-media-streaming::publish._log-table', ['log' => $log, 'statusLabels' => $statusLabels, 'presets' => $presets, 'showRecord' => true])
@endsection
