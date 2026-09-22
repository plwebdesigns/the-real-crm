<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class LeadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $lookups = [
            LeadStatus::all(),
            LeadSource::all(),
        ];

        User::query()
            ->whereNotNull('location_id')
            ->get()
            ->each(function (User $user) use ($lookups): void {
                $leads = Lead::factory()
                    ->count(10)
                    ->recycle([...$lookups, $user->location])
                    ->create();

                $user->leads()->attach($leads->modelKeys());
            });
    }
}
