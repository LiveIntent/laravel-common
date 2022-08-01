<?php

namespace LiveIntent\LaravelCommon\Http;

use Illuminate\Support\Traits\ForwardsCalls;

class BasicQueryBuilder
{
    use ForwardsCalls;

    public function __call($name, $arguments)
    {
        $result = $this->forwardCallTo($this->subject, $name, $arguments);

        /*
         * If the forwarded method call is part of a chain we can return $this
         * instead of the actual $result to keep the chain going.
         */
        if ($result === $this->subject) {
            return $this;
        }

        return $result;
    }
}
