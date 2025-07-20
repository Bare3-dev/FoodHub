<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Resources\Api\UserResource;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        // Define the number of items per page, with a default of 15 and a maximum of 100.
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        // Retrieve users (staff) with pagination and transform them using UserResource collection.
        return response(UserResource::collection(User::paginate($perPage)));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): Response
    {
        // The request is automatically validated by StoreUserRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Hash the password before creating the user record.
        $validated['password'] = Hash::make($validated['password']);

        // Create a new User record (staff) with the validated data.
        $user = User::create($validated);

        // Return the newly created user transformed by UserResource
        // with a 201 Created status code.
        return response(new UserResource($user), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user): Response
    {
        // Return the specified user transformed by UserResource.
        // Laravel's route model binding automatically retrieves the user.
        return response(new UserResource($user));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user): Response
    {
        // The request is automatically validated by UpdateUserRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // If a new password is provided, hash it before updating.
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        // Update the existing User record (staff) with the validated data.
        $user->update($validated);

        // Return the updated user transformed by UserResource.
        return response(new UserResource($user));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): Response
    {
        // Delete the specified user record.
        $user->delete();

        // Return a 204 No Content response, indicating successful deletion.
        return response(null, 204);
    }
}
