<?php

namespace LiveIntent\LaravelCommon\Console;

use Illuminate\Support\Str;
use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

class ApiResourceMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:api-resource';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new API resource';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->option('all') || empty(array_filter($this->options()))) {
            $this->input->setOption('controller', true);
            $this->input->setOption('factory', true);
            $this->input->setOption('migration', true);
            $this->input->setOption('model', true);
            $this->input->setOption('requests', true);
            $this->input->setOption('resource', true);
            $this->input->setOption('tests', true);
        }

        if ($this->option('model')) {
            $this->createModel();
        }

        if ($this->option('resource')) {
            $this->createResource();
        }

        if ($this->option('controller')) {
            $this->createController();
        }

        if ($this->option('requests')) {
            $this->createRequests();
        }

        if ($this->option('tests')) {
            $this->createTests();
        }
    }

    /**
     * Get the desired class name from the input.
     *
     * @return string
     */
    protected function getNameInput()
    {
        return Str::singular(parent::getNameInput());
    }

    /**
     * Create tests for the model.
     *
     * @return void
     */
    protected function createTests()
    {
        $name = $this->getNameInput();

        $this->call('make:test', [
            'name' => "Api/{$name}/Store{$name}Test",
            '--model' => $name,
            '--type' => 'store',
        ]);

        $this->call('make:test', [
            'name' => "Api/{$name}/Update{$name}Test",
            '--model' => $name,
            '--type' => 'update',
        ]);

        $this->call('make:test', [
            'name' => "Api/{$name}/View{$name}Test",
            '--model' => $name,
            '--type' => 'view',
        ]);

        $this->call('make:test', [
            'name' => "Api/{$name}/Delete{$name}Test",
            '--model' => $name,
            '--type' => 'delete',
        ]);
    }

    /**
     * Create form requests for the model.
     *
     * @return void
     */
    protected function createRequests()
    {
        $this->call('make:request', [
            'name' => 'Store'.$this->getNameInput().'Request',
            '--type' => 'store',
        ]);

        $this->call('make:request', [
            'name' => 'Update'.$this->getNameInput().'Request',
            '--type' => 'update',
        ]);
    }

    /**
     * Create an Eloquent model.
     *
     * @return void
     */
    protected function createModel()
    {
        $this->call('make:model', array_filter([
            'name' => $this->getNameInput(),
            '--factory' => $this->option('factory'),
            '--migration' => $this->option('migration'),
        ]));
    }

    /**
     * Create a resource for the model.
     *
     * @return void
     */
    protected function createResource()
    {
        $this->call('make:resource', array_filter([
            'name' => $this->getNameInput().'Resource',
        ]));
    }

    /**
     * Create a controller for the model.
     *
     * @return void
     */
    protected function createController()
    {
        $controller = Str::studly(class_basename($this->getNameInput()));

        $modelName = $this->qualifyClass('Models/'.$this->getNameInput());

        $this->call('make:controller', array_filter([
            'name' => "{$controller}Controller",
            '--model' => $modelName,
            '--api' => true,
        ]));
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        // unused
        return '';
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['all', 'a', InputOption::VALUE_NONE, 'Generate a model, migrations, factory, controller, requests, resource, and tests'],
            ['controller', null, InputOption::VALUE_NONE, 'Create an api resource controller for the resource'],
            ['factory', null, InputOption::VALUE_NONE, 'Create a database factory for the resource'],
            ['migration', null, InputOption::VALUE_NONE, 'Create a migration file for the resource'],
            ['model', null, InputOption::VALUE_NONE, 'Create an Eloquent model for the resource'],
            ['requests', null, InputOption::VALUE_NONE, 'Create form requests for the resource'],
            ['resource', null, InputOption::VALUE_NONE, 'Create a new resource for the resource'],
            ['tests', null, InputOption::VALUE_NONE, 'Create api tests for the resource'],
        ];
    }
}
