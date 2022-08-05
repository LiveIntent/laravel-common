<?php

namespace LiveIntent\LaravelCommon\Http;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SearchRequestValidator
{
    /**
     * Create a new instance.
     */
    public function __construct(private Request $request, private AbstractResource $resource) {}

    /**
     * Build the validation rules for searching the resource.
     */
    public function rules()
    {
        return [
            ...$this->scopeRules(),
            ...$this->filterRules(),
            ...$this->paginationRules(),
            // search rules
        ];
    }

    /**
     * Get the scope validation rules.
     */
    protected function scopeRules()
    {
        $exposedScopes = collect($this->resource->allowedScopes())->map->getName()->join(',');

        return [
            'scopes' => ['sometimes', 'array'],
            'scopes.*.name' => ['required_with:scopes', 'in:'.$exposedScopes],
            'scopes.*.parameters' => ['sometimes', 'array'],
        ];
    }

    /**
     * Get the filter validation rules.
     */
    protected function filterRules()
    {
        $maxDepth = floor($this->getArrayDepth($this->request->input('filters', [])) / 2);
        $configMaxNestedDepth = config('liveintent.search.max_nested_depth', 15);

        // Bork early if the nesting is to big so we don't screw ourselves
        throw_if(
            $maxDepth > $configMaxNestedDepth,
            ValidationException::withMessages([
                __('Max nested depth :depth is exceeded', ['depth' => $configMaxNestedDepth])
            ])
        );

        return array_merge([
            'filters' => ['sometimes', 'array'], // TODO add array keys
        ], $this->getNestedFilterRules('filters', $maxDepth));
    }

    /**
     * Get the pagination validation rules.
     */
    protected function paginationRules()
    {
        $maxPageSize = config('json-api-paginate.max_results');

        return [
            'page.size' => "integer|lte:{$maxPageSize}"
        ];
    }

    /**
     * @param string $prefix
     * @param int $maxDepth
     * @param array $rules
     * @param int $currentDepth
     * @return array
     */
    protected function getNestedFilterRules(string $prefix, int $maxDepth, array $rules = [], int $currentDepth = 1): array
    {
        $filterableFields = collect($this->resource->allowedFilters())->keyBy->getName();

        $rules = array_merge($rules, [
            $prefix.'.*.type' => ['sometimes', 'in:and,or'],
            $prefix.'.*.nested' => ['sometimes', 'array',],
            $prefix.'.*.field' => [
                "required_without:{$prefix}.*.nested",
                Rule::in($filterableFields->keys()->toArray())
            ],
            $prefix.'.*.operator' => Rule::forEach(function ($_, $attribute, $item) use ($filterableFields) {
                $key = str($attribute)->beforeLast('.')->toString();

                if (!$filter = $filterableFields->get($item["{$key}.field"] ?? '')) {
                    return [];
                }

                return [
                    'sometimes',
                    Rule::in($filter->getAllowedOperators())
                ];
            }),
            $prefix.'.*.value' => Rule::forEach(function ($_, $attribute, $item) use ($filterableFields) {
                $key = str($attribute)->beforeLast('.')->toString();

                if (!$filter = $filterableFields->get($item["{$key}.field"] ?? '')) {
                    return [];
                }

                $operator = $item["{$key}.operator"] ?? '=';
                if (in_array($operator, ['in', 'not in'])) {
                    return ['required', 'array'];
                }

                return $filter->getValueRules();
            }),
            $prefix.'.*.value.*' => Rule::forEach(function ($_, $attribute, $item) use ($filterableFields) {
                $key = str($attribute)->beforeLast('.')->beforeLast('.')->toString();

                if (!$filter = $filterableFields->get($item["{$key}.field"] ?? '')) {
                    return [];
                }

                return $filter->getValueRules();
            }),
        ]);

        if ($maxDepth >= $currentDepth) {
            $rules = array_merge(
                $rules,
                $this->getNestedFilterRules("{$prefix}.*.nested", $maxDepth, $rules, ++$currentDepth)
            );
        }

        return $rules;
    }

    /**
     * Get the depth of an array.
     */
    protected function getArrayDepth($array): int
    {
        $maxDepth = 0;

        foreach ($array as $value) {
            if (is_array($value)) {
                $depth = $this->getArrayDepth($value) + 1;

                $maxDepth = max($depth, $maxDepth);
            }
        }

        return $maxDepth;
    }

    /**
     * Create a validator for a request.
     */
    public function validator()
    {
       return Validator::make($this->request->all(), $this->rules());
    }

    /**
     * Validate an incoming request.
     */
    public function validate()
    {
        $this->validator()->validate();
    }

    /**
     * Check if the validator fails.
     */
    public function fails()
    {
        return $this->validator()->fails();
    }

    /**
     * Make a new validator instance.
     */
    public static function make(Request $request, AbstractResource $resource)
    {
        return new static($request, $resource);
    }
}
