<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Points Per Vendor Onboarding
    |--------------------------------------------------------------------------
    |
    | How many referral points a sharer earns when a vendor successfully
    | onboards using their referral code. Only applies to users whose role
    | is NOT in the earning-capable list (influencer, field_agent, marketer).
    | Those roles earn GHS via the existing Earning flow instead.
    |
    */
    'points_per_vendor_onboarding' => (int) env('REFERRAL_POINTS_PER_VENDOR_ONBOARDING', 100),

    /*
    |--------------------------------------------------------------------------
    | Milestone First Threshold
    |--------------------------------------------------------------------------
    |
    | The first milestone threshold a user crosses. Crossing this threshold
    | creates a pending ReferralMilestoneReward row for admin fulfillment.
    |
    */
    'milestone_first' => (int) env('REFERRAL_MILESTONE_FIRST', 1000),

    /*
    |--------------------------------------------------------------------------
    | Milestone Increment
    |--------------------------------------------------------------------------
    |
    | After the first threshold, subsequent milestone thresholds repeat at
    | this increment. Default sequence with milestone_first=1000 and
    | milestone_increment=5000 is: [1000, 5000, 10000, 15000, 20000, ...].
    |
    */
    'milestone_increment' => (int) env('REFERRAL_MILESTONE_INCREMENT', 5000),
];
