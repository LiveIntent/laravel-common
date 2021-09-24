<?php

namespace LiveIntent\LaravelCommon\Console;

use Illuminate\Support\Str;
use Illuminate\Foundation\Console\ResourceMakeCommand as BaseResourceMakeCommand;

class ResourceMakeCommand extends BaseResourceMakeCommand
{
    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param  string  $stub
     * @return string
     */
    protected function resolveStubPath($stub)
    {
        return __DIR__.$stub;
    }

    /**
     * Build the class with the given name.
     *
     * Remove the base controller import if we are already in the base namespace.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {
        $replace = $this->buildReplacements($name);

        return str_replace(
            array_keys($replace),
            array_values($replace),
            parent::buildClass($name)
        );
    }

    /**
     * Build the replacement values.
     *
     * @param  string  $replace
     * @return array
     */
    protected function buildReplacements(string $name)
    {
        if (!Str::endsWith($name, 'Resource')) {
            $this->error("Resources must be suffixed with 'Resource'!");
            exit(1);
        }

        $model = class_basename(Str::beforeLast($name, 'Resource'));

        return [
            '{{ model }}' => $model,
            '{{model}}' => $model,
        ];
    }
}
