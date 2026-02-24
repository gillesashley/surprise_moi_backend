<?php

namespace App\Providers;

use App\Contracts\Sms\SmsProviderInterface;
use App\Services\KairosAfrikaSmsService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind SMS provider interface to implementation
        $this->app->bind(SmsProviderInterface::class, KairosAfrikaSmsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure morph map for polymorphic relationships
        Relation::morphMap([
            'product' => \App\Models\Product::class,
            'service' => \App\Models\Service::class,
        ]);

        // Allow public access to API documentation
        Gate::define('viewApiDocs', function () {
            return true;
        });

        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            // Generate the API verification URL
            $apiUrl = $notifiable->getEmailVerificationUrl();

            return (new MailMessage)
                ->subject('Verify Your Email Address - Surprise Moi')
                ->greeting('Hello '.$notifiable->name.'!')
                ->line('Thank you for creating an account with Surprise Moi.')
                ->line('Please click the button below to verify your email address.')
                ->action('Verify Email Address', $apiUrl)
                ->line('If you did not create an account, no further action is required.')
                ->salutation('Best regards, The Surprise Moi Team');
        });
    }
}
