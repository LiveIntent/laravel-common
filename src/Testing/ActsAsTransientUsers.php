<?php

namespace LiveIntent\LaravelCommon\Testing;

trait ActsAsTransientUsers
{
    use ActsAsUsers;

    /**
     * Get the persistance mode that should be used when creating factory models.
     */
    protected function shouldPersist()
    {
        return false;
    }
}
