<?php

namespace Database\Seeders;

use App\Models\MusicGenre;
use Illuminate\Database\Seeder;

class MusicGenreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $genres = [
            ['name' => 'Pop', 'icon' => '🎤'],
            ['name' => 'Rock', 'icon' => '🎸'],
            ['name' => 'Hip-Hop', 'icon' => '🎙️'],
            ['name' => 'Jazz', 'icon' => '🎷'],
            ['name' => 'Classical', 'icon' => '🎻'],
            ['name' => 'Electronic', 'icon' => '🎛️'],
            ['name' => 'R&B', 'icon' => '🎵'],
            ['name' => 'Country', 'icon' => '🤠'],
            ['name' => 'Latin', 'icon' => '🥁'],
            ['name' => 'Soul', 'icon' => '💿'],
        ];

        foreach ($genres as $genre) {
            MusicGenre::updateOrCreate(
                ['name' => $genre['name']],
                ['icon' => $genre['icon']]
            );
        }
    }
}
