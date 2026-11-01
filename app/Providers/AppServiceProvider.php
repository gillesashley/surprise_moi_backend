<?php

namespace App\Providers;

use App\Contracts\Sms\SmsProviderInterface;
use App\Models\Advertisement;
use App\Models\Category;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\Service;
use App\Models\Shop;
use App\Models\SpecialOffer;
use App\Models\User;
use App\Models\UserPayoutDetail;
use App\Models\VendorApplication;
use App\Models\WawVideoLike;
use App\Observers\AdvertisementObserver;
use App\Observers\CategoryObserver;
use App\Observers\FieldVerificationInvalidationObserver;
use App\Observers\MessageObserver;
use App\Observers\OrderObserver;
use App\Observers\ProductObserver;
use App\Observers\ReviewObserver;
use App\Observers\ReviewReplyObserver;
use App\Observers\ShopObserver;
use App\Observers\SpecialOfferObserver;
use App\Observers\VendorApplicationObserver;
use App\Observers\WawVideoLikeObserver;
use App\Policies\TeamMemberPolicy;
use App\Policies\UserPayoutDetailPolicy;
use App\Services\KairosAfrikaSmsService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
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
        // Log lazy loading violations to catch N+1 queries without breaking the app
        Model::preventLazyLoading();
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) {
            Log::warning('Lazy loading detected', [
                'model' => $model::class,
                'relation' => $relation,
                'id' => $model->getKey(),
            ]);
        });

        // Log slow queries (> 100ms) in production for monitoring
        if ($this->app->isProduction()) {
            DB::listen(function ($query) {
                if ($query->time > 100) {
                    Log::warning('Slow query detected', [
                        'sql' => $query->sql,
                        'time_ms' => $query->time,
                        'connection' => $query->connectionName,
                    ]);
                }
            });
        }

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Product::observe(ProductObserver::class);
        Review::observe(ReviewObserver::class);
        WawVideoLike::observe(WawVideoLikeObserver::class);
        ReviewReply::observe(ReviewReplyObserver::class);
        Order::observe(OrderObserver::class);
        Message::observe(MessageObserver::class);
        VendorApplication::observe(VendorApplicationObserver::class);

        User::updated(function (User $user) {
            app(FieldVerificationInvalidationObserver::class)->userUpdated($user);
        });
        VendorApplication::updated(function (VendorApplication $app) {
            app(FieldVerificationInvalidationObserver::class)->vendorApplicationUpdated($app);
        });
        Category::observe(CategoryObserver::class);
        Shop::observe(ShopObserver::class);
        Advertisement::observe(AdvertisementObserver::class);
        SpecialOffer::observe(SpecialOfferObserver::class);

        // Configure morph map for polymorphic relationships
        Relation::morphMap([
            'product' => Product::class,
            'service' => Service::class,
        ]);

        RateLimiter::for('rider-login', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email', '')));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('rider-otp-send', function (Request $request) {
            $phone = (string) $request->input('phone', '');

            return [
                Limit::perMinute(3)->by($phone !== '' ? $phone : $request->ip()),
                Limit::perMinute(10)->by($request->ip()),
            ];
        });

        RateLimiter::for('treasury-transfer', function (Request $request) {
            return Limit::perHour(5)->by($request->user()?->id ?: $request->ip());
        });

        // Allow public access to API documentation
        Gate::define('viewApiDocs', function () {
            return true;
        });

        Gate::define('manageTeam', function (User $user) {
            return $user->isFieldAgent()
                && (bool) $user->is_team_field_agent
                && $user->parent_user_id === null;
        });

        Gate::policy(UserPayoutDetail::class, UserPayoutDetailPolicy::class);
        Gate::policy(User::class, TeamMemberPolicy::class);

        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            // Generate the API verification URL
            $apiUrl = $notifiable->getEmailVerificationUrl();

            return (new MailMessage)
                ->subject('Welcome to Surprise moi! Verify Your Email')
                ->greeting("Hello {$notifiable->name},")
                ->line('Your Surprise moi account has been created successfully. Let the surprises begin!')
                ->line('Please click the button below to verify your email address and get started.')
                ->action('Verify Email Address', $apiUrl)
                ->line('If you did not create an account, no further action is required.')
                ->salutation('Best regards, The Surprise moi Team');
        });
    }
}
