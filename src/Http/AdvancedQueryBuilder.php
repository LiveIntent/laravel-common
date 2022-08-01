<?php

namespace LiveIntent\LaravelCommon\Http;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Traits\ForwardsCalls;
use Spatie\QueryBuilder\Exceptions\InvalidSubject;
use Spatie\QueryBuilder\Concerns\FiltersQuery;

class AdvancedQueryBuilder
{
    /** @var \Spatie\QueryBuilder\QueryBuilderRequest */
    protected $request;

    /** @var \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation */
    protected $subject;
    use ForwardsCalls;
    use FiltersQuery;

    /**
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation $subject
     * @param null|\Illuminate\Http\Request $request
     */
    public function __construct($subject, ?Request $request = null)
    {
        $this->initializeSubject($subject)
            ->initializeRequest($request ?? app(Request::class));
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation $subject
     *
     * @return $this
     */
    protected function initializeSubject($subject): self
    {
        throw_unless(
            $subject instanceof EloquentBuilder || $subject instanceof Relation,
            InvalidSubject::make($subject)
        );

        $this->subject = $subject;

        return $this;
    }

    protected function initializeRequest(?Request $request = null): self
    {
        // $this->request = $request
        //     ? QueryBuilderRequest::fromRequest($request)
        //     : app(QueryBuilderRequest::class);
        $this->request = Request::createFrom($request);

        return $this;
    }

    /**
     * @param EloquentBuilder|Relation|string $subject
     * @param Request|null $request
     *
     * @return static
     */
    public static function for($subject, ?Request $request = null): self
    {
        if (is_subclass_of($subject, Model::class)) {
            $subject = $subject::query();
        }

        return new static($subject, $request);
    }

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
