<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    private const ONLINE_WINDOW_MINUTES = 5;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'avatar',
        'banner',
        'provider',
        'provider_id',
        'role',
        'vendor_tier',
        'vendor_hash',
        'date_of_birth',
        'gender',
        'bio',
        'favorite_color',
        'favorite_music_genre',
        'is_popular',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'date_of_birth' => 'date',
            'is_popular' => 'boolean',
        ];
    }

    /**
     * Get all products created by this vendor.
     * Only applicable for users with 'vendor' role.
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'vendor_id');
    }

    /**
     * Get all services created by this vendor.
     * Only applicable for users with 'vendor' role.
     */
    public function services()
    {
        return $this->hasMany(Service::class, 'vendor_id');
    }

    /**
     * Get all shops owned by this vendor.
     * A vendor can have multiple shops.
     */
    public function shops()
    {
        return $this->hasMany(Shop::class, 'vendor_id');
    }

    /**
     * Get all orders placed by this user.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get all wishlist items for this user.
     */
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Get the user's music genre preferences.
     */
    public function musicGenres(): BelongsToMany
    {
        return $this->belongsToMany(MusicGenre::class)->withTimestamps();
    }

    /**
     * Get all reviews written by this user.
     * Users can review products and services they've purchased.
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get all delivery addresses for this user.
     * Users can have multiple saved addresses.
     */
    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Get the user's interests.
     */
    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class)->withTimestamps();
    }

    /**
     * Get the user's personality traits.
     */
    public function personalityTraits(): BelongsToMany
    {
        return $this->belongsToMany(PersonalityTrait::class)->withTimestamps();
    }

    /**
     * Get all vendor applications submitted by this user.
     * A user can submit multiple applications if previous ones were rejected.
     */
    public function vendorApplications()
    {
        return $this->hasMany(VendorApplication::class);
    }

    /**
     * Get the latest vendor application for the user.
     * Useful for checking current application status.
     */
    public function latestVendorApplication()
    {
        return $this->hasOne(VendorApplication::class)->latestOfMany();
    }

    /**
     * Check if user has an approved vendor application.
     * Returns true if at least one application has been approved.
     */
    public function hasApprovedVendorApplication(): bool
    {
        return $this->vendorApplications()
            ->where('status', VendorApplication::STATUS_APPROVED)
            ->exists();
    }

    /**
     * Get the vendor's balance.
     */
    public function vendorBalance()
    {
        return $this->hasOne(VendorBalance::class, 'vendor_id');
    }

    /**
     * Get the vendor's transactions.
     */
    public function vendorTransactions()
    {
        return $this->hasMany(VendorTransaction::class, 'vendor_id');
    }

    /**
     * Get all payout details for this vendor.
     */
    public function payoutDetails(): HasMany
    {
        return $this->hasMany(VendorPayoutDetail::class, 'vendor_id');
    }

    /**
     * Get the default payout detail for this vendor.
     */
    public function defaultPayoutDetail(): HasOne
    {
        return $this->hasOne(VendorPayoutDetail::class, 'vendor_id')->where('is_default', true);
    }

    /**
     * Get platform commission rate for this vendor based on their tier.
     * Tier 1 (registered business) and Tier 2 (individual vendor) have different rates.
     */
    public function getCommissionRate(): float
    {
        if ($this->role !== 'vendor' || ! $this->vendor_tier) {
            return 0;
        }

        $key = $this->vendor_tier === 1 ? 'vendor_tier1_commission_rate' : 'vendor_tier2_commission_rate';

        return (float) Setting::get($key, $this->vendor_tier === 1 ? 12.00 : 8.00);
    }

    /**
     * Get all conversations where the user is a customer.
     */
    public function customerConversations()
    {
        return $this->hasMany(Conversation::class, 'customer_id');
    }

    /**
     * Get all conversations where the user is a vendor.
     */
    public function vendorConversations()
    {
        return $this->hasMany(Conversation::class, 'vendor_id');
    }

    /**
     * Get all conversations for this user (both as customer and vendor).
     * Uses a scope to find conversations where user is either customer_id or vendor_id.
     */
    public function conversations()
    {
        return Conversation::forUser($this);
    }

    /**
     * Get all messages sent by this user.
     * Includes messages in both customer and vendor conversations.
     */
    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Determine if the user is online based on recent auth activity.
     */
    public function isOnline(): bool
    {
        $cutoff = now()->subMinutes(self::ONLINE_WINDOW_MINUTES);

        $hasRecentTokenActivity = $this->tokens()
            ->whereNotNull('last_used_at')
            ->where('last_used_at', '>=', $cutoff)
            ->exists();

        if ($hasRecentTokenActivity) {
            return true;
        }

        return DB::table('sessions')
            ->where('user_id', $this->id)
            ->where('last_activity', '>=', $cutoff->timestamp)
            ->exists();
    }

    /**
     * Check if user is a vendor.
     */
    public function isVendor(): bool
    {
        return $this->role === 'vendor';
    }

    /**
     * Scope a query to only include popular vendors.
     */
    public function scopePopular($query)
    {
        return $query->where('is_popular', true);
    }

    /**
     * Check if user is a customer.
     */
    public function isCustomer(): bool
    {
        return $this->role === 'customer' || $this->role === null;
    }

    /**
     * Check if user is an admin (includes super_admin).
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    /**
     * Check if user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Check if user is an influencer.
     */
    public function isInfluencer(): bool
    {
        return $this->role === 'influencer';
    }

    /**
     * Check if user is a field agent.
     */
    public function isFieldAgent(): bool
    {
        return $this->role === 'field_agent';
    }

    /**
     * Check if user is a marketer.
     */
    public function isMarketer(): bool
    {
        return $this->role === 'marketer';
    }

    /**
     * Check if user can access the dashboard.
     */
    public function canAccessDashboard(): bool
    {
        return in_array($this->role, ['admin', 'super_admin', 'influencer', 'field_agent', 'marketer']);
    }

    /**
     * Get all referral codes owned by this influencer.
     * Influencers can create multiple codes for different campaigns.
     */
    public function referralCodes()
    {
        return $this->hasMany(ReferralCode::class, 'influencer_id');
    }

    /**
     * Get all referrals made by this influencer.
     * A referral is created when a vendor signs up using an influencer's code.
     */
    public function referrals()
    {
        return $this->hasMany(Referral::class, 'influencer_id');
    }

    /**
     * Get all active referrals for this influencer.
     * Active referrals are within their commission period.
     */
    public function activeReferrals()
    {
        return $this->referrals()->active();
    }

    /**
     * Get all targets assigned to this user.
     * Targets are assigned to field agents and marketers by admins.
     */
    public function targets()
    {
        return $this->hasMany(Target::class);
    }

    /**
     * Get all active targets for this user.
     * Active targets are within their start and end dates.
     */
    public function activeTargets()
    {
        return $this->targets()->active();
    }

    /**
     * Get all target achievements for this user.
     * Achievements are recorded when targets are met or exceeded.
     */
    public function targetAchievements()
    {
        return $this->hasMany(TargetAchievement::class);
    }

    /**
     * Get all earnings for this user.
     * Includes earnings from referral commissions and target achievements.
     */
    public function earnings()
    {
        return $this->hasMany(Earning::class);
    }

    /**
     * Get unpaid earnings for this user.
     * These are earnings that haven't been paid out yet.
     */
    public function unpaidEarnings()
    {
        return $this->earnings()->unpaid();
    }

    /**
     * Get all payout requests for this user.
     * Users request payouts when they want to withdraw their earnings.
     */
    public function payoutRequests()
    {
        return $this->hasMany(PayoutRequest::class);
    }

    /**
     * Get total unpaid earnings amount.
     * Sums all earnings that have status 'unpaid'.
     *
     * @return float Total amount in GHS
     */
    public function getTotalUnpaidEarnings(): float
    {
        return (float) $this->unpaidEarnings()->sum('amount');
    }

    /**
     * Get total paid earnings amount.
     * Sums all earnings that have been successfully paid out.
     *
     * @return float Total amount in GHS
     */
    public function getTotalPaidEarnings(): float
    {
        return (float) $this->earnings()->where('status', Earning::STATUS_PAID)->sum('amount');
    }

    /**
     * Get total unread messages count across all conversations.
     * Combines unread counts from both customer and vendor conversations.
     *
     * @return int Total number of unread messages
     */
    public function getUnreadMessagesCount(): int
    {
        $customerUnread = $this->customerConversations()->sum('customer_unread_count');
        $vendorUnread = $this->vendorConversations()->sum('vendor_unread_count');

        return $customerUnread + $vendorUnread;
    }

    /**
     * Get the email verification URL.
     */
    public function getEmailVerificationUrl(): string
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'api.verification.verify',
            \Illuminate\Support\Carbon::now()->addMinutes(60),
            ['id' => $this->id, 'hash' => sha1($this->email)]
        );
    }

    /**
     * Get all partner profiles created by this user.
     */
    public function partnerProfiles(): HasMany
    {
        return $this->hasMany(PartnerProfile::class);
    }

    /**
     * Get all AI conversations for this user.
     */
    public function aiConversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }

    /**
     * Get all notifications for this user.
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get unread notifications for this user.
     */
    public function unreadNotifications()
    {
        return $this->notifications()->unread();
    }

    /**
     * Get unread notifications count.
     */
    public function getUnreadNotificationsCount(): int
    {
        return $this->unreadNotifications()->count();
    }
}
