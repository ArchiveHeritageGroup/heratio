<?php

echo get_component('aclGroup', 'researcherAclForm', [
    'resource' => $resource,
    'permissions' => $permissions,
    'form' => $form,
]);
