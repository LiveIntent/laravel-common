<?php

namespace LiveIntent\LaravelCommon\Auth;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    public function getActorIdentifier(): int
    {
        return $this->actor;
    }

    /**
     * Check if the user is an admin user.
     */
    public function isAdmin(): bool
    {
        return $this->permission_type === 'admin';
    }

    /**
     * Check if the user is an internal user.
     */
    public function isInternal(): bool
    {
        return $this->permission_type === 'internal' || $this->permission_type === 'admin';
    }

    /**
     * Check if the user is a standard user.
     */
    public function isStandard(): bool
    {
        return $this->permission_type === 'standard';
    }

    /**
     * Check if the user is an external user.
     */
    public function isExternal(): bool
    {
        return ! $this->isInternal();
    }
}
