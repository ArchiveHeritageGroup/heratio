@extends('theme::layouts.1col')

@section('title', 'Archival institution ACL — ' . ($resource->name ?? 'Group ' . $resource->id))

@section('content')
<div class="container py-4">
  <nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">{{ __('Admin') }}</a></li>
      <li class="breadcrumb-item"><a href="{{ route('acl.groups') }}">{{ __('ACL Groups') }}</a></li>
      <li class="breadcrumb-item"><a href="{{ route('acl.edit-group', ['id' => $resource->id]) }}">{{ $resource->name ?? 'Group ' . $resource->id }}</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ __('Archival Institution') }}</li>
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
    // _acl-repository iterates $repositories keyed by repository id; root sentinel at id 0
    $reposForPartial = [0 => $root] + $repositoriesById;
    $rootRepository  = $rootInformationObject;
  @endphp

  @include('ahg-acl::_acl-repository', [
      'resource' => $resource,
      'basicActions' => $basicActions,
      'repositories' => $reposForPartial,
      'repositoryObjects' => $repositoryEntitiesById,
      'rootRepository' => $rootRepository,
  ])
  @include('ahg-acl::_acl-modal-js')
</div>
@endsection
