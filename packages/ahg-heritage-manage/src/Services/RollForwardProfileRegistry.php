<?php

namespace AhgHeritageManage\Services;

use AhgHeritageManage\Contracts\RollForwardProfile;
use AhgHeritageManage\Jurisdiction\GenericRollForwardProfile;

/** Market modules register their wording here; unknown standards get the generic one. */
class RollForwardProfileRegistry
{
    /** @var array<string, RollForwardProfile> */
    private array $profiles = [];

    public function register(RollForwardProfile $profile): void
    {
        $this->profiles[strtoupper($profile->standardCode())] = $profile;
    }

    public function for(?string $standardCode): RollForwardProfile
    {
        return $this->profiles[strtoupper((string) $standardCode)] ?? new GenericRollForwardProfile();
    }
}
