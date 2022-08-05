<?php

namespace LiveIntent\LaravelCommon\Http;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

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
        $configMaxNestedDepth = config('orion.search.max_nested_depth', 1);

        // abort early so we dont screw ourselves
        // abort_if(
        //     $maxDepth > $configMaxNestedDepth,
        //     422,
        //     __('Max nested depth :depth is exceeded', ['depth' => $configMaxNestedDepth])
        // );

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
        $filterableFieldsList = $filterableFields->map->getName()->join(',');

        $rules = array_merge($rules, [
            $prefix.'.*.type' => ['sometimes', 'in:and,or'],
            $prefix.'.*.field' => [
                "required_without:{$prefix}.*.nested",
                "in:{$filterableFieldsList}"
            ],
            $prefix.'.*.operator' => [
                'sometimes',
                'in:<,<=,>,>=,=,!=,like,not like,ilike,not ilike,in,not in,all in,any in',
            ],
            $prefix.'.*.value' => Rule::forEach(function ($_, $attribute, $item) use ($filterableFields) {
                $key = str($attribute)->beforeLast('.')->toString();

                $fieldName = $item["{$key}.field"] ?? '';
                $operator = $item["{$key}.operator"] ?? '=';
                $filter = $filterableFields->get($fieldName);

                if (!$filter) {
                    return [];
                }

                if (in_array($operator, ['in', 'not in'])) {
                    return ['required', 'array'];
                }

                return $filter->getValueRules();
            }),
            $prefix.'.*.value.*' => Rule::forEach(function ($_, $attribute, $item) use ($filterableFields) {
                $key = str($attribute)->beforeLast('.')->beforeLast('.')->toString();
                $fieldName = $item["{$key}.field"] ?? '';
                $filter = $filterableFields->get($fieldName);

                if (!$filter) {
                    return [];
                }

                return $filter->getValueRules();
            }),
            $prefix.'.*.nested' => ['sometimes', 'array',],
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
