<?php

namespace LiveIntent\LaravelCommon\Http;

class AllowedScope implements Aliasable
{
    /** @var string */
    protected $name;

    /** @var string */
    protected $internalName;

    /**
     * Create a new instance.
     */
    public function __construct(string $name, ?string $internalName = null)
    {
        $this->name = $name;
        $this->internalName = $internalName ?? $name;
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
     * Create a new allowed scope.
     */
    public static function name(string $name, ?string $internalName = null)
    {
        return new static($name, $internalName);
    }
}
