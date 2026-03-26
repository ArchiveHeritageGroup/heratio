@include('ahg-acl::_acl-repository', [
    'resource' => $resource,
    'basicActions' => $basicActions,
    'repositories' => $repositories,
    'repositoryObjects' => $repositoryObjects ?? [],
    'rootRepository' => $rootRepository,
])
