# FoodHub Setup Guide for Tomorrow

## Prerequisites
1. **Install Docker Desktop** from https://www.docker.com/products/docker-desktop/
2. Make sure Docker is running

## Step 1: Start PostgreSQL and Redis with Docker
```bash
# Start the database services
docker-compose up -d

# Verify services are running
docker-compose ps
```

## Step 2: Update .env file
Manually edit your `.env` file and change:
```
DB_PASSWORD=postgres123
```

## Step 3: Run Laravel Migrations
```bash
# Run database migrations
php artisan migrate

# Check migration status
php artisan migrate:status
```

## Step 4: Create Fake Data
```bash
# Run seeders to create fake data
php artisan db:seed

# Or run specific seeders if they exist
php artisan db:seed --class=UserSeeder
php artisan db:seed --class=RestaurantSeeder
```

## Step 5: Run Authentication Tests
```bash
# Run all tests
php artisan test

# Run only authentication tests
php artisan test --filter=Auth

# Run specific test file
php artisan test tests/Feature/Auth/
```

## Step 6: Start Development Server
```bash
# Start Laravel development server
php artisan serve
```

## Useful Commands
```bash
# Check database connection
php artisan tinker
>>> DB::connection()->getPdo();

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# View routes
php artisan route:list

# Check application status
php artisan about
```

## Troubleshooting
- If Docker containers fail to start, check if ports 5432 and 6379 are available
- If database connection fails, ensure Docker containers are running
- If tests fail, check if migrations and seeders ran successfully 