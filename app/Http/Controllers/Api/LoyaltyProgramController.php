<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyProgram;
use App\Http\Resources\Api\LoyaltyProgramResource;
use App\Http\Requests\StoreLoyaltyProgramRequest;
use App\Http\Requests\UpdateLoyaltyProgramRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LoyaltyProgramController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        // Define the number of items per page, with a default of 15 and a maximum of 100.
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        // Retrieve loyalty programs with pagination and transform them using LoyaltyProgramResource collection.
        return response(LoyaltyProgramResource::collection(LoyaltyProgram::paginate($perPage)));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreLoyaltyProgramRequest $request): Response
    {
        // The request is automatically validated by StoreLoyaltyProgramRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Create a new LoyaltyProgram record with the validated data.
        $loyaltyProgram = LoyaltyProgram::create($validated);

        // Return the newly created loyalty program transformed by LoyaltyProgramResource
        // with a 201 Created status code.
        return response(new LoyaltyProgramResource($loyaltyProgram), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(LoyaltyProgram $loyaltyProgram): Response
    {
        // Return the specified loyalty program transformed by LoyaltyProgramResource.
        // Laravel's route model binding automatically retrieves the loyalty program.
        return response(new LoyaltyProgramResource($loyaltyProgram));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLoyaltyProgramRequest $request, LoyaltyProgram $loyaltyProgram): Response
    {
        // The request is automatically validated by UpdateLoyaltyProgramRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Update the existing LoyaltyProgram record with the validated data.
        $loyaltyProgram->update($validated);

        // Return the updated loyalty program transformed by LoyaltyProgramResource.
        return response(new LoyaltyProgramResource($loyaltyProgram));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LoyaltyProgram $loyaltyProgram): Response
    {
        // Delete the specified loyalty program record.
        $loyaltyProgram->delete();

        // Return a 204 No Content response, indicating successful deletion.
        return response(null, 204);
    }
}
