<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Str;

class OrderNumberService
{
    /**
     * Generate a new order number in the format: VND-WAPZ-HASH-YYMMDD-HHMMSS-SEQ
     * Example: VND-WAPZ-9SRH-260126-143025-01
     *
     * VND: Vendor prefix
     * WAPZ: Platform/onboarding source (fixed)
     * HASH: 4-character unique vendor hash
     * YYMMDD: Vendor onboarding date
     * HHMMSS: Current time for uniqueness
     * SEQ: Sequence number with random suffix
     */
    public function generate(Order $order): string
    {
        $vendor = $order->vendor;

        if (! $vendor) {
            // Fallback for orders without vendor
            return 'ORD-'.strtoupper(Str::random(10)).'-'.now()->format('ymdHis');
        }

        // Ensure vendor has a hash
        if (! $vendor->vendor_hash) {
            $vendor->vendor_hash = $this->generateVendorHash();
            $vendor->save();
        }

        // Get vendor onboarding date (use created_at or vendor_application approved_at)
        $onboardingDate = $this->getVendorOnboardingDate($vendor);

        // Get current timestamp for uniqueness
        $timestamp = now()->format('His'); // HHMMSS

        // Generate sequence number with random suffix for extra uniqueness
        $sequence = $this->getNextSequenceNumber($vendor, $onboardingDate);
        $randomSuffix = strtoupper(Str::random(2)); // Add 2 random characters

        // Format: VND-WAPZ-HASH-YYMMDD-HHMMSS-SEQ
        return sprintf(
            'VND-WAPZ-%s-%s-%s-%02d%s',
            strtoupper($vendor->vendor_hash),
            $onboardingDate,
            $timestamp,
            $sequence,
            $randomSuffix
        );
    }

    /**
     * Generate a unique 4-character vendor hash.
     */
    private function generateVendorHash(): string
    {
        $hash = '';
        $attempts = 0;
        $maxAttempts = 100;

        do {
            // Generate 4-character hash using uppercase letters and numbers
            $hash = (string) Str::upper(Str::random(4));
            $attempts++;

            if ($attempts >= $maxAttempts) {
                // Fallback if we can't generate unique hash
                $hash = (string) Str::upper(Str::random(4));
                break;
            }
        } while (User::where('vendor_hash', $hash)->exists());

        return $hash;
    }

    /**
     * Get vendor's onboarding date in YYMMDD format.
     */
    private function getVendorOnboardingDate(User $vendor): string
    {
        // Try to get date from vendor application (when approved)
        $application = $vendor->latestVendorApplication()->first();
        if ($application) {
            $approvedDate = $application->reviewed_at ?? $application->created_at;
            if ($approvedDate) {
                return $approvedDate->format('ymd');
            }
        }

        // Fallback to user created_at date
        return $vendor->created_at->format('ymd');
    }

    /**
     * Get the next sequence number for a vendor on a specific date.
     * This ensures each order on the same date has a unique sequence.
     * Combined with timestamp and random suffix for maximum uniqueness.
     */
    private function getNextSequenceNumber(User $vendor, string $dateString): int
    {
        // Use TODAY'S date for counting, not the vendor onboarding date
        // The sequence represents the daily order count
        $startOfDay = now()->startOfDay();
        $endOfDay = now()->endOfDay();

        // Count orders for this vendor created today
        $count = Order::where('vendor_id', $vendor->id)
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->count();

        return $count + 1;
    }
}
