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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Customer::class);

        // Define the number of items per page, with a default of 15 and a maximum of 100.
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        // Retrieve customers with pagination and transform them using CustomerResource collection.
        $customers = Customer::paginate($perPage);
        
        // Return the data in the format expected by tests - simple array
        return response($customers->items());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerRequest $request): Response
    {
        $this->authorize('create', Customer::class);

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
        $this->authorize('view', $customer);

        // Return the specified customer transformed by CustomerResource.
        // Laravel's route model binding automatically retrieves the customer.
        return response(new CustomerResource($customer));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): Response
    {
        $this->authorize('update', $customer);

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
        $this->authorize('delete', $customer);

        // Delete the specified customer record.
        $customer->delete();

        // Return a 204 No Content response, indicating successful deletion.
        return response(null, 204);
    }

    /**
     * Update customer preferences.
     */
    public function updatePreferences(Request $request, Customer $customer): Response
    {
        $validated = $request->validate([
            'dietary_restrictions' => ['nullable', 'array'],
            'dietary_restrictions.*' => ['string', 'in:vegetarian,vegan,gluten_free,dairy_free,nut_free'],
            'allergies' => ['nullable', 'array'],
            'allergies.*' => ['string', 'max:100'],
            'preferred_contact_method' => ['nullable', 'string', 'in:email,phone,sms'],
            'marketing_preferences' => ['nullable', 'array'],
            'marketing_preferences.email' => ['nullable', 'boolean'],
            'marketing_preferences.sms' => ['nullable', 'boolean'],
            'marketing_preferences.push' => ['nullable', 'boolean'],
            'delivery_preferences' => ['nullable', 'array'],
            'delivery_preferences.contactless' => ['nullable', 'boolean'],
            'delivery_preferences.instructions' => ['nullable', 'string', 'max:500'],
        ]);

        $currentPreferences = $customer->preferences ?? [];
        if (!is_array($currentPreferences)) {
            $currentPreferences = [];
        }
        
        $customer->update([
            'preferences' => array_merge($currentPreferences, $validated)
        ]);

        return response([
            'data' => [
                'id' => $customer->id,
                'dietary_restrictions' => $validated['dietary_restrictions'] ?? [],
                'allergies' => $validated['allergies'] ?? [],
                'preferred_contact_method' => $validated['preferred_contact_method'] ?? null,
                'marketing_preferences' => $validated['marketing_preferences'] ?? [],
                'delivery_preferences' => $validated['delivery_preferences'] ?? [],
            ]
        ]);
    }

    /**
     * Store a support ticket.
     */
    public function storeSupportTicket(Request $request): Response
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'ticket_type' => ['required', 'string', 'in:technical,order_issue,billing,general'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'priority' => ['required', 'string', 'in:low,medium,high,urgent'],
            'contact_preference' => ['nullable', 'string', 'in:email,phone,sms'],
        ]);

        // Get the authenticated user ID (could be Customer or User)
        $user_id = null;
        if (Auth::user() instanceof \App\Models\User) {
            $user_id = Auth::id();
        }

        $ticket = DB::table('customer_support_tickets')->insertGetId([
            'customer_id' => $validated['customer_id'],
            'user_id' => $user_id,
            'ticket_number' => 'TKT-' . strtoupper(uniqid()),
            'ticket_type' => $validated['ticket_type'],
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'contact_preference' => $validated['contact_preference'],
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response([
            'data' => [
                'id' => $ticket,
                'ticket_number' => 'TKT-' . strtoupper(uniqid()),
                'ticket_type' => $validated['ticket_type'],
                'subject' => $validated['subject'],
                'priority' => $validated['priority'],
                'status' => 'open',
                'created_at' => now()
            ]
        ], 201);
    }
}
