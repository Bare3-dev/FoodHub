# FoodHub Setup Guide (Without Docker)

## Option A: Install PostgreSQL Locally

### Step 1: Install PostgreSQL
1. Download PostgreSQL from: https://www.postgresql.org/download/windows/
2. Install with default settings
3. Remember the password you set for the `postgres` user

### Step 2: Create Database
```bash
# Connect to PostgreSQL
psql -U postgres

# Create the database
CREATE DATABASE foodhub_api;

# Exit PostgreSQL
\q
```

### Step 3: Update .env file
Edit your `.env` file and update:
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=foodhub_api
DB_USERNAME=postgres
DB_PASSWORD=your_postgres_password_here
```

### Step 4: Install Redis (Optional)
For Redis functionality, you can:
1. Install Redis for Windows from: https://github.com/microsoftarchive/redis/releases
2. Or skip Redis and change in `.env`:
```
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

## Option B: Use SQLite (Simplest)
If you want the simplest setup:

### Step 1: Update .env file
Change your `.env` file to use SQLite:
```
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

### Step 2: Create SQLite database
```bash
# Create the database file
php artisan migrate:install
```

## Continue with Laravel Setup
After setting up the database, continue with:
```bash
# Run migrations
php artisan migrate

# Create fake data
php artisan db:seed

# Run tests
php artisan test

# Start server
php artisan serve
``` 