<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeedCoursesCsvCommandTest extends TestCase
{
    use RefreshDatabase;

    protected string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a tenant for testing
        $this->tenantId = Str::uuid()->toString();
        DB::connection('operational')->table('tenants')->insert([
            'id' => $this->tenantId,
            'name' => 'Test Tenant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_command_requires_tenant_option(): void
    {
        $this->artisan('seed:courses-csv', ['count' => 10])
            ->assertFailed()
            ->expectsOutput('Please provide a tenant UUID using --tenant option');
    }

    public function test_command_validates_tenant_exists(): void
    {
        $invalidTenantId = Str::uuid()->toString();

        $this->artisan('seed:courses-csv', [
            'count' => 10,
            '--tenant' => $invalidTenantId,
        ])->assertFailed()
            ->expectsOutput("Tenant with ID {$invalidTenantId} does not exist");
    }

    public function test_command_creates_courses_successfully(): void
    {
        $this->artisan('seed:courses-csv', [
            'count' => 10,
            '--tenant' => $this->tenantId,
        ])->assertSuccessful();

        $count = DB::connection('operational')
            ->table('courses')
            ->where('tenant_id', $this->tenantId)
            ->count();

        $this->assertEquals(10, $count);
    }

    public function test_command_creates_audit_records(): void
    {
        $this->artisan('seed:courses-csv', [
            'count' => 5,
            '--tenant' => $this->tenantId,
        ])->assertSuccessful();

        // Each course creates 1 CREATE + 6 UPDATE = 7 audit records
        $count = DB::connection('operational')
            ->table('course_audits')
            ->where('tenant_id', $this->tenantId)
            ->count();

        $this->assertEquals(35, $count);
    }

    public function test_command_distributes_records_across_date_range(): void
    {
        $this->artisan('seed:courses-csv', [
            'count' => 20,
            '--tenant' => $this->tenantId,
            '--start-date' => '2024-01-01',
            '--end-date' => '2024-02-28',
        ])->assertSuccessful();

        $count = DB::connection('operational')
            ->table('courses')
            ->where('tenant_id', $this->tenantId)
            ->count();

        $this->assertEquals(20, $count);

        $createdAtDates = DB::connection('operational')
            ->table('courses')
            ->where('tenant_id', $this->tenantId)
            ->pluck('created_at');

        foreach ($createdAtDates as $date) {
            $this->assertGreaterThanOrEqual('2024-01-01', $date);
            $this->assertLessThanOrEqual('2024-02-28 23:59:59', $date);
        }
    }
}
