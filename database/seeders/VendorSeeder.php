<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class VendorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define popular vendors
        $popularVendors = [
            [
                'email' => 'vendor1@example.com',
                'name' => 'Premium Gifts Ghana',
                'bio' => 'Accra, Ghana - Premium gifts and surprises',
                'avatar' => 'https://images.unsplash.com/photo-1560807707-8cc77767d783?w=400&h=400&fit=crop',
                'is_popular' => true,
            ],
            [
                'email' => 'vendor2@example.com',
                'name' => 'Elite Flowers & More',
                'bio' => 'Kumasi, Ghana - Fresh flowers and elegant arrangements',
                'avatar' => 'https://images.unsplash.com/photo-1498049794561-7780e7231661?w=400&h=400&fit=crop',
                'is_popular' => true,
            ],
            [
                'email' => 'vendor3@example.com',
                'name' => 'Luxury Hampers Co',
                'bio' => 'Accra, Ghana - Curated gift hampers for every occasion',
                'avatar' => 'https://images.unsplash.com/photo-1523554888454-84137e72c3ce?w=400&h=400&fit=crop',
                'is_popular' => true,
            ],
            [
                'email' => 'vendor4@example.com',
                'name' => 'Accra Gift Shop',
                'bio' => 'Accra, Ghana - Quality gifts at affordable prices',
                'avatar' => 'https://images.unsplash.com/photo-1515955656352-a1fa3ffcd111?w=400&h=400&fit=crop',
                'is_popular' => false,
            ],
        ];

        foreach ($popularVendors as $vendorData) {
            User::updateOrCreate(
                ['email' => $vendorData['email']],
                [
                    'name' => $vendorData['name'],
                    'bio' => $vendorData['bio'],
                    'avatar' => $vendorData['avatar'],
                    'role' => 'vendor',
                    'is_popular' => $vendorData['is_popular'],
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
