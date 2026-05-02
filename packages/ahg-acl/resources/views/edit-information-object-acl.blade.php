@extends('theme::layouts.1col')

@section('title', 'Archival description ACL — ' . ($resource->name ?? 'Group ' . $resource->id))

@section('content')
<div class="container py-4">
  <nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">{{ __('Admin') }}</a></li>
      <li class="breadcrumb-item"><a href="{{ route('acl.groups') }}">{{ __('ACL Groups') }}</a></li>
      <li class="breadcrumb-item"><a href="{{ route('acl.edit-group', ['id' => $resource->id]) }}">{{ $resource->name ?? 'Group ' . $resource->id }}</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ __('Archival Description') }}</li>
    </ol>
  </nav>

  @include('ahg-acl::_tabs', ['groupsMenu' => $groupsMenu])

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @include('ahg-acl::_acl-information-object', [
      'resource' => $resource,
      'basicActions' => $basicActions,
      'informationObjects' => $informationObjects,
      'informationObjectEntities' => $informationObjectEntities ?? [],
      'root' => $root,
      'repositories' => $repositories,
      'repositoryObjects' => $repositoryObjects ?? [],
      'rootInformationObject' => $rootInformationObject,
  ])
  @include('ahg-acl::_acl-modal-js')
</div>
@endsection
