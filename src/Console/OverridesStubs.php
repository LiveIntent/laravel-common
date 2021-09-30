<?php

namespace LiveIntent\LaravelCommon\Console;

trait OverridesStubs
{
    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param  string  $stub
     * @return string
     */
    protected function resolveStubPath($stub)
    {
        $override = __DIR__.$stub;

        if (file_exists($override)) {
            return $override;
        }

        return parent::resolveStubPath($stub);
    }
}
