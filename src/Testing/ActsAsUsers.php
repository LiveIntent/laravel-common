<?php

namespace LiveIntent\LaravelCommon\Testing;

use App\Models\User;

trait ActsAsUsers
{
    use HandlesUserPersistance;

    /**
     * Act as user with admin permissions.
     */
    public function actingAsAdmin()
    {
        return $this->makeAndImpersonate(
            User::factory()->asAdmin()
        );
    }

    /**
     * Act as a user with internal permissions.
     */
    public function actingAsInternal()
    {
        return $this->makeAndImpersonate(
            User::factory()->asInternal()
        );
    }

    /**
     * Act as a user with standard permissions.
     */
    public function actingAsStandard()
    {
        return $this->makeAndImpersonate(
            User::factory()->asStandard()
        );
    }

    /**
     * Make a new user and optionally persist it.
     */
    private function makeAndImpersonate($factory)
    {
        return $this->actingAs(
            $factory->{$this->persistanceMethod()}()
        );
    }
}
