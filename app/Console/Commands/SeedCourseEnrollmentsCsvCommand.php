<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedCourseEnrollmentsCsvCommand extends Command
{
    use CsvSeedCommandTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:course-enrollments-csv
                            {count=100 : The number of course enrollments to create}
                            {--tenant= : The tenant UUID (required)}
                            {--start-date= : Start date for records (Y-m-d format)}
                            {--end-date= : End date for records (Y-m-d format)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed course enrollments and enrollment audit tables using bulk inserts (optimized for large datasets)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = (int) $this->argument('count');
        $tenantId = $this->option('tenant');
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');

        if (! $tenantId) {
            $this->error('Please provide a tenant UUID using --tenant option');

            return Command::FAILURE;
        }

        // Validate tenant exists
        if (! $this->validateTenant($tenantId)) {
            $this->error("Tenant with ID {$tenantId} does not exist");

            return Command::FAILURE;
        }

        // Get counts first to validate data exists
        $this->info("Checking users and courses for tenant {$tenantId}...");

        $userCount = DB::connection('operational')
            ->table('users')
            ->where('tenant_id', $tenantId)
            ->count();

        $courseCount = DB::connection('operational')
            ->table('courses')
            ->where('tenant_id', $tenantId)
            ->count();

        if ($userCount === 0) {
            $this->error("No users found for tenant {$tenantId}. Please seed users first.");

            return Command::FAILURE;
        }

        if ($courseCount === 0) {
            $this->error("No courses found for tenant {$tenantId}. Please seed courses first.");

            return Command::FAILURE;
        }

        $this->info("Found {$userCount} users and {$courseCount} courses");

        // Fetch a reasonable sample of users and courses (max 100,000 each) to reduce memory usage
        $userSampleSize = min($userCount, 100000);
        $courseSampleSize = min($courseCount, 100000);

        $userIds = DB::connection('operational')
            ->table('users')
            ->where('tenant_id', $tenantId)
            ->inRandomOrder()
            ->limit($userSampleSize)
            ->pluck('id')
            ->toArray();

        $courseIds = DB::connection('operational')
            ->table('courses')
            ->where('tenant_id', $tenantId)
            ->inRandomOrder()
            ->limit($courseSampleSize)
            ->pluck('id')
            ->toArray();

        $this->info('Using sample of '.count($userIds).' users and '.count($courseIds).' courses for enrollments');

        // Calculate monthly distribution
        try {
            $distribution = $this->calculateMonthlyDistribution($count, $startDate, $endDate);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        // Display distribution info
        if ($startDate && $endDate) {
            $this->info("Distributing {$count} enrollments across ".count($distribution).' month(s):');
            foreach ($distribution as $period) {
                $this->info("  - {$period['start']->format('Y-m-d')} to {$period['end']->format('Y-m-d')}: {$period['count']} records");
            }
        }

        $this->info("Generating and inserting {$count} enrollments and ".($count * 7).' audit records...');
        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        $recordIndex = 0;

        // Generate and insert enrollments and audit records per period
        foreach ($distribution as $period) {
            $enrollments = [];
            $audits = [];

            for ($i = 0; $i < $period['count']; $i++) {
                $enrollmentId = Str::uuid();
                $timestamp = $this->generateRandomTimestamp($period['start'], $period['end']);

                // Pick random user and course
                $userId = $userIds[array_rand($userIds)];
                $courseId = $courseIds[array_rand($courseIds)];

                // 70% completed
                $isCompleted = (mt_rand(1, 100) <= 70);
                $enrolledAt = $timestamp->format('Y-m-d');

                // Collect enrollment record
                $enrollments[] = [
                    $enrollmentId,
                    $tenantId,
                    $userId,
                    $courseId,
                    $enrolledAt,
                    $isCompleted,
                    $timestamp->format('Y-m-d H:i:s'),
                    $timestamp->format('Y-m-d H:i:s'),
                ];

                $transactionHash = hash('sha256', $enrollmentId.time().$recordIndex);

                // 1 CREATE audit
                $audits[] = [
                    $tenantId,
                    $enrollmentId,
                    1, // type: CREATE
                    json_encode([
                        'old' => null,
                        'new' => [
                            'id' => $enrollmentId,
                            'tenant_id' => $tenantId,
                            'user_id' => $userId,
                            'course_id' => $courseId,
                            'enrolled_at' => $enrolledAt,
                            'is_completed' => $isCompleted,
                            'created_at' => $timestamp->format('Y-m-d H:i:s'),
                            'updated_at' => $timestamp->format('Y-m-d H:i:s'),
                        ],
                    ]),
                    $transactionHash.'_create',
                    $enrollmentId,
                    'System User',
                    $timestamp->format('Y-m-d H:i:s'),
                ];

                // 6 UPDATE audits (spread over time after creation) - toggling completion status
                for ($j = 1; $j <= 6; $j++) {
                    $updateTime = $timestamp->copy()->addSeconds($j * 3600);
                    $oldCompleted = $j % 2 === 1 ? $isCompleted : ! $isCompleted;
                    $newCompleted = ! $oldCompleted;

                    $audits[] = [
                        $tenantId,
                        $enrollmentId,
                        2, // type: UPDATE
                        json_encode([
                            'old' => [
                                'id' => $enrollmentId,
                                'tenant_id' => $tenantId,
                                'user_id' => $userId,
                                'course_id' => $courseId,
                                'enrolled_at' => $enrolledAt,
                                'is_completed' => $oldCompleted,
                                'updated_at' => $timestamp->copy()->addSeconds(($j - 1) * 3600)->format('Y-m-d H:i:s'),
                            ],
                            'new' => [
                                'id' => $enrollmentId,
                                'tenant_id' => $tenantId,
                                'user_id' => $userId,
                                'course_id' => $courseId,
                                'enrolled_at' => $enrolledAt,
                                'is_completed' => $newCompleted,
                                'updated_at' => $updateTime->format('Y-m-d H:i:s'),
                            ],
                        ]),
                        $transactionHash.'_update_'.$j,
                        $enrollmentId,
                        'System User',
                        $updateTime->format('Y-m-d H:i:s'),
                    ];
                }

                $recordIndex++;

                if ($recordIndex % 10000 === 0) {
                    $progressBar->advance(10000);
                }
            }

            // Bulk insert for this period
            if (! $this->bulkInsert('course_enrollments', ['id', 'tenant_id', 'user_id', 'course_id', 'enrolled_at', 'is_completed', 'created_at', 'updated_at'], $enrollments)) {
                $progressBar->finish();

                return Command::FAILURE;
            }

            if (! $this->bulkInsert('course_enrollment_audits', ['tenant_id', 'object_id', 'type', 'diffs', 'transaction_hash', 'blame_id', 'blame_user', 'created_at'], $audits)) {
                $progressBar->finish();

                return Command::FAILURE;
            }

            // Clear arrays to free memory
            unset($enrollments, $audits);
        }

        // Advance progress bar for remaining records
        if ($recordIndex % 10000 !== 0) {
            $progressBar->advance($recordIndex % 10000);
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Successfully inserted {$count} enrollments and ".($count * 7).' audit records!');
        $this->displayTableCount('course_enrollments', $tenantId, 'enrollments');
        $this->displayTableCount('course_enrollment_audits', $tenantId, 'enrollment audit records');

        return Command::SUCCESS;
    }
}
