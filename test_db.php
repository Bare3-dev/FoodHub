<?php

require_once 'vendor/autoload.php';

// Set environment variables
putenv('DB_CONNECTION=pgsql');
putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=5432');
putenv('DB_DATABASE=foodhub');
putenv('DB_USERNAME=postgres');
putenv('DB_PASSWORD=12345');

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Testing database connection...\n";
    DB::connection()->getPdo();
    echo "Database connection successful!\n";
    
    echo "Running migrations...\n";
    Artisan::call('migrate:fresh', ['--force' => true]);
    echo "Migrations completed!\n";
    
    echo "Running seeders...\n";
    Artisan::call('db:seed', ['--force' => true]);
    echo "Seeding completed!\n";
    
    echo "All done successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 