<?php

namespace LiveIntent\LaravelCommon\Testing;

trait ActsAsPersistentUsers
{
    use ActsAsUsers;

    /**
     * Get the persistance mode that should be used when creating factory models.
     */
    protected function shouldPersist()
    {
        return true;
    }
}
