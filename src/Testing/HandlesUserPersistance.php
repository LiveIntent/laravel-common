<?php

namespace LiveIntent\LaravelCommon\Testing;

use Illuminate\Support\Facades\Auth;
use LiveIntent\LaravelCommon\Auth\LITokenTransientUserProvider;

trait HandlesUserPersistance
{
    /**
     * Get the method to use for persistance.
     */
    public function persistanceMethod()
    {
        return $this->shouldPersist() ? 'create' : 'make';
    }

    /**
     * Get the persistance mode that should be used when creating factory models.
     */
    protected function shouldPersist()
    {
        return $this->guessPersistance();
    }

    /**
     * Guess the persistance mode based on the appplication's config.
     */
    protected function guessPersistance()
    {
        $provider = Auth::createUserProvider('li_token');

        return ! $provider instanceof LITokenTransientUserProvider;
    }
}
