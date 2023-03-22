<?php

namespace LiveIntent\LaravelCommon\Log;

use Monolog\Processor\ProcessorInterface;

/**
 * Augment log record to confirm to LiveIntent logging standards.
 *
 * @phpstan-import-type Record from \Monolog\Logger
 */
class LiveIntentLogProcessor implements ProcessorInterface
{
    /**
     * @return array The processed record
     *
     * @phpstan-param  Record $record
     * @phpstan-return Record
     */
    public function __invoke(array $record)
    {
        $record['log_level'] = $record['level_name'];

        return $record;
    }
}
