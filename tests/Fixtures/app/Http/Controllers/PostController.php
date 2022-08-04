<?php

namespace LiveIntent\LaravelCommon\Tests\Fixtures\App\Http\Controllers;

use LiveIntent\LaravelCommon\Tests\Fixtures\App\Http\Resources\PostResource;

// use App\Http\Resources\{{ model }}Resource;
// use App\Http\Requests\Store{{ model }}Request;
// use App\Http\Requests\Update{{ model }}Request;

class PostController extends Controller
{
    // /**
    //  * Display a listing of the resource.
    //  *
    //  * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    //  */
    // public function index()
    // {
    //     return PostResource::filteredCollection();
    // }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function search()
    {
        return PostResource::search();
    }

    // /**
    //  * Store a newly created resource in storage.
    //  *
    //  * @return \App\Http\Resources\{{ model }}Resource
    //  */
    // public function store(Store{{ model }}Request $request)
    // {
    //     ${{ modelVariable }} = {{ model }}::create($request->validated());

    //     return {{ model }}Resource::make(${{ modelVariable }});
    // }

    // /**
    //  * Display the specified resource.
    //  *
    //  * @return \App\Http\Resources\{{ model }}Resource
    //  */
    // public function show({{ model }} ${{ modelVariable }})
    // {
    //     return {{ model }}Resource::make(${{ modelVariable }});
    // }

    // /**
    //  * Update the specified resource in storage.
    //  *
    //  * @return \App\Http\Resources\{{ model }}Resource
    //  */
    // public function update(Update{{ model }}Request $request, {{ model }} ${{ modelVariable }})
    // {
    //     ${{ modelVariable }}->update($request->validated());

    //     return {{ model }}Resource::make(${{ modelVariable }});
    // }

    // /**
    //  * Remove the specified resource from storage.
    //  */
    // public function destroy({{ model }} ${{ modelVariable }})
    // {
    //     ${{ modelVariable }}->delete();
    // }
}
