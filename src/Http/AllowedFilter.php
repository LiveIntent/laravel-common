<?php

namespace LiveIntent\LaravelCommon\Http;

// use Spatie\QueryBuilder\AllowedFilter as SpatieAllowedFilter;

class AllowedFilter implements Aliasable
{
    /** @var string */
    protected $name;

    /** @var string */
    protected $internalName;

    /** @var array */
    protected $allowedOperators;

    /**
     * Create a new instance.
     */
    public function __construct(string $name, ?string $internalName = null, ?array $allowedOperators = [])
    {
        $this->name = $name;
        $this->internalName = $internalName ?? $name;
        $this->allowedOperators = $allowedOperators ?? [];
    }

    /**
     * Get the external facing name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the internal facing name.
     */
    public function getInternalName(): string
    {
        return $this->internalName;
    }

    /**
     *
     */
    public function rules()
    {
        return 'string';
    }


    /**
     * Get the internal facing name.
     */
    public function getAllowedOperators(): array
    {
        return $this->allowedOperators;
    }

    /**
     * Create a new allowed filter for a string field.
     */
    public static function string(string $name, ?string $internalName = null)
    {
        return new static($name, $internalName, [
            '=', '!=', 'in', 'not in', '>', '>=', '<', '<=', 'like', 'not like'
        ]);
    }

    /**
     * Create a new allowed filter for a number field.
     */
    public static function number(string $name, ?string $internalName = null)
    {
        return new static($name, $internalName, [
            '=', '!=', 'in', 'not in', '>', '>=', '<', '<='
        ]);
    }

    /**
     * Create a new allowed filter for a timestamp field.
     */
    public static function timestamp(string $name, ?string $internalName = null)
    {
        return new static($name, $internalName, [
            '=', '!=', 'in', 'not in', '>', '>=', '<', '<='
        ]);
    }
}
