<?php

namespace Database\Seeders;

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
        $users = User::all();
        $recycleables = [SaleStatus::all()];
        $users->each(function ($user) use ($recycleables) {
            array_push($recycleables, $user->leads);
            $sales = Sale::factory()->withoutAgents()->count(4)->recycle($recycleables)->create();
            $sales->each(function ($sale) use ($user) {
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
