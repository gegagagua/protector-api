<?php

use App\Http\Controllers\Client\AuthController as ClientAuthController;
use App\Http\Controllers\Client\ProfileController as ClientProfileController;
use App\Http\Controllers\Client\BookingController as ClientBookingController;
use App\Http\Controllers\Client\ChatController as ClientChatController;
use App\Http\Controllers\Client\PaymentController as ClientPaymentController;
use App\Http\Controllers\Client\PaymentMethodController as ClientPaymentMethodController;
use App\Http\Controllers\Client\TrackingController as ClientTrackingController;
use App\Http\Controllers\PaymentWebhookController;
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
use App\Http\Controllers\Admin\ReportController as AdminReportController;
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
    Route::post('/signup/send-otp', [ClientAuthController::class, 'sendSignupOtp'])->middleware('throttle:5,1');
    Route::post('/signin/send-otp', [ClientAuthController::class, 'sendSigninOtp'])->middleware('throttle:5,1');
    Route::post('/signup', [ClientAuthController::class, 'signup'])->middleware('throttle:10,1');
    Route::post('/signin', [ClientAuthController::class, 'signin'])->middleware('throttle:10,1');

    // Public booking read endpoints
    Route::get('/services', [ClientBookingController::class, 'getServices']);
    Route::get('/vehicles', [ClientBookingController::class, 'getVehicles']);
    Route::get('/wizard-config', [ClientBookingController::class, 'getWizardConfig']);
    
    // Protected routes
    Route::middleware(['auth:sanctum', 'actor:client,client'])->group(function () {
        Route::post('/logout', [ClientAuthController::class, 'logout']);
        Route::post('/change-password', [ClientAuthController::class, 'changePassword']);
        
        // Profile
        Route::get('/me', [ClientProfileController::class, 'me']);
        Route::get('/profile', [ClientProfileController::class, 'show']);
        Route::put('/profile', [ClientProfileController::class, 'update']);
        Route::post('/profile/avatar', [ClientProfileController::class, 'uploadAvatar']);
        Route::post('/verification/upload', [ClientProfileController::class, 'uploadVerification']);
        Route::get('/verification/status', [ClientProfileController::class, 'verificationStatus']);
        Route::put('/notification-preferences', [ClientProfileController::class, 'updateNotificationPreferences']);
        
        // Bookings
        Route::post('/bookings/quote', [ClientBookingController::class, 'quote']);
        Route::get('/bookings', [ClientBookingController::class, 'index']);
        Route::get('/bookings/active', [ClientBookingController::class, 'active']);
        Route::get('/bookings/history', [ClientBookingController::class, 'history']);
        Route::post('/bookings', [ClientBookingController::class, 'store']);
        Route::get('/bookings/{id}', [ClientBookingController::class, 'show']);
        Route::post('/bookings/{id}/cancel', [ClientBookingController::class, 'cancel']);
        
        // Tracking
        Route::get('/bookings/{id}/tracking', [ClientTrackingController::class, 'getTracking']);
        Route::get('/bookings/{id}/messages', [ClientChatController::class, 'getMessages']);
        Route::post('/bookings/{id}/messages', [ClientChatController::class, 'sendMessage']);
        Route::post('/bookings/{bookingId}/messages/{messageId}/read', [ClientChatController::class, 'markRead']);
        Route::post('/bookings/{id}/sos', [ClientChatController::class, 'triggerSos']);

        // Payment methods
        Route::get('/payment-methods', [ClientPaymentMethodController::class, 'index']);
        Route::post('/payment-methods', [ClientPaymentMethodController::class, 'store']);
        Route::post('/payment-methods/{id}/set-default', [ClientPaymentMethodController::class, 'setDefault']);
        Route::delete('/payment-methods/{id}', [ClientPaymentMethodController::class, 'destroy']);

        // Payments
        Route::get('/payments', [ClientPaymentController::class, 'index']);
        Route::post('/bookings/{id}/pay', [ClientPaymentController::class, 'payBooking']);
    });
});

// Security Personnel Routes
Route::prefix('security')->group(function () {
    // Authentication
    Route::post('/login', [SecurityAuthController::class, 'login']);
    
    // Protected routes
    Route::middleware(['auth:sanctum', 'actor:security,security'])->group(function () {
        Route::post('/logout', [SecurityAuthController::class, 'logout']);
        Route::post('/change-password', [SecurityAuthController::class, 'changePassword']);
        
        // Orders
        Route::get('/orders', [SecurityOrderController::class, 'index']);
        Route::get('/orders/history', [SecurityOrderController::class, 'history']);
        Route::get('/orders/{id}', [SecurityOrderController::class, 'show']);
        
        // Status updates
        Route::post('/orders/{id}/en-route', [SecurityStatusController::class, 'enRoute']);
        Route::post('/orders/{id}/arrived', [SecurityStatusController::class, 'arrived']);
        Route::post('/orders/{id}/complete', [SecurityStatusController::class, 'complete']);
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
    Route::get('/vehicles', [AdminVehicleController::class, 'index']);
    
    // Protected routes
    Route::middleware(['auth:sanctum', 'actor:admin,admin'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::post('/change-password', [AdminAuthController::class, 'changePassword']);
        
        // Dashboard
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);
        Route::get('/reports/summary', [AdminReportController::class, 'summary']);
        
        // Bookings
        Route::get('/bookings', [AdminBookingController::class, 'index']);
        Route::get('/bookings/{id}', [AdminBookingController::class, 'show']);
        Route::post('/bookings/{id}/assign-team', [AdminBookingController::class, 'assignTeam']);
        Route::post('/bookings/{id}/complete', [AdminBookingController::class, 'complete']);
        Route::put('/bookings/{id}', [AdminBookingController::class, 'update']);
        
        // Teams
        Route::get('/teams', [AdminTeamController::class, 'index']);
        Route::post('/teams', [AdminTeamController::class, 'store']);
        
        // Clients
        Route::get('/clients', [AdminClientController::class, 'index']);
        Route::get('/clients/{id}', [AdminClientController::class, 'show']);
        Route::post('/clients/{id}/verify', [AdminClientController::class, 'updateVerification']);
        
        // Vehicles
        Route::post('/vehicles', [AdminVehicleController::class, 'store']);
        
        // Monitoring
        Route::get('/monitoring/live', [AdminMonitoringController::class, 'liveTracking']);
        
        // Payments
        Route::get('/payments', [AdminPaymentController::class, 'index']);
    });
});

Route::post('/payments/webhook', [PaymentWebhookController::class, 'handle']);
