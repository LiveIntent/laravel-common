<?php

namespace LiveIntent\LaravelCommon\Commands;

use Illuminate\Console\Command;

class LaravelCommonCommand extends Command
{
    public $signature = 'laravel-common';

    public $description = 'My command';

    public function handle()
    {
        $this->comment('All done');
    }
}
