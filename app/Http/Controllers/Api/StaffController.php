<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Resources\Api\UserResource;
use App\Http\Requests\StoreStaffRequest; // Use the new request
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
        \Log::info('StaffController@index called');
        
        try {
            $this->authorize('viewAny', User::class);
            \Log::info('StaffController@index - authorization passed');
        } catch (\Exception $e) {
            \Log::error('StaffController@index - authorization failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'user_role' => auth()->user()->role ?? 'unknown',
            ]);
            throw $e;
        }

        // Define the number of items per page, with a default of 15 and a maximum of 100.
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        // Build query with filters
        $query = User::query();

        // Filter by role if provided
        if ($request->has('role')) {
            $query->where('role', $request->input('role'));
        }

        // Filter by restaurant if provided
        if ($request->has('restaurant_id')) {
            $query->where('restaurant_id', $request->input('restaurant_id'));
        }

        // Filter by branch if provided
        if ($request->has('restaurant_branch_id')) {
            $query->where('restaurant_branch_id', $request->input('restaurant_branch_id'));
        }

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Retrieve users (staff) with pagination and transform them using UserResource collection.
        $users = $query->paginate($perPage);
        
        // Return the response in the format expected by tests - with data key
        return response([
            'data' => UserResource::collection($users->items()),
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
            'per_page' => $users->perPage(),
            'total' => $users->total(),
            'from' => $users->firstItem(),
            'to' => $users->lastItem(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStaffRequest $request): Response // Use StoreStaffRequest
    {
        $this->authorize('create', User::class);

        // The request is automatically validated by StoreStaffRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Hash the password before creating the user record.
        $validated['password'] = Hash::make($validated['password']);

        // Create a new User record (staff) with the validated data.
        // The role, restaurant_id, restaurant_branch_id, and permissions are now validated
        // and prepared by StoreStaffRequest.
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
        $this->authorize('view', $user);

        // Return the specified user transformed by UserResource.
        // Laravel's route model binding automatically retrieves the user.
        return response(new UserResource($user));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user): Response
    {
        $this->authorize('update', $user);

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
        $this->authorize('delete', $user);

        // Delete the specified user record.
        $user->delete();

        // Return a 204 No Content response, indicating successful deletion.
        return response(null, 204);
    }
}
