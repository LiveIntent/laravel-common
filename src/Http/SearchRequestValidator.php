<?php

namespace LiveIntent\LaravelCommon\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SearchRequestValidator
{
    /**
     * Create a new instance.
     */
    public function __construct(private Request $request, private AbstractResource $resource)
    {}

    /**
     * Build the validation rules for searching the resource.
     */
    public function rules()
    {
        $maxPageSize = config('json-api-paginate.max_results');

        $exposedScopes = collect($this->resource->allowedScopes())->map->getName()->toArray();

        return [
            ...$this->filterRules(),
            'scopes' => ['sometimes', 'array'],
            'scopes.*.name' => ['required_with:scopes', 'in:'.implode(',', $exposedScopes)],
            'scopes.*.parameters' => ['sometimes', 'array'],

            'page.size' => "integer|lte:{$maxPageSize}"
        ];
    }

    /**
     *
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
            'filters' => ['sometimes', 'array'],
        ], $this->getNestedFilterRules('filters', $maxDepth));
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
        $filterableFieldsMatcher = $filterableFields->map->getName()->join(',');

        // $stringFieldRules = 'string';

        // $this->validator()->sometimes($prefix.'.*.value', 'string', fn ($_, $item) => $item->field === 'foobar');
        // $filterableFields->each(
        //     fn ($filter) => $this->validator()->sometimes($prefix.'.*.value', $filter->rules(), fn ($_, $item) => $item->field === $filter->getName())
        // );
        // $this->validator()->sometimes($prefix.'.*.value', $stringFieldRules, $isStringFilter)

        // $this->validator()->sometimes($prefix.'.*.value', 'array', fn ($input, $item) => in_array($item->operator, ['in', 'not in']));

        $rules = array_merge($rules, [
            $prefix.'.*.type' => ['sometimes', 'in:and,or'],
            $prefix.'.*.field' => [
                "required_without:{$prefix}.*.nested",
                'regex:/^[\w.\_\-\>]+$/',
                // new WhitelistedField($this->filterableBy),
                "in:{$filterableFieldsMatcher}"
            ],
            $prefix.'.*.operator' => [
                'sometimes',
                'in:<,<=,>,>=,=,!=,like,not like,ilike,not ilike,in,not in,all in,any in',
            ],
            // $prefix.'.*.value' => ['nullable'],
            $prefix.'.*.value' => Rule::forEach(function ($_, $attribute, $item) use ($filterableFields) {
                $key = str($attribute)->beforeLast('.')->toString();

                $fieldName = $item["{$key}.field"];
                $operator = $item["{$key}.operator"] ?? '=';
                $filter = $filterableFields->get($fieldName);

                if (in_array($operator, ['in', 'not in'])) {
                    return 'array';
                }
                // $rules = $field === 'color' ? [
                //     'string', 'nullable'
                // ] : [];

                return $filter->getValueRules();
                // if the field is a string, then we need to have string values or array of string values
                // return $rules;
            }),
            $prefix.'.*.nested' => ['sometimes', 'array',],
        ]);

        // dump($rules);

        if ($maxDepth >= $currentDepth) {
            $rules = array_merge(
                $rules,
                $this->getNestedFilterRules("{$prefix}.*.nested", $maxDepth, $rules, ++$currentDepth)
            );
        }

        return $rules;
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

}
