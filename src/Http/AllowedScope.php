<?php

namespace LiveIntent\LaravelCommon\Http;

class AllowedScope implements Aliasable
{
    /** @var string */
    protected $name;

    /** @var string */
    protected $internalName;

    /** @var array */
    protected $args;

    /**
     * Create a new instance.
     */
    public function __construct(string $name, ?string $internalName = null, ?array $args = [])
    {
        $this->name = $name;
        $this->internalName = $internalName ?? $name;
        $this->args = $args ?? [];
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
     * Get the args that should be used to call the scope.
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * Add arguments to the scope.
     */
    public function withArgs(array $args)
    {
        $this->args = array_merge($this->args, $args);

        return $this;
    }

    /**
     * Create a new allowed scope.
     */
    public static function name(string $name, ?string $internalName = null, ?array $args = [])
    {
        return new static($name, $internalName, $args);
    }
}
