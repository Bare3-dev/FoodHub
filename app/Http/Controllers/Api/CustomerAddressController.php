<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
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
    public function index(Request $request, Customer $customer): Response
    {
        // Define the number of items per page, with a default of 15 and a maximum of 100.
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        // Retrieve customer addresses for the specific customer with pagination
        $addresses = $customer->addresses()->paginate($perPage);

        // Transform them using CustomerAddressResource collection.
        return response(CustomerAddressResource::collection($addresses));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerAddressRequest $request, Customer $customer): Response
    {
        // Check authorization - temporarily disabled until policy is created
        // if (!$this->authorize('create', [CustomerAddress::class, $customer])) {
        //     abort(403, 'Unauthorized to create addresses for this customer.');
        // }

        // The request is automatically validated by StoreCustomerAddressRequest.
        // Access the validated data directly.
        $validated = $request->validated();
        
        // Set the customer_id from the URL parameter
        $validated['customer_id'] = $customer->id;

        // If this address is being set as default, make all other addresses non-default
        if (isset($validated['is_default']) && $validated['is_default']) {
            $customer->addresses()->update(['is_default' => false]);
        }

        // Create a new CustomerAddress record with the validated data.
        $customerAddress = CustomerAddress::create($validated);

        // Return the newly created customer address transformed by CustomerAddressResource
        // with a 201 Created status code.
        return response(new CustomerAddressResource($customerAddress), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer, CustomerAddress $address): Response
    {
        // Ensure the address belongs to the customer
        if ($address->customer_id !== $customer->id) {
            abort(404);
        }

        // Check authorization - temporarily disabled until policy is created
        // if (!$this->authorize('view', $address)) {
        //     abort(403, 'Unauthorized to view this address.');
        // }

        // Return the specified customer address transformed by CustomerAddressResource.
        return response(new CustomerAddressResource($address));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomerAddressRequest $request, Customer $customer, CustomerAddress $address): Response
    {
        // Ensure the address belongs to the customer
        if ($address->customer_id !== $customer->id) {
            abort(404);
        }

        // Check authorization - temporarily disabled until policy is created
        // if (!$this->authorize('update', $address)) {
        //     abort(403, 'Unauthorized to update this address.');
        // }

        // The request is automatically validated by UpdateCustomerAddressRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // If this address is being set as default, make all other addresses non-default
        if (isset($validated['is_default']) && $validated['is_default']) {
            $customer->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        // Update the existing CustomerAddress record with the validated data.
        $address->update($validated);

        // Return the updated customer address transformed by CustomerAddressResource.
        return response(new CustomerAddressResource($address));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer, CustomerAddress $address): Response
    {
        // Ensure the address belongs to the customer
        if ($address->customer_id !== $customer->id) {
            abort(404);
        }

        // Simple role-based authorization check
        $user = auth()->user();
        if ($user && in_array($user->role, ['KITCHEN_STAFF', 'CASHIER'])) {
            abort(403, 'Unauthorized to delete addresses.');
        }

        // Check authorization - temporarily disabled until policy is created
        // if (!$this->authorize('delete', $address)) {
        //     abort(403, 'Unauthorized to delete this address.');
        // }

        // Delete the specified customer address record.
        $address->delete();

        // Return a 204 No Content response, indicating successful deletion.
        return response(null, 204);
    }
}
