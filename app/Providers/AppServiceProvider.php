<?php

namespace App\Providers;

use App\Contracts\Sms\SmsProviderInterface;
use App\Models\Product;
use App\Models\Review;
use App\Observers\ProductObserver;
use App\Observers\ReviewObserver;
use App\Services\KairosAfrikaSmsService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Product::observe(ProductObserver::class);
        Review::observe(ReviewObserver::class);
        \App\Models\WawVideoLike::observe(\App\Observers\WawVideoLikeObserver::class);
        \App\Models\ReviewReply::observe(\App\Observers\ReviewReplyObserver::class);
        \App\Models\Order::observe(\App\Observers\OrderObserver::class);
        \App\Models\Message::observe(\App\Observers\MessageObserver::class);
        \App\Models\VendorApplication::observe(\App\Observers\VendorApplicationObserver::class);
        \App\Models\Category::observe(\App\Observers\CategoryObserver::class);
        \App\Models\Shop::observe(\App\Observers\ShopObserver::class);
        \App\Models\Advertisement::observe(\App\Observers\AdvertisementObserver::class);
        \App\Models\SpecialOffer::observe(\App\Observers\SpecialOfferObserver::class);

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
                ->subject('Welcome to Surprise Moi! Verify Your Email')
                ->greeting("Hello {$notifiable->name},")
                ->line('Your Surprise Moi account has been created successfully. Let the surprises begin!')
                ->line('Please click the button below to verify your email address and get started.')
                ->action('Verify Email Address', $apiUrl)
                ->line('If you did not create an account, no further action is required.')
                ->salutation('Best regards, The Surprise Moi Team');
        });
    }
}
