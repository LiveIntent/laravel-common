<?php

namespace LiveIntent\LaravelCommon\Auth;

enum PermissionType: string
{
    /**
     *
     */
    case Standard = 'standard';

    /**
     *
     */
    case Internal = 'internal';

    /**
     *
     */
    case Admin = 'admin';
}
