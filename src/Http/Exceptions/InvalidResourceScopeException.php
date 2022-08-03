<?php

namespace LiveIntent\LaravelCommon\Http\Exceptions;

use Exception;
use LiveIntent\LaravelCommon\Http\AllowedScope;

class InvalidResourceScopeException extends Exception
{
    /**
     * Create a new instance.
     *
     * @param mixed $scope
     */
    public function __construct($scope)
    {
        $type = is_object($scope) ? $scope::class : gettype($scope);

        $allowedScope = AllowedScope::class;

        parent::__construct("Allowed scopes must be instances of '{$allowedScope}'. Got: '{$type}'.");
    }
}
