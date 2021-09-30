<?php

namespace LiveIntent\LaravelCommon\Console;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Foundation\Console\TestMakeCommand as BaseTestMakeCommand;

class TestMakeCommand extends BaseTestMakeCommand
{
    use OverridesStubs;

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        if ($type = $this->option('type')) {
            return $this->resolveStubPath("/stubs/test.{$type}.stub");
        }

        return parent::getStub();
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
        $replace = $this->buildReplacements();

        return str_replace(
            array_keys($replace),
            array_values($replace),
            parent::buildClass($name)
        );
    }

    /**
     * Get the fully-qualified model class name.
     *
     * @param  string  $model
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function parseModel($model)
    {
        if (preg_match('([^A-Za-z0-9_/\\\\])', $model)) {
            throw new InvalidArgumentException('Model name contains invalid characters.');
        }

        return $this->qualifyModel($model);
    }

    /**
     * Qualify the given model class base name.
     *
     * @param  string  $model
     * @return string
     */
    protected function qualifyModel(string $model)
    {
        $model = ltrim($model, '\\/');

        $model = str_replace('/', '\\', $model);

        $rootNamespace = $this->laravel->getNamespace();

        if (Str::startsWith($model, $rootNamespace)) {
            return $model;
        }

        return is_dir(app_path('Models'))
                    ? $rootNamespace.'Models\\'.$model
                    : $rootNamespace.$model;
    }

    /**
     * Build the replacement values.
     *
     * @param  array  $replace
     * @return array
     */
    protected function buildReplacements()
    {
        $modelClass = $this->parseModel($this->option('model') ?: 'Example');
        $table = Str::snake(Str::pluralStudly(class_basename($modelClass)));

        return [
            '{{ namespacedModel }}' => $modelClass,
            '{{namespacedModel}}' => $modelClass,
            '{{ model }}' => class_basename($modelClass),
            '{{model}}' => class_basename($modelClass),
            '{{ modelVariable }}' => lcfirst(class_basename($modelClass)),
            '{{modelVariable}}' => lcfirst(class_basename($modelClass)),
            '{{ modelLowercased }}' => Str::snake(class_basename($modelClass)),
            '{{modelLowercased}}' => Str::snake(class_basename($modelClass)),
            '{{ modelLowercasedPlural }}' => Str::snake(Str::plural(class_basename($modelClass))),
            '{{modelLowercasedPlural}}' => Str::snake(Str::plural(class_basename($modelClass))),
            '{{ table }}' => $table,
            '{{table}}' => $table,
            '{{ uri }}' => "/api/{$table}",
            '{{uri}}' => "/api/{$table}",
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['unit', 'u', InputOption::VALUE_NONE, 'Create a unit test.'],
            ['type', null, InputOption::VALUE_REQUIRED, 'Manually specify the test stub file to use.'],
            ['model', 'm', InputOption::VALUE_REQUIRED, 'Specify the model to use in the test'],
        ];
    }
}
