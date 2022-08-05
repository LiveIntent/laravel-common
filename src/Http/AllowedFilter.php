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

    /** @var array */
    protected $rules;

    /**
     * Create a new instance.
     */
    public function __construct(string $name, ?string $internalName = null, ?array $allowedOperators = [], ?array $rules = [])
    {
        $this->name = $name;
        $this->internalName = $internalName ?? $name;
        $this->allowedOperators = $allowedOperators ?? [];
        $this->rules = $rules ?? [];
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
     * Get the internal facing name.
     */
    public function getAllowedOperators(): array
    {
        return $this->allowedOperators;
    }

    /**
     * Get rules to use for validating the value of the filter.
     */
    public function getValueRules(): array
    {
        return $this->rules;
    }

    /**
     * Create a new allowed filter for a string field.
     */
    public static function string(string $name, ?string $internalName = null)
    {
        return new static(
            $name,
            $internalName,
            ['=', '!=', 'in', 'not in', '>', '>=', '<', '<=', 'like', 'not like'],
            ['string', 'nullable']
        );
    }

    /**
     * Create a new allowed filter for a number field.
     */
    public static function number(string $name, ?string $internalName = null)
    {
        return new static(
            $name,
            $internalName,
            ['=', '!=', 'in', 'not in', '>', '>=', '<', '<='],
            ['integer', 'numeric', 'nullable', function ($attribute, $value, $fail) {
                if (!is_int($value)) {
                    $fail('The '.$attribute.' must be an integer.');
                }
            }]
        );
    }

    /**
     * Create a new allowed filter for a timestamp field.
     */
    public static function timestamp(string $name, ?string $internalName = null)
    {
        return new static(
            $name,
            $internalName,
            ['=', '!=', 'in', 'not in', '>', '>=', '<', '<='],
            ['date', 'nullable']
        );
    }
}
