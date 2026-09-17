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

        $leads = Lead::factory()->count(100)->recycle($lookups)->create();
        $users = User::all();
        $assignments = $leads->shuffle()->chunk(10);

        $users->each(function (User $user, int $index) use ($assignments): void {
            if (! $assignments->has($index)) {
                return;
            }

            $user->leads()->attach($assignments[$index]->modelKeys());
        });
    }
}
