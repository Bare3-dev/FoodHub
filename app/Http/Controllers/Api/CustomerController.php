<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Http\Resources\Api\CustomerResource;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        // Define the number of items per page, with a default of 15 and a maximum of 100.
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        // Retrieve customers with pagination and transform them using CustomerResource collection.
        return response(CustomerResource::collection(Customer::paginate($perPage)));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerRequest $request): Response
    {
        // The request is automatically validated by StoreCustomerRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Hash the password before creating the customer record.
        $validated['password'] = Hash::make($validated['password']);

        // Create a new Customer record with the validated data.
        $customer = Customer::create($validated);

        // Return the newly created customer transformed by CustomerResource
        // with a 201 Created status code.
        return response(new CustomerResource($customer), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer): Response
    {
        // Return the specified customer transformed by CustomerResource.
        // Laravel's route model binding automatically retrieves the customer.
        return response(new CustomerResource($customer));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): Response
    {
        // The request is automatically validated by UpdateCustomerRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // If a new password is provided, hash it before updating.
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        // Update the existing Customer record with the validated data.
        $customer->update($validated);

        // Return the updated customer transformed by CustomerResource.
        return response(new CustomerResource($customer));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer): Response
    {
        // Delete the specified customer record.
        $customer->delete();

        // Return a 204 No Content response, indicating successful deletion.
        return response(null, 204);
    }
}
