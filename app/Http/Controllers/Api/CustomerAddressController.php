<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Http\Resources\Api\CustomerAddressResource;
use App\Http\Requests\StoreCustomerAddressRequest;
use App\Http\Requests\UpdateCustomerAddressRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CustomerAddressController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        // Define the number of items per page, with a default of 15 and a maximum of 100.
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        // Retrieve customer addresses with pagination and transform them using CustomerAddressResource collection.
        return response(CustomerAddressResource::collection(CustomerAddress::paginate($perPage)));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerAddressRequest $request): Response
    {
        // The request is automatically validated by StoreCustomerAddressRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Create a new CustomerAddress record with the validated data.
        $customerAddress = CustomerAddress::create($validated);

        // Return the newly created customer address transformed by CustomerAddressResource
        // with a 201 Created status code.
        return response(new CustomerAddressResource($customerAddress), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(CustomerAddress $customerAddress): Response
    {
        // Return the specified customer address transformed by CustomerAddressResource.
        // Laravel's route model binding automatically retrieves the customer address.
        return response(new CustomerAddressResource($customerAddress));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomerAddressRequest $request, CustomerAddress $customerAddress): Response
    {
        // The request is automatically validated by UpdateCustomerAddressRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Update the existing CustomerAddress record with the validated data.
        $customerAddress->update($validated);

        // Return the updated customer address transformed by CustomerAddressResource.
        return response(new CustomerAddressResource($customerAddress));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CustomerAddress $customerAddress): Response
    {
        // Delete the specified customer address record.
        $customerAddress->delete();

        // Return a 204 No Content response, indicating successful deletion.
        return response(null, 204);
    }
}
