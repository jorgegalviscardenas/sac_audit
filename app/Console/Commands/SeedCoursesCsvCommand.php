<?php

namespace App\Console\Commands;

use Database\Common\DatabaseConnections as DB_CONN;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SeedCoursesCsvCommand extends Command
{
    use CsvSeedCommandTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:courses-csv
                            {count=100 : The number of courses to create}
                            {--tenant= : The tenant UUID (required)}
                            {--start-date= : Start date for records (Y-m-d format)}
                            {--end-date= : End date for records (Y-m-d format)}
                            {--keep-csv : Keep CSV files after import}
                            {--audit-connection=operational : Database connection for audit tables (operational or audit)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed courses and course audit tables using bulk inserts (optimized for large datasets)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = (int) $this->argument('count');
        $tenantId = $this->option('tenant');
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');
        $keepCsv = $this->option('keep-csv');
        $auditConnection = $this->option('audit-connection');

        if (! $tenantId) {
            $this->error('Please provide a tenant UUID using --tenant option');

            return Command::FAILURE;
        }

        // Validate tenant exists
        if (! $this->validateTenant($tenantId)) {
            $this->error("Tenant with ID {$tenantId} does not exist");

            return Command::FAILURE;
        }

        // Calculate monthly distribution
        try {
            $distribution = $this->calculateMonthlyDistribution($count, $startDate, $endDate);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        // Display distribution info
        if ($startDate && $endDate) {
            $this->info("Distributing {$count} courses across ".count($distribution).' month(s):');
            foreach ($distribution as $period) {
                $this->info("  - {$period['start']->format('Y-m-d')} to {$period['end']->format('Y-m-d')}: {$period['count']} records");
            }
        }

        $this->info("Generating and inserting {$count} courses and ".($count * 7).' audit records...');
        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        $recordIndex = 0;

        // Generate and insert courses and audit records per period
        foreach ($distribution as $period) {
            $courses = [];
            $audits = [];

            for ($i = 0; $i < $period['count']; $i++) {
                $courseId = Str::uuid();
                $timestamp = $this->generateRandomTimestamp($period['start'], $period['end']);
                $courseTitle = $this->generateCourseTitle().' '.$recordIndex;
                $courseDescription = 'Description for '.$courseTitle;

                // Collect course record
                $courses[] = [
                    $courseId,
                    $tenantId,
                    $courseTitle,
                    $courseDescription,
                    $timestamp->format('Y-m-d H:i:s'),
                    $timestamp->format('Y-m-d H:i:s'),
                ];

                $transactionHash = hash('sha256', $courseId.time().$recordIndex);

                // 1 CREATE audit
                $audits[] = [
                    $tenantId,
                    $courseId,
                    1, // type: CREATE
                    json_encode([
                        'old' => null,
                        'new' => [
                            'id' => $courseId,
                            'tenant_id' => $tenantId,
                            'title' => $courseTitle,
                            'description' => $courseDescription,
                            'created_at' => $timestamp->format('Y-m-d H:i:s'),
                            'updated_at' => $timestamp->format('Y-m-d H:i:s'),
                        ],
                    ]),
                    $transactionHash.'_create',
                    $courseId,
                    'System User',
                    $timestamp->format('Y-m-d H:i:s'),
                ];

                // 6 UPDATE audits (spread over time after creation)
                for ($j = 1; $j <= 6; $j++) {
                    $updateTime = $timestamp->copy()->addSeconds($j * 3600);
                    $oldDescription = $j === 1 ? $courseDescription : $courseDescription.' v'.($j - 1);
                    $newDescription = $courseDescription.' v'.$j;

                    $audits[] = [
                        $tenantId,
                        $courseId,
                        2, // type: UPDATE
                        json_encode([
                            'old' => [
                                'id' => $courseId,
                                'tenant_id' => $tenantId,
                                'title' => $courseTitle,
                                'description' => $oldDescription,
                                'updated_at' => $timestamp->copy()->addSeconds(($j - 1) * 3600)->format('Y-m-d H:i:s'),
                            ],
                            'new' => [
                                'id' => $courseId,
                                'tenant_id' => $tenantId,
                                'title' => $courseTitle,
                                'description' => $newDescription,
                                'updated_at' => $updateTime->format('Y-m-d H:i:s'),
                            ],
                        ]),
                        $transactionHash.'_update_'.$j,
                        $courseId,
                        'System User',
                        $updateTime->format('Y-m-d H:i:s'),
                    ];
                }

                $recordIndex++;

                if ($recordIndex % 10000 === 0) {
                    $progressBar->advance(10000);
                }
            }

            // Bulk insert for this period - direct COPY for main table
            if (! $this->bulkInsertWithCopy('courses', ['id', 'tenant_id', 'title', 'description', 'created_at', 'updated_at'], $courses, $keepCsv, DB_CONN::OPERATIONAL)) {
                $progressBar->finish();

                return Command::FAILURE;
            }

            // Bulk insert for audit - use partitioned method for audit connection
            if ($auditConnection === DB_CONN::AUDIT) {
                if (! $this->bulkInsertWithCopyPartitioned('course_audits', ['tenant_id', 'object_id', 'type', 'diffs', 'transaction_hash', 'blame_id', 'blame_user', 'created_at'], $audits, $keepCsv, $auditConnection)) {
                    $progressBar->finish();

                    return Command::FAILURE;
                }
            } else {
                if (! $this->bulkInsertWithCopy('course_audits', ['tenant_id', 'object_id', 'type', 'diffs', 'transaction_hash', 'blame_id', 'blame_user', 'created_at'], $audits, $keepCsv, $auditConnection)) {
                    $progressBar->finish();

                    return Command::FAILURE;
                }
            }

            // Clear arrays to free memory
            unset($courses, $audits);
        }

        // Advance progress bar for remaining records
        if ($recordIndex % 10000 !== 0) {
            $progressBar->advance($recordIndex % 10000);
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Successfully inserted {$count} courses and ".($count * 7).' audit records!');
        $this->displayTableCount('courses', $tenantId);
        $this->displayTableCount('course_audits', $tenantId, 'course audit records');

        return Command::SUCCESS;
    }
}
