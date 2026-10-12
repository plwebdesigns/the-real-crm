<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class LeadSeeder extends Seeder
{
    private const int LEADS_PER_AGENT = 20;

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
                $leads = $this->leadsForAgent($user, $lookups);

                $user->leads()->attach($leads->modelKeys());

                $leads->each(function (Lead $lead) use ($user): void {
                    $happenedAtFor = $this->timestampBetween(...);

                    LeadActivity::factory()
                        ->count(fake()->numberBetween(1, 3))
                        ->for($lead)
                        ->for($user)
                        ->state(function () use ($happenedAtFor, $lead): array {
                            return [
                                'happened_at' => $happenedAtFor($lead->created_at, now()),
                            ];
                        })
                        ->create();
                });
            });
    }

    /**
     * @param  array{0: Collection<int, LeadStatus>, 1: Collection<int, LeadSource>}  $lookups
     * @return Collection<int, Lead>
     */
    private function leadsForAgent(User $user, array $lookups): Collection
    {
        $quarterCount = now()->quarter;
        $perQuarter = intdiv(self::LEADS_PER_AGENT, $quarterCount);
        $remainder = self::LEADS_PER_AGENT % $quarterCount;
        $leads = new Collection;

        foreach (range(1, $quarterCount) as $quarter) {
            $count = $perQuarter + ($quarter <= $remainder ? 1 : 0);

            $leads = $leads->merge(
                $this->leadsInQuarter($user, $lookups, $quarter, $count),
            );
        }

        return $leads;
    }

    /**
     * @param  array{0: Collection<int, LeadStatus>, 1: Collection<int, LeadSource>}  $lookups
     * @return Collection<int, Lead>
     */
    private function leadsInQuarter(User $user, array $lookups, int $quarter, int $count): Collection
    {
        [$start, $end] = $this->quarterBounds($quarter);
        $createdAtFor = $this->timestampBetween(...);

        /** @var Collection<int, Lead> $leads */
        $leads = Lead::factory()
            ->count($count)
            ->recycle([...$lookups, $user->location])
            ->state(function () use ($createdAtFor, $start, $end): array {
                $createdAt = $createdAtFor($start, $end);

                return [
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            })
            ->create();

        return $leads;
    }

    /**
     * Inclusive bounds for a quarter of the current year, ending no later than now.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function quarterBounds(int $quarter): array
    {
        $start = now()->startOfYear()->addQuarters($quarter - 1)->startOfDay();
        $end = $start->copy()->endOfQuarter()->min(now());

        return [$start, $end];
    }

    private function timestampBetween(CarbonInterface $start, CarbonInterface $end): DateTimeInterface
    {
        if ($end->lessThanOrEqualTo($start)) {
            return $start;
        }

        return fake()->dateTimeBetween($start, $end);
    }
}
