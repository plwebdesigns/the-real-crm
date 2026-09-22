<?php

namespace Tests\Feature\Models;

use App\Models\Lead;
use App\Models\Location;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_location_name_must_be_unique(): void
    {
        Location::factory()->create(['name' => 'Downtown']);

        $this->expectException(QueryException::class);

        Location::factory()->create(['name' => 'Downtown']);
    }

    public function test_location_in_use_cannot_be_deleted(): void
    {
        $location = Location::factory()->create();
        User::factory()->for($location)->create();

        $this->expectException(QueryException::class);

        $location->delete();
    }

    public function test_location_has_users_leads_and_sales(): void
    {
        $location = Location::factory()->create();
        $user = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();
        $sale = Sale::factory()->recycle($location)->for($lead)->create();

        $this->assertTrue($location->users->contains($user));
        $this->assertTrue($location->leads->contains($lead));
        $this->assertTrue($location->sales->contains($sale));
        $this->assertTrue($lead->office->is($location));
        $this->assertTrue($sale->location->is($location));
        $this->assertTrue($user->location->is($location));
    }

    public function test_visible_to_scope_hides_other_locations_from_location_members(): void
    {
        $miami = Location::factory()->create();
        $boston = Location::factory()->create();
        $admin = User::factory()->admin()->for($miami)->create();
        $localLead = Lead::factory()->recycle($miami)->create();
        Lead::factory()->recycle($boston)->create();

        $this->assertSame(
            [$localLead->id],
            Lead::query()->visibleTo($admin)->orderBy('id')->pluck('id')->all(),
        );
    }

    public function test_visible_to_scope_does_not_filter_super_admins(): void
    {
        $miami = Location::factory()->create();
        $boston = Location::factory()->create();
        $admin = User::factory()->superAdmin()->create();
        $miamiLead = Lead::factory()->recycle($miami)->create();
        $bostonLead = Lead::factory()->recycle($boston)->create();

        $this->assertSame(
            [$miamiLead->id, $bostonLead->id],
            Lead::query()->visibleTo($admin)->orderBy('id')->pluck('id')->all(),
        );
    }
}
