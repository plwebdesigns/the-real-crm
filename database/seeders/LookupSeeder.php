<?php

namespace Database\Seeders;

use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Models\SaleStatus;
use Illuminate\Database\Seeder;

class LookupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['name' => 'New', 'slug' => 'new'],
            ['name' => 'Contacted', 'slug' => 'contacted'],
            ['name' => 'Qualified', 'slug' => 'qualified'],
            ['name' => 'Lost', 'slug' => 'lost'],
            ['name' => 'Converted', 'slug' => 'converted'],
        ] as $status) {
            LeadStatus::query()->updateOrCreate(['slug' => $status['slug']], $status);
        }

        foreach ([
            ['name' => 'Pending', 'slug' => 'pending'],
            ['name' => 'Closed', 'slug' => 'closed'],
            ['name' => 'Cancelled', 'slug' => 'cancelled'],
        ] as $status) {
            SaleStatus::query()->updateOrCreate(['slug' => $status['slug']], $status);
        }

        foreach ([
            ['name' => 'Website', 'slug' => 'website'],
            ['name' => 'Referral', 'slug' => 'referral'],
            ['name' => 'Zillow', 'slug' => 'zillow'],
            ['name' => 'Open House', 'slug' => 'open-house'],
            ['name' => 'Facebook', 'slug' => 'facebook'],
            ['name' => 'Instagram', 'slug' => 'instagram'],
            ['name' => 'Twitter', 'slug' => 'twitter'],
            ['name' => 'LinkedIn', 'slug' => 'linkedin'],
            ['name' => 'YouTube', 'slug' => 'youtube'],
            ['name' => 'Car Ads', 'slug' => 'car-ads'],
        ] as $source) {
            LeadSource::query()->updateOrCreate(['slug' => $source['slug']], $source);
        }
    }
}
