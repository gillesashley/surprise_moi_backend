<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ListFieldAgents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'field-agents:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all field agents';

    public function handle(): int
    {
        $agents = \App\Models\FieldAgentApplication::all();

        if ($agents->isEmpty()) {
            $this->info('No field agents found in database.');

            return Command::SUCCESS;
        }

        $this->table(['First Name', 'Last Name', 'Email', 'Status', 'Region ID', 'City ID', 'Location'], $agents->map(fn ($a) => [
            $a->first_name,
            $a->last_name,
            $a->email,
            $a->status->value ?? $a->status,
            $a->region_id,
            $a->city_id,
            $a->location,
        ]));

        return Command::SUCCESS;
    }
}
