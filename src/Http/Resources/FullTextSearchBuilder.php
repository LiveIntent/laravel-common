<?php

namespace LiveIntent\LaravelCommon\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Orion\Http\Requests\Request;

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
