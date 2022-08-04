<?php

namespace LiveIntent\LaravelCommon\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SearchRequestValidator
{
    /**
     * Validate an incoming request.
     */
    public function validate(Request $request, AbstractResource $resource)
    {
        Validator::validate($request->all(), $this->rules($resource));
    }

    /**
     * Build the validation rules for searching the resource.
     */
    public function rules(AbstractResource $resource)
    {
        $maxPageSize = config('json-api-paginate.max_results');

        return [
            'page.size' => "integer|lte:{$maxPageSize}"
        ];
    }
}
