<?php

namespace LiveIntent\LaravelCommon\Console;

use Symfony\Component\Console\Input\InputOption;
use Illuminate\Foundation\Console\RequestMakeCommand as BaseRequestMakeCommand;

class RequestMakeCommand extends BaseRequestMakeCommand
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
            return $this->resolveStubPath("/stubs/request.{$type}.stub");
        }

        return $this->resolveStubPath('/stubs/request.stub');
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
        ];
    }
}
