<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// API Documentation Routes
Route::prefix('api/docs')->name('api.docs.')->group(function () {
    Route::get('/', function () {
        return view('api.docs.index');
    })->name('index');
    
    Route::get('/migration', function () {
        return view('api.docs.migration');
    })->name('migration');
    
    Route::get('/changelog', function () {
        return view('api.docs.changelog');
    })->name('changelog');
    
    Route::get('/examples', function () {
        return view('api.docs.examples');
    })->name('examples');
});

require __DIR__.'/auth.php';
