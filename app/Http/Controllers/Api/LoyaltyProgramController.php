<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyProgram;
use App\Http\Resources\Api\LoyaltyProgramResource;
use App\Http\Requests\StoreLoyaltyProgramRequest;
use App\Http\Requests\UpdateLoyaltyProgramRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

class LoyaltyProgramController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LoyaltyProgram::class);

        // Define the number of items per page, with a default of 15 and a maximum of 100.
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        // Build query with filters
        $query = LoyaltyProgram::query();

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by restaurant if provided
        if ($request->has('restaurant_id')) {
            $query->where('restaurant_id', $request->input('restaurant_id'));
        }

        // Retrieve loyalty programs with pagination and transform them using LoyaltyProgramResource collection.
        $loyaltyPrograms = $query->paginate($perPage);
        
        // Return the standard Laravel pagination response format with links and meta
        return LoyaltyProgramResource::collection($loyaltyPrograms)->response();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreLoyaltyProgramRequest $request): JsonResponse
    {
        $this->authorize('create', LoyaltyProgram::class);

        // The request is automatically validated by StoreLoyaltyProgramRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Create a new LoyaltyProgram record with the validated data.
        $loyaltyProgram = LoyaltyProgram::create($validated);

        // Return the newly created loyalty program transformed by LoyaltyProgramResource
        // with a 201 Created status code.
        return (new LoyaltyProgramResource($loyaltyProgram))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(LoyaltyProgram $loyaltyProgram): JsonResponse
    {
        $this->authorize('view', $loyaltyProgram);

        // Return the specified loyalty program transformed by LoyaltyProgramResource.
        // Laravel's route model binding automatically retrieves the loyalty program.
        return (new LoyaltyProgramResource($loyaltyProgram))->response();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLoyaltyProgramRequest $request, LoyaltyProgram $loyaltyProgram): JsonResponse
    {
        $this->authorize('update', $loyaltyProgram);

        // The request is automatically validated by UpdateLoyaltyProgramRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Update the existing LoyaltyProgram record with the validated data.
        $loyaltyProgram->update($validated);

        // Return the updated loyalty program transformed by LoyaltyProgramResource.
        return (new LoyaltyProgramResource($loyaltyProgram))->response();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LoyaltyProgram $loyaltyProgram): Response
    {
        $this->authorize('delete', $loyaltyProgram);

        // Delete the specified loyalty program record.
        $loyaltyProgram->delete();

        // Return a 204 No Content response, indicating successful deletion.
        return response(null, 204);
    }
}
