<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\Sale;
use App\Models\SaleStatus;
use App\Models\SaleUser;
use App\Models\User;
use Illuminate\Database\Seeder;

class SaleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::query()->whereNotNull('location_id')->get();
        $statuses = SaleStatus::all();

        $users->each(function (User $user) use ($statuses): void {
            $user->leads->unique('id')->take(4)->each(function (Lead $lead) use ($user, $statuses): void {
                $sale = Sale::factory()
                    ->withoutAgents()
                    ->for($lead)
                    ->recycle([$statuses])
                    ->create();

                $sale->agents()->attach($user, [
                    'commission_percent' => 100,
                    'net_commission' => SaleUser::netCommissionFor(
                        Sale::remainingCommissionFor($sale->gross_commission, $sale->brokerage_fee),
                        100,
                    ),
                ]);
            });
        });
    }
}
