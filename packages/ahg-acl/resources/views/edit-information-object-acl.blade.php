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
