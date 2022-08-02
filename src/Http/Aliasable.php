<?php

namespace LiveIntent\LaravelCommon\Http;

interface Aliasable
{
    /**
     * Get the external facing name.
     */
    public function getName(): string;

    /**
     * Get the internal facing name.
     */
    public function getInternalName(): string;
}
