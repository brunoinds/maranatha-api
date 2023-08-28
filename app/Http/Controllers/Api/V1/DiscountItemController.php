<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreDiscountItemRequest;
use App\Http\Requests\UpdateDiscountItemRequest;
use App\Models\DiscountItem;
use App\Http\Controllers\Controller;

class DiscountItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDiscountItemRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(DiscountItem $discountItem)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DiscountItem $discountItem)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDiscountItemRequest $request, DiscountItem $discountItem)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DiscountItem $discountItem)
    {
        //
    }
}
