<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RegionCitySeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'Greater Accra' => ['Accra', 'Tema', 'Madina', 'Teshie', 'Nungua', 'Ashaiman', 'Dodowa', 'Adenta'],
            'Ashanti' => ['Kumasi', 'Obuasi', 'Ejisu', 'Mampong', 'Konongo', 'Offinso', 'Bekwai'],
            'Western' => ['Takoradi', 'Sekondi', 'Tarkwa', 'Axim', 'Prestea', 'Shama'],
            'Western North' => ['Sefwi Wiawso', 'Bibiani', 'Juaboso', 'Enchi'],
            'Central' => ['Cape Coast', 'Winneba', 'Kasoa', 'Swedru', 'Elmina', 'Mankessim'],
            'Eastern' => ['Koforidua', 'Nkawkaw', 'Akosombo', 'Akim Oda', 'Suhum', 'Begoro'],
            'Volta' => ['Ho', 'Keta', 'Hohoe', 'Aflao', 'Kpando', 'Sogakope'],
            'Oti' => ['Dambai', 'Jasikan', 'Nkwanta', 'Kadjebi', 'Krachi'],
            'Northern' => ['Tamale', 'Yendi', 'Savelugu', 'Gushegu', 'Karaga'],
            'Savannah' => ['Damongo', 'Salaga', 'Bole', 'Sawla'],
            'North East' => ['Nalerigu', 'Walewale', 'Gambaga', 'Chereponi'],
            'Upper East' => ['Bolgatanga', 'Bawku', 'Navrongo', 'Zebilla', 'Paga'],
            'Upper West' => ['Wa', 'Tumu', 'Lawra', 'Jirapa', 'Nadowli'],
            'Bono' => ['Sunyani', 'Berekum', 'Dormaa Ahenkro', 'Wenchi'],
            'Bono East' => ['Techiman', 'Kintampo', 'Atebubu', 'Nkoranza'],
            'Ahafo' => ['Goaso', 'Kukuom', 'Hwidiem', 'Mim'],
        ];

        foreach ($data as $regionName => $cities) {
            $region = Region::firstOrCreate(
                ['slug' => Str::slug($regionName)],
                ['name' => $regionName]
            );

            foreach ($cities as $cityName) {
                City::firstOrCreate(
                    ['region_id' => $region->id, 'slug' => Str::slug($cityName)],
                    ['name' => $cityName]
                );
            }

            $this->command->info("Seeded region: {$regionName} ({$region->cities()->count()} cities)");
        }
    }
}
