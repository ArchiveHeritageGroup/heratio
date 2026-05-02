@extends('theme::layouts.1col')

@section('title', 'Authority record ACL — ' . ($resource->name ?? 'Group ' . $resource->id))

@section('content')
<div class="container py-4">
  <nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">{{ __('Admin') }}</a></li>
      <li class="breadcrumb-item"><a href="{{ route('acl.groups') }}">{{ __('ACL Groups') }}</a></li>
      <li class="breadcrumb-item"><a href="{{ route('acl.edit-group', ['id' => $resource->id]) }}">{{ $resource->name ?? 'Group ' . $resource->id }}</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ __('Authority Record') }}</li>
    </ol>
  </nav>

  @include('ahg-acl::_tabs', ['groupsMenu' => $groupsMenu])

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @php
    // Reshape into the structure _acl-actor expects: $actors keyed by actor id with key 0 = root
    $actorsForPartial = [0 => $root] + $actors;
    $rootActor = $rootInformationObject; // shared root sentinel object {id:0, slug:'root', is_root:true}
  @endphp

  @include('ahg-acl::_acl-actor', [
      'resource' => $resource,
      'basicActions' => $basicActions,
      'actors' => $actorsForPartial,
      'rootActor' => $rootActor,
      'actorObjects' => $actorEntities ?? [],
  ])
</div>
@endsection
