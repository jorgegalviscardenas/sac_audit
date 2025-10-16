<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeedCourseEnrollmentsCsvCommandTest extends TestCase
{
    use RefreshDatabase;

    protected string $tenantId;

    protected string $userId;

    protected string $courseId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->tenantId = Str::uuid()->toString();
        $this->userId = Str::uuid()->toString();
        $this->courseId = Str::uuid()->toString();

        DB::connection('operational')->table('tenants')->insert([
            'id' => $this->tenantId,
            'name' => 'Test Tenant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('operational')->table('users')->insert([
            'id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'email' => 'test@example.com',
            'full_name' => 'Test User',
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('operational')->table('courses')->insert([
            'id' => $this->courseId,
            'tenant_id' => $this->tenantId,
            'title' => 'Test Course',
            'description' => 'Test Description',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_command_requires_tenant_option(): void
    {
        $this->artisan('seed:course-enrollments-csv', ['count' => 10])
            ->assertFailed()
            ->expectsOutput('Please provide a tenant UUID using --tenant option');
    }

    public function test_command_validates_tenant_exists(): void
    {
        $invalidTenantId = Str::uuid()->toString();

        $this->artisan('seed:course-enrollments-csv', [
            'count' => 10,
            '--tenant' => $invalidTenantId,
        ])->assertFailed()
            ->expectsOutput("Tenant with ID {$invalidTenantId} does not exist");
    }

    public function test_command_requires_users_to_exist(): void
    {
        // Create a tenant without users
        $emptyTenantId = Str::uuid()->toString();
        DB::connection('operational')->table('tenants')->insert([
            'id' => $emptyTenantId,
            'name' => 'Empty Tenant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('seed:course-enrollments-csv', [
            'count' => 10,
            '--tenant' => $emptyTenantId,
        ])->assertFailed()
            ->expectsOutput("No users found for tenant {$emptyTenantId}. Please seed users first.");
    }

    public function test_command_creates_enrollments_successfully(): void
    {
        $this->artisan('seed:course-enrollments-csv', [
            'count' => 10,
            '--tenant' => $this->tenantId,
        ])->assertSuccessful();

        $count = DB::connection('operational')
            ->table('course_enrollments')
            ->where('tenant_id', $this->tenantId)
            ->count();

        $this->assertEquals(10, $count);
    }

    public function test_command_creates_audit_records(): void
    {
        $this->artisan('seed:course-enrollments-csv', [
            'count' => 5,
            '--tenant' => $this->tenantId,
        ])->assertSuccessful();

        // Each enrollment creates 1 CREATE + 6 UPDATE = 7 audit records
        $count = DB::connection('operational')
            ->table('course_enrollment_audits')
            ->where('tenant_id', $this->tenantId)
            ->count();

        $this->assertEquals(35, $count);
    }

    public function test_command_distributes_records_across_date_range(): void
    {
        $this->artisan('seed:course-enrollments-csv', [
            'count' => 20,
            '--tenant' => $this->tenantId,
            '--start-date' => '2024-01-01',
            '--end-date' => '2024-02-28',
        ])->assertSuccessful();

        $count = DB::connection('operational')
            ->table('course_enrollments')
            ->where('tenant_id', $this->tenantId)
            ->count();

        $this->assertEquals(20, $count);

        $createdAtDates = DB::connection('operational')
            ->table('course_enrollments')
            ->where('tenant_id', $this->tenantId)
            ->pluck('created_at');

        foreach ($createdAtDates as $date) {
            $this->assertGreaterThanOrEqual('2024-01-01', $date);
            $this->assertLessThanOrEqual('2024-02-28 23:59:59', $date);
        }
    }
}
