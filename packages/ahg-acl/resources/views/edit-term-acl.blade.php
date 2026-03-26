@include('ahg-acl::_term-acl-form', [
    'resource' => $resource,
    'termActions' => $termActions ?? $basicActions ?? [],
    'taxonomyPermissions' => $taxonomyPermissions ?? [],
    'taxonomyObjects' => $taxonomyObjects ?? [],
    'rootPermissions' => $rootPermissions ?? [],
    'rootTerm' => $rootTerm,
])
