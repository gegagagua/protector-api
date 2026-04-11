<?php

namespace App\Providers;

use App\Events\LocationUpdated;
use App\Events\MessageSent;
use App\Listeners\ForwardLocationToSocketIo;
use App\Listeners\ForwardMessageToSocketIo;
use App\Services\Otp\LogOtpSender;
use App\Services\Otp\OtpSender;
use App\Services\Payments\MockPaymentGateway;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OtpSender::class, function () {
            return new LogOtpSender;
        });

        $this->app->singleton(PaymentGateway::class, function () {
            return new MockPaymentGateway;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(MessageSent::class, ForwardMessageToSocketIo::class);
        Event::listen(LocationUpdated::class, ForwardLocationToSocketIo::class);
    }
}
