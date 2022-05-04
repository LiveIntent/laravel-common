<?php

namespace LiveIntent\LaravelCommon\Testing;

use App\Models\User;

trait ActsAsUsers
{
    /**
     * Act as user with admin permissions.
     */
    public function actingAsAdmin()
    {
        return $this->actingAs(
            User::factory()->asAdmin()->make()
        );
    }

    /**
     * Act as a user with internal permissions.
     */
    public function actingAsInternal()
    {
        return $this->actingAs(
            User::factory()->asInternal()->make()
        );
    }

    /**
     * Act as a user with standard permissions.
     */
    public function actingAsStandard()
    {
        return $this->actingAs(
            User::factory()->asStandard()->make()
        );
    }
}
