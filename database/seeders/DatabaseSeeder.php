<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Models\Sale;
use App\Models\SaleStatus;
use App\Models\SaleUser;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(LookupSeeder::class);

        $admin = User::factory()->admin()->create([
            'name' => 'Paul Longo',
            'email' => 'paullongo@outlook.com',
            'password' => Hash::make('Password123'),
        ]);

        $lookups = [
            LeadStatus::all(),
            LeadSource::all(),
            SaleStatus::all(),
        ];

        $leads = Lead::factory(20)->recycle($lookups)->create();
        $admin->leads()->attach($leads->pluck('id'));

        $sales = Sale::factory(10)->recycle($lookups)->create();
        $sales->each(function (Sale $sale) use ($admin): void {
            $sale->agents()->sync([
                $admin->id => [
                    'commission_percent' => 100,
                    'net_commission' => SaleUser::netCommissionFor($sale->gross_commission, 100),
                ],
            ]);

            if ($sale->status->name === 'Closed') {
                // Assign admin to the lead
                $admin->leads()->attach($sale->lead_id);
            }
        });

    }
}
