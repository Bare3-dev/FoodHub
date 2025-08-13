# FoodHub Laravel API Development Plan

## Objective
Develop the complete backend API for the FoodHub application using Laravel 11 and PHP 8.2+. The API should serve as the central hub for all business logic, data management, and integrations, supporting the various frontend applications (web dashboard, customer mobile app, driver mobile app).

## Instructions for Development
Process the development sprints sequentially. For each sprint, generate the requested output (text for planning, code for implementation) and then await explicit instructions to move to the next sprint. Do NOT proceed to the next sprint until explicitly prompted.

---

## Sprint 1: Planning & API Design

### Objective
Define the API's purpose and the resources it will expose. Design endpoints using RESTful principles. Define initial Authentication and Authorization requirements. Define the initial Versioning strategy. Create preliminary documentation.

### Tasks

1. **Confirm API Purpose**: Confirm the API's purpose as the central hub for all FoodHub business logic, data management, and integrations.

2. **Identify Primary Resources**: Identify primary API resources: restaurants, branches, users, customers, loyalty-programs, orders, menu-items, drivers, integrations, analytics.

3. **Define RESTful Endpoints**: Define initial RESTful API endpoint structures (e.g., /api/v1/restaurants, /api/v1/customers/{id}/orders).

4. **Authentication Setup**: Establish the initial Authentication requirement using Laravel Sanctum for API token authentication.

5. **RBAC Requirements**: Outline the initial Role-Based Access Control (RBAC) requirements for roles like SUPER_ADMIN, RESTAURANT_OWNER, BRANCH_MANAGER, CASHIER, KITCHEN_STAFF, DELIVERY_MANAGER, DRIVER, CUSTOMER_SERVICE.

6. **API Versioning**: Define the initial API versioning strategy (e.g., using Route::prefix('v1')).

7. **Documentation**: Create preliminary API documentation (e.g., Postman Collection or basic Swagger schema) outlining key endpoints and expected request/response formats.

**Output**: Provide a detailed text summary of the planning outcomes for each task listed above. Do NOT generate any code for this sprint.

---

## Sprint 2: Database Setup & Models

### Objective
Configure the database connection. Create Models and Migrations to define database tables and their relationships. Create Seeders to populate the database with essential test data.

### Tasks

1. **Create Coding Standards**: Create CODING_STANDARDS.md file in the project's root directory (see file for content).

2. **Laravel Project Setup**: Initiate a new Laravel 10 project named 'FoodHub' using Composer, with PHP 8.2+.

3. **Database Configuration**: Configure the .env file for PostgreSQL database connection (development, staging, production environments).

4. **Generate Migrations**: Generate PostgreSQL migrations for all core tables:
   - restaurants
   - restaurant_branches
   - users (restaurant staff)
   - customers
   - customer_addresses
   - loyalty_programs
   - loyalty_tiers
   - customer_loyalty_points
   - loyalty_points_history
   - stamp_cards
   - stamp_history
   - customer_challenges
   - menu_categories
   - menu_items
   - branch_menu_items
   - orders
   - order_items
   - order_status_history
   - drivers
   - driver_working_zones
   - order_assignments
   - delivery_tracking
   - delivery_reviews

   Ensure all migrations include necessary fields, data types, indexes (especially for frequently queried columns like status, created_at, customer_id, restaurant_id), unique constraints, and foreign key relationships. Utilize PostgreSQL-specific features like JSONB for JSON columns where appropriate.

5. **Create Models**: Create Eloquent Models for all defined tables with appropriate relationships (e.g., hasMany, belongsTo).

6. **Database Seeding**: Implement database seeding for roles, permissions, and initial dummy data for restaurants, branches, users, customers, and basic menu items to facilitate testing.

**Output**: Provide well-commented Laravel code for the migrations, models, and seeders.

---

## Sprint 3: Building Routes & Controllers

### Objective
Define API Routes. Create Resource Controllers to handle CRUD operations. Use API Resources to transform and format Eloquent Model data.

### Tasks

1. **Define API Routes**: Define API Routes in the routes/api.php file, grouped under the api middleware group and the chosen version prefix (e.g., v1).

2. **Create Resource Controllers**: Create Resource Controllers to handle CRUD operations for core modules:
   - **Restaurant & Branch Management**: CRUD for restaurants and restaurant_branches. Endpoints to manage branch-specific settings (opening hours, delivery zones, delivery fees).
   - **User Management (Restaurant Staff)**: CRUD for users (restaurant staff). Endpoints for assigning and updating roles and permissions.
   - **Customer Management**: CRUD for customers and customer_addresses.
   - **Digital Menu Management**: CRUD for menu_categories, menu_items, and branch_menu_items. Endpoints for customers to browse, search, and filter the menu.
   - **Driver Management**: CRUD for drivers and driver_working_zones.

3. **API Resources**: Implement API Resources (and Collections) to transform and format Eloquent Model data into consistent and structured JSON responses for all API endpoints.

**Output**: Provide well-commented Laravel code for the API routes, resource controllers, and API resources.

---

## Sprint 4: Request & Data Handling

### Objective
Validate Requests. Implement Pagination to efficiently handle large datasets.

### Tasks

1. **Request Validation**: Implement Laravel's data validation system for all incoming API requests. Use Form Requests for complex validation logic (e.g., order creation, user registration).

2. **Pagination**: Implement Pagination for all API endpoints that return lists of resources (e.g., customers, orders, menu items) to efficiently handle large datasets and improve performance.

**Output**: Provide well-commented Laravel code demonstrating request validation (Form Requests) and pagination implementation for relevant API endpoints.

---

## Sprint 5: Security Implementation

### Objective
Set up Authentication and Authorization. Implement Rate Limiting. Enable CORS. Secure the API in general.

### Tasks

1. **Authentication**: Finalize Laravel Sanctum setup for API token authentication. Ensure it supports token issuance and validation for all user types (customers, restaurant staff, drivers).

2. **Authorization**: Develop a robust Role-Based Access Control (RBAC) system. Create migrations for roles and permissions tables. Implement granular permissions (e.g., menu.manage, orders.view, customers.manage, deliveries.assign) using Laravel Gates/Policies. Ensure all API endpoints are protected by appropriate authentication and authorization middleware.

3. **Multi-Factor Authentication**: Implement backend logic for Multi-Factor Authentication (MFA) (e.g., SMS-based verification) during user login and account security settings.

4. **Rate Limiting**: Implement API rate limiting using Laravel's built-in middleware (e.g., throttle:60,1 for 60 requests per minute per user) to prevent misuse and protect against brute-force attacks.

5. **CORS Configuration**: Configure Laravel's CORS middleware to allow your API to be accessed from different origins (your frontend applications).

6. **General API Security**: 
   - Enforce HTTPS for all API communication
   - Implement best practices for data encryption at the application level for sensitive customer and payment information
   - Implement basic logging for security incidents
   - Ensure input sanitization to prevent SQL injection and XSS attacks

**Output**: Provide well-commented Laravel code demonstrating Sanctum setup, RBAC (Gates/Policies), MFA logic, rate limiting middleware, CORS configuration, and examples of data encryption and security logging.

---

## Sprint 6: Error Handling & API Experience

### Objective
Professionally handle errors.

### Tasks

1. **Exception Handling**: Implement Laravel's Exception Handling mechanism to return consistent, structured, and understandable JSON error responses for all API errors (e.g., validation failures, authentication errors, not found resources, server errors).

2. **Error Message Security**: Ensure error messages are informative but do not expose sensitive internal details.

**Output**: Provide well-commented Laravel code for custom exception handling, demonstrating how to return standardized JSON error responses for various scenarios.

---

## Sprint 7: API Testing

### Objective
Write Unit & Feature Tests to ensure the API works correctly and reliably.

### Tasks

1. **Unit Tests**: Write Unit Tests for models, services, and complex business logic components using PHPUnit.

2. **Feature Tests**: Write Feature Tests for all API endpoints to ensure they behave as expected, covering various scenarios including successful requests, validation failures, authentication/authorization failures, and edge cases. This is a continuous and iterative step throughout development.

**Output**: Provide well-commented Laravel code for example Unit and Feature Tests for a few key API endpoints and business logic components.

---

## Sprint 8: API Versioning

### Objective
Implement a comprehensive URL-based API versioning strategy with 6-12 month backward compatibility, clear migration paths, and robust monitoring.

### Implementation Plan

#### Phase 1: Core Versioning Infrastructure (Week 1-2)

1. **Route Restructuring**
   - Wrap all existing API routes in version groups (`/api/v1/*`)
   - Implement version detection middleware
   - Create version fallback mechanism (default to latest stable)

2. **Version Management System**
   - Create `ApiVersion` model and migration
   - Implement version lifecycle management (active, deprecated, sunset)
   - Add version metadata (release date, sunset date, migration guide URL)

3. **Middleware Stack**
   - `ApiVersionMiddleware`: Route versioning and validation
   - `VersionDeprecationMiddleware`: Add deprecation headers
   - `VersionAnalyticsMiddleware`: Track usage patterns

#### Phase 2: Backward Compatibility & Migration (Week 3-4)

1. **Critical Endpoint Protection**
   - **Always maintain longer support (12+ months):**
     - Authentication/Authorization (`/auth/*`)
     - Core business operations (`/orders/*`, `/payments/*`, `/customers/*`)
     - High-volume endpoints (`/restaurants/*`, `/menu-items/*`)
     - Public-facing integrations (`/pos/*`, `/webhooks/*`)
   
   - **Can deprecate faster (6 months):**
     - Analytics/reporting (`/analytics/*`, `/reports/*`)
     - Administrative functions (`/admin/*`, `/staff/*`)
     - Experimental features (`/challenges/*`, `/spin-wheel/*`)
     - Low-usage endpoints

2. **Migration Paths**
   - Create comprehensive migration guides for each version
   - Implement automatic redirects for deprecated endpoints
   - Add migration helper endpoints (`/api/migration/check`, `/api/migration/guide`)

#### Phase 3: Communication & Monitoring (Week 5-6)

1. **Deprecation Warnings System**
   - **API Response Headers:**
     ```
     Sunset: Sat, 31 Dec 2025 23:59:59 GMT
     Deprecation: true
     Link: <https://api.foodhub.com/v2/restaurants>; rel="successor-version"
     ```
   
   - **Multi-channel notifications:**
     - Email notifications (30-60 days advance notice)
     - Dashboard notifications for registered developers
     - Documentation updates with migration guides

2. **Version Analytics & Monitoring**
   - Track version usage by restaurant
   - Monitor error rates by version
   - Track migration progress
   - Version-specific rate limiting (same limits initially, different for maintenance versions)

#### Phase 4: Testing & Documentation (Week 7-8)

1. **Compatibility Testing**
   - **Critical workflow tests:**
     - Order processing flow
     - Staff authentication flow
     - Customer loyalty points flow
   
   - **Integration testing:**
     - Restaurant POS systems
     - Mobile apps (iOS/Android)
     - Third-party delivery integrations
     - Staff management dashboards

2. **Documentation Strategy**
   - Single documentation with version badges/notes
   - Migration guides for each breaking change
   - Version comparison tables
   - Code examples for each version

### Technical Implementation Details

#### Route Structure
```php
// routes/api.php
Route::prefix('v1')->group(function () {
    // All existing routes wrapped here
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::get('/restaurants', [RestaurantController::class, 'index']);
    // ... etc
});

Route::prefix('v2')->group(function () {
    // Future breaking changes go here
    Route::get('/restaurants', [RestaurantController::class, 'indexV2']);
});
```

#### Version Management
```php
// app/Models/ApiVersion.php
class ApiVersion extends Model
{
    protected $fillable = [
        'version', 'status', 'release_date', 'sunset_date',
        'migration_guide_url', 'breaking_changes', 'is_default'
    ];
    
    const STATUS_ACTIVE = 'active';
    const STATUS_DEPRECATED = 'deprecated';
    const STATUS_SUNSET = 'sunset';
}
```

#### Deprecation Headers
```php
// app/Http/Middleware/VersionDeprecationMiddleware.php
public function handle($request, Closure $next)
{
    $response = $next($request);
    
    if ($this->isDeprecated($request->version)) {
        $response->headers->set('Sunset', $this->getSunsetDate($request->version));
        $response->headers->set('Deprecation', 'true');
        $response->headers->set('Link', $this->getSuccessorVersionLink($request->version));
    }
    
    return $response;
}
```

### Timeline & Communication Strategy

#### 90+ Days Notice
- Payment/order critical endpoints
- Authentication systems
- Core business operations

#### 60 Days Notice
- Major feature changes
- High-impact integrations
- Public API changes

#### 30 Days Notice
- Minor deprecations
- Administrative functions
- Experimental features

### Success Metrics

1. **Migration Success Rate**
   - 95%+ of restaurants migrated within 6 months
   - Zero critical endpoint failures during transition

2. **Version Adoption**
   - Track new version adoption rates
   - Monitor deprecation warning effectiveness

3. **Client Satisfaction**
   - Reduced support tickets during migration
   - Positive feedback on migration guides

### Risk Mitigation

1. **Rollback Plan**
   - Maintain v1 endpoints for 12 months minimum
   - Quick rollback capability for critical issues

2. **Client Communication**
   - Proactive outreach to major clients
   - Dedicated migration support team

3. **Testing Strategy**
   - Comprehensive integration testing
   - Staging environment for client testing

**Output**: Complete API versioning system with URL-based routing, 6-12 month backward compatibility, comprehensive monitoring, and clear migration paths for all endpoints.

---

## Sprint 9: Performance Optimization

### Objective
Use Caching for frequently accessed data. Optimize database queries. Use Queues for long-running tasks.

### Tasks

1. **Redis Integration**: Integrate Redis as the primary cache driver for Laravel. Implement caching strategies for frequently accessed data (e.g., menu items, restaurant settings, loyalty program rules) to improve API response times.

2. **Query Optimization**: Optimize database queries using Eloquent eager loading (with()) to avoid the N+1 problem. Analyze and optimize complex queries for performance.

3. **Queue Configuration  including POS sync jobs**: 
   - Configure Redis as the queue driver for background job processing
   - Set up and configure Laravel Horizon for monitoring and managing background jobs
   - Implement Queues for long-running tasks (e.g., sending notifications, syncing with POS systems, generating complex reports, processing loyalty points asynchronously)
   - OrderSyncToPOSJob
   - MenuSyncFromPOSJob  
   - InventoryUpdateFromPOSJob
   - PaymentSyncToPOSJob
   - OrderStatusSyncFromPOSJob


Week 1: Redis Setup + Basic Caching
Week 2: Query Optimization + N+1 Fixes  
Week 3: Queue Infrastructure + Horizon
Week 4: POS Sync Jobs Implementation
💡 Pro Tips for Success:

Start with OrderSyncToPOSJob - it's the most critical for restaurant operations
Add retry logic with exponential backoff for POS sync failures
Implement webhook endpoints to receive POS status updates
Cache POS connection status to avoid repeated failed attempts
**Output**: Provide well-commented Laravel code demonstrating caching implementation, examples of optimized Eloquent queries, and a queued job with Horizon configuration.

---

## Sprint 10: API Documentation & Integrations

### Objective
Create and maintain clear and comprehensive API documentation. Implement external integrations.

### Tasks

1. **API Documentation**: Create and maintain clear and comprehensive API documentation using tools like Swagger/OpenAPI or Postman. This is essential for developers who will be using your API.

2. **POS Integrations**: 
   - Develop a flexible service structure (POSSyncService) to integrate with external POS systems
   - Implement specific integration logic for Square POS (syncing orders, updating status, handling webhooks for order/inventory updates)
   - Outline the structure for Toast POS integration, demonstrating how it would fit into the generic service

3. **Payment Gateway Integrations**: 
   - Develop a unified PaymentService to handle various payment gateways
   - Implement specific integration logic for MADA Payment Gateway (initiating, capturing, refunding payments, checking status, handling webhooks)
   - Outline how other payment methods like Apple Pay and Google Pay would integrate (focus on backend token processing)

4. **Google Maps Integration**: Implement Laravel service methods to interact with Google Maps APIs for:
   - Geocoding/Reverse Geocoding
   - Address Validation
   - Distance Matrix Calculation
   - Interface to send delivery stops to the Python service and receive optimized routes

5. **AI/ML Service Integration**: Implement API endpoints that Laravel will use to call the Python AI/ML service for advanced analytics, recommendations, churn prediction, and demand forecasting. Laravel will act as a client to these Python services.

**Output**: Provide well-commented Laravel code for integration services (POS, Payment, Google Maps client, AI/ML client) and a basic structure for API documentation generation.

---

## Output Requirements (General for all code sprints)

1. Provide clear, well-commented code for each line, explaining its purpose and functionality.
2. Ensure all code adheres to Laravel best practices and coding standards.
3. Include basic error handling and validation for all API endpoints.
4. Do not include any frontend code or UI elements.
5. Focus solely on the Laravel backend API.

## Development Notes

- **Sequential Processing**: Each sprint must be completed and approved before moving to the next
- **Target Stack**: Laravel 10, PHP 8.2+, PostgreSQL, Redis
- **Architecture**: RESTful API following Laravel conventions
- **Authentication**: Laravel Sanctum for API token management
- **Testing**: Comprehensive unit and feature testing
- **Performance**: Optimized for scalability and high performance
- **Security**: Enterprise-level security implementation
- **Integration Ready**: Prepared for external service integrations 