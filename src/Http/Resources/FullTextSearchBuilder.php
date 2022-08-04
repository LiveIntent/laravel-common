<?php

namespace LiveIntent\LaravelCommon\Http\Resources;

class FullTextSearchBuilder // implements \Orion\Contracts\RelationsResolver
{
    /**
     * @var array
     */
    private $searchableBy;

    public function __construct(array $searchableBy)
    {
        $this->searchableBy = $searchableBy;
    }

    public function searchableBy(): array
    {
        return $this->searchableBy;
    }
}
