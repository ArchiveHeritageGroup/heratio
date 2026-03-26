@include('ahg-acl::_acl-actor', [
    'resource' => $resource,
    'basicActions' => $basicActions,
    'actors' => $actors,
    'rootActor' => $rootActor,
    'actorObjects' => $actorObjects ?? [],
])
