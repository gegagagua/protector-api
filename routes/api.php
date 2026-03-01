<?php

use App\Http\Controllers\Client\AuthController as ClientAuthController;
use App\Http\Controllers\Client\ProfileController as ClientProfileController;
use App\Http\Controllers\Client\BookingController as ClientBookingController;
use App\Http\Controllers\Client\TrackingController as ClientTrackingController;
use App\Http\Controllers\SecurityPersonnel\AuthController as SecurityAuthController;
use App\Http\Controllers\SecurityPersonnel\OrderController as SecurityOrderController;
use App\Http\Controllers\SecurityPersonnel\StatusController as SecurityStatusController;
use App\Http\Controllers\SecurityPersonnel\ChatController as SecurityChatController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\TeamController as AdminTeamController;
use App\Http\Controllers\Admin\ClientController as AdminClientController;
use App\Http\Controllers\Admin\VehicleController as AdminVehicleController;
use App\Http\Controllers\Admin\MonitoringController as AdminMonitoringController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
|
*/

// Client Routes
Route::prefix('client')->group(function () {
    // Authentication
    Route::post('/send-otp', [ClientAuthController::class, 'sendOtp']);
    Route::post('/verify-otp', [ClientAuthController::class, 'verifyOtp']);
    
    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [ClientAuthController::class, 'logout']);
        
        // Profile
        Route::get('/profile', [ClientProfileController::class, 'show']);
        Route::put('/profile', [ClientProfileController::class, 'update']);
        Route::post('/verification/upload', [ClientProfileController::class, 'uploadVerification']);
        
        // Bookings
        Route::get('/services', [ClientBookingController::class, 'getServices']);
        Route::get('/vehicles', [ClientBookingController::class, 'getVehicles']);
        Route::get('/bookings', [ClientBookingController::class, 'index']);
        Route::post('/bookings', [ClientBookingController::class, 'store']);
        Route::get('/bookings/{id}', [ClientBookingController::class, 'show']);
        Route::post('/bookings/{id}/cancel', [ClientBookingController::class, 'cancel']);
        
        // Tracking
        Route::get('/bookings/{id}/tracking', [ClientTrackingController::class, 'getTracking']);
    });
});

// Security Personnel Routes
Route::prefix('security')->group(function () {
    // Authentication
    Route::post('/login', [SecurityAuthController::class, 'login']);
    
    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [SecurityAuthController::class, 'logout']);
        
        // Orders
        Route::get('/orders', [SecurityOrderController::class, 'index']);
        Route::get('/orders/{id}', [SecurityOrderController::class, 'show']);
        
        // Status updates
        Route::post('/orders/{id}/en-route', [SecurityStatusController::class, 'enRoute']);
        Route::post('/orders/{id}/arrived', [SecurityStatusController::class, 'arrived']);
        Route::post('/location/update', [SecurityStatusController::class, 'updateLocation']);
        
        // Chat
        Route::get('/orders/{id}/messages', [SecurityChatController::class, 'getMessages']);
        Route::post('/orders/{id}/messages', [SecurityChatController::class, 'sendMessage']);
    });
});

// Admin Routes
Route::prefix('admin')->group(function () {
    // Authentication
    Route::post('/login', [AdminAuthController::class, 'login']);
    
    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        
        // Dashboard
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);
        
        // Bookings
        Route::get('/bookings', [AdminBookingController::class, 'index']);
        Route::get('/bookings/{id}', [AdminBookingController::class, 'show']);
        Route::post('/bookings/{id}/assign-team', [AdminBookingController::class, 'assignTeam']);
        Route::put('/bookings/{id}', [AdminBookingController::class, 'update']);
        
        // Teams
        Route::get('/teams', [AdminTeamController::class, 'index']);
        Route::post('/teams', [AdminTeamController::class, 'store']);
        
        // Clients
        Route::get('/clients', [AdminClientController::class, 'index']);
        Route::get('/clients/{id}', [AdminClientController::class, 'show']);
        Route::post('/clients/{id}/verify', [AdminClientController::class, 'updateVerification']);
        
        // Vehicles
        Route::get('/vehicles', [AdminVehicleController::class, 'index']);
        Route::post('/vehicles', [AdminVehicleController::class, 'store']);
        
        // Monitoring
        Route::get('/monitoring/live', [AdminMonitoringController::class, 'liveTracking']);
        
        // Payments
        Route::get('/payments', [AdminPaymentController::class, 'index']);
    });
});
