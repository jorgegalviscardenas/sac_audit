<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeedTenantsCsvCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_tenants_successfully(): void
    {
        $this->artisan('seed:tenants-csv', ['count' => 10])
            ->assertSuccessful();

        $this->assertEquals(10, DB::connection('operational')->table('tenants')->count());
    }

    public function test_command_creates_audit_records(): void
    {
        $this->artisan('seed:tenants-csv', ['count' => 5])
            ->assertSuccessful();

        // Each tenant creates 1 CREATE + 6 UPDATE = 7 audit records
        $this->assertEquals(35, DB::connection('operational')->table('tenant_audits')->count());
    }

    public function test_command_distributes_records_across_date_range(): void
    {
        $this->artisan('seed:tenants-csv', [
            'count' => 20,
            '--start-date' => '2024-01-01',
            '--end-date' => '2024-02-28',
        ])->assertSuccessful();

        $this->assertEquals(20, DB::connection('operational')->table('tenants')->count());

        $createdAtDates = DB::connection('operational')
            ->table('tenants')
            ->pluck('created_at');

        foreach ($createdAtDates as $date) {
            $this->assertGreaterThanOrEqual('2024-01-01', $date);
            $this->assertLessThanOrEqual('2024-02-28 23:59:59', $date);
        }
    }

    public function test_command_validates_invalid_date_range(): void
    {
        $this->artisan('seed:tenants-csv', [
            'count' => 10,
            '--start-date' => '2024-12-31',
            '--end-date' => '2024-01-01',
        ])->assertFailed();
    }
}
