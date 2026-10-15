<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\Sale;
use App\Models\SaleStatus;
use App\Models\SaleUser;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SaleSeeder extends Seeder
{
    private const int SALES_PER_AGENT = 12;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clock = SeedClock::fromConnection(DB::connection());
        $users = User::query()->whereNotNull('location_id')->get();
        $statuses = SaleStatus::all();
        $quarterCount = now()->quarter;
        $quarters = range(1, $quarterCount);
        $extraQuarters = array_map(
            fn (): int => fake()->randomElement($quarters),
            range(1, self::SALES_PER_AGENT - $quarterCount),
        );

        $users->each(function (User $user) use ($clock, $extraQuarters, $quarters, $statuses): void {
            /** @var Collection<int, Lead> $leads */
            $leads = $user->leads->unique('id')->values();
            $usedLeadIds = [];

            foreach ([...$quarters, ...$extraQuarters] as $quarter) {
                $lead = $this->leadForQuarter($leads, $quarter, $usedLeadIds);
                $usedLeadIds[] = $lead->id;

                $this->createSale($user, $lead, $statuses, $clock, $quarter);
            }
        });
    }

    /**
     * @param  Collection<int, Lead>  $leads
     * @param  list<int>  $usedLeadIds
     */
    private function leadForQuarter(Collection $leads, int $quarter, array $usedLeadIds): Lead
    {
        [$start, $end] = SeedClock::quarterBounds($quarter);

        $leadInQuarter = $leads->first(function (Lead $lead) use ($end, $start, $usedLeadIds): bool {
            if (in_array($lead->id, $usedLeadIds, true) || ! $lead->created_at instanceof CarbonInterface) {
                return false;
            }

            return $lead->created_at->between($start, $end);
        });

        if ($leadInQuarter instanceof Lead) {
            return $leadInQuarter;
        }

        $earlierLead = $leads->first(function (Lead $lead) use ($end, $usedLeadIds): bool {
            if (in_array($lead->id, $usedLeadIds, true) || ! $lead->created_at instanceof CarbonInterface) {
                return false;
            }

            return $lead->created_at->lessThanOrEqualTo($end);
        });

        if ($earlierLead instanceof Lead) {
            return $earlierLead;
        }

        return $leads->firstOrFail();
    }

    /**
     * @param  Collection<int, SaleStatus>  $statuses
     */
    private function createSale(User $user, Lead $lead, Collection $statuses, SeedClock $clock, int $quarter): void
    {
        [$start, $end] = SeedClock::quarterBounds($quarter);
        $windowStart = $lead->created_at instanceof CarbonInterface
            ? $lead->created_at->max($start)
            : $start;
        $occurredAt = $clock->between($windowStart, $end);

        $sale = Sale::factory()
            ->withoutAgents()
            ->for($lead)
            ->recycle([$statuses])
            ->create();

        $attributes = [
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ];

        if ($sale->status?->slug === 'closed') {
            $attributes['closed_at'] = $occurredAt;
        }

        $sale->forceFill($attributes)->save();

        $sale->agents()->attach($user, [
            'commission_percent' => 100,
            'net_commission' => SaleUser::netCommissionFor(
                Sale::remainingCommissionFor($sale->gross_commission, $sale->brokerage_fee),
                100,
            ),
        ]);
    }
}
